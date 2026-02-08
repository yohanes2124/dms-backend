<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Block A, Block B, etc.
            $table->text('description')->nullable();
            $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed');
            $table->integer('total_rooms')->default(0);
            $table->integer('floors')->default(1);
            $table->json('facilities')->nullable(); // Array of facilities
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Seed with existing blocks from rooms table
        $existingBlocks = \DB::table('rooms')
            ->select('block')
            ->distinct()
            ->pluck('block');

        foreach ($existingBlocks as $blockName) {
            $roomCount = \DB::table('rooms')->where('block', $blockName)->count();
            $maxFloor = \DB::table('rooms')
                ->where('block', $blockName)
                ->selectRaw('MAX(CAST(SUBSTRING(room_number, 2, 1) AS UNSIGNED)) as max_floor')
                ->value('max_floor') ?? 1;

            \DB::table('blocks')->insert([
                'name' => $blockName,
                'description' => "Dormitory Block {$blockName}",
                'total_rooms' => $roomCount,
                'floors' => $maxFloor,
                'facilities' => json_encode(['Wi-Fi', 'Laundry', 'Common Room']),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};