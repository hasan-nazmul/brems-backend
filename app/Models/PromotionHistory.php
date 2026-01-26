<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionHistory extends Model
{
    protected $guarded = [];

    // 👇 ADD THIS MISSING RELATIONSHIP
    public function employee() {
        return $this->belongsTo(Employee::class);
    }
}