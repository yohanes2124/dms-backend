<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update the enum to only include 'four' and 'six'
        DB::statement("ALTER TABLE rooms MODIFY COLUMN room_type ENUM('four', 'six') DEFAULT 'four'");
        DB::statement("ALTER TABLE dormitory_applications MODIFY COLUMN room_type_preference ENUM('four', 'six') DEFAULT 'four'");
    }

    public function down()
    {
        // Revert back to previous enum
        DB::statement("ALTER TABLE rooms MODIFY COLUMN room_type ENUM('triple', 'quad', 'six') DEFAULT 'triple'");
        DB::statement("ALTER TABLE dormitory_applications MODIFY COLUMN room_type_preference ENUM('triple', 'quad', 'six') DEFAULT 'triple'");
    }
};