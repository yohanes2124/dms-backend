<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('year_level');
            $table->text('address')->nullable()->after('date_of_birth');
            $table->string('emergency_contact')->nullable()->after('address');
            $table->string('emergency_phone')->nullable()->after('emergency_contact');
            $table->enum('mobile_operator', ['ethiotelecom', 'safaricom'])->nullable()->after('phone');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'address', 
                'emergency_contact',
                'emergency_phone',
                'mobile_operator'
            ]);
        });
    }
};