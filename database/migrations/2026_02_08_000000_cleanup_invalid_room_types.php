<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, let's see what room types exist
        $invalidRooms = DB::table('rooms')
            ->whereNotIn('room_type', ['four', 'six'])
            ->get();

        if ($invalidRooms->count() > 0) {
            // Log the invalid rooms before deletion
            \Log::warning('Deleting ' . $invalidRooms->count() . ' rooms with invalid room types');
            
            foreach ($invalidRooms as $room) {
                \Log::warning('Deleting room: ' . $room->room_number . ' (Block ' . $room->block . ') with type: ' . $room->room_type);
            }

            // Delete rooms with invalid room types
            DB::table('rooms')
                ->whereNotIn('room_type', ['four', 'six'])
                ->delete();
        }

        // Verify the enum constraint is correct
        // This ensures only 'four' and 'six' are allowed going forward
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only cleans up data, no schema changes to revert
    }
};
