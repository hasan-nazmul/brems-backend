<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Drop old tables (Safe to run multiple times)
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');

        // 2. Update Employees Table (Check before adding)
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'profile_picture')) {
                $table->string('profile_picture')->nullable()->after('last_name');
            }
            
            if (!Schema::hasColumn('employees', 'phone')) {
                // Check if 'email' exists to avoid error on 'after'
                if (Schema::hasColumn('employees', 'email')) {
                    $table->string('phone')->nullable()->after('email');
                } else {
                    $table->string('phone')->nullable();
                }
            }
            
            if (!Schema::hasColumn('employees', 'address')) {
                $table->text('address')->nullable();
            }
        });

        // 3. Update Profile Requests Table (Check before adding)
        Schema::table('profile_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('profile_requests', 'proposed_changes')) {
                $table->json('proposed_changes')->nullable();
            }
            if (!Schema::hasColumn('profile_requests', 'attachment')) {
                $table->string('attachment')->nullable();
            }
        });
    }

    public function down()
    {
        // No rollback needed for this cleanup migration
    }
};