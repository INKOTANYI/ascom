<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared list + add/edit/delete screen for the simple setup tables.
 * Each child describes its model, columns, form fields and validation; the React page
 * Setup/Resource renders any of them the same way.
 */
abstract class ResourceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $routeName;

    protected string $title;

    protected string $description = '';

    protected string $managePermission = 'structure.manage';

    protected string $viewPermission = 'structure.view';

    /** Columns searched by the search box. */
    protected array $searchable = ['name'];

    protected int $perPage = 50;

    abstract protected function columns(): array;

    abstract protected function fields(): array;

    abstract protected function rules(?Model $record): array;

    protected function query(): Builder
    {
        return $this->model::query();
    }

    protected function row(Model $record): array
    {
        return $record->toArray();
    }

    protected function prepare(array $data): array
    {
        return $data;
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can($this->viewPermission) || $request->user()->can($this->managePermission), 403);

        $search = trim((string) $request->query('search', ''));
        $records = $this->query()
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $w) use ($search) {
                    foreach ($this->searchable as $column) {
                        $w->orWhere($column, 'ilike', "%{$search}%");
                    }
                });
            })
            ->paginate($this->perPage)
            ->withQueryString()
            ->through(fn (Model $record) => $this->row($record));

        return Inertia::render('Setup/Resource', [
            'title' => __($this->title),
            'description' => __($this->description),
            'routeName' => $this->routeName,
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'records' => $records,
            'search' => $search,
            'canManage' => $request->user()->can($this->managePermission),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can($this->managePermission), 403);
        $data = $this->prepare($request->validate($this->rules(null)));
        $this->model::query()->create($data);

        return back()->with('success', __('Saved.'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can($this->managePermission), 403);
        $record = $this->model::query()->findOrFail($id);
        $record->update($this->prepare($request->validate($this->rules($record))));

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can($this->managePermission), 403);
        try {
            $this->model::query()->findOrFail($id)->delete();
        } catch (QueryException) {
            // Foreign keys restrict deletes (design section 16): history is never lost.
            return back()->with('error', __('This record is in use and cannot be deleted. Mark it as not offered or inactive instead.'));
        }

        return back()->with('success', __('Deleted.'));
    }

    /** ['a' => 'A label'] to [{value, label}] for select fields, translated. */
    protected static function options(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => __($label)])->values()->all();
    }
}
