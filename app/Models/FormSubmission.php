<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    // IMPORTANT: Treat the 'data' column as an Array/JSON automatically
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function employee() { return $this->belongsTo(Employee::class); }
    public function form() { return $this->belongsTo(Form::class); }
}