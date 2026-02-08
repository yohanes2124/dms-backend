<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clearance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('room_assignment_id')->constrained('room_assignments')->onDelete('cascade');
            $table->enum('clearance_type', [
                'semester_end', 'graduation', 'transfer', 'disciplinary', 'voluntary'
            ]);
            $table->enum('status', [
                'initiated', 'inspection_pending', 'inspection_completed', 
                'pending_payment', 'completed', 'cancelled'
            ])->default('initiated');
            $table->foreignId('initiated_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('initiated_at');
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->enum('room_condition', [
                'excellent', 'good', 'fair', 'poor', 'damaged'
            ])->nullable();
            $table->json('damages_reported')->nullable();
            $table->decimal('damages_cost', 8, 2)->default(0);
            $table->json('items_left_behind')->nullable();
            $table->boolean('keys_returned')->default(false);
            $table->enum('cleaning_status', [
                'excellent', 'good', 'needs_improvement', 'poor'
            ])->nullable();
            $table->text('final_inspection_notes')->nullable();
            $table->boolean('clearance_certificate_issued')->default(false);
            $table->string('certificate_number')->nullable()->unique();
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index(['status', 'clearance_type']);
            $table->index('initiated_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clearance_records');
    }
};