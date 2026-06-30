<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicFormVersion extends Model
{
    protected $fillable = ['dynamic_form_id', 'version', 'source_file_path', 'compiled_schema', 'source_metadata', 'is_published'];

    protected $casts = [
        'compiled_schema' => 'array',
        'source_metadata' => 'array',
        'is_published' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'dynamic_form_id');
    }
}
