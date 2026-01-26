<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'proposed_changes' => 'array',
    ];

    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}