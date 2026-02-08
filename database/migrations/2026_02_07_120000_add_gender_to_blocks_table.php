<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration is now a no-op since gender column is added in initial blocks migration
     */
    public function up(): void
    {
        // Check if gender column already exists (from initial migration)
        if (!Schema::hasColumn('blocks', 'gender')) {
            Schema::table('blocks', function (Blueprint $table) {
                $table->enum('gender', ['male', 'female', 'mixed'])->default('mixed')->after('description');
            });
        }

        // Update existing blocks with gender based on their names
        // Assuming blocks ending with certain letters are for specific genders
        $blocks = \DB::table('blocks')->get();
        
        foreach ($blocks as $block) {
            $gender = 'mixed'; // default
            
            // Simple logic: blocks A, C, E, G, I, K, M, O are male
            // blocks B, D, F, H, J, L, N, P are female
            $blockLetter = strtoupper(substr($block->name, -1));
            
            if (in_array($blockLetter, ['A', 'C', 'E', 'G', 'I', 'K', 'M', 'O'])) {
                $gender = 'male';
            } elseif (in_array($blockLetter, ['B', 'D', 'F', 'H', 'J', 'L', 'N', 'P'])) {
                $gender = 'female';
            }
            
            \DB::table('blocks')
                ->where('id', $block->id)
                ->update(['gender' => $gender]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop column if it was added in initial migration
        // Only drop if this migration added it
        if (Schema::hasColumn('blocks', 'gender')) {
            // Check if this is the only migration that added it
            // For safety, we'll leave the column in place
        }
    }
};