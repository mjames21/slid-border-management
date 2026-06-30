<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportXlsFormRequest;
use App\Models\DynamicForm;
use App\Models\DynamicFormVersion;
use App\Services\XlsFormCompiler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FormImportController extends Controller
{
    public function __invoke(ImportXlsFormRequest $request, XlsFormCompiler $compiler): JsonResponse
    {
        $path = $request->file('file')->store('xlsforms');
        $fullPath = Storage::path($path);

        return DB::transaction(function () use ($request, $compiler, $path, $fullPath) {
            $reportingModule = DynamicForm::normalizeModule($request->validated('reporting_module'));
            $standardReference = $request->validated('standard_reference') ?: DynamicForm::standardReferenceForModule($reportingModule);
            $preflight = $compiler->compile($fullPath, $request->input('title'), reportingModule: $reportingModule, standardReference: $standardReference);
            $countryCode = $request->validated('country_code');
            $formId = $preflight['schema']['formId'];

            if (DynamicForm::query()->where('country_code', $countryCode)->where('form_id', $formId)->where('is_template', true)->exists()) {
                return response()->json([
                    'message' => 'This form ID belongs to a protected template. Clone the template before importing or publishing an operational project.',
                ], 422);
            }

            $form = DynamicForm::query()->firstOrCreate(
                [
                    'country_code' => $countryCode,
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
                $this->publish($form, $version);
            }

            return response()->json(['form' => $form->fresh('versions'), 'version' => $version->fresh()], 201);
        });
    }

    private function publish(DynamicForm $form, DynamicFormVersion $version): void
    {
        $form->versions()->update(['is_published' => false]);
        $version->forceFill(['is_published' => true])->save();
        $form->forceFill(['published_version_id' => $version->id])->save();
    }
}
