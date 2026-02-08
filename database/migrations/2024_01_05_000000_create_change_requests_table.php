<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('current_room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('requested_room_id')->nullable()->constrained('rooms')->onDelete('set null');
            $table->enum('request_type', [
                'room_change', 'block_change', 'roommate_change', 'emergency_change'
            ]);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->timestamp('requested_at');
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('requested_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('change_requests');
    }
};