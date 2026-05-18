<?php
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Broadcast;
// Cho phép API xác thực quyền WebSocket bằng token Sanctum
Broadcast::routes(['middleware' => ['auth:sanctum']]);
// Các API không cần đăng nhập
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);

// Các API yêu cầu đã đăng nhập (có token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'getUser']);
    // API dành cho Quản lý Nhãn (Labels)
    Route::apiResource('labels', LabelController::class);

    // API dành cho Quản lý Ghi chú (Notes)
    Route::apiResource('notes', NoteController::class);
    // Endpoint riêng để kiểm tra mật khẩu ghi chú (Yêu cầu nâng cao của đồ án)
    Route::post('/notes/{note}/verify-password', [NoteController::class, 'verifyPassword']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/preferences', [UserController::class, 'updatePreferences']);
    Route::put('/user/change-password', [UserController::class, 'changePassword']);
    // API Đặt/Đổi/Gỡ mật khẩu ghi chú (POST)
    Route::post('/notes/{id}/setup-password', [NoteController::class, 'setupPassword']);
    // API Mở khóa ghi chú (POST)
    Route::post('/notes/{id}/unlock', [NoteController::class, 'unlockNote']);
    // Luồng API xử lý Chia sẻ ghi chú
    Route::post('/notes/{id}/share', [NoteController::class, 'shareNote']);
    Route::put('/notes/{id}/share', [NoteController::class, 'updateShare']);
    Route::delete('/notes/{id}/share', [NoteController::class, 'revokeShare']);

    Route::post('/email/resend-verification', [AuthController::class, 'resendVerificationEmail']);
});

// Route xác thực tài khoản từ Gmail gửi lên
Route::post('/email/verify-api', function (Request $request) {
    $url = $request->input('url');
    
    // Tạo một Request giả lập để bóc tách các tham số từ URL mã hóa
    $fakeRequest = \Illuminate\Http\Request::create($url, 'GET');
    
    // Lấy chính xác ID và Hash từ chuỗi Query String (?id=...&hash=...)
    $userId = $fakeRequest->query('id');
    $userHash = $fakeRequest->query('hash');
    
    // Tìm người dùng trong Database
    $user = \App\Models\User::findOrFail($userId);
    
    // Kiểm tra xem chữ ký bảo mật của link có trùng khớp không 
    if (!hash_equals((string) $userHash, sha1($user->getEmailForVerification()))) {
        return response()->json(['success' => false, 'message' => 'Đường dẫn xác thực không hợp lệ hoặc đã hết hạn!'], 400);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['success' => true, 'message' => 'Tài khoản này đã được xác thực từ trước!']);
    }

    // Đánh dấu kích hoạt thành công vào MySQL
    $user->markEmailAsVerified();

    return response()->json(['success' => true, 'message' => 'Xác thực tài khoản thành công!']);
})->name('verification.verify'); 