<?php

namespace App\Services;

use App\Models\User;
use App\Models\TemporaryLeaveRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupervisorAssignmentService
{
    /**
     * Assign the best supervisor for a temporary leave request
     * 
     * @param User $student
     * @param string $leaveType
     * @return User|null
     */
    public function assignSupervisor(User $student, string $leaveType = null): ?User
    {
        // Get student's block from room assignment
        $studentBlock = $this->getStudentBlock($student);
        
        if (!$studentBlock) {
            // If no room assignment, use round-robin across all supervisors
            return $this->assignByRoundRobin();
        }
        
        // Get all supervisors for the student's block
        $blockSupervisors = User::where('user_type', 'supervisor')
            ->where('assigned_block', $studentBlock)
            ->where('status', 'active')
            ->get();
        
        if ($blockSupervisors->isEmpty()) {
            Log::warning("No supervisors found for block {$studentBlock}");
            return $this->assignByRoundRobin();
        }
        
        // Use date-based assignment strategy
        return $this->assignByDateSchedule($blockSupervisors);
    }
    
    /**
     * Get student's block from room assignment
     */
    private function getStudentBlock(User $student): ?string
    {
        $roomAssignment = $student->roomAssignment;
        
        if ($roomAssignment && $roomAssignment->room) {
            return $roomAssignment->room->block;
        }
        
        return null;
    }
    
    /**
     * Assign supervisor based on day of week schedule
     * 3 supervisors per block with rotating schedule
     */
    private function assignByDateSchedule($supervisors): User
    {
        // Convert supervisors collection to array and sort by ID for consistency
        $supervisorArray = $supervisors->sortBy('id')->values()->all();
        
        // Get current day of week (1 = Monday, 7 = Sunday)
        $dayOfWeek = now()->dayOfWeek;
        if ($dayOfWeek == 0) $dayOfWeek = 7; // Convert Sunday from 0 to 7
        
        // Schedule assignment based on day of week
        $scheduleMap = [
            1 => 0, // Monday → Supervisor 1 (index 0)
            2 => 0, // Tuesday → Supervisor 1 (index 0)
            3 => 1, // Wednesday → Supervisor 2 (index 1)
            4 => 1, // Thursday → Supervisor 2 (index 1)
            5 => 2, // Friday → Supervisor 3 (index 2)
            6 => 2, // Saturday → Supervisor 3 (index 2)
            7 => 2, // Sunday → Supervisor 3 (index 2)
        ];
        
        // Get the supervisor index for today
        $supervisorIndex = $scheduleMap[$dayOfWeek];
        
        // Handle cases where there are fewer than 3 supervisors
        if ($supervisorIndex >= count($supervisorArray)) {
            $supervisorIndex = $supervisorIndex % count($supervisorArray);
        }
        
        $assignedSupervisor = $supervisorArray[$supervisorIndex];
        
        // Log the assignment decision
        $dayNames = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
        ];
        
        Log::info("Date-based supervisor assigned", [
            'day_of_week' => $dayNames[$dayOfWeek],
            'supervisor_id' => $assignedSupervisor->id,
            'supervisor_name' => $assignedSupervisor->name,
            'supervisor_index' => $supervisorIndex + 1,
            'total_supervisors' => count($supervisorArray)
        ]);
        
        return $assignedSupervisor;
    }
    
    /**
     * Round-robin assignment when no block-specific supervisors available
     */
    private function assignByRoundRobin(): ?User
    {
        // Get the supervisor who was assigned least recently
        $supervisor = User::where('user_type', 'supervisor')
            ->where('status', 'active')
            ->leftJoin('temporary_leave_requests', 'users.id', '=', 'temporary_leave_requests.supervisor_id')
            ->select('users.*', DB::raw('MAX(temporary_leave_requests.created_at) as last_assigned'))
            ->groupBy('users.id')
            ->orderBy('last_assigned', 'asc')
            ->first();
        
        if (!$supervisor) {
            // Fallback: get any active supervisor
            $supervisor = User::where('user_type', 'supervisor')
                ->where('status', 'active')
                ->first();
        }
        
        Log::info("Round-robin supervisor assigned", [
            'supervisor_id' => $supervisor?->id,
            'supervisor_name' => $supervisor?->name
        ]);
        
        return $supervisor;
    }
    
    /**
     * Get supervisor workload statistics for a block
     */
    public function getBlockWorkloadStats(string $block): array
    {
        $supervisors = User::where('user_type', 'supervisor')
            ->where('assigned_block', $block)
            ->where('status', 'active')
            ->get();
        
        $stats = [];
        
        foreach ($supervisors as $supervisor) {
            $pendingCount = TemporaryLeaveRequest::where('supervisor_id', $supervisor->id)
                ->where('supervisor_approval', 'pending')
                ->count();
            
            $approvedCount = TemporaryLeaveRequest::where('supervisor_id', $supervisor->id)
                ->where('supervisor_approval', 'approved')
                ->count();
            
            $activeCount = TemporaryLeaveRequest::where('supervisor_id', $supervisor->id)
                ->where('supervisor_approval', 'approved')
                ->where('start_date', '<=', now())
                ->where('return_date', '>=', now())
                ->whereNull('returned_at')
                ->count();
            
            $stats[] = [
                'supervisor_id' => $supervisor->id,
                'supervisor_name' => $supervisor->name,
                'pending_requests' => $pendingCount,
                'approved_requests' => $approvedCount,
                'active_leaves' => $activeCount,
                'total_workload' => $pendingCount + $activeCount
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get the supervisor schedule for a specific block
     */
    public function getBlockSchedule(string $block): array
    {
        $supervisors = User::where('user_type', 'supervisor')
            ->where('assigned_block', $block)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
        
        if ($supervisors->count() < 3) {
            // If less than 3 supervisors, distribute available ones
            $schedule = [];
            $supervisorArray = $supervisors->values()->all();
            
            for ($day = 1; $day <= 7; $day++) {
                $supervisorIndex = ($day - 1) % count($supervisorArray);
                $schedule[] = [
                    'day' => $this->getDayName($day),
                    'day_number' => $day,
                    'supervisor' => $supervisorArray[$supervisorIndex] ?? null
                ];
            }
            
            return $schedule;
        }
        
        // Standard 3-supervisor schedule
        $schedule = [
            ['day' => 'Monday', 'day_number' => 1, 'supervisor' => $supervisors[0]],
            ['day' => 'Tuesday', 'day_number' => 2, 'supervisor' => $supervisors[0]],
            ['day' => 'Wednesday', 'day_number' => 3, 'supervisor' => $supervisors[1]],
            ['day' => 'Thursday', 'day_number' => 4, 'supervisor' => $supervisors[1]],
            ['day' => 'Friday', 'day_number' => 5, 'supervisor' => $supervisors[2]],
            ['day' => 'Saturday', 'day_number' => 6, 'supervisor' => $supervisors[2]],
            ['day' => 'Sunday', 'day_number' => 7, 'supervisor' => $supervisors[2]],
        ];
        
        return $schedule;
    }
    
    /**
     * Get day name from day number
     */
    private function getDayName(int $dayNumber): string
    {
        $dayNames = [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 
            4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
        ];
        
        return $dayNames[$dayNumber] ?? 'Unknown';
    }
    
    /**
     * Get current day's supervisor for a block
     */
    public function getTodaysSupervisor(string $block): ?User
    {
        $supervisors = User::where('user_type', 'supervisor')
            ->where('assigned_block', $block)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
        
        if ($supervisors->isEmpty()) {
            return null;
        }
        
        return $this->assignByDateSchedule($supervisors);
    }
}