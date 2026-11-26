<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use App\Enums\VerificationChannel;
use App\Enums\VerificationPurpose;

class SendVerificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $recipient = trim((string) $this->input('recipient'));

        $channel = VerificationChannel::tryFrom(
            (string) $this->input('channel')
        );

        if ($channel === VerificationChannel::EMAIL) {

            $recipient = strtolower($recipient);

        }

        if ($channel === VerificationChannel::SMS) {

            $recipient = preg_replace('/\s+/', '', $recipient);

            if (preg_match('/^09\d{9}$/', $recipient)) {

                $recipient = '+63' . substr($recipient, 1);

            }

        }

        $this->merge([
            'recipient' => $recipient,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'recipient' => [

                'required',

                'string',

                'max:255',

            ],

            'channel' => [

                'required',

                Rule::enum(
                    VerificationChannel::class
                ),

            ],

            'purpose' => [

                'required',

                Rule::enum(
                    VerificationPurpose::class
                ),

            ],

        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [

            function ($validator) {

                $channel = VerificationChannel::tryFrom(
                    $this->channel
                );

                if (! $channel) {
                    return;
                }

                if (

                    $channel === VerificationChannel::EMAIL

                    &&

                    ! filter_var(
                        $this->recipient,
                        FILTER_VALIDATE_EMAIL
                    )

                ) {

                    $validator->errors()->add(

                        'recipient',

                        'Please enter a valid email address.'

                    );

                }

                if (

                    $channel === VerificationChannel::SMS

                    &&

                    ! preg_match(
                        '/^\+639\d{9}$/',
                        $this->recipient
                    )

                ) {

                    $validator->errors()->add(

                        'recipient',

                        'Please enter a valid Philippine mobile number.'

                    );

                }

            }

        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [

            'recipient.required' => 'Recipient is required.',

            'channel.required' => 'Verification channel is required.',

            'channel.enum' => 'Invalid verification channel.',

            'purpose.required' => 'Verification purpose is required.',

            'purpose.enum' => 'Invalid verification purpose.',

        ];
    }

    /**
     * Custom attribute names.
     */
    public function attributes(): array
    {
        return [

            'recipient' => 'email address or mobile number',

        ];
    }
}
