<?php

namespace App\Http\Controllers;

use App\Models\DormitoryApplication;
use App\Models\Room;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DormitoryApplicationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = DormitoryApplication::with(['student', 'preferredRoom', 'approver']);

        // Filter based on user type
        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isSupervisor()) {
            // Supervisors see applications for their assigned block
            if ($user->assigned_block) {
                $query->where('preferred_block', $user->assigned_block);
            }
        }
        // Admins see all applications

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('block')) {
            $query->where('preferred_block', $request->block);
        }

        if ($request->has('date_from')) {
            $query->whereDate('application_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('application_date', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if ($sortBy === 'priority') {
            $query->byPriority();
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $applications = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('=== APPLICATION SUBMISSION START ===');
        \Log::info('Request Data:', $request->all());
        
        $user = auth()->user();
        \Log::info('Authenticated User:', $user ? $user->toArray() : 'No user');

        if (!$user->isStudent()) {
            \Log::warning('Non-student attempted application submission', ['user_type' => $user->user_type]);
            return response()->json([
                'success' => false,
                'message' => 'Only students can submit applications'
            ], 403);
        }

        // Check if student already has an active application
        $existingApps = $user->dormitoryApplications()
            ->whereIn('status', ['pending', 'approved'])
            ->get();
            
        \Log::info('Existing applications check:', ['count' => $existingApps->count(), 'apps' => $existingApps->toArray()]);
        
        if ($existingApps->count() > 0) {
            \Log::warning('User has active application', ['existing_count' => $existingApps->count()]);
            return response()->json([
                'success' => false,
                'message' => 'You already have an active application',
                'existing_applications' => $existingApps->map(function($app) {
                    return [
                        'id' => $app->id,
                        'status' => $app->status,
                        'created_at' => $app->created_at
                    ];
                })
            ], 422);
        }

        // FIXED VALIDATION - MATCH YOUR REQUIREMENTS
        $validator = Validator::make($request->all(), [
            'preferred_room_id' => 'nullable|exists:rooms,id',
            'preferred_block' => 'required|string|max:255',
            'special_requirements' => 'nullable|array',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:255',
            'medical_conditions' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // ✅ CAPACITY VALIDATION - Check if block has space
        $blockCapacityCheck = $this->validateBlockCapacity($request->preferred_block);
        if (!$blockCapacityCheck['hasSpace']) {
            \Log::warning('Block capacity exceeded', [
                'block' => $request->preferred_block,
                'capacity_info' => $blockCapacityCheck
            ]);
            return response()->json([
                'success' => false,
                'message' => "Block {$request->preferred_block} is currently full. Please choose a different block.",
                'capacity_info' => $blockCapacityCheck
            ], 422);
        }

        // Validate preferred room if specified
        if ($request->preferred_room_id) {
            $room = Room::find($request->preferred_room_id);
            if (!$room || !$room->isAvailable()) {
                \Log::warning('Invalid room selected', ['room_id' => $request->preferred_room_id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Selected room is not available'
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $applicationData = [
                'student_id' => $user->id,
                'preferred_room_id' => $request->preferred_room_id,
                'preferred_block' => $request->preferred_block,
                'application_date' => now()->toDateString(),
                'status' => DormitoryApplication::STATUS_PENDING,
                'special_requirements' => $request->special_requirements ?? [],
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'medical_conditions' => $request->medical_conditions
            ];
            
            \Log::info('Creating application with data:', $applicationData);
            
            $application = DormitoryApplication::create($applicationData);

            // Calculate and update priority score
            $priorityScore = $application->calculatePriorityScore();
            $application->update(['priority_score' => $priorityScore]);

            // Send notification to supervisors and admins
            $this->notificationService->notifyApplicationSubmitted($user, $application);

            DB::commit();

            $application->load(['student', 'preferredRoom']);
            
            \Log::info('Application created successfully:', ['application_id' => $application->id]);

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully',
                'data' => $application
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Application creation failed:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ CAPACITY VALIDATION METHOD
     * Check if a block has remaining capacity for new applications
     */
    private function validateBlockCapacity($blockName)
    {
        try {
            // Calculate total capacity for this block
            $totalCapacity = Room::where('block', $blockName)->sum('capacity');
            
            // Calculate used spaces (active room assignments)
            $usedSpaces = \App\Models\RoomAssignment::whereHas('room', function($q) use ($blockName) {
                $q->where('block', $blockName);
            })->whereIn('status', ['assigned', 'active'])->count();
            
            // Add pending applications that haven't been assigned yet
            $pendingApplications = DormitoryApplication::where('preferred_block', $blockName)
                ->where('status', 'pending')
                ->count();
            
            $totalUsed = $usedSpaces + $pendingApplications;
            $remainingSpaces = $totalCapacity - $totalUsed;
            
            return [
                'blockName' => $blockName,
                'totalCapacity' => $totalCapacity,
                'usedSpaces' => $usedSpaces,
                'pendingApplications' => $pendingApplications,
                'totalUsed' => $totalUsed,
                'remainingSpaces' => max(0, $remainingSpaces),
                'hasSpace' => $remainingSpaces > 0
            ];
        } catch (\Exception $e) {
            \Log::error('Block capacity validation error:', [
                'block' => $blockName,
                'error' => $e->getMessage()
            ]);
            return [
                'blockName' => $blockName,
                'hasSpace' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        $application = DormitoryApplication::with(['student', 'preferredRoom', 'approver', 'roomAssignment'])
                                         ->findOrFail($id);

        // Authorization check
        if ($user->isStudent() && $application->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($user->isSupervisor() && $application->preferred_block !== $user->assigned_block) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $application = DormitoryApplication::findOrFail($id);

        // Only students can update their own draft applications
        if (!$user->isStudent() || $application->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($application->status !== DormitoryApplication::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft applications can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'preferred_room_id' => 'nullable|exists:rooms,id',
            'preferred_block' => 'sometimes|string|max:10',
            'special_requirements' => 'nullable|array',
            'emergency_contact_name' => 'sometimes|string|max:255',
            'emergency_contact_phone' => 'sometimes|string|max:20',
            'medical_conditions' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $application->update($request->only([
            'preferred_room_id', 'preferred_block',
            'special_requirements', 'emergency_contact_name', 
            'emergency_contact_phone', 'medical_conditions'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully',
            'data' => $application->load(['student', 'preferredRoom'])
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

        $application = DormitoryApplication::findOrFail($id);

        if (!$application->canBeApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Application cannot be approved'
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
            $application->approve($user->id, $request->notes);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application approved successfully',
                'data' => $application->load(['student', 'preferredRoom', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve application'
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

        $application = DormitoryApplication::findOrFail($id);

        if (!$application->canBeRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Application cannot be rejected'
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

        DB::beginTransaction();
        try {
            $application->reject($user->id, $request->reason, $request->notes);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application rejected successfully',
                'data' => $application->load(['student', 'preferredRoom', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject application'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $application = DormitoryApplication::findOrFail($id);

        // Only students can delete their own draft applications
        if (!$user->isStudent() || $application->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($application->status !== DormitoryApplication::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft applications can be deleted'
            ], 422);
        }

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application deleted successfully'
        ]);
    }

    public function getStats()
    {
        $user = auth()->user();
        
        if (!$user->isSupervisor() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = DormitoryApplication::query();
        
        if ($user->isSupervisor() && $user->assigned_block) {
            $query->where('preferred_block', $user->assigned_block);
        }

        $stats = [
            'total' => $query->count(),
            'pending' => $query->where('status', DormitoryApplication::STATUS_PENDING)->count(),
            'approved' => $query->where('status', DormitoryApplication::STATUS_APPROVED)->count(),
            'rejected' => $query->where('status', DormitoryApplication::STATUS_REJECTED)->count(),
            'completed' => $query->where('status', DormitoryApplication::STATUS_COMPLETED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}