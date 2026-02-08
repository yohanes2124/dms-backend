<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('user_type', ['student', 'supervisor', 'admin'])->default('student');
            $table->string('student_id')->nullable()->unique();
            $table->string('department')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('phone')->nullable();
            $table->enum('status', ['pending', 'active', 'inactive', 'suspended', 'rejected'])->default('pending');
            $table->string('assigned_block')->nullable(); // For supervisors
            $table->integer('year_level')->nullable(); // For students
            $table->text('profile_picture')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['user_type', 'status']);
            $table->index('student_id');
        });
    }

    public function down()// we use this to rollback the migration
    {
        Schema::dropIfExists('users');
    }
};