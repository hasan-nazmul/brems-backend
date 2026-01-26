<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; 
class Employee extends Model {
    use SoftDeletes; // <--- ENABLE IT

    protected $guarded = [];

    // Relationship to Designation (New Architecture)
    public function designation() {
        return $this->belongsTo(Designation::class);
    }

    public function office() {
        return $this->belongsTo(Office::class, 'current_office_id');
    }

    public function transfers() {
        return $this->hasMany(TransferHistory::class);
    }

    public function promotions() {
        return $this->hasMany(PromotionHistory::class);
    }
    
    // Link back to the User Login
    public function user() {
        return $this->hasOne(User::class);
    }
}