<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);
            $table->string('title');
            $table->text('description');
            $table->integer('order_number')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rules');
    }
};
