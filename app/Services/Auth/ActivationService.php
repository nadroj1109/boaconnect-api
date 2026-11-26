<?php

namespace App\Services\Auth;

use App\Mail\AccountActivationMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivationService
{
    /**
     * Generate activation token.
     */
    public function generateToken(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Token expiration.
     */
    public function expiration(): Carbon
    {
        return now()->addDays(3);
    }

    /**
     * Send activation email.
     */
    public function sendActivation(User $user): void
    {
        if (!$this->isConnected()) {
            return;
        }

        Mail::to($user->email)->queue(
            new AccountActivationMail($user)
        );
    }

    /**
     * Verify activation token.
     */
    public function verify(string $token): User
    {
        $user = User::where(
            'activation_token',
            $token
        )->first();

        if (!$user) {
            $this->validationError('Activation link is invalid.');
        }

        if ($user->hasActivated()) {
            $this->validationError('This account has already been activated.');
        }

        if ($user->activationExpired()) {
            $this->validationError('Activation link has expired.');
        }

        return $user;
    }

    /**
     * Activate account.
     */
    public function activate(
        string $token,
        string $password
    ): User {

        return DB::transaction(function () use (
            $token,
            $password
        ) {

            $user = $this->verify($token);

            $user->update([

                'password' => Hash::make($password),

                'activation_token' => null,

                'activation_expires_at' => null,

                'activated_at' => now(),

                'email_verified_at' => now(),

                'is_active' => true,

            ]);

            return $user;
        });
    }

    /**
     * Resend activation.
     */
    public function resend(User $user): User
    {
        $user->update([

            'activation_token' => $this->generateToken(),

            'activation_expires_at' => $this->expiration(),

        ]);

        $this->sendActivation($user);

        return $user;
    }

    /**
     * Check internet connection.
     */
    protected function isConnected(): bool
    {
        try {

            return checkdnsrr("google.com");

        } catch (\Throwable $e) {

            return false;

        }
    }

    protected function validationError(string $message): never
    {
        throw ValidationException::withMessages([
            'token' => [$message],
        ]);
    }
}