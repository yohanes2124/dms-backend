<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    public function run()
    {
        $blocks = ['A', 'B', 'C'];
        $roomTypes = [
            'four' => ['capacity' => 4],
            'six' => ['capacity' => 6],
        ];

        $facilities = [
            'four' => ['Shared Bathroom', 'Study Desk', 'Wardrobe', 'Fan'],
            'six' => ['Shared Bathroom', 'Study Desk', 'Wardrobe'],
        ];

        foreach ($blocks as $block) {
            // Create rooms for each block
            for ($floor = 1; $floor <= 3; $floor++) {
                for ($roomNum = 1; $roomNum <= 20; $roomNum++) {
                    $roomNumber = $block . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                    
                    // Distribute room types
                    if ($roomNum <= 15) {
                        $roomType = 'four';
                    } else {
                        $roomType = 'six';
                    }

                    Room::create([
                        'room_number' => $roomNumber,
                        'block' => $block,
                        'capacity' => $roomTypes[$roomType]['capacity'],
                        'current_occupancy' => 0,
                        'room_type' => $roomType,
                        'status' => Room::STATUS_AVAILABLE,
                        'description' => "Block {$block}, Floor {$floor}, {$roomType} occupancy room",
                        'facilities' => $facilities[$roomType],
                    ]);
                }
            }
        }

        // Update some rooms to maintenance status
        Room::where('room_number', 'A101')->update([
            'status' => Room::STATUS_MAINTENANCE,
            'description' => 'Under maintenance - plumbing issues',
        ]);
        
        Room::where('room_number', 'B205')->update([
            'status' => Room::STATUS_MAINTENANCE,
            'description' => 'Under maintenance - electrical work',
        ]);
    }
}