<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResidentRegistrationResubmissionRequest extends ResidentRegistrationRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Resubmission does not require another OTP or password.
        unset(
            $rules['verification_token'],
            $rules['password']
        );

        $rules['email_address'] = ['prohibited'];

        return $rules;
    }
}
