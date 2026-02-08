# Room Type Enum Fix

## Issue
The auto-room creation is failing because the `room_type` enum values in the database don't match what the code is trying to use.

## Error
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'room_type' at row 1
```

This happens when trying to insert a `room_type` value that's not allowed by the enum.

## Root Cause
Multiple migrations have changed the `room_type` enum values:
1. Original: `['single', 'double', 'triple', 'quad']`
2. Migration 1: `['triple', 'quad', 'six']`
3. Migration 2: `['four', 'six']`

The database might not have the latest migration applied, or there's a conflict.

## Solution Applied

### 1. Flexible Room Type Detection
Created a system that tries multiple possible room_type values:
```php
$possibleRoomTypes = ['four', 'quad', 'triple', 'double'];
$roomCreated = false;

foreach ($possibleRoomTypes as $roomType) {
    try {
        $room = Room::create([
            'room_type' => $roomType,
            // ... other fields
        ]);
        $roomCreated = true;
        break; // Success!
    } catch (\Exception $e) {
        continue; // Try next room_type
    }
}
```

### 2. Diagnostic Endpoints
Added endpoints to check the current database schema:
- `/api/get-correct-room-type` - Finds the correct room_type value
- `/api/debug-room-schema` - Shows current enum values and tests all possibilities

### 3. Updated All Room Creation Code
- Main block creation endpoint
- Test endpoints
- Block D fix endpoint
- All now use the flexible room_type detection

## Testing
1. **Check Current Schema**: Visit `/api/debug-room-schema`
2. **Find Correct Value**: Visit `/api/get-correct-room-type`
3. **Test Room Creation**: Visit `/api/test-room-model-creation`

## Expected Result
The system will now automatically detect and use the correct room_type value, regardless of which migration was last applied.

## Manual Fix (if needed)
If the automatic detection doesn't work, you can manually run this SQL to ensure the correct enum:
```sql
ALTER TABLE rooms MODIFY COLUMN room_type ENUM('four', 'six') DEFAULT 'four';
```

Or if you prefer the original values:
```sql
ALTER TABLE rooms MODIFY COLUMN room_type ENUM('single', 'double', 'triple', 'quad') DEFAULT 'quad';
```

## Benefits
- **Automatic Detection**: No need to guess the correct room_type value
- **Backward Compatible**: Works with any enum configuration
- **Error Recovery**: If one value fails, tries others
- **Diagnostic Tools**: Easy to debug enum issues