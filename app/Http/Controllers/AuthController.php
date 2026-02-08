<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'user_type' => 'required|in:student,supervisor',
            'student_id' => 'required_if:user_type,student|unique:users,student_id',
            'department' => 'nullable|string|max:255',
            'gender' => 'required|in:male,female', // Required for both students and supervisors
            'assigned_block' => 'required_if:user_type,supervisor|string|max:10',
            'year_level' => 'required_if:user_type,student|integer|min:1|max:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate supervisor limit per block (max 3 supervisors per block)
        if ($request->user_type === 'supervisor' && $request->assigned_block) {
            // Check if the selected block matches supervisor's gender
            $block = \DB::table('blocks')
                ->where('name', $request->assigned_block)
                ->where('status', 'active')
                ->first();
            
            if (!$block) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected block not found or inactive',
                    'errors' => [
                        'assigned_block' => ['The selected block is not available.']
                    ]
                ], 422);
            }
            
            // Validate gender matching
            if ($block->gender !== $request->gender) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gender mismatch with selected block',
                    'errors' => [
                        'assigned_block' => ["Block {$request->assigned_block} is designated for {$block->gender} supervisors only."]
                    ]
                ], 422);
            }
            
            $existingSupervisors = User::where('user_type', 'supervisor')
                ->where('assigned_block', $request->assigned_block)
                ->count();
            
            if ($existingSupervisors >= 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Block supervisor limit reached',
                    'errors' => [
                        'assigned_block' => ["Block {$request->assigned_block} already has the maximum of 3 supervisors. Please choose a different block or contact admin."]
                    ]
                ], 422);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'student_id' => $request->student_id,
            'department' => $request->department,
            'gender' => $request->gender,
            'assigned_block' => $request->assigned_block,
            'year_level' => $request->year_level,
            'status' => 'pending' // ALL users need admin approval now
        ]);

        // Send notification to admins when anyone registers
        $this->notificationService->notifyUserRegistered($user);

        // Only create token for non-pending users
        $token = null;
        if ($user->status !== 'pending') {
            $token = JWTAuth::fromUser($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Your account is pending admin approval. You will be notified once approved.',
            'data' => [
                'user' => $user,
                'token' => null, // No token until approved
                'requires_approval' => true
            ]
        ], 201);
    }

    /**
     * Get available blocks for supervisor registration
     */
    public function getAvailableBlocks()
    {
        try {
            // Get all blocks from blocks table (not just rooms table)
            $allBlocks = \DB::table('blocks')
                ->select('name')
                ->where('status', 'active') // Only active blocks
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
            
            // If no blocks in blocks table, fallback to rooms table for backward compatibility
            if (empty($allBlocks)) {
                $allBlocks = \DB::table('rooms')
                    ->select('block')
                    ->distinct()
                    ->orderBy('block')
                    ->pluck('block')
                    ->toArray();
            }
            
            // Clean block names - remove "Block " prefix if it exists
            $allBlocks = array_map(function($blockName) {
                return preg_replace('/^Block\s+/i', '', $blockName);
            }, $allBlocks);
            
            // Get supervisor count per block
            $blockInfo = [];
            foreach ($allBlocks as $block) {
                $supervisorCount = User::where('user_type', 'supervisor')
                    ->where('assigned_block', $block)
                    ->count();
                
                $blockInfo[] = [
                    'block' => $block,
                    'supervisor_count' => $supervisorCount,
                    'available_slots' => max(0, 3 - $supervisorCount),
                    'is_full' => $supervisorCount >= 3
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $blockInfo
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available blocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create token'
            ], 500);
        }

        $user = auth()->user();
        
        // Check if account is pending approval
        if ($user->status === 'pending') {
            // Invalidate the token
            try {
                JWTAuth::invalidate($token);
            } catch (JWTException $e) {
                // Ignore token invalidation errors
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending admin approval. Please wait for approval before logging in.'
            ], 403);
        }
        
        if ($user->status !== User::STATUS_ACTIVE) {
            // Invalidate the token
            try {
                JWTAuth::invalidate($token);
            } catch (JWTException $e) {
                // Ignore token invalidation errors
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Account is not active'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    public function me()
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalid'
            ], 401);
        }

        // Load additional data based on user type
        $userData = $user->toArray();
        
        if ($user->isStudent()) {
            $userData['room_assignment'] = $user->roomAssignment;
            $userData['active_application'] = $user->dormitoryApplications()
                ->whereIn('status', ['pending', 'approved'])
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $userData
        ]);
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh();
            return response()->json([
                'success' => true,
                'data' => ['token' => $token]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token could not be refreshed'
            ], 401);
        }
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout'
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|before:today',
            'address' => 'sometimes|string|max:500',
            'emergency_contact' => 'sometimes|string|max:255',
            'emergency_phone' => 'sometimes|string|max:20',
            'current_password' => 'required_with:password',
            'password' => 'sometimes|string|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Age validation if date_of_birth is provided
        if ($request->has('date_of_birth')) {
            $age = \Carbon\Carbon::parse($request->date_of_birth)->age;
            
            if ($age < 16 || $age > 70) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date of birth results in unrealistic age',
                    'errors' => [
                        'date_of_birth' => [
                            $age < 16 
                                ? 'Age must be at least 16 years old' 
                                : 'Age cannot exceed 70 years'
                        ]
                    ]
                ], 422);
            }
        }

        // Verify current password if changing password
        if ($request->has('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 422);
            }
            $user->password = Hash::make($request->password);
        }

        // Update profile fields
        $updateFields = ['name', 'department', 'date_of_birth', 'address', 'emergency_contact', 'emergency_phone'];
        foreach ($updateFields as $field) {
            if ($request->has($field)) {
                $user->$field = $request->$field;
            }
        }

        $user->save();

        // Include age and warning in response
        $userData = $user->toArray();
        $userData['age'] = $user->age;
        $userData['age_warning'] = $user->age_warning;

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $userData
        ]);
    }

    // Admin Management Methods
    public function getAllUsers()
    {
        $users = User::select('id', 'name', 'email', 'user_type', 'student_id', 'department', 'year_level', 'assigned_block', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function getStudents()
    {
        $students = User::where('user_type', User::TYPE_STUDENT)
            ->select('id', 'name', 'email', 'student_id', 'department', 'year_level', 'gender', 'status', 'created_at')
            ->orderByRaw("FIELD(status, 'pending', 'active', 'inactive', 'suspended', 'rejected')")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }

    public function approveStudent($id)
    {
        $student = User::where('user_type', User::TYPE_STUDENT)
            ->where('id', $id)
            ->firstOrFail();

        if ($student->status !== User::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending students can be approved'
            ], 422);
        }

        $student->update(['status' => User::STATUS_ACTIVE]);

        // Note: Notification for approval can be added later if needed
        // For now, just update the status

        return response()->json([
            'success' => true,
            'message' => 'Student approved successfully',
            'data' => $student
        ]);
    }

    public function rejectStudent(Request $request, $id)
    {
        $student = User::where('user_type', User::TYPE_STUDENT)
            ->where('id', $id)
            ->firstOrFail();

        if ($student->status !== User::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending students can be rejected'
            ], 422);
        }

        $student->update(['status' => User::STATUS_REJECTED]);

        // Note: Notification for rejection can be added later if needed
        // For now, just update the status

        return response()->json([
            'success' => true,
            'message' => 'Student rejected successfully',
            'data' => $student
        ]);
    }

    public function getSupervisors()
    {
        $supervisors = User::where('user_type', User::TYPE_SUPERVISOR)
            ->select('id', 'name', 'email', 'assigned_block', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $supervisors
        ]);
    }

    public function getAdministrators()
    {
        $admins = User::where('user_type', User::TYPE_ADMIN)
            ->select('id', 'name', 'email', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $admins
        ]);
    }

    public function createAdministrator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => User::TYPE_ADMIN,
            'status' => User::STATUS_ACTIVE
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Administrator created successfully',
            'data' => $admin
        ], 201);
    }

    public function updateAdministrator(Request $request, $id)
    {
        $admin = User::where('user_type', User::TYPE_ADMIN)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin->update($request->only(['name', 'email', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'Administrator updated successfully',
            'data' => $admin
        ]);
    }

    public function resetAdminPassword(Request $request, $id)
    {
        $admin = User::where('user_type', User::TYPE_ADMIN)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Administrator password reset successfully'
        ]);
    }

    public function getUser($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only(['name', 'email', 'status']));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function updateUserStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user
        ]);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting the last admin
        if ($user->user_type === User::TYPE_ADMIN) {
            $adminCount = User::where('user_type', User::TYPE_ADMIN)->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the last administrator'
                ], 422);
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get all pending users (students and supervisors)
     */
    public function getPendingUsers()
    {
        try {
            $pendingUsers = User::where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pendingUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve any user (student or supervisor)
     */
    public function approveUser($id)
    {
        try {
            $user = User::findOrFail($id);
            
            if ($user->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not pending approval'
                ], 400);
            }

            $user->update(['status' => 'active']);

            // Send notification
            $this->notificationService->notifyUserApproved($user);

            return response()->json([
                'success' => true,
                'message' => ucfirst($user->user_type) . ' approved successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject any user (student or supervisor)
     */
    public function rejectUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            
            if ($user->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not pending approval'
                ], 400);
            }

            $user->update(['status' => 'rejected']);

            // Send notification with reason
            $reason = $request->input('reason', 'No reason provided');
            $this->notificationService->notifyUserRejected($user, $reason);

            return response()->json([
                'success' => true,
                'message' => ucfirst($user->user_type) . ' rejected successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}