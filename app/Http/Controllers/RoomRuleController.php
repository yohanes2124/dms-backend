<?php

namespace App\Http\Controllers;

use App\Models\RoomRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomRuleController extends Controller
{
    /**
     * Get all active room rules (public access for students)
     */
    public function index()
    {
        $rules = RoomRule::active()
            ->ordered()
            ->get()
            ->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    /**
     * Get all room rules including inactive (admin only)
     */
    public function adminIndex()
    {
        $rules = RoomRule::ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    /**
     * Get available categories
     */
    public function categories()
    {
        return response()->json([
            'success' => true,
            'data' => RoomRule::getCategories()
        ]);
    }

    /**
     * Create new room rule (admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'order_number' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rule = RoomRule::create([
            'category' => $request->category,
            'title' => $request->title,
            'description' => $request->description,
            'order_number' => $request->order_number ?? 0,
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Room rule created successfully',
            'data' => $rule
        ], 201);
    }

    /**
     * Get single room rule
     */
    public function show($id)
    {
        $rule = RoomRule::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rule
        ]);
    }

    /**
     * Update room rule (admin only)
     */
    public function update(Request $request, $id)
    {
        $rule = RoomRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category' => 'sometimes|string|max:100',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:2000',
            'order_number' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $rule->update($request->only([
            'category',
            'title',
            'description',
            'order_number',
            'is_active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Room rule updated successfully',
            'data' => $rule
        ]);
    }

    /**
     * Delete room rule (admin only)
     */
    public function destroy($id)
    {
        $rule = RoomRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room rule deleted successfully'
        ]);
    }

    /**
     * Toggle rule active status (admin only)
     */
    public function toggleActive($id)
    {
        $rule = RoomRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Rule status updated successfully',
            'data' => $rule
        ]);
    }

    /**
     * Reorder rules (admin only)
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rules' => 'required|array',
            'rules.*.id' => 'required|exists:room_rules,id',
            'rules.*.order_number' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->rules as $ruleData) {
            RoomRule::where('id', $ruleData['id'])
                ->update(['order_number' => $ruleData['order_number']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rules reordered successfully'
        ]);
    }
}
