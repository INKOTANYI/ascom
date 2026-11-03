<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Level;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AccessSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private User $dos;

    private SchoolClass $s1a;

    private SchoolClass $s1b;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed([AccessSeeder::class, AcademicStructureSeeder::class]);
        SchoolProfile::query()->create(['name' => 'GS Test', 'short_name' => 'GS Test', 'school_type' => 'mixed', 'district' => 'X', 'sector' => 'Y']);
        $this->dos = $this->userWithRole('dos');
        $year = AcademicYear::current();
        $s1 = Level::query()->where('code', 'S1')->first();
        $this->s1a = SchoolClass::query()->create(['academic_year_id' => $year->id, 'level_id' => $s1->id, 'stream' => 'A', 'name' => 'S1 A', 'capacity' => 2]);
        $this->s1b = SchoolClass::query()->create(['academic_year_id' => $year->id, 'level_id' => $s1->id, 'stream' => 'B', 'name' => 'S1 B']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('name', $role)->value('id')]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'sdms_id' => 'sd-001', 'first_name' => 'Aline', 'last_name' => 'Uwimana', 'gender' => 'F', 'date_of_birth' => '2013-03-14',
            'class_id' => $this->s1a->id, 'enrollment_type' => 'new', 'admission_date' => '2026-09-07',
            'guardians' => [['first_name' => 'Josiane', 'last_name' => 'Mukamana', 'phone' => '788123456', 'relationship' => 'mother', 'is_primary' => true]],
        ], $overrides);
    }

    public function test_a_student_is_registered_with_a_parent_and_a_class(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload())->assertSessionHasNoErrors();

        $student = Student::query()->where('sdms_id', 'SD-001')->firstOrFail();
        $this->assertSame('S1 A', $student->currentEnrollment->schoolClass->name);
        $guardian = $student->primaryGuardian()->first();
        $this->assertSame('+250788123456', $guardian->phone);
        $this->assertTrue(AuditLog::query()->where('table_name', 'students')->where('action', 'create')->where('record_id', $student->id)->exists());
    }

    public function test_brothers_and_sisters_share_one_parent_record(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload());
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload(['sdms_id' => 'SD-002', 'first_name' => 'Eric', 'gender' => 'M']));

        $this->assertSame(1, Guardian::query()->count());
        $this->assertSame(2, Guardian::query()->first()->students()->count());
        $this->actingAs($this->dos)->getJson(route('guardians.lookup', ['phone' => '0788 123 456']))->assertJsonPath('0.first_name', 'Josiane');
    }

    public function test_the_parent_who_receives_sms_needs_a_phone(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload(['guardians' => [['phone' => '']]]))
            ->assertSessionHasErrors('guardians.0.phone');
        $this->assertSame(0, Student::query()->count());
    }

    public function test_only_one_primary_guardian_and_one_class_per_year(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload([
            'guardians' => [
                ['first_name' => 'Josiane', 'phone' => '788123456', 'relationship' => 'mother', 'is_primary' => true],
                ['first_name' => 'Jean', 'phone' => '722123456', 'relationship' => 'father', 'is_primary' => true],
            ],
        ]))->assertSessionHasNoErrors();
        $student = Student::query()->firstOrFail();
        $this->assertSame(1, $student->guardians()->wherePivot('is_primary', true)->count());

        // BR-01, enforced by the database too.
        $this->expectException(QueryException::class);
        Enrollment::query()->create(['student_id' => $student->id, 'class_id' => $this->s1b->id, 'academic_year_id' => $this->s1b->academic_year_id]);
    }

    public function test_moving_class_keeps_one_enrolment_and_respects_capacity(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload());
        $student = Student::query()->firstOrFail();

        $this->actingAs($this->dos)->put(route('students.class', $student), ['class_id' => $this->s1b->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame($this->s1b->id, $student->enrollments()->first()->class_id);

        // S1 A holds two students.
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload(['sdms_id' => 'B1', 'guardians' => []]));
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload(['sdms_id' => 'B2', 'guardians' => []]));
        $this->actingAs($this->dos)->put(route('students.class', $student), ['class_id' => $this->s1a->id])->assertSessionHasErrors('class_id');
    }

    public function test_a_transferred_student_leaves_the_class_and_the_change_is_recorded(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload());
        $student = Student::query()->firstOrFail();

        $this->actingAs($this->dos)->post(route('students.status', $student), ['status' => 'transferred', 'date' => '2026-10-01', 'other_school' => 'GS Runda'])
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('transferred', $student->status);
        $this->assertSame('left', $student->enrollments()->first()->status);
        $this->assertSame('GS Runda', $student->transfers()->first()->other_school);
        $this->assertTrue(AuditLog::query()->where('table_name', 'students')->where('action', 'update')->where('new_values->status', 'transferred')->exists());

        // BR-11: no class for a student who left.
        $this->actingAs($this->dos)->put(route('students.class', $student), ['class_id' => $this->s1b->id])->assertSessionHasErrors('class_id');

        $this->actingAs($this->dos)->post(route('students.status', $student), ['status' => 'active', 'date' => '2026-10-05'])->assertSessionHasNoErrors();
        $this->assertSame('active', $student->enrollments()->first()->status);
    }

    public function test_photo_is_stored_privately_and_shrunk(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload(['photo' => UploadedFile::fake()->image('me.jpg', 2000, 1500)]))->assertSessionHasNoErrors();
        $student = Student::query()->firstOrFail();

        Storage::disk('local')->assertExists($student->photo_path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($student->photo_path));
        $this->assertSame(600, max($w, $h));
        $this->actingAs($this->dos)->get(route('students.photo', $student))->assertOk();
        $this->post('/logout');
        $this->get(route('students.photo', $student))->assertRedirect(route('login'));
    }

    public function test_permissions_follow_the_design(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload());
        $student = Student::query()->firstOrFail();

        $bursar = $this->userWithRole('bursar');
        $this->actingAs($bursar)->get(route('students.index'))->assertOk();
        $this->actingAs($bursar)->get(route('students.show', $student))->assertOk();
        $this->actingAs($bursar)->get(route('students.create'))->assertForbidden();

        foreach (['teacher', 'discipline', 'parent'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('students.index'))->assertForbidden();
        }
    }

    public function test_every_student_page_renders(): void
    {
        $this->actingAs($this->dos)->post(route('students.store'), $this->payload());
        $student = Student::query()->firstOrFail();

        foreach ([route('students.index'), route('students.index', ['class_id' => $this->s1a->id, 'status' => 'all', 'search' => '0788']),
            route('students.create'), route('students.show', $student), route('students.edit', $student), route('guardians.index'),
            route('students.import')] as $url) {
            $this->actingAs($this->dos)->get($url)->assertOk();
        }
        $this->actingAs($this->dos)->get(route('students.export'))->assertOk()->assertDownload();
        $this->actingAs($this->dos)->get(route('students.import.template'))->assertOk()->assertDownload();
    }

    public function test_excel_import_matches_columns_checks_rows_and_registers_students(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['GS Test student list'],
            ['Names', 'Sex', 'Date of birth', 'Class', 'Parent', 'Telephone', 'SDMS'],
            ['UWIMANA Aline', 'F', '14/03/2013', 's1a', 'MUKAMANA Josiane', '0788123456', 'X1'],
            ['NSHIMIYIMANA Eric', 'Gabo', '02/11/2012', 'S1 B', 'HABIMANA Jean', '+250 722 123 456', 'X2'],
            ['BAD Row', 'Z', '31/02/2012', 'S9 Z', '', '0700', 'X3'],
            ['UWIMANA Aline', 'F', '', 'S1 A', '', '', 'X1'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($book))->save($path);

        $this->actingAs($this->dos)->post(route('students.import.upload'), ['file' => new UploadedFile($path, 'list.xlsx', null, null, true)])
            ->assertRedirect();
        $token = str_replace('.xlsx', '', basename(Storage::disk('local')->files('imports')[0]));

        $this->actingAs($this->dos)->get(route('students.import', ['token' => $token]))->assertInertia(fn ($page) => $page
            ->where('sheet.count', 4)
            ->where('sheet.mapping', ['full_name', 'gender', 'date_of_birth', 'class', 'guardian_name', 'guardian_phone', 'sdms_id']));

        $options = ['token' => $token, 'mapping' => ['full_name', 'gender', 'date_of_birth', 'class', 'guardian_name', 'guardian_phone', 'sdms_id'], 'enrollment_type' => 'new'];
        $this->actingAs($this->dos)->post(route('students.import.run'), [...$options, 'action' => 'check'])
            ->assertSessionHas('importReport', fn ($r) => $r['create'] === 2 && $r['error_count'] === 2);
        $this->assertSame(0, Student::query()->count());

        $this->actingAs($this->dos)->post(route('students.import.run'), [...$options, 'action' => 'import'])
            ->assertSessionHas('importReport', fn ($r) => $r['result']['created'] === 2);

        $aline = Student::query()->where('sdms_id', 'X1')->firstOrFail();
        $this->assertSame(['Aline', 'UWIMANA'], [$aline->first_name, $aline->last_name]);
        $this->assertSame('S1 A', $aline->currentEnrollment->schoolClass->name);
        $this->assertSame('2013-03-14', $aline->date_of_birth->toDateString());
        $eric = Student::query()->where('sdms_id', 'X2')->firstOrFail();
        $this->assertSame('M', $eric->gender);
        $this->assertSame('+250722123456', $eric->primaryGuardian()->first()->phone);
        $this->assertTrue(AuditLog::query()->where('action', 'import')->exists());
    }
}
