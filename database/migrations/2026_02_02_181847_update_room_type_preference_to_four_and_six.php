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
        // Use raw SQL to handle the enum change safely
        DB::statement("ALTER TABLE dormitory_applications MODIFY room_type_preference ENUM('four', 'six', 'single', 'double', 'triple', 'quad') NULL");
        
        // Update existing data to new values
        DB::statement("UPDATE dormitory_applications SET room_type_preference = 'four' WHERE room_type_preference IN ('single', 'double', 'triple', 'quad')");
        
        // Now remove the old enum values
        DB::statement("ALTER TABLE dormitory_applications MODIFY room_type_preference ENUM('four', 'six') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE dormitory_applications MODIFY room_type_preference ENUM('single', 'double', 'triple', 'quad') NULL");
    }
};