<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChangeRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = ChangeRequest::with(['student', 'currentRoom', 'requestedRoom', 'processor']);

        // Filter based on user type
        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isSupervisor()) {
            // Supervisors see requests for their assigned block
            if ($user->assigned_block) {
                $query->whereHas('currentRoom', function($q) use ($user) {
                    $q->where('block', $user->assigned_block);
                })->orWhereHas('requestedRoom', function($q) use ($user) {
                    $q->where('block', $user->assigned_block);
                });
            }
        }
        // Admins see all requests

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('request_type')) {
            $query->where('request_type', $request->request_type);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $requests = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isStudent()) {
            return response()->json([
                'success' => false,
                'message' => 'Only students can submit change requests'
            ], 403);
        }

        // Check if student has an active room assignment
        if (!$user->hasRoomAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'You must have a room assignment to request changes'
            ], 422);
        }

        // Check if student already has a pending change request
        if ($user->changeRequests()->where('status', ChangeRequest::STATUS_PENDING)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending change request'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'request_type' => 'required|in:room_change,block_change,maintenance',
            'requested_room_id' => 'nullable|exists:rooms,id',
            'requested_block' => 'nullable|string|max:10',
            'reason' => 'required|string',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate requested room if specified
        if ($request->requested_room_id) {
            $requestedRoom = Room::find($request->requested_room_id);
            if (!$requestedRoom->isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Requested room is not available'
                ], 422);
            }
        }

        $currentAssignment = $user->roomAssignment;

        $changeRequest = ChangeRequest::create([
            'student_id' => $user->id,
            'current_room_id' => $currentAssignment->room_id,
            'requested_room_id' => $request->requested_room_id,
            'requested_block' => $request->requested_block,
            'request_type' => $request->request_type,
            'reason' => $request->reason,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => ChangeRequest::STATUS_PENDING
        ]);

        $changeRequest->load(['student', 'currentRoom', 'requestedRoom']);

        return response()->json([
            'success' => true,
            'message' => 'Change request submitted successfully',
            'data' => $changeRequest
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $changeRequest = ChangeRequest::with(['student', 'currentRoom', 'requestedRoom', 'processor'])
                                    ->findOrFail($id);

        // Authorization check
        if ($user->isStudent() && $changeRequest->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $changeRequest
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $changeRequest = ChangeRequest::findOrFail($id);

        // Only students can update their own pending requests
        if (!$user->isStudent() || $changeRequest->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($changeRequest->status !== ChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'requested_room_id' => 'sometimes|exists:rooms,id',
            'requested_block' => 'sometimes|string|max:10',
            'reason' => 'sometimes|string',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $changeRequest->update($request->only([
            'requested_room_id', 'requested_block', 'reason', 
            'description', 'priority'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Change request updated successfully',
            'data' => $changeRequest->load(['student', 'currentRoom', 'requestedRoom'])
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user->isSupervisor() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $changeRequest = ChangeRequest::findOrFail($id);

        if ($changeRequest->status !== ChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be approved'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $changeRequest->update([
                'status' => ChangeRequest::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
                'notes' => $request->notes
            ]);

            // If it's a room change request, handle the room reassignment
            if ($changeRequest->request_type === 'room_change' && $changeRequest->requested_room_id) {
                $this->handleRoomChange($changeRequest);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Change request approved successfully',
                'data' => $changeRequest->load(['student', 'currentRoom', 'requestedRoom', 'processor'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve change request'
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        
        if (!$user->isSupervisor() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $changeRequest = ChangeRequest::findOrFail($id);

        if ($changeRequest->status !== ChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be rejected'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $changeRequest->update([
            'status' => ChangeRequest::STATUS_REJECTED,
            'approved_by' => $user->id,
            'rejected_reason' => $request->reason,
            'notes' => $request->notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Change request rejected successfully',
            'data' => $changeRequest->load(['student', 'currentRoom', 'requestedRoom', 'processor'])
        ]);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $changeRequest = ChangeRequest::findOrFail($id);

        // Only students can delete their own pending requests
        if (!$user->isStudent() || $changeRequest->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($changeRequest->status !== ChangeRequest::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending requests can be deleted'
            ], 422);
        }

        $changeRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Change request deleted successfully'
        ]);
    }

    private function handleRoomChange($changeRequest)
    {
        $student = $changeRequest->student;
        $currentAssignment = $student->roomAssignment;
        $newRoom = Room::find($changeRequest->requested_room_id);

        if (!$newRoom->canAccommodate()) {
            throw new \Exception('Requested room is no longer available');
        }

        // Update current assignment
        $currentAssignment->update([
            'status' => 'inactive',
            'unassigned_at' => now()
        ]);

        // Decrease occupancy of current room
        $currentAssignment->room->decrementOccupancy();

        // Create new assignment
        $student->roomAssignment()->create([
            'room_id' => $newRoom->id,
            'assigned_at' => now(),
            'status' => 'active'
        ]);

        // Increase occupancy of new room
        $newRoom->incrementOccupancy();
    }
}