<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use HasFactory;

    // This line fixes the "Mass Assignment" error
    // It tells Laravel: "I trust all data sent to this model"
    protected $guarded = []; 

    public function fields() {
        return $this->hasMany(FormField::class);
    }
}