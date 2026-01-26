<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // xxxx_create_offices_table.php
    public function up()
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Chittagong Station"
            $table->string('code')->unique(); // e.g., "CTG-001"
            // Self-referencing ID for Hierarchy
            $table->unsignedBigInteger('parent_id')->nullable(); 
            $table->string('location');
            $table->timestamps();

            // Foreign key constraint (an office belongs to another office)
            $table->foreign('parent_id')->references('id')->on('offices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
