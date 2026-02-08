# Auto-Allocation System Documentation

## Overview
Complete automatic room allocation system with student notifications. Allocates pending dormitory applications to available rooms based on preferences, gender matching, and priority scores.

## Features

### 1. Smart Allocation Algorithm
- **Priority-Based**: Processes applications by priority score (medical conditions, special requirements, application date)
- **Gender Matching**: Ensures students are allocated to gender-appropriate blocks
- **Preference Respect**: Honors room type preferences (4-bed or 6-bed)
- **Even Distribution**: Fills rooms evenly to optimize occupancy
- **No Redundancy**: Prevents duplicate allocations with transaction safety

### 2. Automatic Notifications
- Each allocated student receives a notification with:
  - Room number and block
  - Room type (4-bed or 6-bed)
  - Confirmation message
- Notifications stored in database for student dashboard

### 3. Allocation Statistics
- Total pending applications
- Successfully allocated count
- Failed allocations with reasons
- Notifications sent count
- Real-time occupancy rates

## API Endpoints

### Auto Allocate
```
POST /api/allocations/auto
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Allocation complete: 45 allocated, 2 failed",
  "data": {
    "total_applications": 47,
    "allocated": 45,
    "failed": 2,
    "notifications_sent": 45,
    "allocations": [
      {
        "application_id": 1,
        "student_id": 101,
        "student_name": "John Doe",
        "student_email": "john@example.com",
        "room_id": 5,
        "room_number": "A-201",
        "block": "A",
        "room_type": "four",
        "capacity": 4,
        "assignment_id": 1,
        "assigned_at": "2026-02-07T10:30:00Z"
      }
    ],
    "failures": [
      {
        "application_id": 2,
        "student_id": 102,
        "student_name": "Jane Smith",
        "reason": "No available rooms in preferred block for female students"
      }
    ]
  }
}
```

### Get Allocation Statistics
```
GET /api/allocations/stats
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_applications": 100,
    "pending_applications": 10,
    "approved_applications": 85,
    "rejected_applications": 5,
    "total_allocations": 85,
    "active_allocations": 80,
    "total_rooms": 200,
    "available_rooms": 45,
    "occupancy_rate": 77.5
  }
}
```

### List Allocations
```
GET /api/allocations
Authorization: Bearer {token}
```

### Get Allocation Details
```
GET /api/allocations/{id}
Authorization: Bearer {token}
```

## Allocation Process Flow

1. **Fetch Pending Applications**
   - Get all applications with status = 'pending'
   - Sort by priority score (highest first)

2. **For Each Application:**
   - Check if student already has active assignment
   - Get student's gender
   - Find available room matching criteria:
     - Gender-appropriate block
     - Preferred block (if available)
     - Room type preference
     - Has available capacity

3. **Create Assignment**
   - Create RoomAssignment record
   - Update application status to 'approved'
   - Increment room occupancy
   - Send notification to student

4. **Handle Failures**
   - Log reason for failed allocations
   - Return failure details in response

## Database Transactions
- All allocations wrapped in database transaction
- Rollback on any error to maintain data consistency
- Prevents race conditions and duplicate allocations

## Notification Content
Students receive notifications with:
- Room number and block
- Room type (4-bed or 6-bed)
- Capacity information
- Confirmation message

Example:
```
"Congratulations! You have been allocated to Room A-201 in Block A. 
Room Type: four-bed. Please check your dashboard for more details."
```

## Error Handling

### Common Failures
1. **Student already has assignment** - Skipped
2. **No gender specified** - Skipped with reason
3. **No available rooms** - Marked as failed
4. **Block at capacity** - Tries alternative blocks

### Logging
- All allocations logged with student ID and room number
- Failures logged with reason
- Exceptions logged with full trace

## Frontend Integration

### Allocations Page
- Real-time statistics dashboard
- Auto allocation button with confirmation modal
- Results display with allocated/failed counts
- Recent allocations list
- Detailed failure reasons

### User Experience
1. Admin clicks "Auto Allocation" button
2. Confirmation modal shows process details
3. System processes all pending applications
4. Results modal shows:
   - Success/failure status
   - Statistics (allocated, failed, notifications sent)
   - Sample of allocated students
   - Sample of failed allocations

## Configuration

### Current Settings
- Semester: 'Fall' (configurable in config/app.php)
- Academic Year: '2024-2025' (configurable in config/app.php)
- System Admin ID: 1 (for assignments without authenticated user)

### Customization
Edit `AllocationService.php` to modify:
- Priority score calculation
- Room selection algorithm
- Notification message format
- Failure handling logic

## Security
- Admin-only access (role:admin middleware)
- Authorization checks for viewing allocations
- Supervisors see only their block allocations
- Students see only their own allocations
- Database transactions prevent data corruption

## Performance
- Efficient database queries with eager loading
- Indexed lookups for gender blocks
- Batch processing of applications
- Minimal database round-trips

## Testing

### Manual Test
1. Create pending applications with different preferences
2. Ensure rooms are available in gender-appropriate blocks
3. Click "Auto Allocation" button
4. Verify all students are allocated
5. Check notifications in student dashboard

### Edge Cases
- No available rooms → Failures logged
- All rooms full → Failures logged
- Mixed gender blocks → Proper filtering
- Priority score calculation → Correct ordering
