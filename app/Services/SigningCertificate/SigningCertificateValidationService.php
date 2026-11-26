<?php

namespace App\Services\SigningCertificate;

use App\Models\UserSigningCertificate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SigningCertificateValidationService
{
    /**
     * Perform all currently available certificate checks.
     *
     * This does not establish PNPKI trust or revocation status.
     * A successful result here must not automatically activate
     * a certificate.
     */
    public function validate(
        UserSigningCertificate $certificate
    ): array {
        $disk = Storage::disk('local');

        if (! $disk->exists($certificate->certificate_path)) {
            throw new RuntimeException(
                'The stored certificate file could not be found.'
            );
        }

        $contents = $disk->get($certificate->certificate_path);

        $x509 = $this->readCertificate($contents);

        if ($x509 === false) {
            return $this->failedResult(
                'The stored file is not a valid X.509 certificate.'
            );
        }

        $details = openssl_x509_parse($x509);

        if (! $details) {
            return $this->failedResult(
                'Unable to parse certificate details.'
            );
        }

        $identity = $this->validateIdentity(
            user: $certificate->user()->with('resident')->first(),
            subject: $details['subject'] ?? [],
        );

        $fingerprint = strtolower(
            openssl_x509_fingerprint($x509, 'sha256')
        );

        $now = now()->timestamp;

        $validFrom = $details['validFrom_time_t'] ?? null;
        $validUntil = $details['validTo_time_t'] ?? null;

        $dateValid = $validFrom !== null
            && $validUntil !== null
            && $now >= $validFrom
            && $now <= $validUntil;

        /*
         * Key Usage
         *
         * A PDF signing certificate should permit digital signatures.
         * Some certificates may also use nonRepudiation
         * (contentCommitment).
         */
        $keyUsage = $details['extensions']['keyUsage'] ?? null;

        $keyUsageValues = $keyUsage
            ? array_map('trim', explode(',', $keyUsage))
            : [];

        $allowsDigitalSignature = in_array(
            'Digital Signature',
            $keyUsageValues,
            true
        ) || in_array(
            'Non Repudiation',
            $keyUsageValues,
            true
        );

        /*
         * Extended Key Usage is reported for inspection.
         *
         * We are deliberately not enforcing a specific EKU OID yet.
         * We need an actual PNPKI certificate to determine the
         * appropriate policy for PDF document signing.
         */
        $extendedKeyUsage = $details['extensions']['extendedKeyUsage']
            ?? null;

        $fingerprintMatches = hash_equals(
            strtolower($certificate->fingerprint_sha256),
            $fingerprint
        );

        $pnpkiValidation = $this->validatePnpkiCertificate($certificate);

        $trustChain = $pnpkiValidation['trust_chain'] ?? [
            'passed' => false,
            'message' => 'PNPKI trust-chain validation was not completed.',
        ];

        $revocation = $pnpkiValidation['revocation'] ?? [
            'passed' => false,
            'message' => 'Certificate revocation validation was not completed.',
        ];

        $checks = [
            'certificate_format' => [
                'passed' => true,
                'message' => 'Valid X.509 certificate.',
            ],

            'fingerprint' => [
                'passed' => $fingerprintMatches,
                'message' => $fingerprintMatches
                    ? 'Certificate fingerprint matches the registered record.'
                    : 'Certificate fingerprint does not match the registered record.',
            ],

            'validity_period' => [
                'passed' => $dateValid,
                'message' => $dateValid
                    ? 'Certificate is currently within its validity period.'
                    : 'Certificate is expired, not yet valid, or has missing validity dates.',
            ],

            'key_usage' => [
                'passed' => $allowsDigitalSignature,
                'message' => $allowsDigitalSignature
                    ? 'Certificate permits digital signatures.'
                    : 'Certificate does not explicitly permit digital signatures.',
            ],

            'extended_key_usage' => [
                'passed' => null,
                'message' => $extendedKeyUsage
                    ? 'Extended Key Usage found; requires PNPKI policy review.'
                    : 'No Extended Key Usage extension found.',
                'value' => $extendedKeyUsage,
            ],

            'trust_chain' => $trustChain,

            'revocation' => $revocation,

            'identity' => $identity,
        ];

        $basicChecksPassed = $fingerprintMatches
            && $dateValid
            && $allowsDigitalSignature;

        $identityPassed = ($identity['passed'] ?? false) === true;

        $trustChainPassed =
            ($checks['trust_chain']['passed'] ?? null) === true;

        $revocationPassed =
            ($checks['revocation']['passed'] ?? null) === true;

        $canActivate = $basicChecksPassed
            && $identityPassed
            && $trustChainPassed
            && $revocationPassed;

        $activationBlockers = [];

        if (! $fingerprintMatches) {
            $activationBlockers[] = 'Certificate fingerprint verification failed.';
        }

        if (! $dateValid) {
            $activationBlockers[] = 'Certificate validity check failed.';
        }

        if (! $allowsDigitalSignature) {
            $activationBlockers[] = 'Certificate does not permit digital signatures.';
        }

        if (! $identityPassed) {
            $activationBlockers[] = $identity['message']
                ?? 'Certificate identity verification failed.';
        }

        if (! $trustChainPassed) {
            $activationBlockers[] = 'PNPKI trust-chain verification has not passed.';
        }

        if (! $revocationPassed) {
            $activationBlockers[] = 'Certificate revocation verification has not passed.';
        }

        return [
            'certificate_id' => $certificate->id,
            'subject_dn' => $certificate->subject_dn,
            'issuer_dn' => $certificate->issuer_dn,
            'serial_number' => $certificate->serial_number,
            'fingerprint_sha256' => $fingerprint,
            'valid_from' => $validFrom
                ? now()->setTimestamp($validFrom)->toDateTimeString()
                : null,
            'valid_until' => $validUntil
                ? now()->setTimestamp($validUntil)->toDateTimeString()
                : null,
            'checks' => $checks,
            'basic_checks_passed' => $basicChecksPassed,
            'can_activate' => $canActivate,

            'activation_message' => $canActivate
                ? 'All required certificate checks passed. The certificate can be activated.'
                : implode(' ', $activationBlockers),

            'activation_blockers' => $activationBlockers,
        ];
    }

    /**
     * Validate the PNPKI certificate trust chain and revocation status.
     */
    private function validatePnpkiCertificate(
        UserSigningCertificate $certificate
    ): array {
        $disk = Storage::disk('local');

        $environment = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT');
            $windowsDir = getenv('WINDIR') ?: $systemRoot;

            if ($systemRoot) {
                $environment['SystemRoot'] = $systemRoot;
            }

            if ($windowsDir) {
                $environment['WINDIR'] = $windowsDir;
            }
        }

        $process = new Process(
            [
                config('pnpki.python_binary'),
                base_path('scripts/validate_pnpki_certificate.py'),
                '--registered-cert',
                $disk->path($certificate->certificate_path),
                '--trust-dir',
                config('pnpki.trust_directory'),
                '--cert-dir',
                config('pnpki.certificates_directory'),
            ],
            base_path(),
            $environment
        );

        $process->setTimeout(
            config('pnpki.verification.timeout', 60)
        );

        Log::debug('PNPKI web process environment', [
            'php_sapi' => PHP_SAPI,
            'systemroot' => getenv('SYSTEMROOT'),
            'windir' => getenv('WINDIR'),
            'path' => getenv('PATH'),
            'temp' => getenv('TEMP'),
            'tmp' => getenv('TMP'),
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            $result = json_decode($process->getOutput(), true);

            $message = $result['message']
                ?? trim($process->getErrorOutput())
                ?: 'PNPKI certificate validation could not be completed.';

            return [
                'valid' => false,
                'trust_chain' => $result['trust_chain'] ?? [
                    'passed' => false,
                    'message' => $message,
                ],
                'revocation' => $result['revocation'] ?? [
                    'passed' => false,
                    'message' => $message,
                ],
                'message' => $message,
            ];
        }

        $result = json_decode($process->getOutput(), true);

        if (! is_array($result) || ! ($result['valid'] ?? false)) {
            return [
                'passed' => false,
                'message' => $result['message']
                    ?? 'Invalid response from PNPKI certificate validator.',
            ];
        }

        return $result;
    }

    /**
     * Read PEM or DER-encoded X.509 certificate.
     */
    private function readCertificate(string $contents)
    {
        $certificate = openssl_x509_read($contents);

        if ($certificate !== false) {
            return $certificate;
        }

        $pem = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($contents), 64, "\n")
            . "-----END CERTIFICATE-----\n";

        return openssl_x509_read($pem);
    }

    private function failedResult(string $message): array
    {
        return [
            'basic_checks_passed' => false,
            'can_activate' => false,
            'activation_message' => $message,
            'checks' => [
                'certificate_format' => [
                    'passed' => false,
                    'message' => $message,
                ],
            ],
        ];
    }
    
    /**
     * Verify that the certificate belongs to an active barangay signatory.
     */
    private function validateIdentity(
        ?User $user,
        array $subject
    ): array {
        if (! $user || ! $user->resident) {
            return [
                'passed' => false,
                'message' => 'The certificate owner has no linked resident record.',
            ];
        }

        $resident = $user->resident;

        $commonName = $subject['CN'] ?? null;

        if (! is_string($commonName) || trim($commonName) === '') {
            return [
                'passed' => false,
                'message' => 'The certificate does not contain a usable Common Name.',
            ];
        }

        /*
         * Compare certificate CN against the resident's name components.
         *
         * Supports:
         * - Given Middle Surname
         * - Surname Given Middle
         *
         * All name components must match.
         */
        $certificateName = $this->normalizeName($commonName);

        $givenName = $this->normalizeName($resident->first_name ?? '');
        $middleName = $this->normalizeName($resident->middle_name ?? '');
        $surname = $this->normalizeName($resident->last_name ?? '');

        $residentNameOrders = [
            $givenName . $middleName . $surname,
            $surname . $givenName . $middleName,
        ];

        if (! empty($resident->ext_name)) {
            $extension = $this->normalizeName($resident->ext_name);

            $residentNameOrders = array_merge(
                $residentNameOrders,
                [
                    $givenName . $middleName . $surname . $extension,
                    $surname . $givenName . $middleName . $extension,
                ]
            );
        }

        $nameMatches = $certificateName !== ''
            && in_array($certificateName, $residentNameOrders, true);

        if (! $nameMatches) {
            return [
                'passed' => false,
                'message' => 'The certificate Common Name does not match the linked resident name.',
                'certificate_name' => $commonName,
                'resident_name' => $resident->fullName,
            ];
        }

        // Check the resident's active official appointments.
        $officials = $resident->barangayOfficials()
            ->with('position')
            ->where('barangay_id', $resident->barangay_id)
            ->where('is_active', true)
            ->whereDate('term_start', '<=', today())
            ->where(function ($query) {
                $query->whereNull('term_end')
                    ->orWhereDate('term_end', '>=', today());
            })
            ->get();

        $eligibleOfficial = $officials->first(function ($official) {
            $position = mb_strtolower(
                trim($official->position?->name ?? '')
            );

            return in_array($position, [
                'chairperson',
                'barangay chairperson',
                'punong barangay',
                'secretary',
                'barangay secretary',
            ], true);
        });

        if (! $eligibleOfficial) {
            return [
                'passed' => false,
                'message' => 'The certificate owner does not have an active Chairperson or Secretary appointment.',
                'certificate_name' => $commonName,
                'resident_name' => $resident->fullName,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Certificate identity matches an active barangay signatory.',
            'certificate_name' => $commonName,
            'resident_name' => $resident->fullName,
            'barangay_id' => $eligibleOfficial->barangay_id,
            'official_position' => $eligibleOfficial->position?->name,
            'official_id' => $eligibleOfficial->id,
        ];
    }

    /**
     * Normalize a person's name for comparison.
     */
    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return preg_replace('/[^\pL\pN]/u', '', $name) ?? '';
    }
}
