<?php

namespace App\Services\SigningCertificate;

use App\Models\User;
use App\Models\UserSigningCertificate;
use App\Services\SigningCertificate\SigningCertificateValidationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SigningCertificateService
{
    public function __construct(
        protected SigningCertificateValidationService $validation,
    ) {
    }

    public function register(
        User $user,
        UploadedFile $file
    ): UserSigningCertificate {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw ValidationException::withMessages([
                'certificate' => 'Unable to read the uploaded certificate.',
            ]);
        }

        $certificate = openssl_x509_read($contents);

        // Support DER-encoded certificates.
        if ($certificate === false) {
            $pem = "-----BEGIN CERTIFICATE-----\n"
                . chunk_split(base64_encode($contents), 64, "\n")
                . "-----END CERTIFICATE-----\n";

            $certificate = openssl_x509_read($pem);
        }

        if ($certificate === false) {
            throw ValidationException::withMessages([
                'certificate' => 'The uploaded file is not a valid X.509 certificate.',
            ]);
        }

        $details = openssl_x509_parse($certificate);

        if (! $details) {
            throw ValidationException::withMessages([
                'certificate' => 'Unable to read certificate details.',
            ]);
        }

        $fingerprint = strtolower(
            openssl_x509_fingerprint($certificate, 'sha256')
        );

        if (! $fingerprint) {
            throw ValidationException::withMessages([
                'certificate' => 'Unable to calculate certificate fingerprint.',
            ]);
        }

        if (
            UserSigningCertificate::where(
                'fingerprint_sha256',
                $fingerprint
            )->exists()
        ) {
            throw ValidationException::withMessages([
                'certificate' => 'This certificate has already been registered.',
            ]);
        }

        $validFrom = $details['validFrom_time_t'] ?? null;
        $validUntil = $details['validTo_time_t'] ?? null;

        if (! $validFrom || ! $validUntil) {
            throw ValidationException::withMessages([
                'certificate' => 'Certificate validity dates are missing.',
            ]);
        }

        if (
            now()->timestamp < $validFrom ||
            now()->timestamp > $validUntil
        ) {
            throw ValidationException::withMessages([
                'certificate' => 'The certificate is not currently valid.',
            ]);
        }

        $subject = $this->formatDistinguishedName(
            $details['subject'] ?? []
        );

        $issuer = $this->formatDistinguishedName(
            $details['issuer'] ?? []
        );

        $serialNumber = $details['serialNumberHex']
            ?? $details['serialNumber']
            ?? null;

        if (! $subject || ! $issuer || ! $serialNumber) {
            throw ValidationException::withMessages([
                'certificate' => 'Required certificate information is missing.',
            ]);
        }

        $path = 'signing-certificates/'
            . $user->id . '/'
            . Str::uuid() . '.cer';

        Storage::disk('local')->put($path, $contents);

        try {
            return UserSigningCertificate::create([
                'user_id' => $user->id,
                'certificate_path' => $path,
                'subject_dn' => $subject,
                'issuer_dn' => $issuer,
                'serial_number' => (string) $serialNumber,
                'fingerprint_sha256' => $fingerprint,
                'valid_from' => date('Y-m-d H:i:s', $validFrom),
                'valid_until' => date('Y-m-d H:i:s', $validUntil),
                'status' => 'pending',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function formatDistinguishedName(array $values): string
    {
        $parts = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = implode(' ', $value);
            }

            $parts[] = $key . '=' . $value;
        }

        return implode(', ', $parts);
    }

    /**
     * Get the authenticated user's registered certificates.
     */
    public function getForUser(
        User $user
    ): Collection {
        return $user->signingCertificates()
            ->latest()
            ->get();
    }

    /**
     * Get registered certificates for administrative review.
     */
    public function getForAdministration(
        ?string $status = null
    ): Collection {
        $query = UserSigningCertificate::with([
            'user.resident',
            'verifier',
        ])->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Get a certificate for administrative inspection.
     */
    public function getForReview(
        UserSigningCertificate $certificate
    ): UserSigningCertificate {
        return $certificate->load([
            'user.resident',
            'verifier',
        ]);
    }

    public function approve(
        UserSigningCertificate $certificate,
        User $reviewer
    ): UserSigningCertificate {

        return DB::transaction(function () use (
            $certificate,
            $reviewer
        ) {

            $certificate = UserSigningCertificate::query()
                ->lockForUpdate()
                ->findOrFail($certificate->id);

            if ($certificate->status !== 'pending') {
                throw ValidationException::withMessages([
                    'certificate' => 'Only pending certificates can be approved.',
                ]);
            }

            if ($certificate->user_id === $reviewer->id) {
                throw ValidationException::withMessages([
                    'certificate' => 'You cannot approve your own certificate.',
                ]);
            }

            // Revalidate while processing the approval.
            $validation = $this->validation->validate($certificate);

            if (($validation['can_activate'] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'certificate' => $validation['activation_message']
                        ?? 'The certificate has not passed all required verification checks.',
                ]);
            }

            $certificate->update([
                'status' => 'active',
                'verified_by' => $reviewer->id,
                'verified_at' => now(),
                'rejection_reason' => null,
                'revoked_at' => null,
            ]);

            return $certificate->fresh([
                'user.resident',
                'verifier',
            ]);

        });

    }

    /**
     * Reject a submitted signing certificate.
     */
    public function reject(
        UserSigningCertificate $certificate,
        User $reviewer,
        string $reason
    ): UserSigningCertificate {
        return DB::transaction(function () use (
            $certificate,
            $reviewer,
            $reason
        ) {
            $certificate = UserSigningCertificate::query()
                ->lockForUpdate()
                ->findOrFail($certificate->id);

            if ($certificate->status !== 'pending') {
                throw ValidationException::withMessages([
                    'certificate' => 'Only pending certificates can be rejected.',
                ]);
            }

            if ($certificate->user_id === $reviewer->id) {
                throw ValidationException::withMessages([
                    'certificate' => 'You cannot review your own certificate.',
                ]);
            }

            $certificate->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            return $certificate->fresh([
                'user.resident',
                'verifier',
            ]);
        });
    }
}
