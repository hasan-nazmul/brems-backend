<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model {
    protected $fillable = ['name', 'code', 'parent_id', 'location'];

    // Parent Office (e.g., Zone office is parent of Station)
    public function parent() {
        return $this->belongsTo(Office::class, 'parent_id');
    }

    // Child Offices (e.g., Station has many sub-offices)
    public function children() {
        return $this->hasMany(Office::class, 'parent_id');
    }

    public function employees() {
        return $this->hasMany(Employee::class, 'current_office_id');
    }
}
