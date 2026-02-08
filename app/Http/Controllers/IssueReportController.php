<?php

namespace App\Http\Controllers;

use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IssueReportController extends Controller
{
    /**
     * Get all issue reports (admin/supervisor)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = IssueReport::with(['student', 'room', 'assignedTo']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Supervisors only see issues in their assigned block
        if ($user->isSupervisor()) {
            $query->whereHas('room', function ($q) use ($user) {
                $q->where('block', $user->assigned_block);
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $issues = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $issues
        ]);
    }

    /**
     * Get student's own issue reports
     */
    public function myIssues()
    {
        $user = auth()->user();
        
        $issues = IssueReport::with(['room', 'assignedTo'])
            ->where('student_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $issues
        ]);
    }

    /**
     * Create new issue report
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'room_id' => 'nullable|exists:rooms,id',
            'category' => 'required|in:plumbing,electrical,furniture,cleaning,other',
            'priority' => 'required|in:low,medium,high,urgent',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // If no room_id provided, try to get student's current room
        $roomId = $request->room_id;
        if (!$roomId && $user->isStudent()) {
            $assignment = $user->roomAssignment;
            $roomId = $assignment ? $assignment->room_id : null;
        }

        $issue = IssueReport::create([
            'student_id' => $user->id,
            'room_id' => $roomId,
            'category' => $request->category,
            'priority' => $request->priority,
            'title' => $request->title,
            'description' => $request->description,
            'status' => IssueReport::STATUS_PENDING
        ]);

        $issue->load(['student', 'room', 'assignedTo']);

        return response()->json([
            'success' => true,
            'message' => 'Issue report submitted successfully',
            'data' => $issue
        ], 201);
    }

    /**
     * Get single issue report
     */
    public function show($id)
    {
        $user = auth()->user();
        $issue = IssueReport::with(['student', 'room', 'assignedTo'])->findOrFail($id);

        // Students can only view their own issues
        if ($user->isStudent() && $issue->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Supervisors can only view issues in their block
        if ($user->isSupervisor()) {
            if (!$issue->room || $issue->room->block !== $user->assigned_block) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $issue
        ]);
    }

    /**
     * Update issue report (admin/supervisor)
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $issue = IssueReport::findOrFail($id);

        // Supervisors can only update issues in their block
        if ($user->isSupervisor()) {
            if (!$issue->room || $issue->room->block !== $user->assigned_block) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'resolution_notes' => 'nullable|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['status', 'priority', 'assigned_to', 'resolution_notes']);

        // Set resolved_at timestamp when status changes to resolved
        if ($request->has('status') && $request->status === IssueReport::STATUS_RESOLVED && $issue->status !== IssueReport::STATUS_RESOLVED) {
            $updateData['resolved_at'] = now();
        }

        $issue->update($updateData);
        $issue->load(['student', 'room', 'assignedTo']);

        return response()->json([
            'success' => true,
            'message' => 'Issue report updated successfully',
            'data' => $issue
        ]);
    }

    /**
     * Delete issue report (admin only)
     */
    public function destroy($id)
    {
        $issue = IssueReport::findOrFail($id);
        $issue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Issue report deleted successfully'
        ]);
    }

    /**
     * Get issue statistics
     */
    public function statistics()
    {
        $user = auth()->user();
        $query = IssueReport::query();

        // Supervisors only see stats for their block
        if ($user->isSupervisor()) {
            $query->whereHas('room', function ($q) use ($user) {
                $q->where('block', $user->assigned_block);
            });
        }

        $stats = [
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', IssueReport::STATUS_PENDING)->count(),
            'in_progress' => (clone $query)->where('status', IssueReport::STATUS_IN_PROGRESS)->count(),
            'resolved' => (clone $query)->where('status', IssueReport::STATUS_RESOLVED)->count(),
            'closed' => (clone $query)->where('status', IssueReport::STATUS_CLOSED)->count(),
            'by_category' => [
                'plumbing' => (clone $query)->where('category', IssueReport::CATEGORY_PLUMBING)->count(),
                'electrical' => (clone $query)->where('category', IssueReport::CATEGORY_ELECTRICAL)->count(),
                'furniture' => (clone $query)->where('category', IssueReport::CATEGORY_FURNITURE)->count(),
                'cleaning' => (clone $query)->where('category', IssueReport::CATEGORY_CLEANING)->count(),
                'other' => (clone $query)->where('category', IssueReport::CATEGORY_OTHER)->count(),
            ],
            'by_priority' => [
                'urgent' => (clone $query)->where('priority', IssueReport::PRIORITY_URGENT)->count(),
                'high' => (clone $query)->where('priority', IssueReport::PRIORITY_HIGH)->count(),
                'medium' => (clone $query)->where('priority', IssueReport::PRIORITY_MEDIUM)->count(),
                'low' => (clone $query)->where('priority', IssueReport::PRIORITY_LOW)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
