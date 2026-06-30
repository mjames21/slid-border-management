<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FormPublishController extends Controller
{
    public function __invoke(string $formId, int $version): JsonResponse
    {
        $form = DynamicForm::query()
            ->where('form_id', $formId)
            ->where('is_template', false)
            ->firstOrFail();
        $formVersion = $form->versions()->where('version', $version)->firstOrFail();

        DB::transaction(function () use ($form, $formVersion) {
            $form->versions()->update(['is_published' => false]);
            $formVersion->forceFill(['is_published' => true])->save();
            $form->forceFill(['published_version_id' => $formVersion->id])->save();
        });

        return response()->json(['message' => 'Form version published.', 'version' => $formVersion->fresh()]);
    }
}
