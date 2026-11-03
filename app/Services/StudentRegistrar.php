<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Transfer;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a student with their guardians and class. The form and the Excel import both use it,
 * so the rules are the same everywhere (BR-01 one class per year, BR-10 one primary guardian).
 */
class StudentRegistrar
{
    /**
     * @param  array  $student  student columns
     * @param  array  $guardians  rows of {id?, first_name, last_name, phone, email, national_id, occupation, relationship, is_primary}
     * @param  array|null  $enrollment  {class_id, enrollment_type, date_joined?, previous_school?, previous_school_code?}
     */
    public function save(?Student $existing, array $student, array $guardians, ?array $enrollment): Student
    {
        return DB::transaction(function () use ($existing, $student, $guardians, $enrollment) {
            $record = $existing ?? new Student(['status' => 'active']);
            $record->fill($student)->save();

            $this->syncGuardians($record, $guardians);

            if ($enrollment && ! empty($enrollment['class_id'])) {
                $this->enrol($record, $enrollment);
            }

            return $record;
        });
    }

    /** Links the guardians, reusing an existing guardian with the same phone and first name (brothers and sisters). */
    public function syncGuardians(Student $student, array $rows): void
    {
        $links = [];
        foreach (array_values($rows) as $row) {
            $phone = Phone::normalize($row['phone'] ?? null);
            $fields = [
                'first_name' => trim($row['first_name']),
                'last_name' => trim((string) ($row['last_name'] ?? '')) ?: null,
                'phone' => $phone,
                'email' => ! empty($row['email']) ? strtolower(trim($row['email'])) : null,
                'national_id' => $row['national_id'] ?? null,
                'occupation' => $row['occupation'] ?? null,
            ];

            $guardian = ! empty($row['id']) ? Guardian::query()->find($row['id']) : null;
            $guardian ??= $phone ? Guardian::query()->where('phone', $phone)
                ->whereRaw('lower(first_name) = ?', [mb_strtolower($fields['first_name'])])->first() : null;

            if ($guardian) {
                // Keep what is already known when the new row leaves a field empty.
                $guardian->update(array_filter($fields, fn ($v) => $v !== null && $v !== ''));
            } else {
                $guardian = Guardian::query()->create($fields);
            }
            $links[$guardian->id] = ['relationship' => $row['relationship'] ?? 'guardian', 'is_primary' => (bool) ($row['is_primary'] ?? false)];
        }

        // Exactly one primary guardian when there is at least one (BR-10).
        $primaries = array_keys(array_filter($links, fn ($l) => $l['is_primary']));
        foreach ($links as $id => $l) {
            $links[$id]['is_primary'] = $primaries ? $id === $primaries[0] : $id === array_key_first($links);
        }

        $before = $student->guardians()->get()->mapWithKeys(fn ($g) => [$g->id => [$g->pivot->relationship, (bool) $g->pivot->is_primary]])->all();
        // Clear the primary flag first so the "one primary" index never sees two at once.
        DB::table('student_guardians')->where('student_id', $student->id)->update(['is_primary' => false]);
        $student->guardians()->sync($links);
        $after = collect($links)->map(fn ($l) => [$l['relationship'], $l['is_primary']])->all();
        ksort($before);
        ksort($after);
        if ($before !== $after) {
            AuditLog::record('update', 'student_guardians', ['guardians' => $before], ['guardians' => $after], $student->id);
        }
    }

    /** Puts the student in a class for that class's year; moves them if they already have a class that year (BR-01). */
    public function enrol(Student $student, array $data): Enrollment
    {
        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $current = Enrollment::query()->where('student_id', $student->id)->where('academic_year_id', $class->academic_year_id)->first();

        if ($class->capacity && (! $current || $current->class_id !== $class->id)
            && $class->activeEnrollments()->count() >= $class->capacity) {
            throw ValidationException::withMessages(['class_id' => __('Class :name is full (:n students).', ['name' => $class->name, 'n' => $class->capacity])]);
        }
        if ($student->isClosed()) {
            throw ValidationException::withMessages(['class_id' => __('This student has left the school. Set the status back to active first.')]);
        }

        $type = $data['enrollment_type'] ?? $current?->enrollment_type ?? 'new';
        $enrollment = $current ?? new Enrollment(['student_id' => $student->id, 'academic_year_id' => $class->academic_year_id, 'date_joined' => $data['date_joined'] ?? now()->toDateString()]);
        $enrollment->fill(['class_id' => $class->id, 'enrollment_type' => $type, 'status' => 'active', 'date_left' => null])->save();

        if ($type === 'transfer_in' && ! empty($data['previous_school']) && ! $student->transfers()->where('direction', 'in')->exists()) {
            Transfer::query()->create([
                'student_id' => $student->id, 'direction' => 'in', 'other_school' => $data['previous_school'],
                'other_school_code' => $data['previous_school_code'] ?? null, 'transfer_date' => $enrollment->date_joined ?? now(),
                'recorded_by' => auth()->id(),
            ]);
        }

        return $enrollment;
    }

    /** Changes the student's status; leaving the school closes the current enrolment (BR-11). */
    public function changeStatus(Student $student, string $status, array $details = []): void
    {
        DB::transaction(function () use ($student, $status, $details) {
            $date = $details['date'] ?? now()->toDateString();
            $student->update(['status' => $status]);

            $enrollment = $student->currentEnrollment()->first();
            if (in_array($status, Student::CLOSED_STATUSES, true)) {
                $enrollment?->update(['status' => 'left', 'date_left' => $date]);
            } elseif ($status === 'active' && $enrollment?->status === 'left') {
                $enrollment->update(['status' => 'active', 'date_left' => null]);
            }

            if ($status === 'transferred') {
                Transfer::query()->create([
                    'student_id' => $student->id, 'direction' => 'out', 'other_school' => $details['other_school'] ?? null,
                    'other_school_code' => $details['other_school_code'] ?? null, 'transfer_date' => $date,
                    'reason' => $details['reason'] ?? null, 'recorded_by' => auth()->id(),
                ]);
            } elseif (! empty($details['reason'])) {
                AuditLog::record('update', $student, null, ['status' => $status, 'reason' => $details['reason']]);
            }
        });
    }
}
