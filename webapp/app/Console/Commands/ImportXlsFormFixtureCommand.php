<?php

namespace App\Console\Commands;

use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use App\Services\XlsFormCompiler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportXlsFormFixtureCommand extends Command
{
    protected $signature = 'forms:import-fixture {path} {--publish} {--title=} {--module=immigration} {--standard=}';
    protected $description = 'Import an XLSForm fixture and optionally publish the created version.';

    public function handle(XlsFormCompiler $compiler): int
    {
        $sourcePath = (string) $this->argument('path');
        if (!is_file($sourcePath)) {
            $sourcePath = base_path($sourcePath);
        }

        if (!is_file($sourcePath)) {
            $this->error('Fixture file not found.');
            return self::FAILURE;
        }

        $storedPath = 'xlsforms/'.basename($sourcePath);
        Storage::put($storedPath, file_get_contents($sourcePath));
        $fullPath = Storage::path($storedPath);

        DB::transaction(function () use ($compiler, $storedPath, $fullPath) {
            $title = $this->option('title');
            $reportingModule = DynamicForm::normalizeModule((string) $this->option('module'));
            $standardReference = trim((string) $this->option('standard')) ?: DynamicForm::standardReferenceForModule($reportingModule);
            $preflight = $compiler->compile($fullPath, $title, reportingModule: $reportingModule, standardReference: $standardReference);
            $form = DynamicForm::query()->firstOrCreate(
                ['form_id' => $preflight['schema']['formId']],
                [
                    'reporting_module' => $reportingModule,
                    'title' => $preflight['schema']['title'],
                ]
            );
            $form->forceFill([
                'title' => $preflight['schema']['title'],
                'reporting_module' => $reportingModule,
            ])->save();

            $nextVersion = ((int) $form->versions()->max('version')) + 1;
            $compiled = $compiler->compile($fullPath, $title, $nextVersion, $form->reporting_module, $standardReference);

            $version = $form->versions()->create([
                'version' => $nextVersion,
                'source_file_path' => $storedPath,
                'compiled_schema' => $compiled['schema'],
                'source_metadata' => $compiled['metadata'],
                'is_published' => false,
            ]);

            if ($this->option('publish')) {
                $form->versions()->update(['is_published' => false]);
                $version->forceFill(['is_published' => true])->save();
                $form->forceFill(['published_version_id' => $version->id])->save();
            }

            $this->info("Imported {$form->form_id} version {$version->version}.");
        });

        return self::SUCCESS;
    }
}
