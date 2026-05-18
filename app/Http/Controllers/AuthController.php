<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    // 1. Xử lý Đăng ký
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $activationToken = \Illuminate\Support\Str::random(60);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'activation_token' => $activationToken,
        ]);

        // Gửi email kích hoạt
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Exception $e) {}

        // Tự động đăng nhập sau khi đăng ký thành công (theo yêu cầu đồ án)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công! Vui lòng kiểm tra email để kích hoạt tài khoản.',
            'user' => $user,
            'token' => $token
        ], 201);
    }

    // 2. Xử lý Đăng nhập
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác'
            ], 401);
        }
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // 3. Xử lý Quên mật khẩu - Gửi mã OTP
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        // Tạo mã OTP ngẫu nhiên 6 số
        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $otp, 
                'created_at' => Carbon::now()
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Mã OTP đã được gửi đến email của bạn',
        ], 200);
    }

    // 4. Xác nhận OTP và Đặt lại mật khẩu mới
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|numeric',
            'password' => 'required|min:4'
        ]);

        // Kiểm tra xem email này có đang yêu cầu đổi mật khẩu không
        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || $record->token !== (string)$request->otp) {
            return response()->json(['success' => false, 'message' => 'Mã OTP không chính xác!'], 400);
        }

        // Kiểm tra xem mã OTP có bị quá hạn không (Ví dụ: 15 phút)
        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['success' => false, 'message' => 'Mã OTP đã hết hạn, vui lòng yêu cầu gửi lại!'], 400);
        }

        // Nếu mọi thứ OK -> Cập nhật mật khẩu mới cho User
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Xóa mã OTP khỏi bảng tạm sau khi dùng xong để bảo mật
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true, 
            'message' => 'Đổi mật khẩu thành công! Bạn có thể đăng nhập ngay.'
        ]);
    }

    // 5. Đăng xuất
    public function logout(Request $request)
    {
        // Xóa token hiện tại đang sử dụng
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công'
        ], 200);
    }
    
    // Lấy thông tin user hiện tại
    public function getUser(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ], 200);
    }
    
    // 6. Xử lý Kích hoạt tài khoản qua email
    public function verifyEmail($token)
    {
        // Tìm user có token trùng khớp
        $user = User::where('activation_token', $token)->first();

        // Nếu không tìm thấy user tương ứng với token đó
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Đường dẫn kích hoạt không hợp lệ hoặc đã từng được sử dụng.'
            ], 400);
        }

        // Tiến hành kích hoạt
        $user->email_verified_at = now();   
        $user->activation_token = null;     
        $user->save();

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        return redirect($frontendUrl . '/login?activated=true');
    }
    
    // 7. Xử lý Gửi lại email kích hoạt
    public function resendVerificationEmail(Request $request)
    {
        $user = $request->user();

        // 1. Kiểm tra bằng hàm chuẩn của Laravel xem đã kích hoạt chưa
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã được kích hoạt rồi.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi lại email kích hoạt thành công. Vui lòng kiểm tra hộp thư Gmail!'
        ], 200);
    }
    // 8. API Kiểm tra xem email đã được xác thực chưa (Dành cho React gọi khi người dùng bấm "Gửi lại" ở HomePage)
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ], [
            'email.exists' => 'Email này chưa được đăng ký trong hệ thống!'
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();
        if ($user->email_verified_at === null) {
            return response()->json([
                'success' => false, 
                'message' => 'Tài khoản này chưa xác thực Email. Vui lòng xác thực trước khi khôi phục mật khẩu!'
            ], 400);
        }
        // Tạo mã OTP ngẫu nhiên 6 số
        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => $otp, 'created_at' => Carbon::now()]
        );

        Mail::raw("Mã OTP khôi phục mật khẩu của bạn là: $otp \n\nMã này sẽ hết hạn sau 15 phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.", function ($message) use ($request) {
            $message->to($request->email)->subject('Mã OTP khôi phục mật khẩu - TakeNote');
        });

        return response()->json([
            'success' => true, 
            'message' => 'Mã OTP đã được gửi đến email của bạn!'
        ]);
    }
}
