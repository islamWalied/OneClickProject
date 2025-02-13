<?php

namespace App\Traits;
use App\Traits\ResponseTrait;
use App\Mail\VerifyMail;
use App\Mail\VerifyMailWithToken;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

trait EmailVerifyTrait
{
use ResponseTrait;
    /**
     * to use this you should follow these steps
     * check from the existence of these attributes
     * --> email_verified_at & verification_token & verification_token_expires_at & email
     * you should make sure of the existence of update method
     * also VerifyMail
     * check the existence of email_verify.blade.php
     * and also check that you passed the right name of constructor variable
     *
     * =============================================
     * and you should do this in your api route file
     * Route::get('email/verify/{token}',[AuthController::class, 'EmailVerify'])->name('email.verify');
     * =============================================
     * and this to your controller
     * public function EmailVerify($token)
     * {
     * $msg = $this->authService->EmailVerify($token);
     * return $this->success(__($msg),200);
     * }
     * =============================================
     * and in your service
     * public function EmailVerify($token)
     * {
     * $user = $this->verifyEmail($token,$this->user);
     * if ($user) {
     * return "Your email has been verified";
     * }
     * return "Invalid or expired token";
     * }
     * =============================================
     * and you do what in the repository and interface by yourself 😊
     **/



     public function sendVerificationEmail($request, $userModel)
    {
        $user = $userModel->getByEmail($request->email);
        if (!$user) {
            return $this->returnError(__('messages.verify.invalid_user'), 404);
        }

        try {
            $otp = mt_rand(1000, 9999);
            $user->verification_token = Hash::make($otp);
            $user->verification_token_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            Mail::to($user->email)->send(new VerifyMail($otp));

            return $this->success(__('messages.verify.verify_code_sent'), 200);
        } catch (\Exception $e) {
            Log::error("Error sending verification email: " . $e->getMessage());
            return $this->returnError(__('messages.verify.verify_code_error'), 500);
        }
    }

    // public function verifyEmail($request, $userModel)
    // {
    //     $user = $userModel->getByEmail($request->email);

    //     if (!$user) {
    //         $this->returnError(__('messages.verify.token_invalid'), 404);
    //     }

    //     $user->email_verified_at = Carbon::now();
    //     $user->is_verified = true;
    //     $user->verification_token = null;
    //     $user->verification_token_expires_at = null;
    //     $user->save();

    //     return $this->success(__('messages.verify.email_verified'), 200);
    // }

    public function verifyEmail($request, $userModel)
    {
        $user = $userModel->getByEmail($request->email);

        if (!$user) {
            $this->returnError(__('messages.verify.invalid_user'), 404);
        }

        if (!$user->verification_token || !Hash::check($request->verification_token, $user->verification_token)) {
            $this->returnError(__('messages.verify.token_invalid'), 400);
        }

        if (Carbon::now()->greaterThan($user->verification_token_expires_at)) {
            $this->returnError(__('messages.verify.token_invalid'), 400);
        }

        // OTP is valid, proceed to verify the email
        $user->email_verified_at = Carbon::now();
        $user->is_verified = true;
        $user->verification_token = null;
        $user->verification_token_expires_at = null;
        $user->save();

        return $this->success(__('messages.verify.email_verified'), 200);
    }











    public function sendVerificationEmailWithToken($user,$IName)
    {
        if (!$user->email_verified_at) {
            $otp = mt_rand(1000, 9999);
            $user->verification_token = Hash::make($otp);
            $user->verification_token_expires_at = Carbon::now()->addHours(24);
            $IName->update($user);
            Mail::to($user->email)->send(new VerifyMailWithToken($otp));
        }
    }
    public function validateEmailToken($request,$IName)
    {
        $pin = $request->verification_token;
        $user = $this->user->getByEmail($request->email);
        if (Hash::check($pin, $user->verification_token) && $user->verification_token != null && $user->verification_token_expires_at > now()) {
            $user->verification_token = null;
            $user->verification_token_expires_at = null;
            $IName->update($user);
            return true;
        } else
            throw new Exception(__("messages.email.invalid"));
    }
}
