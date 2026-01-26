<?php

use Illuminate\Support\Facades\Route;
use App\Models\Employee;

Route::get('/test-create-employee', function () {
    try {
        // 1. Hardcoded Valid Data (Using ID 1 which we know exists from Seeder)
        $employee = Employee::create([
            'first_name' => 'Debug',
            'last_name' => 'User',
            'nid_number' => 'TEST-' . rand(1000, 9999), // Unique NID
            'designation_id' => 1, // Assumes ID 1 exists
            'current_salary' => 50000,
            'current_office_id' => 1, // Assumes ID 1 exists
            'status' => 'active',
            'is_verified' => true
        ]);
        
        return "SUCCESS! Created Employee ID: " . $employee->id;
    } catch (\Exception $e) {
        return "ERROR: " . $e->getMessage();
    }
});
