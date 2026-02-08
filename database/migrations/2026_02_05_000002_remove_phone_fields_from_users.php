<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove phone-related columns
            $table->dropColumn(['phone', 'mobile_operator']);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore columns if rolling back
            $table->string('phone')->nullable()->after('department');
            $table->string('mobile_operator')->nullable()->after('phone');
        });
    }
};
