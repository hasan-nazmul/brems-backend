<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model {
    use SoftDeletes;

    protected $guarded = [];

    // ✅ THIS IS THE FIX FOR THE SQL ERROR
    protected $casts = [
        'family_info' => 'array',
        'address_info' => 'array',
        'is_verified' => 'boolean',
    ];

    public function designation() {
        return $this->belongsTo(Designation::class);
    }

    public function office() {
        return $this->belongsTo(Office::class, 'current_office_id');
    }

    public function user() {
        return $this->hasOne(User::class);
    }
    
    // Relationship for the new Education Tab
    public function academics() {
        return $this->hasMany(AcademicRecord::class);
    }

    public function transfers() {
        return $this->hasMany(TransferHistory::class);
    }

    public function promotions() {
        return $this->hasMany(PromotionHistory::class);
    }
}