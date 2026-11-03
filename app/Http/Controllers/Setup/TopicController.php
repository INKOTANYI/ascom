<?php

namespace App\Http\Controllers\Setup;

use App\Models\Level;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Response;

class TopicController extends ResourceController
{
    protected string $model = Topic::class;

    protected string $routeName = 'setup.topics';

    protected string $title = 'Topics and units';

    protected string $description = 'Units or learning outcomes per subject and level. Assessments are linked to them for learning gap analysis.';

    protected array $searchable = ['title'];

    protected function query(): Builder
    {
        $request = request();

        return Topic::query()->with(['subject:id,code,name', 'level:id,code'])
            ->when($request->integer('subject_id'), fn ($q, $id) => $q->where('subject_id', $id))
            ->when($request->integer('level_id'), fn ($q, $id) => $q->where('level_id', $id))
            ->orderBy('subject_id')->orderBy('level_id')->orderBy('unit_no');
    }

    public function index(Request $request): Response
    {
        return parent::index($request)->with('filters', [
            ['name' => 'subject_id', 'label' => __('Subject'), 'options' => $this->subjectOptions(), 'value' => $request->query('subject_id')],
            ['name' => 'level_id', 'label' => __('Level'), 'options' => $this->levelOptions(), 'value' => $request->query('level_id')],
        ]);
    }

    protected function row(Model $record): array
    {
        return [...$record->toArray(), 'subject_label' => $record->subject?->name, 'level_label' => $record->level?->code];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'subject_label', 'label' => __('Subject')],
            ['key' => 'level_label', 'label' => __('Level'), 'type' => 'badge'],
            ['key' => 'unit_no', 'label' => __('Unit')],
            ['key' => 'title', 'label' => __('Topic')],
            ['key' => 'term_number', 'label' => __('Term')],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'subject_id', 'label' => __('Subject'), 'type' => 'select', 'required' => true, 'options' => $this->subjectOptions()],
            ['name' => 'level_id', 'label' => __('Level'), 'type' => 'select', 'required' => true, 'options' => $this->levelOptions()],
            ['name' => 'unit_no', 'label' => __('Unit number'), 'type' => 'number'],
            ['name' => 'term_number', 'label' => __('Planned term'), 'type' => 'select', 'options' => [['value' => 1, 'label' => '1'], ['value' => 2, 'label' => '2'], ['value' => 3, 'label' => '3']]],
            ['name' => 'title', 'label' => __('Topic or competence'), 'type' => 'text', 'required' => true, 'width' => 'full'],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:levels,id'],
            'unit_no' => ['nullable', 'integer', 'min:1', 'max:99'],
            'term_number' => ['nullable', 'integer', 'between:1,3'],
            'title' => ['required', 'string', 'max:200'],
        ];
    }

    private function subjectOptions(): array
    {
        return Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all();
    }

    private function levelOptions(): array
    {
        return Level::query()->where('is_offered', true)->orderBy('order_no')->get(['id', 'code', 'name'])
            ->map(fn ($l) => ['value' => $l->id, 'label' => "{$l->code} · {$l->name}"])->all();
    }
}
