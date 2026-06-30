<?php

namespace Tests\Feature;

use App\Models\DynamicForm;
use App\Services\FormTemplateLibrary;
use Database\Seeders\FormTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_template_seeder_uploads_published_starter_templates_once(): void
    {
        $this->seed(FormTemplateSeeder::class);
        $this->seed(FormTemplateSeeder::class);

        $templates = app(FormTemplateLibrary::class)->all();

        $this->assertDatabaseCount('dynamic_forms', count($templates));
        $this->assertDatabaseCount('dynamic_form_versions', count($templates));

        foreach ($templates as $template) {
            $form = DynamicForm::query()
                ->where('country_code', 'SLE')
                ->where('form_id', $template['form_id'])
                ->firstOrFail();

            $this->assertSame($template['title'], $form->title);
            $this->assertSame($template['reporting_module'], $form->reporting_module);
            $this->assertTrue($form->is_template);
            $this->assertNotEmpty($form->template_summary['sections'] ?? []);
            $this->assertNotNull($form->published_version_id);
            $this->assertSame(1, $form->versions()->count());
            $this->assertTrue($form->publishedVersion->is_published);
            $this->assertSame($template['standard_reference'], $form->publishedVersion->compiled_schema['standardReference']);
            $this->assertNotEmpty($form->publishedVersion->compiled_schema['fields']);
        }
    }
}
