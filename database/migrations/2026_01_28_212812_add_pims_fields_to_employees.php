<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Update Employees Table with JSON columns
        Schema::table('employees', function (Blueprint $table) {
            $table->json('family_info')->nullable(); // Stores Father, Mother, Spouse[], Children[]
            $table->json('address_info')->nullable(); // Stores Present & Permanent Address objects
        });

        // 2. Create Academic Records Table
        Schema::create('academic_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('exam_name'); // SSC, HSC, etc.
            $table->string('institute');
            $table->string('subject')->nullable();
            $table->string('passing_year');
            $table->string('result_type'); // Division, GPA
            $table->string('result'); // 5.00, 1st Class
            $table->string('certificate_path')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('academic_records');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['family_info', 'address_info']);
        });
    }
};