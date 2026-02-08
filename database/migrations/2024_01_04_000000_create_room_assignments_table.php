<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('application_id')->nullable()->constrained('dormitory_applications')->onDelete('set null');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at');
            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->enum('status', ['assigned', 'active', 'inactive', 'completed', 'cancelled', 'transferred'])->default('assigned');
            $table->string('semester')->nullable();
            $table->string('academic_year')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index(['room_id', 'status']);
            $table->index(['semester', 'academic_year']);
            $table->unique(['student_id', 'status'], 'unique_active_assignment');
        });
    }

    public function down()
    {
        Schema::dropIfExists('room_assignments');
    }
};