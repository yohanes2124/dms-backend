<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dormitory_applications', function (Blueprint $table) {
            // First, drop the existing enum column
            $table->dropColumn('room_type_preference');
        });
        
        Schema::table('dormitory_applications', function (Blueprint $table) {
            // Then add it back with the new enum values
            $table->enum('room_type_preference', ['triple', 'quad', 'six'])->nullable()->after('preferred_block');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dormitory_applications', function (Blueprint $table) {
            // Revert back to the original enum values
            $table->dropColumn('room_type_preference');
        });
        
        Schema::table('dormitory_applications', function (Blueprint $table) {
            $table->enum('room_type_preference', ['single', 'double', 'triple', 'quad'])->nullable()->after('preferred_block');
        });
    }
};
