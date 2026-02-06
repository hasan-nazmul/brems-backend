<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            // Remove old salary column if exists
            if (Schema::hasColumn('designations', 'basic_salary')) {
                $table->dropColumn('basic_salary');
            }
            
            // Add salary range
            $table->decimal('salary_min', 12, 2)->default(0)->after('grade');
            $table->decimal('salary_max', 12, 2)->default(0)->after('salary_min');
            
            // Add new fields
            $table->text('method_of_recruitment')->nullable()->after('salary_max');
            $table->text('qualifications')->nullable()->after('method_of_recruitment');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropColumn(['salary_min', 'salary_max', 'method_of_recruitment', 'qualifications']);
            $table->decimal('basic_salary', 12, 2)->default(0);
        });
    }
};