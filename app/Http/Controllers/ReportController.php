<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Models\DormitoryApplication;
use App\Models\RoomAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function occupancyReport()
    {
        try {
            $totalRooms = Room::count();
            $occupiedRooms = Room::whereHas('roomAssignments', function ($query) {
                $query->where('status', 'active');
            })->count();
            $availableRooms = $totalRooms - $occupiedRooms;
            $occupancyRate = $totalRooms > 0 ? ($occupiedRooms / $totalRooms) * 100 : 0;

            // Room occupancy by block - handle null block names
            $blockOccupancy = Room::select(DB::raw('COALESCE(block, "Unassigned") as block_name'))
                ->selectRaw('COUNT(*) as total_rooms')
                ->selectRaw('COUNT(CASE WHEN EXISTS(SELECT 1 FROM room_assignments WHERE room_assignments.room_id = rooms.id AND room_assignments.status = "active") THEN 1 END) as occupied_rooms')
                ->groupBy('block')
                ->get()
                ->map(function ($block) {
                    $block->available_rooms = $block->total_rooms - $block->occupied_rooms;
                    $block->occupancy_rate = $block->total_rooms > 0 ? round(($block->occupied_rooms / $block->total_rooms) * 100, 2) : 0;
                    return $block;
                });

            // Room type occupancy - handle null room types
            $roomTypeOccupancy = Room::select(DB::raw('COALESCE(room_type, "Standard") as room_type'))
                ->selectRaw('COUNT(*) as total_rooms')
                ->selectRaw('COUNT(CASE WHEN EXISTS(SELECT 1 FROM room_assignments WHERE room_assignments.room_id = rooms.id AND room_assignments.status = "active") THEN 1 END) as occupied_rooms')
                ->groupBy('room_type')
                ->get()
                ->map(function ($type) {
                    $type->available_rooms = $type->total_rooms - $type->occupied_rooms;
                    $type->occupancy_rate = $type->total_rooms > 0 ? round(($type->occupied_rooms / $type->total_rooms) * 100, 2) : 0;
                    return $type;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_rooms' => $totalRooms,
                        'occupied_rooms' => $occupiedRooms,
                        'available_rooms' => $availableRooms,
                        'occupancy_rate' => round($occupancyRate, 2)
                    ],
                    'by_block' => $blockOccupancy,
                    'by_room_type' => $roomTypeOccupancy
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Occupancy Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate occupancy report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function applicationsReport()
    {
        try {
            $totalApplications = DormitoryApplication::count();
            $pendingApplications = DormitoryApplication::where('status', 'pending')->count();
            $approvedApplications = DormitoryApplication::where('status', 'approved')->count();
            $rejectedApplications = DormitoryApplication::where('status', 'rejected')->count();

            // Applications by status
            $applicationsByStatus = DormitoryApplication::select('status')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('status')
                ->get();

            // Applications by month (last 12 months)
            $applicationsByMonth = DormitoryApplication::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Recent applications
            $recentApplications = DormitoryApplication::with('student')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_applications' => $totalApplications,
                        'pending_applications' => $pendingApplications,
                        'approved_applications' => $approvedApplications,
                        'rejected_applications' => $rejectedApplications
                    ],
                    'by_status' => $applicationsByStatus,
                    'by_month' => $applicationsByMonth,
                    'recent_applications' => $recentApplications
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate applications report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function studentsReport()
    {
        try {
            $totalStudents = User::where('user_type', 'student')->count();
            $housedStudents = User::where('user_type', 'student')
                ->whereHas('roomAssignments', function ($query) {
                    $query->where('status', 'active');
                })
                ->count();
            $unhousedStudents = $totalStudents - $housedStudents;

            // Students by block - only count active assignments
            $studentsByBlock = DB::table('users')
                ->join('room_assignments', 'users.id', '=', 'room_assignments.student_id')
                ->join('rooms', 'room_assignments.room_id', '=', 'rooms.id')
                ->where('users.user_type', 'student')
                ->where('room_assignments.status', 'active')
                ->select(DB::raw('COALESCE(rooms.block, "Unassigned") as block_name'))
                ->selectRaw('COUNT(*) as student_count')
                ->groupBy('rooms.block')
                ->get();

            // Students by year level - handle null values
            $studentsByYear = User::where('user_type', 'student')
                ->select(DB::raw('COALESCE(year_level, "Not Specified") as year_level'))
                ->selectRaw('COUNT(*) as count')
                ->groupBy('year_level')
                ->orderBy('year_level')
                ->get();

            // Recent student registrations
            $recentStudents = User::where('user_type', 'student')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'student_id', 'name', 'email', 'year_level', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_students' => $totalStudents,
                        'housed_students' => $housedStudents,
                        'unhoused_students' => $unhousedStudents,
                        'housing_rate' => $totalStudents > 0 ? round(($housedStudents / $totalStudents) * 100, 2) : 0
                    ],
                    'by_block' => $studentsByBlock,
                    'by_year_level' => $studentsByYear,
                    'recent_students' => $recentStudents
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Students Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate students report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function roomsReport()
    {
        try {
            $totalRooms = Room::count();
            $availableRooms = Room::where('status', 'available')->count();
            $occupiedRooms = Room::whereHas('roomAssignments', function ($query) {
                $query->where('status', 'active');
            })->count();
            $maintenanceRooms = Room::where('status', 'maintenance')->count();

            // Rooms by status - handle all possible statuses
            $roomsByStatus = Room::select(DB::raw('COALESCE(status, "available") as status'))
                ->selectRaw('COUNT(*) as count')
                ->groupBy('status')
                ->get();

            // Rooms by type and block - handle null values
            $roomsByTypeAndBlock = Room::select(
                DB::raw('COALESCE(room_type, "Standard") as room_type'),
                DB::raw('COALESCE(block, "Unassigned") as block_name')
            )
                ->selectRaw('COUNT(*) as count')
                ->groupBy('room_type', 'block')
                ->orderBy('block')
                ->orderBy('room_type')
                ->get();

            // Room utilization over time - last 12 months
            $roomUtilization = DB::table('room_assignments')
                ->select(
                    DB::raw('DATE_FORMAT(assigned_at, "%Y-%m") as month'),
                    DB::raw('COUNT(DISTINCT room_id) as rooms_assigned')
                )
                ->where('assigned_at', '>=', now()->subMonths(12))
                ->whereNotNull('assigned_at')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Rooms needing attention - maintenance or with notes
            $roomsNeedingAttention = Room::where(function ($query) {
                $query->where('status', 'maintenance');
            })
                ->get(['id', 'room_number', 'block', 'status', 'description'])
                ->map(function ($room) {
                    $room->block_name = $room->block ?? 'Unassigned';
                    $room->notes = $room->description ?? '';
                    return $room;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_rooms' => $totalRooms,
                        'available_rooms' => $availableRooms,
                        'occupied_rooms' => $occupiedRooms,
                        'maintenance_rooms' => $maintenanceRooms
                    ],
                    'by_status' => $roomsByStatus,
                    'by_type_and_block' => $roomsByTypeAndBlock,
                    'utilization_over_time' => $roomUtilization,
                    'rooms_needing_attention' => $roomsNeedingAttention
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Rooms Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate rooms report: ' . $e->getMessage()
            ], 500);
        }
    }
}