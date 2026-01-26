<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('title'); 
            $table->string('grade'); 
            $table->decimal('basic_salary', 10, 2); 
            
            // Added 'super_admin' to the list here:
            $table->enum('default_role', ['super_admin', 'office_admin', 'verified_user'])
                  ->default('verified_user');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};