<?php

namespace App\Http\Controllers;

use App\Models\TemporaryLeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TemporaryLeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $query = TemporaryLeaveRequest::with(['student', 'supervisor']);

        // Filter based on user type
        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isSupervisor()) {
            // Supervisors see requests from their block students
            $query->where('supervisor_id', $user->id);
        }
        // Admins see all requests

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('approval_status')) {
            $query->where('supervisor_approval', $request->approval_status);
        }

        if ($request->has('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        if ($request->has('date_from')) {
            $query->where('start_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('end_date', '<=', $request->date_to);
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
                'message' => 'Only students can request temporary leave'
            ], 403);
        }

        // Check if student has an active room assignment (make it optional for now)
        $roomAssignment = $user->roomAssignment;
        if (!$roomAssignment) {
            // For testing purposes, we'll allow requests without room assignment
            // In production, you might want to enforce this
            \Log::warning("Student {$user->id} submitted leave request without room assignment");
        }

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|in:weekend,holiday,emergency,medical,family_visit,other',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'return_date' => 'required|date|after_or_equal:end_date',
            'destination' => 'required|string|max:255',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20',
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate duration (max 30 days)
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $duration = $startDate->diffInDays($endDate) + 1;

        if ($duration > 30) {
            return response()->json([
                'success' => false,
                'message' => 'Temporary leave cannot exceed 30 days. For longer periods, please use the regular clearance process.'
            ], 422);
        }

        // Check for overlapping requests
        $overlapping = TemporaryLeaveRequest::where('student_id', $user->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->where(function($query) use ($request) {
                $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                      ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                      ->orWhere(function($q) use ($request) {
                          $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                      });
            })
            ->exists();

        if ($overlapping) {
            return response()->json([
                'success' => false,
                'message' => 'You have an overlapping leave request for the selected dates'
            ], 422);
        }

        // Get supervisor for the student's block using smart assignment
        $supervisorService = new \App\Services\SupervisorAssignmentService();
        $supervisor = $supervisorService->assignSupervisor($user, $request->leave_type);

        $leaveRequest = TemporaryLeaveRequest::create([
            'student_id' => $user->id,
            'supervisor_id' => $supervisor?->id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'return_date' => $request->return_date,
            'destination' => $request->destination,
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_phone' => $request->emergency_contact_phone,
            'reason' => $request->reason,
            'status' => TemporaryLeaveRequest::STATUS_SUBMITTED
        ]);

        $leaveRequest->load(['student', 'supervisor']);

        // TODO: Send notification to supervisor

        return response()->json([
            'success' => true,
            'message' => 'Temporary leave request submitted successfully',
            'data' => $leaveRequest
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $leaveRequest = TemporaryLeaveRequest::with(['student', 'supervisor'])->findOrFail($id);

        // Authorization check
        if ($user->isStudent() && $leaveRequest->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($user->isSupervisor() && $leaveRequest->supervisor_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $leaveRequest
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $leaveRequest = TemporaryLeaveRequest::findOrFail($id);

        // Authorization check
        if ($user->isStudent() && $leaveRequest->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Students can only update draft requests
        if ($user->isStudent() && $leaveRequest->status !== TemporaryLeaveRequest::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft requests can be updated'
            ], 422);
        }

        $rules = [];
        
        if ($user->isStudent()) {
            $rules = [
                'leave_type' => 'sometimes|in:weekend,holiday,emergency,medical,family_visit,other',
                'start_date' => 'sometimes|date|after_or_equal:today',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'return_date' => 'sometimes|date|after_or_equal:end_date',
                'destination' => 'sometimes|string|max:255',
                'emergency_contact_name' => 'sometimes|string|max:255',
                'emergency_contact_phone' => 'sometimes|string|max:20',
                'reason' => 'sometimes|string|max:1000'
            ];
        } else {
            // Supervisors can update approval status
            $rules = [
                'supervisor_approval' => 'sometimes|in:pending,approved,rejected',
                'supervisor_notes' => 'sometimes|string|max:1000',
                'room_secured' => 'sometimes|boolean'
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(array_keys($rules));

        // Handle approval/rejection
        if (isset($updateData['supervisor_approval'])) {
            if ($updateData['supervisor_approval'] === 'approved') {
                $leaveRequest->approve($user->id, $request->supervisor_notes);
            } elseif ($updateData['supervisor_approval'] === 'rejected') {
                $leaveRequest->reject($user->id, $request->supervisor_notes);
            }
        } else {
            $leaveRequest->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request updated successfully',
            'data' => $leaveRequest->load(['student', 'supervisor'])
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        $leaveRequest = TemporaryLeaveRequest::findOrFail($id);

        if (!$user->isSupervisor() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only supervisors and admins can approve leave requests'
            ], 403);
        }

        if (!$leaveRequest->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be approved'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leaveRequest->approve($user->id, $request->notes);

        return response()->json([
            'success' => true,
            'message' => 'Leave request approved successfully',
            'data' => $leaveRequest->load(['student', 'supervisor'])
        ]);
    }

    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        $leaveRequest = TemporaryLeaveRequest::findOrFail($id);

        if (!$user->isSupervisor() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only supervisors and admins can reject leave requests'
            ], 403);
        }

        if (!$leaveRequest->canBeRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'This request cannot be rejected'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leaveRequest->reject($user->id, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected',
            'data' => $leaveRequest->load(['student', 'supervisor'])
        ]);
    }

    public function markReturned($id)
    {
        $user = auth()->user();
        $leaveRequest = TemporaryLeaveRequest::findOrFail($id);

        if (!$user->isSupervisor() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only supervisors and admins can mark students as returned'
            ], 403);
        }

        if (!$leaveRequest->isActive() && !$leaveRequest->isOverdue()) {
            return response()->json([
                'success' => false,
                'message' => 'Student is not currently on leave'
            ], 422);
        }

        $leaveRequest->markAsReturned();

        return response()->json([
            'success' => true,
            'message' => 'Student marked as returned successfully',
            'data' => $leaveRequest->load(['student', 'supervisor'])
        ]);
    }

    public function getStats()
    {
        $user = auth()->user();
        
        $query = TemporaryLeaveRequest::query();
        
        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isSupervisor()) {
            $query->where('supervisor_id', $user->id);
        }

        $stats = [
            'total_requests' => $query->count(),
            'pending_approval' => $query->where('supervisor_approval', 'pending')->count(),
            'approved_requests' => $query->where('supervisor_approval', 'approved')->count(),
            'active_leaves' => $query->where('status', 'approved')
                                   ->where('start_date', '<=', now())
                                   ->where('return_date', '>=', now())
                                   ->whereNull('returned_at')
                                   ->count(),
            'overdue_returns' => $query->where('status', 'approved')
                                      ->where('return_date', '<', now())
                                      ->whereNull('returned_at')
                                      ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}