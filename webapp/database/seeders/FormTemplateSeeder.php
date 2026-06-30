<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use App\Services\FormBuilderCompiler;
use App\Services\FormTemplateLibrary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormTemplateSeeder extends Seeder
{
    /**
     * Upload the built-in standards-backed starter forms for demo and pilot use.
     */
    public function run(): void
    {
        $templates = app(FormTemplateLibrary::class);
        $builder = app(FormBuilderCompiler::class);

        DB::transaction(function () use ($templates, $builder): void {
            foreach ($this->targetCountryCodes() as $countryCode) {
                foreach ($templates->all() as $key => $template) {
                    $this->publishTemplate($countryCode, $key, $template, $builder);
                }
            }
        });
    }

    /**
     * Keep seeded template forms country-scoped. For local demos this means SLE,
     * while future active tenants automatically receive the same starter catalog.
     *
     * @return array<int, string>
     */
    private function targetCountryCodes(): array
    {
        $codes = Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->map(fn (string $code): string => strtoupper($code))
            ->all();

        return $codes ?: ['SLE'];
    }

    /**
     * Publish a template only when it is missing or unpublished, so seeded demo
     * content does not replace versions an admin has already customized.
     *
     * @param  array{name: string, description: string, reporting_module: string, standard_reference: string, form_id: string, title: string, fields: array<int, array<string, mixed>>}  $template
     */
    private function publishTemplate(string $countryCode, string $key, array $template, FormBuilderCompiler $builder): void
    {
        $form = DynamicForm::query()->firstOrCreate(
            [
                'country_code' => $countryCode,
                'form_id' => strtolower($template['form_id']),
            ],
            [
                'reporting_module' => DynamicForm::normalizeModule($template['reporting_module']),
                'title' => $template['title'],
            ]
        );

        $form->forceFill([
            'reporting_module' => DynamicForm::normalizeModule($template['reporting_module']),
            'title' => $form->title ?: $template['title'],
            'is_template' => true,
            'template_key' => $key,
            'template_description' => $template['description'],
            'template_summary' => app(FormTemplateLibrary::class)->summarize($template),
        ])->save();

        if ($form->versions()->exists()) {
            $this->ensurePublishedVersion($form);

            return;
        }

        $input = [
            'country_code' => $countryCode,
            'form_id' => $form->form_id,
            'title' => $template['title'],
            'reporting_module' => DynamicForm::normalizeModule($template['reporting_module']),
            'standard_reference' => $template['standard_reference'],
            'fields' => $template['fields'],
        ];

        $compiled = $builder->compile($input, 1);
        $version = $form->versions()->create([
            'version' => 1,
            'source_file_path' => "template://{$countryCode}/{$key}/versions/1",
            'compiled_schema' => $compiled['schema'],
            'source_metadata' => array_merge($compiled['metadata'], [
                'template_key' => $key,
                'template_name' => $template['name'],
                'template_description' => $template['description'],
            ]),
            'is_published' => true,
        ]);

        $form->forceFill(['published_version_id' => $version->id])->save();
    }

    private function ensurePublishedVersion(DynamicForm $form): void
    {
        if ($form->published_version_id && $form->publishedVersion()->exists()) {
            return;
        }

        /** @var DynamicFormVersion|null $latestVersion */
        $latestVersion = $form->versions()->latest('version')->first();

        if (! $latestVersion) {
            return;
        }

        $form->versions()->update(['is_published' => false]);
        $latestVersion->forceFill(['is_published' => true])->save();
        $form->forceFill(['published_version_id' => $latestVersion->id])->save();
    }
}
