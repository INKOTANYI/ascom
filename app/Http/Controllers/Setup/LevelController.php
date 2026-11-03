<?php

namespace App\Http\Controllers\Setup;

use App\Models\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class LevelController extends ResourceController
{
    protected string $model = Level::class;

    protected string $routeName = 'setup.levels';

    protected string $title = 'Levels';

    protected string $description = 'Nursery, primary, O-level, A-level and TVET levels. Untick "Offered" for levels this school does not teach.';

    protected array $searchable = ['code', 'name'];

    protected function query(): Builder
    {
        return Level::query()->orderBy('order_no');
    }

    protected function row(Model $record): array
    {
        return [...$record->toArray(), 'cycle_label' => __(Level::CYCLES[$record->cycle] ?? $record->cycle)];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code'), 'type' => 'code'],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'cycle_label', 'label' => __('Cycle'), 'type' => 'badge'],
            ['key' => 'order_no', 'label' => __('Order')],
            ['key' => 'is_offered', 'label' => __('Offered'), 'type' => 'bool'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => __('Code'), 'type' => 'text', 'required' => true, 'placeholder' => 'S4'],
            ['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'required' => true],
            ['name' => 'cycle', 'label' => __('Cycle'), 'type' => 'select', 'required' => true, 'options' => self::options(Level::CYCLES)],
            ['name' => 'order_no', 'label' => __('Order'), 'type' => 'number', 'required' => true, 'help' => __('Used for sorting and promotion to the next level.')],
            ['name' => 'is_offered', 'label' => __('Offered by this school'), 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('levels', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:50'],
            'cycle' => ['required', Rule::in(array_keys(Level::CYCLES))],
            'order_no' => ['required', 'integer', 'min:1', 'max:99'],
            'is_offered' => ['boolean'],
        ];
    }
}
