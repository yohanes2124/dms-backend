<?php

namespace App\Http\Controllers;

use App\Models\ClearanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClearanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = ClearanceRecord::with(['student', 'processedBy']);

        // Filter based on user type
        if ($user->isStudent()) {
            $query->where('student_id', $user->id);
        }
        // Supervisors and admins see all clearance records

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('clearance_type')) {
            $query->where('clearance_type', $request->clearance_type);
        }

        if ($request->has('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $clearances = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $clearances
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isStudent()) {
            return response()->json([
                'success' => false,
                'message' => 'Only students can request clearance'
            ], 403);
        }

        // Check if student has an active room assignment
        if (!$user->hasRoomAssignment()) {
            return response()->json([
                'success' => false,
                'message' => 'You must have a room assignment to request clearance'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'clearance_type' => 'required|in:graduation,transfer,temporary_leave,disciplinary',
            'academic_year' => 'required|string',
            'semester' => 'required|string',
            'reason' => 'nullable|string',
            'expected_clearance_date' => 'nullable|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if student already has a pending clearance
        if ($user->clearanceRecords()->where('status', ClearanceRecord::STATUS_PENDING)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending clearance request'
            ], 422);
        }

        $clearance = ClearanceRecord::create([
            'student_id' => $user->id,
            'clearance_type' => $request->clearance_type,
            'academic_year' => $request->academic_year,
            'semester' => $request->semester,
            'reason' => $request->reason,
            'expected_clearance_date' => $request->expected_clearance_date,
            'status' => ClearanceRecord::STATUS_PENDING,
            'checklist' => $this->getDefaultChecklist($request->clearance_type)
        ]);

        $clearance->load(['student']);

        return response()->json([
            'success' => true,
            'message' => 'Clearance request submitted successfully',
            'data' => $clearance
        ], 201);
    }

    public function show($id)
    {
        $user = auth()->user();
        $clearance = ClearanceRecord::with(['student', 'processedBy'])->findOrFail($id);

        // Authorization check
        if ($user->isStudent() && $clearance->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $clearance
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $clearance = ClearanceRecord::findOrFail($id);

        // Authorization check
        if ($user->isStudent() && $clearance->student_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Students can only update pending clearances
        if ($user->isStudent() && $clearance->status !== ClearanceRecord::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending clearances can be updated'
            ], 422);
        }

        $rules = [];
        
        if ($user->isStudent()) {
            $rules = [
                'reason' => 'sometimes|string',
                'expected_clearance_date' => 'sometimes|date|after:today'
            ];
        } else {
            // Supervisors/Admins can update more fields
            $rules = [
                'status' => 'sometimes|in:pending,in_progress,completed,rejected',
                'checklist' => 'sometimes|array',
                'notes' => 'sometimes|string',
                'completion_date' => 'sometimes|date'
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

        // If status is being updated to completed, set processed_by and completion_date
        if (isset($updateData['status']) && $updateData['status'] === ClearanceRecord::STATUS_COMPLETED) {
            $updateData['processed_by'] = $user->id;
            $updateData['completion_date'] = now();
        }

        $clearance->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Clearance updated successfully',
            'data' => $clearance->load(['student', 'processedBy'])
        ]);
    }

    private function getDefaultChecklist($clearanceType)
    {
        $baseChecklist = [
            'room_inspection' => false,
            'key_return' => false,
            'damage_assessment' => false,
            'outstanding_fees' => false,
            'personal_belongings' => false
        ];

        switch ($clearanceType) {
            case 'graduation':
                $baseChecklist['academic_clearance'] = false;
                $baseChecklist['library_clearance'] = false;
                break;
            case 'transfer':
                $baseChecklist['transfer_documents'] = false;
                break;
            case 'disciplinary':
                $baseChecklist['disciplinary_review'] = false;
                break;
        }

        return $baseChecklist;
    }
}