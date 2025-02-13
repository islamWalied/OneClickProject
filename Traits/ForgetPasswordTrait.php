<?php

namespace App\Traits;

use App\Mail\OTPMail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

trait ForgetPasswordTrait
{
    use ResponseTrait;
    /**
     * to use this you should follow these steps
     * check from the existence of these attributes
     * --> OTP & otp_valid_until & password & password_confirmation & email
     * you should make sure of the existence of update & getByEmail methods
     * also OTPMail
     * check the existence of forget_password.blade.php
     * also check that you passed the right name of constructor variable
     * also make sure of the token property
     *
     * ==============================================
     *
     * and other function and things you should do it
     * you should right these in your api route file
     * Route::post("/resetPassword", [AuthController::class, "resetPassword"]);
     * Route::post("/checkOTP", [AuthController::class, "checkOTP"]);
     * Route::post("/updatePassword", [AuthController::class, "updatePassword"]);
     *
     * ============================================================
     *  and pass it to your controller like that
     * public function resetPassword(ResetPasswordRequest $request)
     * {
     * $this->authService->resetPasswordOTP($request);
     * return $this->success(__("messages.Email Sent Successfully"),200);
     * }
     * public function checkOTP(ValidateOTPRequest $request)
     * {
     * $this->authService->checkOTP($request);
     * return $this->success(__("messages.Go to change your password now"),200);
     * }
     * public function updatePassword(PasswordUpdateOTPRequest $request)
     * {
     * $this->authService->updatePass($request);
     * return $this->success(__("messages.Password Updated"),200);
     * }
     *
     * ============================================
     * and in last you make this in your service
     *
     * public function resetPasswordOTP($request): void
     * {
     * $this->resetPassword($request,$this->user);
     * }
     * public function checkOTP($request): void
     * {
     * $this->validateOTP($request,$this->user);
     * }
     * public function updatePass($request): void
     * {
     * $this->updatePassword($request,$this->user);
     * }
     *  =============================================
     *  and you do what in the repository and interface by yourself 😊
     **/
    public function resetPassword($request, $userModel)
    {
        $user = $userModel->getByEmail($request->email);

        if (!$user) {
            $this->returnError(__('messages.password.invalid_token'), 404);
        }

        try {
            $otp = mt_rand(1000, 9999);

            $user->OTP = Hash::make($otp);

            $user->otp_valid_until = Carbon::now()->addMinutes(10);

            $user->save();

            Mail::to($user->email)->send(new OTPMail($otp));

            Log::info("success Message for passowrd reset " . $this->success(__('messages.password.otp_sent_success'), 200));

            return $this->success(__('messages.password.otp_sent_success'), 200);

        } catch (\Exception $e) {
            Log::info("Error Message for passowrd reset " . $e);

            $this->returnError(__('messages.password.otp_sent_error'), 500);
        }
    }


    






    public function validateOTP($request,$IName): void
    {
        $pin = $request->token;
        $user = $this->user->getByEmail($request->email);
        if (Hash::check($pin, $user->OTP) && $user->otp_valid_until > now()) {
            $user->OTP = null;
            $user->otp_valid_until = null;
            $IName->update($user);
        } else
        throw new Exception(__("messages.password.invalid_token"));    }



    // public function updatePassword($request, $userModel)
    // {
    //     if ($request->password !== $request->password_confirmation) {
    //         $this->returnError(__('messages.password.password_mismatch'), 400);
    //     }

    //     $user = $userModel->getByEmail($request->email);

    //     if (!$user) {
    //         $this->returnError(__('messages.password.email_not_found'), 404);
    //     }

    //     // Update password if validation passes
    //     $user->password = Hash::make($request->password);
    //     $userModel->update($user);

    //     return $this->success(__('messages.password.updated'), 200);
    // }

    public function updatePassword($request, $userModel)
    {

        $user = $userModel->getByEmail($request->email);
    
        if (!$user) {
            return $this->returnError(__('messages.password.email_not_found'), 404);
        }
    
     
    
        // OTP is valid, update the password
        $user->password = Hash::make($request->password);
    

        $user->save();
    
        return $this->success(__('messages.password.updated'), 200);
    }
    




}
