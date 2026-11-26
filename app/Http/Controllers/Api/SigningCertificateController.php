<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SigningCertificate\StoreSigningCertificateRequest;
use App\Http\Requests\SigningCertificate\RejectSigningCertificateRequest;
use App\Models\UserSigningCertificate;
use App\Services\SigningCertificate\SigningCertificateService;
use App\Services\SigningCertificate\SigningCertificateValidationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SigningCertificateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SigningCertificateService $certificate,
        protected SigningCertificateValidationService $validation,
    ) {
    }

    /**
     * Register the authenticated user's PNPKI certificate.
     */
    public function store(
        StoreSigningCertificateRequest $request
    ) {
        $certificate = $this->certificate->register(
            user: $request->user(),
            file: $request->file('certificate'),
        );

        return $this->success(
            [
                'id' => $certificate->id,
                'subject_dn' => $certificate->subject_dn,
                'issuer_dn' => $certificate->issuer_dn,
                'serial_number' => $certificate->serial_number,
                'fingerprint_sha256' => $certificate->fingerprint_sha256,
                'valid_from' => $certificate->valid_from,
                'valid_until' => $certificate->valid_until,
                'status' => $certificate->status,
            ],
            'Certificate submitted for verification.'
        );
    }

    /**
     * Display the authenticated user's registered certificates.
     */
    public function index(
        Request $request
    ) {
        $certificates = $this->certificate->getForUser(
            user: $request->user(),
        );

        return $this->success(
            $certificates->map(fn ($certificate) => [
                'id' =>
                    $certificate->id,

                'subject_dn' =>
                    $certificate->subject_dn,

                'issuer_dn' =>
                    $certificate->issuer_dn,

                'serial_number' =>
                    $certificate->serial_number,

                'fingerprint_sha256' =>
                    $certificate->fingerprint_sha256,

                'valid_from' =>
                    $certificate->valid_from,

                'valid_until' =>
                    $certificate->valid_until,

                'status' =>
                    $certificate->status,

                'verified_at' =>
                    $certificate->verified_at,

                'revoked_at' =>
                    $certificate->revoked_at,

                'created_at' =>
                    $certificate->created_at,
            ])->values(),
            'Signing certificates retrieved successfully.'
        );
    }

    /**
     * Display all registered certificates for administrative review.
     */
    public function adminIndex(Request $request)
    {
        $status = $request->query('status');

        if (
            $status !== null &&
            ! in_array($status, ['pending', 'active', 'rejected', 'revoked'], true)
        ) {
            return $this->error(
                'Invalid certificate status.',
                422
            );
        }

        $certificates = $this->certificate->getForAdministration(
            status: $status,
        );

        return $this->success(
            $certificates->map(fn ($certificate) => [
                'id' => $certificate->id,
                'user_id' => $certificate->user_id,
                'user_name' => $certificate->user?->name,
                'resident_name' => $certificate->user?->resident?->fullName,
                'subject_dn' => $certificate->subject_dn,
                'issuer_dn' => $certificate->issuer_dn,
                'serial_number' => $certificate->serial_number,
                'fingerprint_sha256' => $certificate->fingerprint_sha256,
                'valid_from' => $certificate->valid_from?->format('m/d/Y h:i:s A'),
                'valid_until' => $certificate->valid_until?->format('m/d/Y h:i:s A'),
                'status' => $certificate->status,
                'rejection_reason' => $certificate->rejection_reason,
                'created_at' => $certificate->created_at?->format('m/d/Y h:i:s A'),
            ])->values(),
            'Signing certificates retrieved successfully.'
        );
    }

    /**
     * Display certificate details and validation results for administrators.
     */
    public function adminShow(UserSigningCertificate $certificate)
    {
        $certificate->load([
            'user.resident',
            'verifier',
        ]);

        $validation = $this->validation->validate($certificate);

        return $this->success(
            [
                'certificate' => [
                    'id' => $certificate->id,
                    'user_id' => $certificate->user_id,
                    'user_name' => $certificate->user?->name,
                    'resident_name' => $certificate->user?->resident?->fullName,
                    'subject_dn' => $certificate->subject_dn,
                    'issuer_dn' => $certificate->issuer_dn,
                    'serial_number' => $certificate->serial_number,
                    'fingerprint_sha256' => $certificate->fingerprint_sha256,
                    'valid_from' => $certificate->valid_from,
                    'valid_until' => $certificate->valid_until,
                    'status' => $certificate->status,
                    'rejection_reason' => $certificate->rejection_reason,
                    'verified_by' => $certificate->verifier?->name,
                    'verified_at' => $certificate->verified_at,
                    'created_at' => $certificate->created_at,
                ],

                'validation' => $validation,
            ],
            'Signing certificate details retrieved successfully.'
        );
    }

    /**
     * Approve and activate a verified signing certificate.
     */
    public function approve(
        Request $request,
        UserSigningCertificate $certificate
    ) {
        $certificate = $this->certificate->approve(
            certificate: $certificate,
            reviewer: $request->user(),
        );

        return $this->success(
            [
                'id' => $certificate->id,
                'status' => $certificate->status,
                'verified_by' => $certificate->verified_by,
                'verified_at' => $certificate->verified_at,
            ],
            'Signing certificate approved and activated successfully.'
        );
    }

    /**
     * Reject a submitted signing certificate.
     */
    public function reject(
        RejectSigningCertificateRequest $request,
        UserSigningCertificate $certificate
    ) {
        $certificate = $this->certificate->reject(
            certificate: $certificate,
            reviewer: $request->user(),
            reason: $request->validated('reason'),
        );

        return $this->success(
            [
                'id' => $certificate->id,
                'status' => $certificate->status,
                'rejection_reason' => $certificate->rejection_reason,
            ],
            'Signing certificate rejected successfully.'
        );
    }
}
