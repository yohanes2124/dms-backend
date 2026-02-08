<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DormitoryApplicationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ChangeRequestController;
use App\Http\Controllers\ClearanceController;
use App\Http\Controllers\TemporaryLeaveController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\IssueReportController;
use App\Http\Controllers\RoomRuleController;
use App\Http\Controllers\AllocationController;
use App\Http\Controllers\AllocationReportController;
use App\Models\Room;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check endpoints (no auth required)
Route::get('/health', [HealthController::class, 'health']);
Route::get('/health/detailed', [HealthController::class, 'detailed']);

// Test endpoint for debugging
Route::get('/test-rooms', function() {
    try {
        $rooms = \DB::table('rooms')
            ->where('status', 'available')
            ->limit(5)
            ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Test endpoint working',
            'data' => $rooms,
            'count' => $rooms->count()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Emergency endpoint to add gender column
Route::get('/emergency-add-gender-column', function() {
    try {
        // Check if column exists
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'gender'");
        
        if (count($columns) > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Gender column already exists!',
                'column' => $columns[0]
            ]);
        }
        
        // Add the column
        DB::statement("ALTER TABLE users ADD COLUMN gender ENUM('male', 'female') NULL AFTER department");
        
        // Verify
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'gender'");
        
        return response()->json([
            'success' => true,
            'message' => 'Gender column added successfully!',
            'column' => $columns[0] ?? null
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Debug endpoint to see available blocks clearly
Route::get('/debug-available-blocks', function() {
    try {
        $response = app(\App\Http\Controllers\AuthController::class)->getAvailableBlocks();
        $data = json_decode($response->getContent(), true);
        
        if ($data['success']) {
            $html = '<h2>Available Blocks for Supervisor Registration</h2>';
            $html .= '<p><strong>Total blocks found: ' . count($data['data']) . '</strong></p>';
            
            foreach ($data['data'] as $block) {
                $status = $block['is_full'] ? 'FULL' : 'AVAILABLE';
                $color = $block['is_full'] ? 'red' : 'green';
                
                $html .= '<div style="border: 1px solid #ddd; margin: 10px 0; padding: 10px; background: ' . ($block['is_full'] ? '#ffe6e6' : '#e6ffe6') . '">';
                $html .= '<h3 style="color: ' . $color . '">Block ' . $block['block'] . ' - ' . $status . '</h3>';
                $html .= '<p>Supervisors: ' . $block['supervisor_count'] . '/3</p>';
                $html .= '<p>Available slots: ' . $block['available_slots'] . '</p>';
                $html .= '<p>Will show in registration: ' . ($block['is_full'] ? 'NO' : 'YES') . '</p>';
                $html .= '</div>';
            }
            
            $availableCount = count(array_filter($data['data'], function($block) { return !$block['is_full']; }));
            
            if ($availableCount > 0) {
                $html .= '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">';
                $html .= '<h3 style="color: green;">✅ Registration Form Status: AVAILABLE</h3>';
                $html .= '<p>Supervisor registration form will show dropdown with ' . $availableCount . ' available blocks</p>';
                $html .= '</div>';
            } else {
                $html .= '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;">';
                $html .= '<h3 style="color: red;">❌ Registration Form Status: NO POSITIONS</h3>';
                $html .= '<p>Supervisor registration form will show "No Supervisor Positions Available" message</p>';
                $html .= '</div>';
            }
            
            return response($html)->header('Content-Type', 'text/html');
        } else {
            return response('<h2>Error: ' . $data['message'] . '</h2>')->header('Content-Type', 'text/html');
        }
    } catch (\Exception $e) {
        return response('<h2>Error: ' . $e->getMessage() . '</h2>')->header('Content-Type', 'text/html');
    }
});

// Create blocks table endpoint
Route::get('/create-blocks-table', function() {
    try {
        // Check if blocks table already exists
        if (\Schema::hasTable('blocks')) {
            return response()->json([
                'success' => true,
                'message' => 'Blocks table already exists',
                'blocks_count' => \DB::table('blocks')->count()
            ]);
        }
        
        // Create blocks table
        \DB::statement("
            CREATE TABLE blocks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                description TEXT NULL,
                total_rooms INT NOT NULL DEFAULT 0,
                floors INT NOT NULL DEFAULT 1,
                facilities JSON NULL,
                status ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Seed with existing blocks from rooms table
        \DB::statement("
            INSERT IGNORE INTO blocks (name, description, total_rooms, floors, facilities, status, created_at, updated_at)
            SELECT 
                block as name,
                CONCAT('Dormitory Block ', block) as description,
                COUNT(*) as total_rooms,
                GREATEST(1, MAX(CAST(SUBSTRING(room_number, 2, 1) AS UNSIGNED))) as floors,
                JSON_ARRAY('Wi-Fi', 'Laundry', 'Common Room') as facilities,
                'active' as status,
                NOW() as created_at,
                NOW() as updated_at
            FROM rooms 
            GROUP BY block
        ");
        
        $blocksCount = \DB::table('blocks')->count();
        $blocks = \DB::table('blocks')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Blocks table created and seeded successfully',
            'blocks_count' => $blocksCount,
            'blocks' => $blocks
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create blocks table: ' . $e->getMessage(),
            'error_type' => get_class($e)
        ], 500);
    }
});

// Test endpoint for blocks
Route::get('/test-blocks-table', function() {
    try {
        // Check if blocks table exists
        $tableExists = \Schema::hasTable('blocks');
        
        if (!$tableExists) {
            return response()->json([
                'success' => false,
                'message' => 'Blocks table does not exist',
                'solution' => 'Run: php artisan migrate'
            ]);
        }
        
        // Try to get blocks count
        $blocksCount = \DB::table('blocks')->count();
        
        // Get sample blocks
        $blocks = \DB::table('blocks')->limit(5)->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Blocks table exists',
            'blocks_count' => $blocksCount,
            'sample_blocks' => $blocks
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'error_type' => get_class($e)
        ], 500);
    }
});

// Add more Block D rooms to match other blocks (60 rooms total)
Route::get('/fix-block-d-rooms-complete', function() {
    try {
        $existingCount = Room::where('block', 'D')->count();
        
        // Target: 60 rooms total (same as other blocks)
        $targetRooms = 60;
        $roomsToCreate = $targetRooms - $existingCount;
        
        if ($roomsToCreate <= 0) {
            return response()->json([
                'success' => true,
                'message' => "Block D already has {$existingCount} rooms (target: {$targetRooms})",
                'status' => 'No additional rooms needed'
            ]);
        }
        
        // Create additional rooms following the pattern: D101, D102, ..., D320
        $createdRooms = [];
        $roomsCreated = 0;
        
        // 3 floors, 20 rooms per floor
        for ($floor = 1; $floor <= 3 && $roomsCreated < $roomsToCreate; $floor++) {
            for ($roomNum = 1; $roomNum <= 20 && $roomsCreated < $roomsToCreate; $roomNum++) {
                $roomNumber = 'D' . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                
                // Check if room already exists
                $exists = Room::where('room_number', $roomNumber)->exists();
                if (!$exists) {
                    Room::create([
                        'room_number' => $roomNumber,
                        'block' => 'D',
                        'capacity' => 4,
                        'current_occupancy' => 0,
                        'room_type' => 'four',
                        'status' => 'available',
                        'description' => 'Female dormitory room',
                        'facilities' => ['Wi-Fi', 'Study Desk', 'Wardrobe', 'Shared Bathroom']
                    ]);
                    
                    $createdRooms[] = $roomNumber;
                    $roomsCreated++;
                }
            }
        }
        
        $finalCount = Room::where('block', 'D')->count();
        
        return response()->json([
            'success' => true,
            'message' => "Block D now has {$finalCount} rooms total! Created {$roomsCreated} new rooms.",
            'rooms_created' => $roomsCreated,
            'total_block_d_rooms' => $finalCount,
            'sample_new_rooms' => array_slice($createdRooms, 0, 10),
            'status' => 'Female students will now see plenty of Block D rooms!'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Add more Block D rooms
Route::get('/add-more-block-d-rooms', function() {
    try {
        $existingCount = \DB::table('rooms')->where('block', 'D')->count();
        
        // Add more rooms if we have less than 15
        if ($existingCount < 15) {
            $roomsToAdd = [
                'D106', 'D107', 'D108', 'D109', 'D110',
                'D201', 'D202', 'D203', 'D204', 'D205',
                'D206', 'D207', 'D208', 'D209', 'D210'
            ];
            
            foreach ($roomsToAdd as $roomNumber) {
                // Check if room already exists
                $exists = \DB::table('rooms')->where('room_number', $roomNumber)->exists();
                if (!$exists) {
                    \DB::table('rooms')->insert([
                        'room_number' => $roomNumber,
                        'block' => 'D',
                        'capacity' => 4,
                        'current_occupancy' => 0,
                        'room_type' => 'four',
                        'status' => 'available',
                        'description' => 'Female dormitory room',
                        'facilities' => json_encode(['Wi-Fi', 'Study Desk', 'Wardrobe']),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
        
        $finalCount = \DB::table('rooms')->where('block', 'D')->count();
        $allBlockDRooms = \DB::table('rooms')->where('block', 'D')->orderBy('room_number')->pluck('room_number');
        
        return response()->json([
            'success' => true,
            'message' => "Block D now has {$finalCount} rooms total!",
            'all_block_d_rooms' => $allBlockDRooms,
            'status' => 'Female users should now see both Block A and Block D rooms!'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// SIMPLE: Create Block D rooms directly
Route::get('/add-block-d-rooms-now', function() {
    try {
        // Check if Block D already has rooms
        $existingCount = \DB::table('rooms')->where('block', 'D')->count();
        
        if ($existingCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "Block D already has {$existingCount} rooms",
                'existing_rooms' => \DB::table('rooms')->where('block', 'D')->limit(5)->pluck('room_number')
            ]);
        }
        
        // Create Block D rooms using direct DB insert
        $roomsToCreate = [
            'D101', 'D102', 'D103', 'D104', 'D105',
            'D106', 'D107', 'D108', 'D109', 'D110',
            'D201', 'D202', 'D203', 'D204', 'D205'
        ];
        
        foreach ($roomsToCreate as $roomNumber) {
            \DB::table('rooms')->insert([
                'room_number' => $roomNumber,
                'block' => 'D',
                'capacity' => 4,
                'current_occupancy' => 0,
                'room_type' => 'four',
                'status' => 'available',
                'description' => 'Female dormitory room',
                'facilities' => json_encode(['Wi-Fi', 'Study Desk', 'Wardrobe']),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        $finalCount = \DB::table('rooms')->where('block', 'D')->count();
        $sampleRooms = \DB::table('rooms')->where('block', 'D')->limit(5)->pluck('room_number');
        
        return response()->json([
            'success' => true,
            'message' => "Successfully created {$finalCount} rooms for Block D!",
            'rooms_created' => $finalCount,
            'sample_rooms' => $sampleRooms,
            'next_step' => 'Now female users will see both Block A and Block D rooms!'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error creating Block D rooms: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
});

// Create Block D rooms (simple)
Route::get('/fix-block-d', function() {
    try {
        // Check existing
        $existing = \DB::table('rooms')->where('block', 'D')->count();
        
        if ($existing > 0) {
            return response()->json([
                'success' => true,
                'message' => "Block D already has {$existing} rooms"
            ]);
        }
        
        // Create just a few rooms to test
        \DB::table('rooms')->insert([
            [
                'room_number' => 'D101',
                'block' => 'D',
                'capacity' => 4,
                'current_occupancy' => 0,
                'room_type' => 'four',
                'status' => 'available',
                'description' => 'Female dormitory room',
                'facilities' => '["Wi-Fi", "Study Desk"]',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'room_number' => 'D102',
                'block' => 'D',
                'capacity' => 4,
                'current_occupancy' => 0,
                'room_type' => 'four',
                'status' => 'available',
                'description' => 'Female dormitory room',
                'facilities' => '["Wi-Fi", "Study Desk"]',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'room_number' => 'D103',
                'block' => 'D',
                'capacity' => 4,
                'current_occupancy' => 0,
                'room_type' => 'four',
                'status' => 'available',
                'description' => 'Female dormitory room',
                'facilities' => '["Wi-Fi", "Study Desk"]',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        
        $created = \DB::table('rooms')->where('block', 'D')->count();
        
        return response()->json([
            'success' => true,
            'message' => "Created {$created} rooms for Block D"
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Create Block D rooms

// Debug blocks and rooms
Route::get('/debug-blocks-rooms', function() {
    try {
        // Get all blocks
        $blocks = \DB::table('blocks')
            ->select('name', 'gender', 'status', 'total_rooms')
            ->orderBy('name')
            ->get();
        
        // Get room counts per block
        $roomCounts = \DB::table('rooms')
            ->select('block', \DB::raw('COUNT(*) as room_count'), \DB::raw('COUNT(CASE WHEN status = "available" THEN 1 END) as available_count'))
            ->groupBy('block')
            ->orderBy('block')
            ->get();
        
        // Check if Block D exists and has rooms
        $blockDInfo = \DB::table('blocks')->where('name', 'D')->first();
        $blockDRooms = \DB::table('rooms')->where('block', 'D')->count();
        $blockDAvailable = \DB::table('rooms')->where('block', 'D')->where('status', 'available')->count();
        
        return response()->json([
            'success' => true,
            'all_blocks' => $blocks,
            'room_counts_by_block' => $roomCounts,
            'block_d_info' => [
                'exists_in_blocks_table' => $blockDInfo ? true : false,
                'block_d_data' => $blockDInfo,
                'total_rooms' => $blockDRooms,
                'available_rooms' => $blockDAvailable
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Test using Room model
Route::get('/test-room-model-creation', function() {
    try {
        $testRoomNumber = 'MODEL001';
        
        // Clean up any existing test room
        Room::where('room_number', $testRoomNumber)->delete();
        
        // Try to create a single room using the model with flexible room_type
        $possibleRoomTypes = ['four', 'quad', 'triple', 'double'];
        $room = null;
        $usedRoomType = null;
        
        foreach ($possibleRoomTypes as $roomType) {
            try {
                $room = Room::create([
                    'room_number' => $testRoomNumber,
                    'block' => 'TEST',
                    'capacity' => 4,
                    'current_occupancy' => 0,
                    'room_type' => $roomType,
                    'status' => 'available',
                    'description' => 'Test room using model',
                    'facilities' => ['Wi-Fi', 'Study Desk', 'Wardrobe']
                ]);
                
                $usedRoomType = $roomType;
                break; // Success!
                
            } catch (\Exception $e) {
                continue; // Try next room_type
            }
        }
        
        if ($room) {
            // Verify creation
            $createdRoom = Room::where('room_number', $testRoomNumber)->first();
            
            // Clean up
            $room->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Room model creation test successful!',
                'created_room' => $createdRoom,
                'used_room_type' => $usedRoomType,
                'facilities_handled' => 'Facilities array was automatically converted to JSON'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test room using model - no valid room_type found',
                'tried_room_types' => $possibleRoomTypes
            ], 500);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Model test failed: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ], 500);
    }
});

// Simple test: create one room
Route::get('/test-create-single-room', function() {
    try {
        $testRoomNumber = 'TEST001';
        
        // Clean up any existing test room
        \DB::table('rooms')->where('room_number', $testRoomNumber)->delete();
        
        // Try to create a single room
        $result = \DB::table('rooms')->insert([
            'room_number' => $testRoomNumber,
            'block' => 'TEST',
            'capacity' => 4,
            'current_occupancy' => 0,
            'room_type' => 'four',
            'status' => 'available',
            'description' => 'Test room',
            'facilities' => json_encode(['Wi-Fi', 'Study Desk']),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        if ($result) {
            // Verify creation
            $createdRoom = \DB::table('rooms')->where('room_number', $testRoomNumber)->first();
            
            // Clean up
            \DB::table('rooms')->where('room_number', $testRoomNumber)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Single room creation test successful!',
                'created_room' => $createdRoom
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create test room'
            ], 500);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ], 500);
    }
});

// Helper function to get correct room_type value
Route::get('/get-correct-room-type', function() {
    try {
        // Check what room_type values currently exist in the database
        $existingRoomTypes = \DB::table('rooms')
            ->select('room_type')
            ->distinct()
            ->pluck('room_type')
            ->toArray();
        
        // Try different possible values for 4-person rooms
        $possibleValues = ['four', 'quad', 'triple', 'double'];
        $correctValue = null;
        
        foreach ($possibleValues as $testValue) {
            try {
                // Try to create a test room
                $testRoomNumber = 'TEST_ROOM_TYPE_' . strtoupper($testValue);
                
                \DB::table('rooms')->insert([
                    'room_number' => $testRoomNumber,
                    'block' => 'TEST',
                    'capacity' => 4,
                    'current_occupancy' => 0,
                    'room_type' => $testValue,
                    'status' => 'available',
                    'description' => 'Test room',
                    'facilities' => json_encode(['Test']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // If we get here, this value works
                $correctValue = $testValue;
                
                // Clean up
                \DB::table('rooms')->where('room_number', $testRoomNumber)->delete();
                break;
                
            } catch (\Exception $e) {
                // This value doesn't work, try the next one
                continue;
            }
        }
        
        return response()->json([
            'success' => true,
            'existing_room_types' => $existingRoomTypes,
            'correct_room_type_for_4_person' => $correctValue,
            'message' => $correctValue ? "Use '{$correctValue}' for 4-person rooms" : 'No valid room_type found'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// URGENT: Check current database schema for room_type
Route::get('/debug-room-schema', function() {
    try {
        // Check the actual room_type enum values in the database
        $result = \DB::select("SHOW COLUMNS FROM rooms LIKE 'room_type'");
        
        // Also check what values currently exist in the database
        $existingRoomTypes = \DB::table('rooms')
            ->select('room_type')
            ->distinct()
            ->pluck('room_type')
            ->toArray();
        
        // Check a sample room
        $sampleRoom = \DB::table('rooms')->first();
        
        // Try to create a test room with different room_type values
        $testResults = [];
        $testValues = ['four', 'six', 'quad', 'triple', 'double', 'single'];
        
        foreach ($testValues as $testValue) {
            try {
                // Try to insert a test room with this room_type
                \DB::table('rooms')->insert([
                    'room_number' => 'TEST_' . strtoupper($testValue),
                    'block' => 'TEST',
                    'capacity' => 4,
                    'current_occupancy' => 0,
                    'room_type' => $testValue,
                    'status' => 'available',
                    'description' => 'Test room',
                    'facilities' => json_encode(['Test']),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $testResults[$testValue] = 'SUCCESS';
                
                // Clean up immediately
                \DB::table('rooms')->where('room_number', 'TEST_' . strtoupper($testValue))->delete();
                
            } catch (\Exception $e) {
                $testResults[$testValue] = 'FAILED: ' . $e->getMessage();
            }
        }
        
        return response()->json([
            'success' => true,
            'database_schema' => $result[0] ?? null,
            'existing_room_types_in_db' => $existingRoomTypes,
            'sample_room' => $sampleRoom,
            'room_type_test_results' => $testResults,
            'diagnosis' => 'Check which room_type values are actually allowed'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Schema check failed: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ], 500);
    }
});

// Debug room_type enum values
Route::get('/debug-room-type-enum', function() {
    try {
        // Check current enum values for room_type
        $result = \DB::select("SHOW COLUMNS FROM rooms LIKE 'room_type'");
        
        if (!empty($result)) {
            $enumValues = $result[0]->Type;
            
            // Also check a sample room
            $sampleRoom = \DB::table('rooms')->first();
            
            return response()->json([
                'success' => true,
                'room_type_enum' => $enumValues,
                'sample_room' => $sampleRoom,
                'message' => 'Room type enum values retrieved successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve room_type enum values'
            ], 500);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
});

// Test auto-room creation functionality
Route::get('/test-auto-room-creation', function() {
    try {
        // Simulate creating a test block with auto-room creation
        $testBlockName = 'TEST';
        $totalRooms = 5;
        $floors = 2;
        $gender = 'male';
        
        // Clean up any existing test data
        Room::where('block', $testBlockName)->delete();
        \DB::table('blocks')->where('name', $testBlockName)->delete();
        
        \DB::beginTransaction();
        
        // Create test block
        $blockId = \DB::table('blocks')->insertGetId([
            'name' => $testBlockName,
            'description' => 'Test block for auto-room creation',
            'gender' => $gender,
            'total_rooms' => $totalRooms,
            'floors' => $floors,
            'facilities' => json_encode(['Wi-Fi', 'Study Desk']),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // AUTO-CREATE ROOMS (same logic as main endpoint)
        $roomsPerFloor = ceil($totalRooms / $floors);
        $roomsCreated = 0;
        $createdRooms = [];
        
        for ($floor = 1; $floor <= $floors; $floor++) {
            for ($roomNum = 1; $roomNum <= $roomsPerFloor && $roomsCreated < $totalRooms; $roomNum++) {
                $roomNumber = $testBlockName . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                
                // Try different room_type values
                $possibleRoomTypes = ['four', 'quad', 'triple', 'double'];
                $roomCreated = false;
                
                foreach ($possibleRoomTypes as $roomType) {
                    try {
                        $room = Room::create([
                            'room_number' => $roomNumber,
                            'block' => $testBlockName,
                            'capacity' => 4,
                            'current_occupancy' => 0,
                            'room_type' => $roomType,
                            'status' => 'available',
                            'description' => ucfirst($gender) . ' dormitory room',
                            'facilities' => ['Wi-Fi', 'Study Desk', 'Wardrobe', 'Shared Bathroom']
                        ]);
                        
                        $roomCreated = true;
                        break;
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                if ($roomCreated) {
                    $createdRooms[] = $roomNumber;
                    $roomsCreated++;
                } else {
                    throw new \Exception("Could not create room {$roomNumber} with any room_type");
                }
            }
        }
        
        \DB::commit();
        
        // Verify creation
        $actualRoomsCreated = Room::where('block', $testBlockName)->count();
        $allTestRooms = Room::where('block', $testBlockName)->orderBy('room_number')->pluck('room_number');
        
        // Clean up test data
        Room::where('block', $testBlockName)->delete();
        \DB::table('blocks')->where('name', $testBlockName)->delete();
        
        return response()->json([
            'success' => true,
            'message' => '✅ Auto-room creation is working perfectly!',
            'test_results' => [
                'requested_rooms' => $totalRooms,
                'rooms_created' => $actualRoomsCreated,
                'floors' => $floors,
                'rooms_per_floor' => $roomsPerFloor,
                'created_room_numbers' => $allTestRooms,
                'pattern_example' => "Block {$testBlockName} → Rooms: " . implode(', ', array_slice($allTestRooms->toArray(), 0, 5))
            ],
            'status' => $actualRoomsCreated === $totalRooms ? 'PERFECT MATCH' : 'MISMATCH DETECTED'
        ]);
        
    } catch (\Exception $e) {
        \DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ], 500);
    }
});

// Simple test endpoint
Route::get('/test-basic', function() {
    return response()->json([
        'success' => true,
        'message' => 'Basic test working'
    ]);
});

// Debug current user and gender filtering
Route::get('/debug-user-gender', function() {
    try {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated user'
            ], 401);
        }
        
        // Get user details
        $userInfo = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'gender' => $user->gender,
            'status' => $user->status
        ];
        
        // Get all blocks with gender
        $allBlocks = \DB::table('blocks')
            ->select('name', 'gender', 'status')
            ->orderBy('name')
            ->get();
        
        // Get blocks that should be available to this user
        $availableBlocks = [];
        if ($user->user_type === 'student' && $user->gender) {
            $availableBlocks = \DB::table('blocks')
                ->where('gender', $user->gender)
                ->where('status', 'active')
                ->pluck('name')
                ->toArray();
        }
        
        return response()->json([
            'success' => true,
            'user' => $userInfo,
            'all_blocks' => $allBlocks,
            'available_blocks_for_user' => $availableBlocks,
            'should_filter' => $user->user_type === 'student' && $user->gender ? true : false
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Debug error: ' . $e->getMessage()
        ], 500);
    }
});

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/available-blocks', [AuthController::class, 'getAvailableBlocks']);

// Public endpoint for block availability (STRICT gender filtering)
Route::get('/blocks/availability', function(Request $request) {
    try {
        $gender = $request->query('gender');
        
        if (!$gender) {
            return response()->json([
                'success' => false,
                'message' => 'Gender parameter required'
            ], 422);
        }
        
        if (!in_array($gender, ['male', 'female'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid gender. Must be male or female'
            ], 422);
        }
        
        // Get blocks STRICTLY filtered by gender
        $allBlocks = \DB::table('blocks')
            ->where('status', 'active')
            ->where('gender', $gender)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
        
        $availability = [];
        
        foreach ($allBlocks as $block) {
            // Count supervisors assigned to this block
            $supervisorCount = \DB::table('users')
                ->where('user_type', 'supervisor')
                ->where('assigned_block', $block)
                ->where('status', 'active')
                ->count();
            
            $maxSupervisors = 3;
            $availableSlots = $maxSupervisors - $supervisorCount;
            $isFull = $supervisorCount >= $maxSupervisors;
            
            // Only include blocks that have available slots
            if (!$isFull) {
                $availability[] = [
                    'block' => $block,
                    'gender' => $gender,
                    'supervisor_count' => $supervisorCount,
                    'max_supervisors' => $maxSupervisors,
                    'available_slots' => max(0, $availableSlots),
                    'is_full' => false
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $availability
        ]);
    } catch (\Exception $e) {
        \Log::error('Block availability error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch block availability: ' . $e->getMessage()
        ], 500);
    }
});

// Temporary public endpoints for testing
Route::get('/public/rooms-available', [RoomController::class, 'available']);
Route::get('/public/rooms-stats', [RoomController::class, 'stats']);

// Working rooms available endpoint (STRICT gender filtering)
Route::get('/rooms-available-working', function(\Illuminate\Http\Request $request) {
    try {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }
        
        $query = \DB::table('rooms')
            ->where('status', 'available')
            ->whereColumn('current_occupancy', '<', 'capacity');
        
        // MANDATORY gender filtering for ALL users except admins
        if ($user->user_type !== 'admin') {
            if (empty($user->gender)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gender information required. Please update your profile.',
                    'data' => []
                ], 422);
            }
            
            // Get blocks matching user's gender
            $genderBlocks = \DB::table('blocks')
                ->where('gender', $user->gender)
                ->where('status', 'active')
                ->pluck('name')
                ->toArray();
            
            if (empty($genderBlocks)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'count' => 0,
                    'message' => "No {$user->gender} blocks available"
                ]);
            }
            
            // STRICT: Only show rooms from gender-matching blocks
            $query->whereIn('block', $genderBlocks);
        }
        
        // Apply additional filters if provided
        if ($request->has('block') && $request->block) {
            $query->where('block', $request->block);
        }
        
        if ($request->has('room_type') && $request->room_type) {
            $query->where('room_type', $request->room_type);
        }
        
        $rooms = $query->orderBy('block')
            ->orderBy('room_number')
            ->get()
            ->map(function($room) {
                return [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'block' => $room->block,
                    'capacity' => (int)$room->capacity,
                    'current_occupancy' => (int)$room->current_occupancy,
                    'room_type' => $room->room_type,
                    'status' => $room->status,
                    'description' => $room->description,
                    'facilities' => $room->facilities ? json_decode($room->facilities, true) : []
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $rooms,
            'count' => $rooms->count()
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Available rooms error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ], 500);
    }
});

// Protected routes with JWT authentication
Route::middleware(['auth:api'])->group(function () {
    // Authentication routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);

    // Room routes
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::get('/rooms-available', [RoomController::class, 'available']);
    Route::get('/rooms-stats', [RoomController::class, 'stats']);
    Route::get('/rooms/my-room', [RoomController::class, 'myRoom']);
    Route::get('/rooms/{id}', [RoomController::class, 'show']);
    
    // Available blocks for applications (STRICT gender-filtered)
    Route::get('/blocks/available', function() {
        try {
            if (!\Schema::hasTable('blocks')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blocks table does not exist'
                ], 500);
            }

            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            
            // Build query with MANDATORY gender filtering (except admins)
            $query = \DB::table('blocks')->where('status', 'active')->orderBy('name');
            
            if ($user->user_type !== 'admin') {
                if (empty($user->gender)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gender information required. Please update your profile.'
                    ], 422);
                }
                
                // STRICT: Only blocks matching user's gender
                $query->where('gender', $user->gender);
            }

            $availableBlocks = $query->get()
                ->map(function ($block) {
                    // Calculate capacity and usage
                    $totalCapacity = \DB::table('rooms')
                        ->where('block', $block->name)
                        ->sum('capacity');
                    
                    if ($totalCapacity == 0 && $block->total_rooms > 0) {
                        $totalCapacity = $block->total_rooms * 6;
                    }
                    
                    $usedSpaces = \DB::table('room_assignments')
                        ->join('rooms', 'room_assignments.room_id', '=', 'rooms.id')
                        ->where('rooms.block', $block->name)
                        ->whereIn('room_assignments.status', ['assigned', 'active'])
                        ->count();
                    
                    $pendingApplications = \DB::table('dormitory_applications')
                        ->where('preferred_block', $block->name)
                        ->where('status', 'pending')
                        ->count();
                    
                    $totalUsed = $usedSpaces + $pendingApplications;
                    $remainingSpaces = $totalCapacity - $totalUsed;
                    
                    return [
                        'name' => $block->name,
                        'description' => $block->description,
                        'gender' => $block->gender ?? 'mixed',
                        'floors' => (int)$block->floors,
                        'totalCapacity' => (int)$totalCapacity,
                        'usedSpaces' => (int)$totalUsed,
                        'remainingSpaces' => max(0, (int)$remainingSpaces),
                        'hasSpace' => true
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $availableBlocks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available blocks: ' . $e->getMessage()
            ], 500);
        }
    });

    // Block routes - using closures instead of controller
    Route::get('/blocks', function() {
        try {
            if (!\Schema::hasTable('blocks')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blocks table does not exist'
                ], 500);
            }

            $blocks = \DB::table('blocks')->get()->map(function ($block) {
                // Get real-time room statistics
                $actualRoomCount = \DB::table('rooms')->where('block', $block->name)->count();
                $occupiedRooms = \DB::table('rooms')->where('block', $block->name)->where('current_occupancy', '>', 0)->count();
                
                // Get supervisor count for this block
                $supervisorCount = \DB::table('users')
                    ->where('user_type', 'supervisor')
                    ->where('assigned_block', $block->name)
                    ->count();
                
                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'description' => $block->description,
                    'totalRooms' => $block->total_rooms, // Use stored value, not calculated
                    'actualRoomCount' => $actualRoomCount, // Actual rooms in database
                    'occupiedRooms' => $occupiedRooms,
                    'floors' => $block->floors,
                    'facilities' => json_decode($block->facilities, true) ?? [],
                    'status' => $block->status,
                    'createdDate' => \Carbon\Carbon::parse($block->created_at)->format('Y-m-d'),
                    'supervisorCount' => $supervisorCount,
                    'occupancyPercentage' => $block->total_rooms > 0 ? round(($occupiedRooms / $block->total_rooms) * 100) : 0
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $blocks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blocks: ' . $e->getMessage()
            ], 500);
        }
    });
    
    Route::get('/blocks/{id}', function($id) {
        try {
            $block = \DB::table('blocks')->where('id', $id)->first();
            
            if (!$block) {
                return response()->json([
                    'success' => false,
                    'message' => 'Block not found'
                ], 404);
            }
            
            // Get real-time statistics
            $actualRoomCount = \DB::table('rooms')->where('block', $block->name)->count();
            $occupiedRooms = \DB::table('rooms')->where('block', $block->name)->where('current_occupancy', '>', 0)->count();
            
            $supervisorCount = \DB::table('users')
                ->where('user_type', 'supervisor')
                ->where('assigned_block', $block->name)
                ->count();

            $blockData = [
                'id' => $block->id,
                'name' => $block->name,
                'description' => $block->description,
                'totalRooms' => $block->total_rooms, // Use stored value
                'actualRoomCount' => $actualRoomCount, // Actual rooms in database
                'occupiedRooms' => $occupiedRooms,
                'floors' => $block->floors,
                'facilities' => json_decode($block->facilities, true) ?? [],
                'status' => $block->status,
                'createdDate' => \Carbon\Carbon::parse($block->created_at)->format('Y-m-d'),
                'supervisorCount' => $supervisorCount,
                'occupancyPercentage' => $block->total_rooms > 0 ? round(($occupiedRooms / $block->total_rooms) * 100) : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $blockData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch block: ' . $e->getMessage()
            ], 500);
        }
    });
    
    Route::get('/blocks-stats', function() {
        try {
            $totalBlocks = \DB::table('blocks')->count();
            $activeBlocks = \DB::table('blocks')->where('status', 'active')->count();
            $maintenanceBlocks = \DB::table('blocks')->where('status', 'maintenance')->count();
            
            $totalRooms = \DB::table('rooms')->count();
            $occupiedRooms = \DB::table('rooms')->where('current_occupancy', '>', 0)->count();
            
            $avgOccupancy = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totalBlocks' => $totalBlocks,
                    'activeBlocks' => $activeBlocks,
                    'maintenanceBlocks' => $maintenanceBlocks,
                    'totalRooms' => $totalRooms,
                    'occupiedRooms' => $occupiedRooms,
                    'avgOccupancy' => $avgOccupancy
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Room management (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::post('/rooms', [RoomController::class, 'store']);
        Route::put('/rooms/{id}', [RoomController::class, 'update']);
        Route::delete('/rooms/{id}', [RoomController::class, 'destroy']);
        Route::post('/rooms/{id}/assign', [RoomController::class, 'assignRoom']);
        Route::post('/rooms/{id}/unassign', [RoomController::class, 'unassignRoom']);
    });

    // Block management (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::post('/blocks', function(\Illuminate\Http\Request $request) {
            try {
                // Validate input
                $validator = \Validator::make($request->all(), [
                    'name' => 'required|string|max:10',
                    'description' => 'nullable|string|max:500',
                    'gender' => 'required|in:male,female,mixed',
                    'total_rooms' => 'required|integer|min:1|max:100',
                    'floors' => 'required|integer|min:1|max:20',
                    'facilities' => 'nullable|array',
                    'status' => 'required|in:active,maintenance,inactive'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                // Check if block name already exists
                $existingBlock = \DB::table('blocks')->where('name', $request->name)->first();
                if ($existingBlock) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Block name already exists',
                        'errors' => ['name' => ['A block with this name already exists']]
                    ], 422);
                }

                \DB::beginTransaction();
                
                try {
                    // Insert new block
                    $blockId = \DB::table('blocks')->insertGetId([
                        'name' => $request->name,
                        'description' => $request->description,
                        'gender' => $request->gender,
                        'total_rooms' => $request->total_rooms,
                        'floors' => $request->floors,
                        'facilities' => json_encode($request->facilities ?? []),
                        'status' => $request->status,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // AUTO-CREATE ROOMS for the new block
                    $totalRooms = $request->total_rooms;
                    $floors = $request->floors;
                    $roomsPerFloor = ceil($totalRooms / $floors);
                    $blockName = $request->name;
                    
                    $roomsCreated = 0;
                    $createdRooms = [];
                    
                    // Create rooms with proper numbering: BlockName + Floor + RoomNumber
                    for ($floor = 1; $floor <= $floors; $floor++) {
                        for ($roomNum = 1; $roomNum <= $roomsPerFloor && $roomsCreated < $totalRooms; $roomNum++) {
                            // Format: A101, A102, A201, A202, etc.
                            $roomNumber = $blockName . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                            
                            // Check if room already exists (prevent duplicates)
                            $existingRoom = \App\Models\Room::where('room_number', $roomNumber)->first();
                            if (!$existingRoom) {
                                // Try different room_type values until one works
                                $possibleRoomTypes = ['four', 'quad', 'triple', 'double'];
                                $roomCreated = false;
                                $lastError = null;
                                
                                foreach ($possibleRoomTypes as $roomType) {
                                    try {
                                        $room = \App\Models\Room::create([
                                            'room_number' => $roomNumber,
                                            'block' => $blockName,
                                            'capacity' => 4,
                                            'current_occupancy' => 0,
                                            'room_type' => $roomType,
                                            'status' => 'available',
                                            'description' => ucfirst($request->gender) . ' dormitory room',
                                            'facilities' => ['Wi-Fi', 'Study Desk', 'Wardrobe', 'Shared Bathroom']
                                        ]);
                                        
                                        $roomCreated = true;
                                        break; // Success! Exit the loop
                                        
                                    } catch (\Exception $roomTypeError) {
                                        $lastError = $roomTypeError;
                                        continue; // Try the next room_type
                                    }
                                }
                                
                                if (!$roomCreated) {
                                    \Log::error('All room_type values failed', [
                                        'room_number' => $roomNumber,
                                        'block' => $blockName,
                                        'tried_types' => $possibleRoomTypes,
                                        'last_error' => $lastError->getMessage()
                                    ]);
                                    throw new \Exception("Failed to create room {$roomNumber} with any room_type: " . $lastError->getMessage());
                                }
                                
                                $createdRooms[] = $roomNumber;
                                $roomsCreated++;
                            } else {
                                // Room already exists, just count it
                                $createdRooms[] = $roomNumber;
                                $roomsCreated++;
                            }
                        }
                    }
                    
                    \DB::commit();
                    
                    $block = \DB::table('blocks')->where('id', $blockId)->first();
                    $actualRoomsCreated = \DB::table('rooms')->where('block', $blockName)->count();

                    return response()->json([
                        'success' => true,
                        'message' => "Block '{$blockName}' created successfully with {$actualRoomsCreated} rooms auto-generated!",
                        'data' => [
                            'id' => $block->id,
                            'name' => $block->name,
                            'description' => $block->description,
                            'gender' => $block->gender,
                            'totalRooms' => $block->total_rooms,
                            'actualRoomsCreated' => $actualRoomsCreated,
                            'floors' => $block->floors,
                            'facilities' => json_decode($block->facilities, true),
                            'status' => $block->status,
                            'sample_rooms' => array_slice($createdRooms, 0, 5),
                            'room_numbering_pattern' => "Rooms created: {$blockName}101, {$blockName}102, {$blockName}201, etc."
                        ]
                    ], 201);
                    
                } catch (\Exception $e) {
                    \DB::rollback();
                    throw $e;
                }
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create block: ' . $e->getMessage()
                ], 500);
            }
        });
        
        Route::put('/blocks/{id}', function(\Illuminate\Http\Request $request, $id) {
            try {
                // Validate input
                $validator = \Validator::make($request->all(), [
                    'name' => 'required|string|max:10',
                    'description' => 'nullable|string|max:500',
                    'gender' => 'required|in:male,female,mixed',
                    'total_rooms' => 'nullable|integer|min:0',
                    'floors' => 'required|integer|min:1|max:20',
                    'facilities' => 'nullable|array',
                    'status' => 'required|in:active,maintenance,inactive'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                // Check if block exists
                $block = \DB::table('blocks')->where('id', $id)->first();
                if (!$block) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Block not found'
                    ], 404);
                }

                // Check if new name conflicts with existing block (excluding current)
                $existingBlock = \DB::table('blocks')
                    ->where('name', $request->name)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingBlock) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Block name already exists',
                        'errors' => ['name' => ['A block with this name already exists']]
                    ], 422);
                }

                // Update block
                \DB::table('blocks')->where('id', $id)->update([
                    'name' => $request->name,
                    'description' => $request->description,
                    'gender' => $request->gender,
                    'total_rooms' => $request->total_rooms ?? 0,
                    'floors' => $request->floors,
                    'facilities' => json_encode($request->facilities ?? []),
                    'status' => $request->status,
                    'updated_at' => now()
                ]);

                $updatedBlock = \DB::table('blocks')->where('id', $id)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Block updated successfully',
                    'data' => [
                        'id' => $updatedBlock->id,
                        'name' => $updatedBlock->name,
                        'description' => $updatedBlock->description,
                        'gender' => $updatedBlock->gender,
                        'totalRooms' => $updatedBlock->total_rooms,
                        'floors' => $updatedBlock->floors,
                        'facilities' => json_decode($updatedBlock->facilities, true),
                        'status' => $updatedBlock->status
                    ]
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update block: ' . $e->getMessage()
                ], 500);
            }
        });
        
        Route::delete('/blocks/{id}', function($id) {
            try {
                // Check if block exists
                $block = \DB::table('blocks')->where('id', $id)->first();
                if (!$block) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Block not found'
                    ], 404);
                }

                \DB::beginTransaction();
                
                try {
                    // Count what we're deleting for the response message
                    $roomCount = \DB::table('rooms')->where('block', $block->name)->count();
                    $supervisorCount = \DB::table('users')
                        ->where('user_type', 'supervisor')
                        ->where('assigned_block', $block->name)
                        ->count();
                    
                    // AUTOMATICALLY DELETE all rooms in this block
                    \DB::table('rooms')->where('block', $block->name)->delete();
                    
                    // AUTOMATICALLY UNASSIGN all supervisors from this block
                    \DB::table('users')
                        ->where('user_type', 'supervisor')
                        ->where('assigned_block', $block->name)
                        ->update(['assigned_block' => null]);
                    
                    // Delete the block itself
                    \DB::table('blocks')->where('id', $id)->delete();
                    
                    \DB::commit();
                    
                    $message = "Block '{$block->name}' deleted successfully";
                    if ($roomCount > 0) {
                        $message .= " (removed {$roomCount} rooms)";
                    }
                    if ($supervisorCount > 0) {
                        $message .= " (unassigned {$supervisorCount} supervisors)";
                    }

                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'deleted' => [
                            'block' => $block->name,
                            'rooms_removed' => $roomCount,
                            'supervisors_unassigned' => $supervisorCount
                        ]
                    ]);
                    
                } catch (\Exception $e) {
                    \DB::rollback();
                    throw $e;
                }
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete block: ' . $e->getMessage()
                ], 500);
            }
        });
    });

    // Dormitory application routes
    Route::get('/applications', [DormitoryApplicationController::class, 'index']);
    Route::post('/applications', [DormitoryApplicationController::class, 'store']);
    Route::get('/applications/{id}', [DormitoryApplicationController::class, 'show']);
    Route::put('/applications/{id}', [DormitoryApplicationController::class, 'update']);
    Route::delete('/applications/{id}', [DormitoryApplicationController::class, 'destroy']);
    Route::get('/applications-stats', [DormitoryApplicationController::class, 'getStats']);
    
    // Application management (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/applications/pending', [DormitoryApplicationController::class, 'pending']);
        Route::post('/applications/{id}/approve', [DormitoryApplicationController::class, 'approve']);
        Route::post('/applications/{id}/reject', [DormitoryApplicationController::class, 'reject']);
    });

    // Change request routes
    Route::get('/change-requests', [ChangeRequestController::class, 'index']);
    Route::post('/change-requests', [ChangeRequestController::class, 'store']);
    Route::get('/change-requests/{id}', [ChangeRequestController::class, 'show']);
    Route::put('/change-requests/{id}', [ChangeRequestController::class, 'update']);
    Route::delete('/change-requests/{id}', [ChangeRequestController::class, 'destroy']);
    
    // Change request management (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::post('/change-requests/{id}/approve', [ChangeRequestController::class, 'approve']);
        Route::post('/change-requests/{id}/reject', [ChangeRequestController::class, 'reject']);
    });

    // Clearance routes
    Route::get('/clearance', [ClearanceController::class, 'index']);
    Route::post('/clearance', [ClearanceController::class, 'store']);
    Route::get('/clearance/{id}', [ClearanceController::class, 'show']);
    Route::put('/clearance/{id}', [ClearanceController::class, 'update']);
    
    // Clearance management (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::post('/clearance/{id}/approve', [ClearanceController::class, 'approve']);
        Route::post('/clearance/{id}/reject', [ClearanceController::class, 'reject']);
    });

    // Temporary leave routes
    Route::get('/temporary-leave', [TemporaryLeaveController::class, 'index']);
    Route::post('/temporary-leave', [TemporaryLeaveController::class, 'store']);
    Route::get('/temporary-leave/{id}', [TemporaryLeaveController::class, 'show']);
    Route::put('/temporary-leave/{id}', [TemporaryLeaveController::class, 'update']);
    Route::get('/temporary-leave-stats', [TemporaryLeaveController::class, 'getStats']);
    
    // Test endpoint for temporary leave
    Route::get('/test-temporary-leave', function() {
        try {
            $user = auth()->user();
            return response()->json([
                'success' => true,
                'message' => 'Temporary leave API is working',
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'user_type' => $user->user_type,
                    'has_room_assignment' => $user->roomAssignment ? true : false
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Temporary leave management (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::post('/temporary-leave/{id}/approve', [TemporaryLeaveController::class, 'approve']);
        Route::post('/temporary-leave/{id}/reject', [TemporaryLeaveController::class, 'reject']);
        Route::post('/temporary-leave/{id}/mark-returned', [TemporaryLeaveController::class, 'markReturned']);
    });

    // Issue report routes
    Route::get('/issues', [IssueReportController::class, 'index']);
    Route::get('/issues/my-issues', [IssueReportController::class, 'myIssues']);
    Route::post('/issues', [IssueReportController::class, 'store']);
    Route::get('/issues/{id}', [IssueReportController::class, 'show']);
    Route::get('/issues-stats', [IssueReportController::class, 'statistics']);
    
    // Issue report management (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::put('/issues/{id}', [IssueReportController::class, 'update']);
        Route::delete('/issues/{id}', [IssueReportController::class, 'destroy']);
    });

    // Room rules routes
    Route::get('/rules', [RoomRuleController::class, 'index']);
    Route::get('/rules/categories', [RoomRuleController::class, 'categories']);
    Route::get('/rules/{id}', [RoomRuleController::class, 'show']);
    
    // Room rules management (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/rules-admin', [RoomRuleController::class, 'adminIndex']);
        Route::post('/rules', [RoomRuleController::class, 'store']);
        Route::put('/rules/{id}', [RoomRuleController::class, 'update']);
        Route::delete('/rules/{id}', [RoomRuleController::class, 'destroy']);
        Route::post('/rules/{id}/toggle', [RoomRuleController::class, 'toggleActive']);
        Route::post('/rules/reorder', [RoomRuleController::class, 'reorder']);
    });

    // Report routes (admin/supervisor only)
    Route::middleware('role:admin,supervisor')->group(function () {
        Route::get('/reports/occupancy', [ReportController::class, 'occupancyReport']);
        Route::get('/reports/applications', [ReportController::class, 'applicationsReport']);
        Route::get('/reports/students', [ReportController::class, 'studentsReport']);
        Route::get('/reports/rooms', [ReportController::class, 'roomsReport']);
        Route::get('/reports/clearance', [ReportController::class, 'clearance']);
        Route::get('/reports/change-requests', [ReportController::class, 'changeRequests']);
        Route::get('/reports/users', [ReportController::class, 'users']);
    });

    // Room allocation and management (admin only)
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        // Room allocation routes
        Route::get('/allocations', [AllocationController::class, 'index']);
        Route::get('/allocations/stats', [AllocationController::class, 'stats']);
        Route::get('/allocations/{id}', [AllocationController::class, 'show']);
        Route::post('/allocations/auto', [AllocationController::class, 'autoAllocate']);
        Route::post('/allocations/reallocate', [AllocationController::class, 'reallocate']);

        // Allocation reports routes
        Route::get('/allocation-reports', [AllocationReportController::class, 'index']);
        Route::get('/allocation-reports/export', [AllocationReportController::class, 'export']);
    });

    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        // User Management
        Route::get('/users', [AuthController::class, 'getAllUsers']);
        Route::get('/users/students', [AuthController::class, 'getStudents']);
        Route::get('/users/pending', [AuthController::class, 'getPendingUsers']);
        Route::post('/users/{id}/approve', [AuthController::class, 'approveUser']);
        Route::post('/users/{id}/reject', [AuthController::class, 'rejectUser']);
        Route::post('/users/students/{id}/approve', [AuthController::class, 'approveStudent']);
        Route::post('/users/students/{id}/reject', [AuthController::class, 'rejectStudent']);
        Route::get('/users/supervisors', [AuthController::class, 'getSupervisors']);
        Route::get('/users/{id}', [AuthController::class, 'getUser']);
        Route::put('/users/{id}', [AuthController::class, 'updateUser']);
        Route::put('/users/{id}/status', [AuthController::class, 'updateUserStatus']);
        Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);
        
        // Administrator Management
        Route::get('/administrators', [AuthController::class, 'getAdministrators']);
        Route::post('/administrators', [AuthController::class, 'createAdministrator']);
        Route::put('/administrators/{id}', [AuthController::class, 'updateAdministrator']);
        Route::post('/administrators/{id}/reset-password', [AuthController::class, 'resetAdminPassword']);
    });
});