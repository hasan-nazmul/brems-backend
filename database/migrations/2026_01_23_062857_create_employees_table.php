<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nid_number')->unique();
            
            // REPLACED: $table->string('designation'); 
            // WITH:
            $table->foreignId('designation_id')->constrained('designations'); 
            
            // Remove current_salary if it is determined by designation, 
            // OR keep it if employees can have custom salaries above their grade.
            $table->decimal('current_salary', 12, 2); 
            
            $table->foreignId('current_office_id')->constrained('offices');
            $table->boolean('is_verified')->default(false); 
            $table->enum('status', ['active', 'released', 'retired'])->default('active');
            
            // Add Soft Deletes (Vital for HR systems)
            $table->softDeletes(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
