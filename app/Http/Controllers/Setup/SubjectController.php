<?php

namespace App\Http\Controllers\Setup;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SubjectController extends ResourceController
{
    protected string $model = Subject::class;

    protected string $routeName = 'setup.subjects';

    protected string $title = 'Subjects and modules';

    protected string $description = 'Subjects and TVET modules (CAMIS "courses"). The CAMIS course code must match CAMIS exactly.';

    protected array $searchable = ['code', 'name', 'camis_course_code'];

    protected function query(): Builder
    {
        return Subject::query()->orderBy('name');
    }

    protected function row(Model $record): array
    {
        return [...$record->toArray(), 'course_type_label' => $record->course_type ? __(Subject::COURSE_TYPES[$record->course_type] ?? $record->course_type) : null];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code'), 'type' => 'code'],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'course_type_label', 'label' => __('Course type'), 'type' => 'badge'],
            ['key' => 'camis_course_code', 'label' => __('CAMIS code'), 'type' => 'code'],
            ['key' => 'is_active', 'label' => __('Active'), 'type' => 'bool'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => __('Code'), 'type' => 'text', 'required' => true, 'placeholder' => 'MATH'],
            ['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'required' => true, 'placeholder' => 'Mathematics'],
            ['name' => 'course_type', 'label' => __('Course type'), 'type' => 'select', 'options' => self::options(Subject::COURSE_TYPES)],
            ['name' => 'camis_course_code', 'label' => __('CAMIS course code'), 'type' => 'text', 'help' => __('As shown in CAMIS / SDMS.')],
            ['name' => 'curriculum_ref', 'label' => __('Curriculum reference'), 'type' => 'text', 'width' => 'full'],
            ['name' => 'is_active', 'label' => __('Active'), 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:120'],
            'course_type' => ['nullable', Rule::in(array_keys(Subject::COURSE_TYPES))],
            'camis_course_code' => ['nullable', 'string', 'max:30'],
            'curriculum_ref' => ['nullable', 'string', 'max:80'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(array $data): array
    {
        return [...$data, 'code' => strtoupper($data['code'])];
    }
}
