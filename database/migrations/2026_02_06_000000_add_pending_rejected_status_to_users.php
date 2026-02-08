<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Add 'pending' and 'rejected' to status enum
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('pending', 'active', 'inactive', 'suspended', 'rejected') DEFAULT 'pending'");
        
        // Keep existing active users as active (don't change them to pending)
        // Only new registrations will start as pending
    }

    public function down()
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'");
    }
};
