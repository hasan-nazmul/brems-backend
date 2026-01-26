<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = ['title', 'grade', 'basic_salary', 'default_role'];
}
