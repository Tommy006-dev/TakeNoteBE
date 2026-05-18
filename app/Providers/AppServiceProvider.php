<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
            // Bẻ hướng link kích hoạt từ Gmail gửi về thẳng giao diện React
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $backendUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60), // Link có hiệu lực trong 60 phút
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Trỏ về trang xử lý kích hoạt trên React (ví dụ cổng 3000)
            return 'http://localhost:3000/verify-email?url=' . urlencode($backendUrl);
        });
    }
}
