<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ResolvesTenantScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuildFormRequest;
use App\Http\Requests\ImportXlsFormRequest;
use App\Models\Country;
use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use App\Models\MobileSubmission;
use App\Services\AuditLogger;
use App\Services\FormBuilderCompiler;
use App\Services\LocationOptionCatalog;
use App\Services\FormTemplateLibrary;
use App\Services\XlsFormCompiler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminFormController extends Controller
{
    use ResolvesTenantScope;

    public function index(Request $request): View
    {
        $selectedCountry = $this->selectedCountryCode($request);
        $selectedModule = DynamicForm::normalizeModule($request->query('reporting_module'));
        $hasModuleFilter = $request->filled('reporting_module');

        $filteredForms = DynamicForm::query()
            ->where('is_template', false)
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->when($hasModuleFilter, fn ($query) => $query->where('reporting_module', $selectedModule));

        $templateForms = DynamicForm::query()
            ->where('is_template', true)
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->when($hasModuleFilter, fn ($query) => $query->where('reporting_module', $selectedModule))
            ->with(['country', 'publishedVersion'])
            ->orderBy('reporting_module')
            ->orderBy('title')
            ->get();

        $forms = (clone $filteredForms)
            ->with(['country', 'publishedVersion', 'versions' => fn ($query) => $query->latest('version')])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $projectStats = $this->projectStats($forms->getCollection(), $selectedCountry);

        return view('admin.forms.index', [
            'forms' => $forms,
            'countries' => $this->countriesForUser($request),
            'moduleLabels' => DynamicForm::moduleLabels(),
            'projectStats' => $projectStats,
            'templateForms' => $templateForms,
            'summary' => [
                'total' => (clone $filteredForms)->count(),
                'published' => (clone $filteredForms)->whereNotNull('published_version_id')->count(),
                'drafts' => (clone $filteredForms)->whereNull('published_version_id')->count(),
                'submissions' => $projectStats->sum('total'),
                'templates' => $templateForms->count(),
            ],
            'filters' => [
                'country_code' => $selectedCountry,
                'reporting_module' => $hasModuleFilter ? $selectedModule : null,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.forms.create', [
            'countries' => $this->countriesForUser($request),
            'moduleLabels' => DynamicForm::moduleLabels(),
            'defaultStandardReference' => DynamicForm::standardReferenceForModule(DynamicForm::MODULE_IMMIGRATION),
        ]);
    }

    public function builder(Request $request, FormBuilderCompiler $builder, FormTemplateLibrary $templates, LocationOptionCatalog $locations): View
    {
        $selectedTemplate = $templates->get($request->query('template'));

        return view('admin.forms.builder', [
            'form' => null,
            'fieldTypes' => $builder->fieldTypes(),
            'rows' => $selectedTemplate['fields'],
            'templates' => $templates->all(),
            'selectedTemplate' => $selectedTemplate,
            'standardReference' => $selectedTemplate['standard_reference'] ?? DynamicForm::standardReferenceForModule($selectedTemplate['reporting_module'] ?? null),
            'optionSources' => $locations->sourceLabels(),
            'countries' => $this->countriesForUser($request),
            'moduleLabels' => DynamicForm::moduleLabels(),
            'action' => route('admin.forms.builder.store'),
        ]);
    }

    public function editBuilder(DynamicForm $form, FormBuilderCompiler $builder, FormTemplateLibrary $templates, LocationOptionCatalog $locations): View
    {
        abort_if($form->is_template, 403, 'Built-in templates are read-only. Clone the template before editing it.');
        $this->assertCanAccessRecordCountry(request(), $form);

        $version = $form->versions()->latest('version')->first();

        return view('admin.forms.builder', [
            'form' => $form,
            'fieldTypes' => $builder->fieldTypes(),
            'rows' => $version ? $this->builderRowsForEdit($form, $version, $builder, $templates) : $templates->get(null)['fields'],
            'templates' => $templates->all(),
            'selectedTemplate' => null,
            'standardReference' => $version?->compiled_schema['standardReference'] ?? DynamicForm::standardReferenceForModule($form->reporting_module),
            'optionSources' => $locations->sourceLabels(),
            'countries' => $this->countriesForUser(request()),
            'moduleLabels' => DynamicForm::moduleLabels(),
            'action' => route('admin.forms.builder.update', $form),
        ]);
    }

    public function storeBuilder(BuildFormRequest $request, FormBuilderCompiler $builder, AuditLogger $audit): RedirectResponse
    {
        $form = $this->createBuilderVersion($request, $builder, $audit);

        return redirect()->route('admin.forms.show', $form)->with('status', 'Form builder version saved successfully.');
    }

    public function updateBuilder(BuildFormRequest $request, DynamicForm $form, FormBuilderCompiler $builder, AuditLogger $audit): RedirectResponse
    {
        if ($form->is_template) {
            return redirect()
                ->route('admin.forms.show', $form)
                ->with('status', 'Built-in templates are read-only. Clone the template before editing it.');
        }
        $this->assertCanAccessRecordCountry($request, $form);

        $form = $this->createBuilderVersion($request, $builder, $audit, $form);

        return redirect()->route('admin.forms.show', $form)->with('status', 'New form builder version saved successfully.');
    }

    public function store(ImportXlsFormRequest $request, XlsFormCompiler $compiler, AuditLogger $audit): RedirectResponse
    {
        $this->assertCanAccessCountry($request, $request->validated('country_code'));

        $path = $request->file('file')->store('xlsforms');
        $fullPath = Storage::path($path);

        $form = DB::transaction(function () use ($request, $compiler, $path, $fullPath) {
            $reportingModule = DynamicForm::normalizeModule($request->validated('reporting_module'));
            $standardReference = $request->validated('standard_reference') ?: DynamicForm::standardReferenceForModule($reportingModule);
            $preflight = $compiler->compile($fullPath, $request->input('title'), reportingModule: $reportingModule, standardReference: $standardReference);
            $formId = $preflight['schema']['formId'];
            $existingTemplate = DynamicForm::query()
                ->where('country_code', $request->validated('country_code'))
                ->where('form_id', $formId)
                ->where('is_template', true)
                ->exists();

            if ($existingTemplate) {
                $formId = $this->uniqueClonedFormId(
                    $request->validated('country_code'),
                    $preflight['schema']['title'],
                    $preflight['schema']['formId']
                );
            }

            $form = DynamicForm::query()->firstOrCreate(
                [
                    'country_code' => $request->validated('country_code'),
                    'form_id' => $formId,
                ],
                [
                    'reporting_module' => $reportingModule,
                    'title' => $preflight['schema']['title'],
                    'is_template' => false,
                ]
            );
            $form->forceFill([
                'title' => $preflight['schema']['title'],
                'reporting_module' => $reportingModule,
                'is_template' => false,
            ])->save();

            $nextVersion = ((int) $form->versions()->max('version')) + 1;
            $compiled = $compiler->compile($fullPath, $request->input('title'), $nextVersion, $form->reporting_module, $standardReference);
            $compiled['schema']['formId'] = $form->form_id;

            $version = $form->versions()->create([
                'version' => $nextVersion,
                'source_file_path' => $path,
                'compiled_schema' => $compiled['schema'],
                'source_metadata' => $compiled['metadata'],
                'is_published' => false,
            ]);

            if ($request->boolean('publish')) {
                $this->publishVersion($form, $version);
            }

            return $form;
        });

        $audit->record('admin.form_imported', $request->user(), auditable: $form, metadata: [
            'form_id' => $form->form_id,
            'reporting_module' => $form->reporting_module,
            'standard_reference' => $form->versions()->latest('version')->first()?->compiled_schema['standardReference'] ?? DynamicForm::standardReferenceForModule($form->reporting_module),
            'published' => $request->boolean('publish'),
        ], request: $request);

        return redirect()->route('admin.forms.show', $form)->with('status', 'XLSForm imported successfully.');
    }

    public function show(DynamicForm $form): View
    {
        $this->assertCanAccessRecordCountry(request(), $form);

        $form->load(['country', 'publishedVersion', 'versions' => fn ($query) => $query->latest('version')]);

        return view('admin.forms.show', ['form' => $form]);
    }

    public function cloneTemplate(Request $request, DynamicForm $template, AuditLogger $audit): RedirectResponse
    {
        abort_unless($template->is_template, 404);

        $validated = $request->validate([
            'country_code' => ['nullable', 'string', 'size:3', 'exists:countries,code'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $sourceVersion = $template->publishedVersion ?: $template->versions()->latest('version')->firstOrFail();
        $countryCode = strtoupper((string) ($validated['country_code'] ?? $template->country_code));
        $this->assertCanAccessCountry($request, $countryCode);

        $title = trim((string) ($validated['title'] ?? '')) ?: $template->title;
        $formId = $this->uniqueClonedFormId($countryCode, $title, $template->form_id);

        $form = DB::transaction(function () use ($template, $sourceVersion, $countryCode, $title, $formId) {
            $schema = $sourceVersion->compiled_schema;
            $schema['formId'] = $formId;
            $schema['title'] = $title;
            $schema['version'] = 1;

            $form = DynamicForm::query()->create([
                'country_code' => $countryCode,
                'reporting_module' => DynamicForm::normalizeModule($template->reporting_module),
                'form_id' => $formId,
                'title' => $title,
                'is_template' => false,
            ]);

            $form->versions()->create([
                'version' => 1,
                'source_file_path' => "template-clone://forms/{$template->id}/versions/{$sourceVersion->version}",
                'compiled_schema' => $schema,
                'source_metadata' => array_merge($sourceVersion->source_metadata ?? [], [
                    'source' => 'template_clone',
                    'template_form_id' => $template->form_id,
                    'template_key' => $template->template_key,
                    'template_title' => $template->title,
                    'cloned_at' => now()->toISOString(),
                ]),
                'is_published' => false,
            ]);

            return $form;
        });

        $audit->record('admin.form_template_cloned', $request->user(), auditable: $form, metadata: [
            'template_form_id' => $template->form_id,
            'form_id' => $form->form_id,
            'country_code' => $form->country_code,
        ], request: $request);

        return redirect()
            ->route('admin.forms.builder.edit', $form)
            ->with('status', 'Template copied into a new editable project. Review the questions, then publish when ready.');
    }

    public function publish(DynamicForm $form, int $version, AuditLogger $audit): RedirectResponse
    {
        if ($form->is_template) {
            return back()->with('status', 'Built-in templates are read-only. Clone the template before publishing an operational project.');
        }
        $this->assertCanAccessRecordCountry(request(), $form);

        $formVersion = $form->versions()->where('version', $version)->firstOrFail();

        DB::transaction(fn () => $this->publishVersion($form, $formVersion));
        $audit->record('admin.form_published', request()->user(), auditable: $formVersion, metadata: [
            'form_id' => $form->form_id,
            'version' => $version,
        ], request: request());

        return back()->with('status', "Version {$version} published.");
    }

    public function destroy(Request $request, DynamicForm $form, AuditLogger $audit): RedirectResponse
    {
        if ($form->is_template) {
            return back()->with('status', 'Built-in templates are protected. Clone a template before changing or deleting an operational project.');
        }

        $this->assertCanAccessRecordCountry($request, $form);

        $submissionCount = MobileSubmission::query()
            ->where('country_code', $form->country_code)
            ->where('form_id', $form->form_id)
            ->count();

        if ($submissionCount > 0) {
            return back()->with(
                'status',
                'This project has synced records, so it was not deleted. Keep it for audit history, exports, and legal review.'
            );
        }

        $metadata = [
            'form_id' => $form->form_id,
            'country_code' => $form->country_code,
            'title' => $form->title,
            'versions' => $form->versions()->count(),
        ];

        DB::transaction(function () use ($form): void {
            $form->forceFill(['published_version_id' => null])->save();
            $form->versions()->delete();
            $form->delete();
        });

        $audit->record('admin.form_deleted', $request->user(), metadata: $metadata, request: $request);

        return redirect()
            ->route('admin.forms.index')
            ->with('status', 'Project deleted. No synced records were attached to it.');
    }

    private function publishVersion(DynamicForm $form, DynamicFormVersion $version): void
    {
        $form->versions()->update(['is_published' => false]);
        $version->forceFill(['is_published' => true])->save();
        $form->forceFill(['published_version_id' => $version->id])->save();
    }

    private function createBuilderVersion(BuildFormRequest $request, FormBuilderCompiler $builder, AuditLogger $audit, ?DynamicForm $form = null): DynamicForm
    {
        return DB::transaction(function () use ($request, $builder, $audit, $form) {
            $validated = $request->validated();
            $this->assertCanAccessCountry($request, $validated['country_code'] ?? $form?->country_code);

            if (! $form) {
                $requestedFormId = strtolower($validated['form_id']);
                $existingTemplate = DynamicForm::query()
                    ->where('country_code', $validated['country_code'])
                    ->where('form_id', $requestedFormId)
                    ->where('is_template', true)
                    ->exists();

                if ($existingTemplate) {
                    $validated['form_id'] = $this->uniqueClonedFormId(
                        $validated['country_code'],
                        $validated['title'],
                        $requestedFormId
                    );
                }
            }

            $form = $form ?: DynamicForm::query()->firstOrCreate(
                [
                    'country_code' => $validated['country_code'],
                    'form_id' => strtolower($validated['form_id']),
                ],
                [
                    'reporting_module' => $validated['reporting_module'],
                    'title' => $validated['title'],
                    'is_template' => false,
                ]
            );

            // Existing forms keep their public form_id stable; mobile devices use it as the sync key.
            $validated['form_id'] = $form->form_id;
            $validated['country_code'] = $form->country_code;
            $form->forceFill([
                'title' => $validated['title'],
                'reporting_module' => $validated['reporting_module'],
                'is_template' => false,
            ])->save();

            $nextVersion = ((int) $form->versions()->max('version')) + 1;
            $compiled = $builder->compile($validated, $nextVersion);
            $version = $form->versions()->create([
                'version' => $nextVersion,
                'source_file_path' => "builder://forms/{$form->id}/versions/{$nextVersion}",
                'compiled_schema' => $compiled['schema'],
                'source_metadata' => $compiled['metadata'],
                'is_published' => false,
            ]);

            if ($request->boolean('publish')) {
                $this->publishVersion($form, $version);
            }

            $audit->record('admin.form_builder_version_saved', $request->user(), auditable: $version, metadata: [
                'form_id' => $form->form_id,
                'reporting_module' => $form->reporting_module,
                'standard_reference' => $validated['standard_reference'] ?? DynamicForm::standardReferenceForModule($form->reporting_module),
                'version' => $nextVersion,
                'published' => $request->boolean('publish'),
            ], request: $request);

            return $form;
        });
    }

    private function builderRowsForEdit(
        DynamicForm $form,
        DynamicFormVersion $version,
        FormBuilderCompiler $builder,
        FormTemplateLibrary $templates
    ): array {
        $savedRows = $version->source_metadata['builderRows'] ?? null;
        if (is_array($savedRows) && $savedRows !== []) {
            return $savedRows;
        }

        $currentRows = $builder->builderRowsFromSchema($version->compiled_schema);
        $template = $this->templateForFormVersion($form, $version, $templates);

        if (!$template) {
            return $currentRows;
        }

        return $this->mergeTemplateRowsWithCurrent($template['fields'], $currentRows);
    }

    private function templateForFormVersion(DynamicForm $form, DynamicFormVersion $version, FormTemplateLibrary $templates): ?array
    {
        $metadata = $version->source_metadata ?? [];
        $templateKey = (string) ($metadata['template_key'] ?? '');
        $templateFormId = (string) ($metadata['template_form_id'] ?? '');

        foreach ($templates->all() as $key => $template) {
            if ($templateKey !== '' && $key === $templateKey) {
                return $template;
            }

            if ($templateFormId !== '' && ($template['form_id'] ?? '') === $templateFormId) {
                return $template;
            }

            if (($template['form_id'] ?? '') === $form->form_id) {
                return $template;
            }

            if (($template['reporting_module'] ?? '') === $form->reporting_module
                && ($template['standard_reference'] ?? '') === ($version->compiled_schema['standardReference'] ?? null)
                && ($template['title'] ?? '') === $form->title) {
                return $template;
            }
        }

        return null;
    }

    private function mergeTemplateRowsWithCurrent(array $templateRows, array $currentRows): array
    {
        $currentById = collect($currentRows)
            ->filter(fn (array $row): bool => trim((string) ($row['id'] ?? '')) !== '')
            ->keyBy(fn (array $row): string => (string) $row['id']);

        $templateIds = [];
        $merged = collect($templateRows)
            ->map(function (array $templateRow) use ($currentById, &$templateIds): array {
                $id = (string) ($templateRow['id'] ?? '');
                $templateIds[] = $id;

                if ($id !== '' && $currentById->has($id)) {
                    return array_merge($templateRow, $currentById->get($id), ['include' => true]);
                }

                return array_merge($templateRow, ['include' => false]);
            })
            ->values();

        $customRows = collect($currentRows)
            ->filter(fn (array $row): bool => !in_array((string) ($row['id'] ?? ''), $templateIds, true))
            ->map(fn (array $row): array => array_merge($row, ['include' => true]))
            ->values();

        return $merged->merge($customRows)->values()->all();
    }

    private function projectStats(iterable $forms, ?string $selectedCountry): \Illuminate\Support\Collection
    {
        $forms = collect($forms);

        if ($forms->isEmpty()) {
            return collect();
        }

        return MobileSubmission::query()
            ->selectRaw('country_code, form_id, count(*) as total')
            ->selectRaw("sum(case when status = 'accepted' then 1 else 0 end) as accepted")
            ->selectRaw("sum(case when status = 'rejected' then 1 else 0 end) as rejected")
            ->selectRaw('max(received_at) as latest_received_at')
            ->whereIn('form_id', $forms->pluck('form_id')->unique()->values())
            ->when($selectedCountry, fn ($query) => $query->where('country_code', $selectedCountry))
            ->groupBy('country_code', 'form_id')
            ->get()
            ->keyBy(fn ($row) => $this->projectKey((string) $row->country_code, (string) $row->form_id));
    }

    private function projectKey(string $countryCode, string $formId): string
    {
        return strtoupper($countryCode).'|'.$formId;
    }

    private function uniqueClonedFormId(string $countryCode, string $title, string $fallback): string
    {
        $base = Str::slug($title, '_') ?: Str::slug($fallback, '_') ?: 'border_report';
        $base = Str::limit(strtolower($base), 90, '');
        $candidate = $base;
        $suffix = 1;

        while (DynamicForm::query()->where('country_code', $countryCode)->where('form_id', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$base}_copy_{$suffix}";
        }

        return $candidate;
    }

}
