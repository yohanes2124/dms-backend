<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    /**
     * Display a listing of rooms.
     */
    public function index(Request $request)
    {
        try {
            $query = Room::query();
            
            // Apply filters
            if ($request->has('block') && $request->block) {
                $query->where('block', $request->block);
            }
            
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('room_type') && $request->room_type) {
                $query->where('room_type', $request->room_type);
            }
            
            $rooms = $query->orderBy('block')
                          ->orderBy('room_number')
                          ->get();
            
            return response()->json([
                'success' => true,
                'data' => $rooms
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rooms: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available rooms (STRICT gender-filtered).
     */
    public function available(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }
            
            $query = Room::where('status', 'available')
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
            
            // Additional filters
            if ($request->has('block') && $request->block) {
                $query->where('block', $request->block);
            }
            
            if ($request->has('room_type') && $request->room_type) {
                $query->where('room_type', $request->room_type);
            }
            
            $rooms = $query->orderBy('block')->orderBy('room_number')->get();
            
            return response()->json([
                'success' => true,
                'data' => $rooms,
                'count' => $rooms->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rooms: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get room statistics.
     */
    public function stats()
    {
        try {
            $totalRooms = Room::count();
            $availableRooms = Room::where('status', 'available')
                                ->whereColumn('current_occupancy', '<', 'capacity')
                                ->count();
            $occupiedRooms = Room::where('current_occupancy', '>', 0)->count();
            $totalCapacity = Room::sum('capacity');
            $totalOccupancy = Room::sum('current_occupancy');
            
            $occupancyRate = $totalCapacity > 0 ? round(($totalOccupancy / $totalCapacity) * 100, 1) : 0;
            
            $stats = [
                'total' => $totalRooms,
                'available' => $availableRooms,
                'occupied' => $occupiedRooms,
                'occupancy_rate' => $occupancyRate,
                'total_capacity' => $totalCapacity,
                'total_occupancy' => $totalOccupancy
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch room statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_number' => 'required|string|max:10',
            'block' => 'required|string|max:10',
            'capacity' => 'required|integer|min:1|max:10',
            'room_type' => 'required|in:four,six',
            'description' => 'nullable|string|max:500',
            'facilities' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room = Room::create([
                'room_number' => $request->room_number,
                'block' => $request->block,
                'capacity' => $request->capacity,
                'current_occupancy' => 0,
                'room_type' => $request->room_type,
                'status' => 'available',
                'description' => $request->description,
                'facilities' => $request->facilities ? json_encode($request->facilities) : null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Room created successfully',
                'data' => $room
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create room: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified room.
     */
    public function show(string $id)
    {
        try {
            $room = Room::with('assignments.student')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $room
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room not found'
            ], 404);
        }
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'room_number' => 'sometimes|string|max:10',
            'block' => 'sometimes|string|max:10',
            'capacity' => 'sometimes|integer|min:1|max:10',
            'room_type' => 'sometimes|in:four,six',
            'status' => 'sometimes|in:available,occupied,maintenance,reserved',
            'description' => 'nullable|string|max:500',
            'facilities' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room = Room::findOrFail($id);
            
            $room->update($request->only([
                'room_number', 'block', 'capacity', 'room_type', 
                'status', 'description'
            ]));
            
            if ($request->has('facilities')) {
                $room->facilities = $request->facilities ? json_encode($request->facilities) : null;
                $room->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Room updated successfully',
                'data' => $room
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update room: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified room.
     */
    public function destroy(string $id)
    {
        try {
            $room = Room::findOrFail($id);
            
            // Check if room has active assignments
            if ($room->current_occupancy > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete room with active assignments'
                ], 422);
            }
            
            $room->delete();

            return response()->json([
                'success' => true,
                'message' => 'Room deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete room: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a student to a room.
     */
    public function assignRoom(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room = Room::findOrFail($id);
            
            // Check if room is available
            if ($room->current_occupancy >= $room->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room is at full capacity'
                ], 422);
            }
            
            // Check if student already has a room assignment
            $existingAssignment = RoomAssignment::where('student_id', $request->student_id)
                                               ->where('status', 'active')
                                               ->first();
            
            if ($existingAssignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student already has an active room assignment'
                ], 422);
            }
            
            // Create room assignment
            $assignment = RoomAssignment::create([
                'room_id' => $room->id,
                'student_id' => $request->student_id,
                'assigned_date' => now(),
                'status' => 'active'
            ]);
            
            // Update room occupancy
            $room->increment('current_occupancy');
            
            return response()->json([
                'success' => true,
                'message' => 'Student assigned to room successfully',
                'data' => $assignment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign room: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current student's room allocation.
     */
    public function myRoom()
    {
        try {
            $user = auth()->user();
            
            if (!$user || $user->user_type !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only students can access this endpoint'
                ], 403);
            }
            
            // Get room assignment for the student (assigned or active status)
            $assignment = RoomAssignment::where('student_id', $user->id)
                                       ->whereIn('status', ['assigned', 'active'])
                                       ->with('room')
                                       ->first();
            
            if (!$assignment) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active room allocation'
                ]);
            }
            
            // Get roommates (other students in the same room)
            $roommates = RoomAssignment::where('room_id', $assignment->room_id)
                                      ->where('student_id', '!=', $user->id)
                                      ->whereIn('status', ['assigned', 'active'])
                                      ->with('student')
                                      ->get()
                                      ->map(function ($mate) {
                                          return [
                                              'id' => $mate->student->id,
                                              'name' => $mate->student->name,
                                              'email' => $mate->student->email
                                          ];
                                      });
            
            $room = $assignment->room;
            
            // Handle facilities - could be JSON string or already an array
            $facilities = [];
            if ($room->facilities) {
                if (is_string($room->facilities)) {
                    $facilities = json_decode($room->facilities, true) ?? [];
                } else {
                    $facilities = is_array($room->facilities) ? $room->facilities : [];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'assignment_id' => $assignment->id,
                    'room_number' => $room->room_number,
                    'block' => $room->block,
                    'room_type' => $room->room_type,
                    'capacity' => $room->capacity,
                    'current_occupancy' => $room->current_occupancy,
                    'check_in_date' => $assignment->check_in_date,
                    'check_out_date' => $assignment->check_out_date,
                    'status' => $assignment->status,
                    'roommates' => $roommates,
                    'facilities' => $facilities,
                    'description' => $room->description
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch room allocation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unassign a student from a room.
     */
    public function unassignRoom(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $room = Room::findOrFail($id);
            
            $assignment = RoomAssignment::where('room_id', $room->id)
                                       ->where('student_id', $request->student_id)
                                       ->where('status', 'active')
                                       ->first();
            
            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active assignment found for this student in this room'
                ], 404);
            }
            
            // Update assignment status
            $assignment->update([
                'status' => 'inactive',
                'unassigned_date' => now()
            ]);
            
            // Update room occupancy
            $room->decrement('current_occupancy');
            
            return response()->json([
                'success' => true,
                'message' => 'Student unassigned from room successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign room: ' . $e->getMessage()
            ], 500);
        }
    }
}
