<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 1. Cập nhật thông tin cá nhân (Tên)
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'nullable|string', // base64 image string
        ]);

        $user = $request->user();
        $user->name = $request->name;

        if ($request->has('avatar')) {
            $user->avatar = $request->avatar; // null or base64 string
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công',
            'user' => $user
        ], 200);
    }

    // 2. Cập nhật cài đặt giao diện (User Preferences)
    public function updatePreferences(Request $request)
    {
        $user = $request->user();
        
        // Cập nhật các trường cấu hình nếu có gửi lên
        if ($request->has('theme')) $user->theme = $request->theme;
        if ($request->has('default_note_color')) $user->default_note_color = $request->default_note_color;
        if ($request->has('font_style')) $user->font_style = $request->font_style;
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu cài đặt giao diện',
            'user' => $user
        ], 200);
    }

    // 3. Đổi mật khẩu
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không chính xác'
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công'
        ], 200);
    }
}