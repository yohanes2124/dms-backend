<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BlockController extends Controller
{
    /**
     * Get all blocks with statistics
     */
    public function index()
    {
        try {
            $blocks = Block::all()->map(function ($block) {
                // Get real-time room statistics
                $rooms = Room::where('block', $block->name);
                $totalRooms = $rooms->count();
                $occupiedRooms = $rooms->where('current_occupancy', '>', 0)->count();
                
                // Get supervisor count for this block
                $supervisorCount = User::where('user_type', 'supervisor')
                    ->where('assigned_block', $block->name)
                    ->count();
                
                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'description' => $block->description ?: ($block->gender ? ucfirst($block->gender) . ' Dormitory Block ' . $block->name : 'Dormitory Block ' . $block->name),
                    'gender' => $block->gender ?: 'male', // Default to male if not set
                    'totalRooms' => $totalRooms,
                    'occupiedRooms' => $occupiedRooms,
                    'floors' => $block->floors,
                    'facilities' => is_array($block->facilities) ? $block->facilities : (is_string($block->facilities) ? json_decode($block->facilities, true) ?? [] : []),
                    'status' => $block->status,
                    'createdDate' => $block->created_at->format('Y-m-d'),
                    'supervisorCount' => $supervisorCount,
                    'occupancyPercentage' => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $blocks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch blocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new block
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:10|unique:blocks,name',
            'description' => 'nullable|string|max:500',
            'gender' => 'required|in:male,female',
            'floors' => 'required|integer|min:1|max:20',
            'total_rooms' => 'nullable|integer|min:0',
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

        try {
            $block = Block::create([
                'name' => $request->name,
                'description' => $request->description,
                'gender' => $request->gender,
                'floors' => $request->floors,
                'total_rooms' => $request->total_rooms,
                'facilities' => $request->facilities ?? [],
                'status' => $request->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Block created successfully',
                'data' => $block
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create block',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific block
     */
    public function show($id)
    {
        try {
            $block = Block::findOrFail($id);
            
            // Get real-time statistics
            $rooms = Room::where('block', $block->name);
            $totalRooms = $rooms->count();
            $occupiedRooms = $rooms->where('current_occupancy', '>', 0)->count();
            
            $supervisorCount = User::where('user_type', 'supervisor')
                ->where('assigned_block', $block->name)
                ->count();

            $blockData = [
                'id' => $block->id,
                'name' => $block->name,
                'description' => $block->description,
                'gender' => $block->gender,
                'totalRooms' => $totalRooms,
                'occupiedRooms' => $occupiedRooms,
                'floors' => $block->floors,
                'facilities' => $block->facilities ?? [],
                'status' => $block->status,
                'createdDate' => $block->created_at->format('Y-m-d'),
                'supervisorCount' => $supervisorCount,
                'occupancyPercentage' => $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $blockData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Block not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update a block
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:10|unique:blocks,name,' . $id,
            'description' => 'nullable|string|max:500',
            'gender' => 'required|in:male,female',
            'floors' => 'required|integer|min:1|max:20',
            'total_rooms' => 'nullable|integer|min:0',
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

        try {
            $block = Block::findOrFail($id);
            
            $block->update([
                'name' => $request->name,
                'description' => $request->description,
                'gender' => $request->gender,
                'floors' => $request->floors,
                'total_rooms' => $request->total_rooms,
                'facilities' => $request->facilities ?? [],
                'status' => $request->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Block updated successfully',
                'data' => $block
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update block',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a block
     */
    public function destroy($id)
    {
        try {
            $block = Block::findOrFail($id);
            
            // Check if block has rooms
            $roomCount = Room::where('block', $block->name)->count();
            if ($roomCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete block with existing rooms. Please remove all rooms first.'
                ], 422);
            }

            // Check if block has supervisors
            $supervisorCount = User::where('user_type', 'supervisor')
                ->where('assigned_block', $block->name)
                ->count();
            if ($supervisorCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete block with assigned supervisors. Please reassign supervisors first.'
                ], 422);
            }

            $block->delete();

            return response()->json([
                'success' => true,
                'message' => 'Block deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete block',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available blocks for student applications
     * Only returns blocks that have remaining capacity
     */
    public function getAvailableBlocks()
    {
        try {
            $availableBlocks = Block::where('status', 'active')->get()->map(function ($block) {
                // Calculate total capacity for this block
                $totalCapacity = Room::where('block', $block->name)->sum('capacity');
                
                // Calculate used spaces (active room assignments + pending applications)
                $usedSpaces = \App\Models\RoomAssignment::whereHas('room', function($q) use ($block) {
                    $q->where('block', $block->name);
                })->whereIn('status', ['assigned', 'active'])->count();
                
                // Add pending applications that haven't been assigned yet
                $pendingApplications = \App\Models\DormitoryApplication::where('preferred_block', $block->name)
                    ->where('status', 'pending')
                    ->count();
                
                $totalUsed = $usedSpaces + $pendingApplications;
                $remainingSpaces = $totalCapacity - $totalUsed;
                
                return [
                    'name' => $block->name,
                    'description' => $block->description,
                    'gender' => $block->gender,
                    'totalCapacity' => $totalCapacity,
                    'usedSpaces' => $totalUsed,
                    'remainingSpaces' => max(0, $remainingSpaces),
                    'hasSpace' => $remainingSpaces > 0
                ];
            })->filter(function ($block) {
                // Only return blocks that have space
                return $block['hasSpace'];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $availableBlocks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available blocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get block statistics
     */
    public function getStats()
    {
        try {
            $totalBlocks = Block::count();
            $activeBlocks = Block::where('status', 'active')->count();
            $maintenanceBlocks = Block::where('status', 'maintenance')->count();
            
            $totalRooms = Room::count();
            $occupiedRooms = Room::where('current_occupancy', '>', 0)->count();
            
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
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}