<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update the enum to include 'six' and remove 'single', 'double'
        DB::statement("ALTER TABLE rooms MODIFY COLUMN room_type ENUM('triple', 'quad', 'six') DEFAULT 'triple'");
        DB::statement("ALTER TABLE dormitory_applications MODIFY COLUMN room_type_preference ENUM('triple', 'quad', 'six') DEFAULT 'triple'");
    }

    public function down()
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE rooms MODIFY COLUMN room_type ENUM('single', 'double', 'triple', 'quad') DEFAULT 'double'");
        DB::statement("ALTER TABLE dormitory_applications MODIFY COLUMN room_type_preference ENUM('single', 'double', 'triple', 'quad') DEFAULT 'triple'");
    }
};