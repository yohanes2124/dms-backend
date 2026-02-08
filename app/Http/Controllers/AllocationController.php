<?php

namespace App\Http\Controllers;

use App\Services\AllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AllocationController extends Controller
{
    protected $allocationService;

    public function __construct(AllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    /**
     * Get allocation statistics
     */
    public function stats()
    {
        try {
            $stats = $this->allocationService->getAllocationStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch allocation stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allocation statistics'
            ], 500);
        }
    }

    /**
     * Perform automatic room allocation
     * POST /api/allocations/auto
     */
    public function autoAllocate(Request $request)
    {
        try {
            // Check authorization - user should be authenticated and admin
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login first.'
                ], 401);
            }

            if ($user->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only admins can perform auto allocation.'
                ], 403);
            }

            Log::info('Auto allocation requested by admin', ['admin_id' => $user->id]);

            // Get optional filters
            $filters = [];
            if ($request->has('block')) {
                $filters['block'] = $request->input('block');
            }
            if ($request->has('gender')) {
                $filters['gender'] = $request->input('gender');
            }

            // Perform allocation
            $result = $this->allocationService->autoAllocate($filters);

            if ($result['success']) {
                return response()->json($result, 200);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Exception $e) {
            Log::error('Auto allocation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Auto allocation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform re-allocation for approved change requests
     * POST /api/allocations/reallocate
     */
    public function reallocate(Request $request)
    {
        try {
            // Check authorization
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please login first.'
                ], 401);
            }

            if ($user->user_type !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only admins can perform re-allocation.'
                ], 403);
            }

            Log::info('Re-allocation requested by admin', ['admin_id' => $user->id]);

            // Get optional filters
            $filters = [];
            if ($request->has('block')) {
                $filters['block'] = $request->input('block');
            }

            // Perform re-allocation for change requests
            $result = $this->allocationService->reallocateChangeRequests($filters);

            if ($result['success']) {
                return response()->json($result, 200);
            } else {
                return response()->json($result, 500);
            }

        } catch (\Exception $e) {
            Log::error('Re-allocation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Re-allocation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of allocations
     * GET /api/allocations
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Only admins and supervisors can view allocations
            if (!in_array($user->user_type, ['admin', 'supervisor'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $query = \App\Models\RoomAssignment::with(['student', 'room', 'application'])
                ->orderBy('assigned_at', 'desc');

            // Supervisors see only their block
            if ($user->user_type === 'supervisor' && $user->assigned_block) {
                $query->whereHas('room', function ($q) use ($user) {
                    $q->where('block', $user->assigned_block);
                });
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('block')) {
                $query->whereHas('room', function ($q) use ($request) {
                    $q->where('block', $request->input('block'));
                });
            }

            $allocations = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $allocations
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch allocations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allocations'
            ], 500);
        }
    }

    /**
     * Get allocation details
     * GET /api/allocations/{id}
     */
    public function show($id)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $allocation = \App\Models\RoomAssignment::with(['student', 'room', 'application'])
                ->findOrFail($id);

            // Authorization check
            if ($user->user_type === 'student' && $allocation->student_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            if ($user->user_type === 'supervisor' && $allocation->room->block !== $user->assigned_block) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $allocation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation not found'
            ], 404);
        }
    }
}
