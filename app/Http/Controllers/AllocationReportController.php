<?php

namespace App\Http\Controllers;

use App\Models\DormitoryApplication;
use App\Models\RoomAssignment;
use App\Models\Room;
use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllocationReportController extends Controller
{
    /**
     * Get comprehensive allocation report
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user || $user->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $report = [
                'summary' => $this->getSummary(),
                'by_status' => $this->getByStatus(),
                'by_block' => $this->getByBlock(),
                'by_gender' => $this->getByGender(),
                'by_room_type' => $this->getByRoomType(),
                'occupancy_details' => $this->getOccupancyDetails(),
                'allocation_timeline' => $this->getAllocationTimeline(),
                'generated_at' => now()
            ];

            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate allocation report: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report'
            ], 500);
        }
    }

    /**
     * Get summary statistics
     */
    private function getSummary()
    {
        $totalApplications = DormitoryApplication::count();
        $pendingApplications = DormitoryApplication::where('status', 'pending')->count();
        $approvedApplications = DormitoryApplication::where('status', 'approved')->count();
        $completedApplications = DormitoryApplication::where('status', 'completed')->count();
        $rejectedApplications = DormitoryApplication::where('status', 'rejected')->count();

        $totalRooms = Room::count();
        $occupiedRooms = Room::where('current_occupancy', '>', 0)->count();
        $availableRooms = Room::where('status', 'available')
            ->whereColumn('current_occupancy', '<', 'capacity')
            ->count();

        $totalCapacity = Room::sum('capacity');
        $totalOccupancy = Room::sum('current_occupancy');
        $occupancyRate = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100, 2) : 0;

        return [
            'applications' => [
                'total' => $totalApplications,
                'pending' => $pendingApplications,
                'approved' => $approvedApplications,
                'completed' => $completedApplications,
                'rejected' => $rejectedApplications,
                'success_rate' => $totalApplications > 0 ? round(($completedApplications / $totalApplications) * 100, 2) : 0
            ],
            'rooms' => [
                'total' => $totalRooms,
                'occupied' => $occupiedRooms,
                'available' => $availableRooms,
                'total_capacity' => $totalCapacity,
                'total_occupancy' => $totalOccupancy,
                'occupancy_rate' => $occupancyRate
            ]
        ];
    }

    /**
     * Get statistics by application status
     */
    private function getByStatus()
    {
        $statuses = ['pending', 'approved', 'completed', 'rejected', 'cancelled'];
        $data = [];

        foreach ($statuses as $status) {
            $count = DormitoryApplication::where('status', $status)->count();
            $data[$status] = $count;
        }

        return $data;
    }

    /**
     * Get statistics by block
     */
    private function getByBlock()
    {
        $blocks = Block::all();
        $data = [];

        foreach ($blocks as $block) {
            $applications = DormitoryApplication::where('preferred_block', $block->name)->count();
            $allocations = RoomAssignment::whereHas('room', function ($q) use ($block) {
                $q->where('block', $block->name);
            })->count();
            $rooms = Room::where('block', $block->name)->count();
            $capacity = Room::where('block', $block->name)->sum('capacity');
            $occupancy = Room::where('block', $block->name)->sum('current_occupancy');

            $data[$block->name] = [
                'gender' => $block->gender,
                'applications' => $applications,
                'allocations' => $allocations,
                'rooms' => $rooms,
                'capacity' => $capacity,
                'occupancy' => $occupancy,
                'occupancy_rate' => $capacity > 0 ? round(($occupancy / $capacity) * 100, 2) : 0
            ];
        }

        return $data;
    }

    /**
     * Get statistics by gender
     */
    private function getByGender()
    {
        $genders = ['male', 'female'];
        $data = [];

        foreach ($genders as $gender) {
            $applications = DormitoryApplication::whereHas('student', function ($q) use ($gender) {
                $q->where('gender', $gender);
            })->count();

            $allocations = RoomAssignment::whereHas('student', function ($q) use ($gender) {
                $q->where('gender', $gender);
            })->count();

            $blocks = Block::where('gender', $gender)->pluck('name')->toArray();
            $capacity = Room::whereIn('block', $blocks)->sum('capacity');
            $occupancy = Room::whereIn('block', $blocks)->sum('current_occupancy');

            $data[$gender] = [
                'applications' => $applications,
                'allocations' => $allocations,
                'blocks' => count($blocks),
                'capacity' => $capacity,
                'occupancy' => $occupancy,
                'occupancy_rate' => $capacity > 0 ? round(($occupancy / $capacity) * 100, 2) : 0
            ];
        }

        return $data;
    }

    /**
     * Get statistics by room type
     */
    private function getByRoomType()
    {
        $roomTypes = ['four', 'six'];
        $data = [];

        foreach ($roomTypes as $type) {
            $rooms = Room::where('room_type', $type)->count();
            $capacity = Room::where('room_type', $type)->sum('capacity');
            $occupancy = Room::where('room_type', $type)->sum('current_occupancy');

            $data[$type . '-bed'] = [
                'rooms' => $rooms,
                'capacity' => $capacity,
                'occupancy' => $occupancy,
                'occupancy_rate' => $capacity > 0 ? round(($occupancy / $capacity) * 100, 2) : 0
            ];
        }

        return $data;
    }

    /**
     * Get detailed occupancy information
     */
    private function getOccupancyDetails()
    {
        $rooms = Room::select('id', 'room_number', 'block', 'room_type', 'capacity', 'current_occupancy', 'status')
            ->orderBy('block')
            ->orderBy('room_number')
            ->get()
            ->map(function ($room) {
                return [
                    'room_number' => $room->room_number,
                    'block' => $room->block,
                    'room_type' => $room->room_type . '-bed',
                    'capacity' => $room->capacity,
                    'occupancy' => $room->current_occupancy,
                    'available_spaces' => $room->capacity - $room->current_occupancy,
                    'occupancy_rate' => round(($room->current_occupancy / $room->capacity) * 100, 2),
                    'status' => $room->status
                ];
            });

        return $rooms;
    }

    /**
     * Get allocation timeline
     */
    private function getAllocationTimeline()
    {
        $allocations = RoomAssignment::with(['student', 'room'])
            ->orderBy('assigned_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($assignment) {
                return [
                    'student_name' => $assignment->student->name,
                    'room_number' => $assignment->room->room_number,
                    'block' => $assignment->room->block,
                    'assigned_at' => $assignment->assigned_at,
                    'status' => $assignment->status
                ];
            });

        return $allocations;
    }

    /**
     * Export report as CSV
     */
    public function export(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user || $user->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $format = $request->get('format', 'csv');

            if ($format === 'csv') {
                return $this->exportCSV();
            } elseif ($format === 'json') {
                return $this->exportJSON();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid format'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to export report: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to export report'
            ], 500);
        }
    }

    /**
     * Export as CSV
     */
    private function exportCSV()
    {
        $allocations = RoomAssignment::with(['student', 'room'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        $csv = "Student Name,Student ID,Room Number,Block,Room Type,Assigned Date,Status\n";

        foreach ($allocations as $allocation) {
            $csv .= "\"{$allocation->student->name}\",";
            $csv .= "\"{$allocation->student->student_id}\",";
            $csv .= "\"{$allocation->room->room_number}\",";
            $csv .= "\"{$allocation->room->block}\",";
            $csv .= "\"{$allocation->room->room_type}-bed\",";
            $csv .= "\"{$allocation->assigned_at}\",";
            $csv .= "\"{$allocation->status}\"\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="allocation-report-' . now()->format('Y-m-d-H-i-s') . '.csv"');
    }

    /**
     * Export as JSON
     */
    private function exportJSON()
    {
        $report = [
            'summary' => $this->getSummary(),
            'by_status' => $this->getByStatus(),
            'by_block' => $this->getByBlock(),
            'by_gender' => $this->getByGender(),
            'by_room_type' => $this->getByRoomType(),
            'occupancy_details' => $this->getOccupancyDetails(),
            'allocation_timeline' => $this->getAllocationTimeline(),
            'generated_at' => now()
        ];

        return response()->json([
            'success' => true,
            'data' => $report
        ])
            ->header('Content-Disposition', 'attachment; filename="allocation-report-' . now()->format('Y-m-d-H-i-s') . '.json"');
    }
}
