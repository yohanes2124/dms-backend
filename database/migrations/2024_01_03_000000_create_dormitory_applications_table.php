<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('dormitory_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('preferred_room_id')->nullable()->constrained('rooms')->onDelete('set null');
            $table->string('preferred_block')->nullable();
            $table->enum('room_type_preference', ['four', 'six'])->nullable();
            $table->date('application_date');
            $table->enum('status', [
                'draft', 'submitted', 'pending', 'approved', 
                'rejected', 'completed', 'cancelled'
            ])->default('draft');
            $table->integer('priority_score')->default(0);
            $table->json('special_requirements')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone');
            $table->text('medical_conditions')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index(['status', 'priority_score']);
            $table->index('application_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('dormitory_applications');
    }
};