<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function index(Request $request)
    {
        $labels = $request->user()->labels()->orderBy('name', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $labels
        ], 200);
    }

    // 1. Tạo nhãn mới
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        // Kiểm tra trùng tên nhãn đối với riêng User này
        $exists = $request->user()->labels()->where('name', $request->name)->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Tên nhãn này đã tồn tại.'
            ], 422);
        }

        $label = $request->user()->labels()->create([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo nhãn thành công',
            'data' => $label
        ], 201);
    }

    // 2. Sửa tên nhãn
    public function update(Request $request, Label $label)
    {
        if ($label->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $exists = $request->user()->labels()
            ->where('name', $request->name)
            ->where('id', '!=', $label->id)
            ->exists();
            
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Tên nhãn này đã tồn tại.'
            ], 422);
        }

        $label->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật nhãn thành công',
            'data' => $label
        ], 200);
    }

    // 3. Xóa nhãn
    public function destroy(Request $request, Label $label)
    {
        if ($label->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Không có quyền truy cập'], 403);
        }

        $label->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa nhãn thành công'
        ], 200);
    }
}