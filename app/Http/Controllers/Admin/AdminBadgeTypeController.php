<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminBadgeTypeController extends Controller
{
    public function index()
    {
        $types = \App\Models\BadgeType::orderBy('id', 'desc')->get();
        return response()->json(['data' => $types]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:badge_types,code|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $badgeType = \App\Models\BadgeType::create($validated);
        return response()->json(['data' => $badgeType], 201);
    }

    public function update(Request $request, $id)
    {
        $badgeType = \App\Models\BadgeType::findOrFail($id);
        
        $validated = $request->validate([
            'code' => 'string|max:50|unique:badge_types,code,' . $badgeType->id,
            'name' => 'string|max:255',
            'description' => 'nullable|string',
        ]);

        $badgeType->update($validated);
        return response()->json(['data' => $badgeType]);
    }

    public function destroy($id)
    {
        $badgeType = \App\Models\BadgeType::findOrFail($id);
        
        // Optionally check if used in badges before delete
        if (\App\Models\Badge::where('type', $badgeType->code)->exists()) {
            return response()->json(['message' => 'Không thể xóa vì loại này đang được sử dụng.'], 400);
        }

        $badgeType->delete();
        return response()->json(['message' => 'Xóa thành công']);
    }
}
