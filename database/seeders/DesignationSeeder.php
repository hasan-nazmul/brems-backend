<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Designation;

class DesignationSeeder extends Seeder
{
    public function run()
    {
        $posts = [
            ['title' => 'General Manager', 'grade' => '3', 'basic_salary' => 56500],
            ['title' => 'Chief Engineer', 'grade' => '4', 'basic_salary' => 50000],
            ['title' => 'Divisional Manager', 'grade' => '5', 'basic_salary' => 43000],
            ['title' => 'Station Master (Grade I)', 'grade' => '9', 'basic_salary' => 22000],
            ['title' => 'Senior Station Master', 'grade' => '10', 'basic_salary' => 16000],
            ['title' => 'Loco Master', 'grade' => '10', 'basic_salary' => 16000],
            ['title' => 'Booking Assistant', 'grade' => '13', 'basic_salary' => 11000],
            ['title' => 'Pointsman', 'grade' => '18', 'basic_salary' => 8800],
            ['title' => 'Wayman', 'grade' => '20', 'basic_salary' => 8250],
        ];

        foreach ($posts as $post) {
            Designation::create($post);
        }
    }
}