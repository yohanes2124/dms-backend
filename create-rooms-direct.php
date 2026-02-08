<?php
/**
 * Direct Room Creation Script
 * Run this from backend directory: php create-rooms-direct.php
 */

// Load Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Room;
use Illuminate\Support\Facades\DB;

try {
    echo "🚀 Starting room creation...\n";
    
    // Clear existing rooms
    $existingCount = Room::count();
    if ($existingCount > 0) {
        echo "⚠️  Found {$existingCount} existing rooms. Deleting...\n";
        Room::truncate();
        echo "✅ Cleared existing rooms\n";
    }
    
    $blocks = ['A', 'B', 'C'];
    $roomTypes = [
        'four' => ['capacity' => 4],
        'six' => ['capacity' => 6],
    ];

    $facilities = [
        'four' => ['Shared Bathroom', 'Study Desk', 'Wardrobe', 'Fan'],
        'six' => ['Shared Bathroom', 'Study Desk', 'Wardrobe'],
    ];

    $totalCreated = 0;
    
    foreach ($blocks as $block) {
        echo "\n📦 Creating rooms for Block {$block}...\n";
        
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
                    'status' => 'available',
                    'description' => "Block {$block}, Floor {$floor}, {$roomType} occupancy room",
                    'facilities' => json_encode($facilities[$roomType]),
                ]);
                
                $totalCreated++;
            }
            echo "  ✅ Floor {$floor}: 20 rooms created\n";
        }
    }
    
    // Update some rooms to maintenance status
    Room::where('room_number', 'A101')->update([
        'status' => 'maintenance',
        'description' => 'Under maintenance - plumbing issues',
    ]);
    
    Room::where('room_number', 'B205')->update([
        'status' => 'maintenance',
        'description' => 'Under maintenance - electrical work',
    ]);
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ SUCCESS! Created {$totalCreated} rooms\n";
    echo str_repeat("=", 50) . "\n";
    echo "\n📊 Summary:\n";
    echo "  • Total Rooms: " . Room::count() . "\n";
    echo "  • Available: " . Room::where('status', 'available')->count() . "\n";
    echo "  • Maintenance: " . Room::where('status', 'maintenance')->count() . "\n";
    echo "  • 4-bed rooms: " . Room::where('room_type', 'four')->count() . "\n";
    echo "  • 6-bed rooms: " . Room::where('room_type', 'six')->count() . "\n";
    echo "\n🎉 Rooms are ready! Refresh your browser to see them.\n";
    
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
