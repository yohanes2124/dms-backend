<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Adding gender column to users table...\n";
    
    // Check if column already exists
    $columns = DB::select("SHOW COLUMNS FROM users LIKE 'gender'");
    
    if (count($columns) > 0) {
        echo "✅ Gender column already exists!\n";
    } else {
        // Add the column
        DB::statement("ALTER TABLE users ADD COLUMN gender ENUM('male', 'female') NULL AFTER department");
        echo "✅ Gender column added successfully!\n";
    }
    
    // Verify
    $columns = DB::select("SHOW COLUMNS FROM users LIKE 'gender'");
    if (count($columns) > 0) {
        echo "✅ Verified: Gender column exists in database\n";
        print_r($columns[0]);
    }
    
    echo "\n✅ SUCCESS! You can now try registration again.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
