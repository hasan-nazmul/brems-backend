<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferHistory extends Model
{
    use HasFactory;
    protected $guarded = [];

    // 👇 ADD THIS MISSING RELATIONSHIP
    public function employee() {
        return $this->belongsTo(Employee::class);
    }

    public function fromOffice() {
        return $this->belongsTo(Office::class, 'from_office_id');
    }

    public function toOffice() {
        return $this->belongsTo(Office::class, 'to_office_id');
    }
}