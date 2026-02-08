<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration// we 
{
    public function up()// we use this to create the table
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_number')->unique();
            $table->string('block');
            $table->integer('capacity')->default(2);
            $table->integer('current_occupancy')->default(0);
            $table->enum('room_type', ['four', 'six'])->default('four');
            $table->enum('status', ['available', 'occupied', 'maintenance', 'reserved'])->default('available');
            $table->text('description')->nullable();
            $table->json('facilities')->nullable(); // JSON array of facilities
            $table->decimal('monthly_fee', 8, 2)->nullable();
            $table->timestamps();
            
            $table->index(['block', 'status']);
            $table->index(['room_type', 'status']);
            $table->index('status');
        });
    } 

    public function down()// we use this to rollback the migration
    {
        Schema::dropIfExists('rooms');
    }
};