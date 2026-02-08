<?php

namespace App\Services;

use App\Models\DormitoryApplication;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllocationService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Perform automatic room allocation for approved applications
     * Allocates students to rooms based on preferences and availability
     * Only processes applications that have been approved by supervisors
     * Sends notifications to allocated students
     */
    public function autoAllocate($filters = [])
    {
        Log::info('=== AUTO ALLOCATION START ===', ['filters' => $filters]);

        DB::beginTransaction();
        try {
            $results = [
                'total_applications' => 0,
                'allocated' => 0,
                'failed' => 0,
                'allocations' => [],
                'failures' => [],
                'notifications_sent' => 0
            ];

            // Get APPROVED applications sorted by priority (highest first)
            // These are applications that have been approved by supervisors
            $query = DormitoryApplication::where('status', DormitoryApplication::STATUS_APPROVED)
                ->with(['student', 'preferredRoom'])
                ->byPriority();

            // Apply filters if provided
            if (!empty($filters['block'])) {
                $query->where('preferred_block', $filters['block']);
            }

            if (!empty($filters['gender'])) {
                $query->whereHas('student', function ($q) use ($filters) {
                    $q->where('gender', $filters['gender']);
                });
            }

            $pendingApplications = $query->get();
            $results['total_applications'] = $pendingApplications->count();

            Log::info("Found {$results['total_applications']} pending applications");

            foreach ($pendingApplications as $application) {
                try {
                    $allocation = $this->allocateStudentToRoom($application);

                    if ($allocation['success']) {
                        $results['allocated']++;
                        $results['allocations'][] = $allocation['data'];

                        // Send notification to student
                        $this->notifyStudentAllocated($application->student, $allocation['data']);
                        $results['notifications_sent']++;

                        Log::info("Allocated student {$application->student_id} to room {$allocation['data']['room_number']}");
                    } else {
                        $results['failed']++;
                        $results['failures'][] = [
                            'application_id' => $application->id,
                            'student_id' => $application->student_id,
                            'student_name' => $application->student->name,
                            'reason' => $allocation['message']
                        ];

                        Log::warning("Failed to allocate student {$application->student_id}: {$allocation['message']}");
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['failures'][] = [
                        'application_id' => $application->id,
                        'student_id' => $application->student_id,
                        'student_name' => $application->student->name,
                        'reason' => $e->getMessage()
                    ];

                    Log::error("Exception during allocation: {$e->getMessage()}", [
                        'application_id' => $application->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            Log::info('=== AUTO ALLOCATION COMPLETE ===', [
                'allocated' => $results['allocated'],
                'failed' => $results['failed'],
                'notifications_sent' => $results['notifications_sent']
            ]);

            return [
                'success' => true,
                'message' => "Allocation complete: {$results['allocated']} allocated, {$results['failed']} failed",
                'data' => $results
            ];

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Auto allocation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Auto allocation failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Allocate a single student to a room
     * Returns success/failure with allocation details
     */
    private function allocateStudentToRoom(DormitoryApplication $application)
    {
        // Check if student already has an active assignment
        $existingAssignment = RoomAssignment::where('student_id', $application->student_id)
            ->whereIn('status', [RoomAssignment::STATUS_ASSIGNED, RoomAssignment::STATUS_ACTIVE])
            ->first();

        if ($existingAssignment) {
            return [
                'success' => false,
                'message' => 'Student already has an active room assignment'
            ];
        }

        // Get student's gender
        $student = $application->student;
        if (!$student->gender) {
            return [
                'success' => false,
                'message' => 'Student gender not specified'
            ];
        }

        // Find available room matching criteria
        $room = $this->findAvailableRoom(
            $application->preferred_block,
            $student->gender,
            $application->room_type_preference
        );

        if (!$room) {
            return [
                'success' => false,
                'message' => "No available rooms in {$application->preferred_block} for {$student->gender} students"
            ];
        }

        // Create room assignment
        $assignment = RoomAssignment::create([
            'student_id' => $application->student_id,
            'room_id' => $room->id,
            'application_id' => $application->id,
            'assigned_by' => auth()->id() ?? 1, // System admin
            'assigned_at' => now(),
            'status' => RoomAssignment::STATUS_ASSIGNED,
            'semester' => config('app.current_semester', 'Fall'),
            'academic_year' => config('app.current_academic_year', '2024-2025')
        ]);

        // Update application status to COMPLETED (room has been assigned)
        $application->update([
            'status' => DormitoryApplication::STATUS_COMPLETED
        ]);

        // Increment room occupancy
        $room->increment('current_occupancy');

        return [
            'success' => true,
            'data' => [
                'application_id' => $application->id,
                'student_id' => $application->student_id,
                'student_name' => $student->name,
                'student_email' => $student->email,
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'block' => $room->block,
                'room_type' => $room->room_type,
                'capacity' => $room->capacity,
                'assignment_id' => $assignment->id,
                'assigned_at' => $assignment->assigned_at
            ]
        ];
    }

    /**
     * Find an available room matching criteria
     * Prioritizes: preferred room > preferred block > any available room
     */
    private function findAvailableRoom($preferredBlock, $studentGender, $roomTypePreference = null)
    {
        // Get blocks matching student's gender
        $genderBlocks = DB::table('blocks')
            ->where('gender', $studentGender)
            ->where('status', 'active')
            ->pluck('name')
            ->toArray();

        if (empty($genderBlocks)) {
            Log::warning("No active blocks found for gender: {$studentGender}");
            return null;
        }

        // Priority 1: Preferred block with available rooms
        if (!empty($preferredBlock) && in_array($preferredBlock, $genderBlocks)) {
            $room = Room::where('block', $preferredBlock)
                ->where('status', 'available')
                ->whereColumn('current_occupancy', '<', 'capacity')
                ->when($roomTypePreference, function ($q) use ($roomTypePreference) {
                    return $q->where('room_type', $roomTypePreference);
                })
                ->orderBy('current_occupancy', 'asc') // Fill rooms evenly
                ->first();

            if ($room) {
                return $room;
            }
        }

        // Priority 2: Any gender-matching block with available rooms
        $room = Room::whereIn('block', $genderBlocks)
            ->where('status', 'available')
            ->whereColumn('current_occupancy', '<', 'capacity')
            ->when($roomTypePreference, function ($q) use ($roomTypePreference) {
                return $q->where('room_type', $roomTypePreference);
            })
            ->orderBy('block') // Prefer earlier blocks
            ->orderBy('current_occupancy', 'asc') // Fill evenly
            ->first();

        return $room;
    }

    /**
     * Send notification to student about room allocation
     */
    private function notifyStudentAllocated(User $student, array $allocationData)
    {
        try {
            $message = "Congratulations! You have been allocated to Room {$allocationData['room_number']} in Block {$allocationData['block']}. " .
                      "Room Type: {$allocationData['room_type']}-bed. Please check your dashboard for more details.";

            Notification::create([
                'user_id' => $student->id,
                'type' => 'room_allocated',
                'title' => 'Room Allocation Confirmed',
                'message' => $message,
                'data' => json_encode($allocationData),
                'sender_id' => auth()->id() ?? 1
            ]);

            Log::info("Notification sent to student {$student->id} about room allocation");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send allocation notification to student {$student->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get allocation statistics
     */
    public function getAllocationStats()
    {
        $stats = [
            'total_applications' => DormitoryApplication::count(),
            'pending_applications' => DormitoryApplication::where('status', DormitoryApplication::STATUS_PENDING)->count(),
            'approved_applications' => DormitoryApplication::where('status', DormitoryApplication::STATUS_APPROVED)->count(),
            'rejected_applications' => DormitoryApplication::where('status', DormitoryApplication::STATUS_REJECTED)->count(),
            'completed_applications' => DormitoryApplication::where('status', DormitoryApplication::STATUS_COMPLETED)->count(),
            'total_allocations' => RoomAssignment::count(),
            'active_allocations' => RoomAssignment::where('status', RoomAssignment::STATUS_ACTIVE)->count(),
            'total_rooms' => Room::count(),
            'available_rooms' => Room::where('status', 'available')
                ->whereColumn('current_occupancy', '<', 'capacity')
                ->count(),
            'occupancy_rate' => $this->calculateOccupancyRate()
        ];

        return $stats;
    }

    /**
     * Perform re-allocation for approved change requests
     * Processes approved room change requests and reallocates students
     */
    public function reallocateChangeRequests($filters = [])
    {
        Log::info('=== RE-ALLOCATION START ===', ['filters' => $filters]);

        DB::beginTransaction();
        try {
            $results = [
                'total_change_requests' => 0,
                'reallocated' => 0,
                'failed' => 0,
                'reallocations' => [],
                'failures' => [],
                'notifications_sent' => 0
            ];

            // Get APPROVED change requests
            $query = \App\Models\ChangeRequest::where('status', 'approved')
                ->with(['student', 'currentRoom', 'preferredRoom'])
                ->orderBy('created_at', 'asc');

            // Apply filters if provided
            if (!empty($filters['block'])) {
                $query->whereHas('preferredRoom', function ($q) use ($filters) {
                    $q->where('block', $filters['block']);
                });
            }

            $changeRequests = $query->get();
            $results['total_change_requests'] = $changeRequests->count();

            Log::info("Found {$results['total_change_requests']} approved change requests");

            foreach ($changeRequests as $changeRequest) {
                try {
                    // Check if student still has current assignment
                    $currentAssignment = RoomAssignment::where('student_id', $changeRequest->student_id)
                        ->whereIn('status', [RoomAssignment::STATUS_ASSIGNED, RoomAssignment::STATUS_ACTIVE])
                        ->first();

                    if (!$currentAssignment) {
                        $results['failed']++;
                        $results['failures'][] = [
                            'change_request_id' => $changeRequest->id,
                            'student_id' => $changeRequest->student_id,
                            'student_name' => $changeRequest->student->name,
                            'reason' => 'Student has no current room assignment'
                        ];
                        continue;
                    }

                    // Find new available room
                    $newRoom = $this->findAvailableRoom(
                        $changeRequest->preferred_room_id ? $changeRequest->preferredRoom->block : $changeRequest->student->assigned_block,
                        $changeRequest->student->gender,
                        $changeRequest->preferredRoom->room_type ?? null
                    );

                    if (!$newRoom) {
                        $results['failed']++;
                        $results['failures'][] = [
                            'change_request_id' => $changeRequest->id,
                            'student_id' => $changeRequest->student_id,
                            'student_name' => $changeRequest->student->name,
                            'reason' => 'No available rooms matching criteria'
                        ];
                        continue;
                    }

                    // Create new assignment
                    $newAssignment = RoomAssignment::create([
                        'student_id' => $changeRequest->student_id,
                        'room_id' => $newRoom->id,
                        'application_id' => $currentAssignment->application_id,
                        'assigned_by' => auth()->id() ?? 1,
                        'assigned_at' => now(),
                        'status' => RoomAssignment::STATUS_ASSIGNED,
                        'semester' => config('app.current_semester', 'Fall'),
                        'academic_year' => config('app.current_academic_year', '2024-2025')
                    ]);

                    // Mark old assignment as inactive
                    $currentAssignment->update(['status' => 'inactive']);

                    // Decrement old room occupancy
                    $currentAssignment->room->decrement('current_occupancy');

                    // Increment new room occupancy
                    $newRoom->increment('current_occupancy');

                    // Mark change request as completed
                    $changeRequest->update(['status' => 'completed']);

                    $results['reallocated']++;
                    $results['reallocations'][] = [
                        'change_request_id' => $changeRequest->id,
                        'student_id' => $changeRequest->student_id,
                        'student_name' => $changeRequest->student->name,
                        'old_room' => $currentAssignment->room->room_number,
                        'new_room' => $newRoom->room_number,
                        'new_block' => $newRoom->block,
                        'assignment_id' => $newAssignment->id,
                        'assigned_at' => $newAssignment->assigned_at
                    ];

                    // Send notification
                    $this->notifyStudentReallocated($changeRequest->student, [
                        'old_room' => $currentAssignment->room->room_number,
                        'old_block' => $currentAssignment->room->block,
                        'new_room' => $newRoom->room_number,
                        'new_block' => $newRoom->block,
                        'room_type' => $newRoom->room_type
                    ]);
                    $results['notifications_sent']++;

                    Log::info("Re-allocated student {$changeRequest->student_id} from {$currentAssignment->room->room_number} to {$newRoom->room_number}");

                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['failures'][] = [
                        'change_request_id' => $changeRequest->id,
                        'student_id' => $changeRequest->student_id,
                        'student_name' => $changeRequest->student->name,
                        'reason' => $e->getMessage()
                    ];

                    Log::error("Exception during re-allocation: {$e->getMessage()}", [
                        'change_request_id' => $changeRequest->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();

            Log::info('=== RE-ALLOCATION COMPLETE ===', [
                'reallocated' => $results['reallocated'],
                'failed' => $results['failed'],
                'notifications_sent' => $results['notifications_sent']
            ]);

            return [
                'success' => true,
                'message' => "Re-allocation complete: {$results['reallocated']} reallocated, {$results['failed']} failed",
                'data' => $results
            ];

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Re-allocation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Re-allocation failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Send notification to student about re-allocation
     */
    private function notifyStudentReallocated(User $student, array $allocationData)
    {
        try {
            $message = "Your room change request has been approved! You have been moved from Room {$allocationData['old_room']} (Block {$allocationData['old_block']}) " .
                      "to Room {$allocationData['new_room']} (Block {$allocationData['new_block']}). " .
                      "Room Type: {$allocationData['room_type']}-bed. Please check your dashboard for more details.";

            Notification::create([
                'user_id' => $student->id,
                'type' => 'room_reallocated',
                'title' => 'Room Change Approved',
                'message' => $message,
                'data' => json_encode($allocationData),
                'sender_id' => auth()->id() ?? 1
            ]);

            Log::info("Notification sent to student {$student->id} about room re-allocation");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send re-allocation notification to student {$student->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Calculate overall occupancy rate
     */
    private function calculateOccupancyRate()
    {
        $totalCapacity = Room::sum('capacity');
        $totalOccupancy = Room::sum('current_occupancy');

        if ($totalCapacity == 0) {
            return 0;
        }

        return round(($totalOccupancy / $totalCapacity) * 100, 2);
    }
}
