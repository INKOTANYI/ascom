<?php

namespace App\Http\Controllers\Setup;

use App\Models\Combination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CombinationController extends ResourceController
{
    protected string $model = Combination::class;

    protected string $routeName = 'setup.combinations';

    protected string $title = 'Combinations and trades';

    protected string $description = 'A-level combinations (e.g. MPC) and TVET trades (e.g. SOD). Use the codes shown in SDMS.';

    protected array $searchable = ['code', 'name'];

    protected function query(): Builder
    {
        return Combination::query()->orderBy('type')->orderBy('code');
    }

    protected function row(Model $record): array
    {
        return [...$record->toArray(), 'type_label' => __(Combination::TYPES[$record->type] ?? $record->type)];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code'), 'type' => 'code'],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'type_label', 'label' => __('Type'), 'type' => 'badge'],
            ['key' => 'is_offered', 'label' => __('Offered'), 'type' => 'bool'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => __('Code'), 'type' => 'text', 'required' => true, 'placeholder' => 'SOD'],
            ['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'required' => true, 'placeholder' => 'Software Development'],
            ['name' => 'type', 'label' => __('Type'), 'type' => 'select', 'required' => true, 'options' => self::options(Combination::TYPES)],
            ['name' => 'is_offered', 'label' => __('Offered by this school'), 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('combinations', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Combination::TYPES))],
            'is_offered' => ['boolean'],
        ];
    }

    protected function prepare(array $data): array
    {
        return [...$data, 'code' => strtoupper($data['code'])];
    }
}
