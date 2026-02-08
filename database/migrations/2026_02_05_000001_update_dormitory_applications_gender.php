<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dormitory_applications', function (Blueprint $table) {
            // Drop the old room_type_preference column
            $table->dropColumn('room_type_preference');
        });
    }

    public function down()
    {
        Schema::table('dormitory_applications', function (Blueprint $table) {
            // Restore the old column if rolling back
            $table->enum('room_type_preference', ['single', 'double', 'triple', 'quad', 'four', 'six'])->nullable()->after('preferred_block');
        });
    }
};
