<?php

namespace App\Http\Controllers\Setup;

use App\Models\Staff;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class StaffController extends ResourceController
{
    protected string $model = Staff::class;

    protected string $routeName = 'setup.staff';

    protected string $title = 'Staff';

    protected string $description = 'Teachers and other staff. Teachers are then assigned to classes and subjects.';

    protected string $managePermission = 'staff.manage';

    protected string $viewPermission = 'staff.view';

    protected array $searchable = ['first_name', 'last_name', 'staff_number', 'phone'];

    protected function query(): Builder
    {
        return Staff::query()->orderByDesc('is_active')->orderBy('last_name')->orderBy('first_name');
    }

    protected function row(Model $record): array
    {
        return [
            ...$record->toArray(),
            'position_label' => __(Staff::POSITIONS[$record->position] ?? $record->position),
            // The form edits the 9 digits after +250.
            'phone' => $record->phone ? substr($record->phone, 4) : null,
            'phone_display' => $record->phone,
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'full_name', 'label' => __('Name'), 'type' => 'strong'],
            ['key' => 'position_label', 'label' => __('Position'), 'type' => 'badge'],
            ['key' => 'phone_display', 'label' => __('Phone')],
            ['key' => 'qualification', 'label' => __('Qualification')],
            ['key' => 'is_active', 'label' => __('Active'), 'type' => 'bool'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'first_name', 'label' => __('First name'), 'type' => 'text', 'required' => true],
            ['name' => 'last_name', 'label' => __('Last name'), 'type' => 'text', 'required' => true],
            ['name' => 'gender', 'label' => __('Gender'), 'type' => 'select', 'options' => [['value' => 'F', 'label' => __('Female')], ['value' => 'M', 'label' => __('Male')]]],
            ['name' => 'phone', 'label' => __('Phone'), 'type' => 'phone'],
            ['name' => 'position', 'label' => __('Position'), 'type' => 'select', 'required' => true, 'options' => self::options(Staff::POSITIONS)],
            ['name' => 'employment_type', 'label' => __('Employment'), 'type' => 'select', 'options' => self::options(Staff::EMPLOYMENT)],
            ['name' => 'staff_number', 'label' => __('Staff number'), 'type' => 'text'],
            ['name' => 'national_id', 'label' => __('National ID'), 'type' => 'text', 'placeholder' => '1 1990 8 0012345 0 12'],
            ['name' => 'qualification', 'label' => __('Qualification'), 'type' => 'text'],
            ['name' => 'hire_date', 'label' => __('Hire date'), 'type' => 'date'],
            ['name' => 'is_active', 'label' => __('Active'), 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', Rule::in(['M', 'F'])],
            'phone' => ['nullable', 'regex:/^7[2389]\d{7}$/'],
            'position' => ['required', Rule::in(array_keys(Staff::POSITIONS))],
            'employment_type' => ['nullable', Rule::in(array_keys(Staff::EMPLOYMENT))],
            'staff_number' => ['nullable', 'string', 'max:30', Rule::unique('staff', 'staff_number')->ignore($record?->id)],
            'national_id' => ['nullable', 'regex:/^\d{16}$/'],
            'qualification' => ['nullable', 'string', 'max:120'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepare(array $data): array
    {
        return [...$data, 'phone' => Phone::normalize($data['phone'] ?? null)];
    }
}
