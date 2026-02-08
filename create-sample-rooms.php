<?php

// Simple script to create sample rooms
require_once 'vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_DATABASE'] ?? 'dormitory_db';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Check if rooms table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'rooms'");
    if ($stmt->rowCount() == 0) {
        echo "Rooms table does not exist. Please run migrations first.\n";
        exit(1);
    }
    
    // Check if rooms already exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "Rooms already exist ($count rooms found). Skipping creation.\n";
        exit(0);
    }
    
    echo "Creating sample rooms...\n";
    
    // Create sample rooms
    $blocks = ['A', 'B', 'C'];
    $roomTypes = [
        'single' => ['capacity' => 1, 'fee' => 150.00],
        'double' => ['capacity' => 2, 'fee' => 100.00],
        'triple' => ['capacity' => 3, 'fee' => 80.00],
        'quad' => ['capacity' => 4, 'fee' => 60.00],
    ];
    
    $facilities = [
        'single' => '["Private Bathroom", "Study Desk", "Wardrobe", "Air Conditioning"]',
        'double' => '["Shared Bathroom", "Study Desk", "Wardrobe", "Fan"]',
        'triple' => '["Shared Bathroom", "Study Desk", "Wardrobe", "Fan"]',
        'quad' => '["Shared Bathroom", "Study Desk", "Wardrobe"]',
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO rooms (room_number, block, capacity, current_occupancy, room_type, status, description, facilities, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $roomsCreated = 0;
    
    foreach ($blocks as $block) {
        for ($floor = 1; $floor <= 2; $floor++) {
            for ($roomNum = 1; $roomNum <= 10; $roomNum++) {
                $roomNumber = $block . $floor . str_pad($roomNum, 2, '0', STR_PAD_LEFT);
                
                // Distribute room types
                if ($roomNum <= 2) {
                    $roomType = 'single';
                } elseif ($roomNum <= 6) {
                    $roomType = 'double';
                } elseif ($roomNum <= 8) {
                    $roomType = 'triple';
                } else {
                    $roomType = 'quad';
                }
                
                $status = 'available';
                $occupancy = 0;
                
                // Make some rooms occupied for demo
                if ($roomNum % 4 == 0) {
                    $status = 'occupied';
                    $occupancy = rand(1, $roomTypes[$roomType]['capacity']);
                }
                
                // Make some rooms maintenance
                if ($roomNum == 1 && $floor == 1) {
                    $status = 'maintenance';
                    $occupancy = 0;
                }
                
                $description = "Block {$block}, Floor {$floor}, {$roomType} occupancy room";
                
                $stmt->execute([
                    $roomNumber,
                    $block,
                    $roomTypes[$roomType]['capacity'],
                    $occupancy,
                    $roomType,
                    $status,
                    $description,
                    $facilities[$roomType],
                    $roomTypes[$roomType]['fee']
                ]);
                
                $roomsCreated++;
            }
        }
    }
    
    echo "Successfully created $roomsCreated rooms.\n";
    
    // Show stats
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM rooms GROUP BY status");
    echo "\nRoom statistics:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['status']}: {$row['count']} rooms\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>