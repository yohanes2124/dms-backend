<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('temporary_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('leave_type', ['weekend', 'holiday', 'emergency', 'medical', 'family_visit', 'other']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('return_date');
            $table->string('destination');
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone');
            $table->text('reason');
            $table->enum('supervisor_approval', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'completed', 'overdue'])->default('draft');
            $table->text('supervisor_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->boolean('room_secured')->default(false);
            $table->json('security_checklist')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['supervisor_id', 'supervisor_approval']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('temporary_leave_requests');
    }
};