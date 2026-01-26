<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Office;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\TransferHistory;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // ==========================================
        // 1. CREATE OFFICES (Hierarchy)
        // ==========================================
        $hq = Office::create([
            'name' => 'Railway Headquarters',
            'code' => 'HQ-001',
            'location' => 'Dhaka',
            'parent_id' => null
        ]);

        $zones = ['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna'];
        $createdOffices = []; // Keep track to assign employees later

        foreach ($zones as $index => $zone) {
            // Create Zone Office
            $zOffice = Office::create([
                'name' => "$zone Zone Office",
                'code' => strtoupper(substr($zone, 0, 3)) . '-ZN',
                'location' => $zone,
                'parent_id' => $hq->id
            ]);
            $createdOffices[] = $zOffice;

            // Create 3 Stations per Zone
            for ($i = 1; $i <= 3; $i++) {
                $createdOffices[] = Office::create([
                    'name' => "$zone Station $i",
                    'code' => strtoupper(substr($zone, 0, 3)) . "-ST-$i",
                    'location' => "$zone City",
                    'parent_id' => $zOffice->id
                ]);
            }
        }

        // ==========================================
        // 2. CREATE DESIGNATIONS (Ranks)
        // ==========================================
        $ranks = [
            ['title' => 'General Manager', 'grade' => 'Grade 1', 'salary' => 78000, 'role' => 'super_admin'],
            ['title' => 'Chief Engineer', 'grade' => 'Grade 2', 'salary' => 65000, 'role' => 'office_admin'],
            ['title' => 'Station Master', 'grade' => 'Grade 5', 'salary' => 45000, 'role' => 'office_admin'],
            ['title' => 'Senior Officer', 'grade' => 'Grade 9', 'salary' => 22000, 'role' => 'verified_user'],
            ['title' => 'Booking Clerk', 'grade' => 'Grade 11', 'salary' => 16000, 'role' => 'verified_user'],
            ['title' => 'Signal Operator', 'grade' => 'Grade 13', 'salary' => 14000, 'role' => 'verified_user'],
        ];

        $createdDesignations = [];
        foreach ($ranks as $rank) {
            $createdDesignations[] = Designation::create([
                'title' => $rank['title'],
                'grade' => $rank['grade'],
                'basic_salary' => $rank['salary'],
                'default_role' => $rank['role']
            ]);
        }

        // ==========================================
        // 3. CREATE EMPLOYEES & USERS
        // ==========================================
        
        // A. Create the SUPER ADMIN (You!)
        $adminDesig = $createdDesignations[0]; // GM
        $adminEmp = Employee::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'nid_number' => '1990123456789',
            'designation_id' => $adminDesig->id,
            'current_salary' => $adminDesig->basic_salary,
            'current_office_id' => $hq->id,
            'status' => 'active',
            'is_verified' => true
        ]);

        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('password'),
            'office_id' => $hq->id,
            'role' => 'super_admin',
            'employee_id' => $adminEmp->id,
            'is_active' => true
        ]);

        // B. Generate 50 Random Employees
        foreach ($createdOffices as $office) {
            // Add 3-5 employees per office
            for ($k = 0; $k < rand(3, 5); $k++) {
                
                $desig = $createdDesignations[rand(2, 5)]; // Pick random lower rank
                
                $emp = Employee::create([
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'nid_number' => $faker->unique()->numerify('##########'),
                    'designation_id' => $desig->id,
                    'current_salary' => $desig->basic_salary,
                    'current_office_id' => $office->id,
                    'status' => 'active',
                    'is_verified' => true,
                    'created_at' => $faker->dateTimeBetween('-2 years', 'now')
                ]);

                // Give 50% of them a login account
                if (rand(0, 1)) {
                    $email = strtolower($emp->first_name) . '.' . $emp->id . '@railway.com';
                    User::create([
                        'name' => $emp->first_name . ' ' . $emp->last_name,
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'office_id' => $office->id,
                        'role' => $desig->default_role,
                        'employee_id' => $emp->id,
                        'is_active' => true
                    ]);
                }

                // C. Create fake Transfer History (for Charts/Reports)
                if (rand(0, 1)) {
                    TransferHistory::create([
                        'employee_id' => $emp->id,
                        'from_office_id' => $hq->id, // Assume they came from HQ
                        'to_office_id' => $office->id,
                        'transfer_date' => $faker->dateTimeBetween('-1 year', 'now'),
                        'order_number' => 'ORD-' . rand(100, 999)
                    ]);
                }
            }
        }

        echo "Seeding Complete!\n";
        echo "1. Login: admin@admin.com / password (Super Admin)\n";
        echo "2. Check database for other generated users (e.g., john.5@railway.com)\n";
    }
}