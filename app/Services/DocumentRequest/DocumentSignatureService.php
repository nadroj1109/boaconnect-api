<?php

namespace App\Services\DocumentRequest;

use App\Constants\Roles;
use App\Enums\DocumentRequestStatus;
use App\Jobs\CreateDocumentBlockchainAnchor;

use App\Models\DocumentFile;
use App\Models\DocumentRequest;
use App\Models\User;

use App\Models\UserSigningCertificate;
use App\Services\SigningCertificate\PnpkiPdfVerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentSignatureService
{
    public function __construct(
        protected DocumentGenerationService $generation,
        protected DocumentVerificationService $verification,
        protected DocumentQrCodeService $qrCode,
        protected DocumentAuthorizationService $authorization,
        protected PnpkiPdfVerificationService $pnpkiVerifier,
        protected DocumentNumberService $documentNumberService,
    ) {
    }

    /**
     * Generate, sign, and finalize a document request.
     */
    public function sign(
        DocumentRequest $request,
        User $user,
    ): DocumentFile {

        $this->authorizeSigner($user);

        $file = DB::transaction(
            function () use (
                $request,
                $user,
            ) {

                $request =
                    $this->lockRequest(
                        $request
                    );

                $this->validateRequest(
                    $request
                );

                $this->validateSignature(
                    $user
                );

                $request->loadMissing([
                    'documentType',
                    'resident',
                    'requestedFor',
                    'barangay',
                ]);

                $this->documentNumberService->ensure($request);

                /*
                |--------------------------------------------------------------------------
                | Mark as generating
                |--------------------------------------------------------------------------
                */

                $request->markGenerating();
                $request->save();

                /*
                |--------------------------------------------------------------------------
                | Generate final signed PDF
                |--------------------------------------------------------------------------
                |
                | The Blade template receives the Chairperson's
                | signature as a base64 image.
                |
                */

                $fileUuid = $this->getVerificationUuid($request);

                $verificationUrl =
                    $this->verification->url(
                        $fileUuid
                    );

                $verificationQr =
                    $this->qrCode->generate(
                        $verificationUrl
                    );

                $signedPdf =
                    $this->generation->generate(
                        request: $request,
                        signer: $user,
                        verificationQr: $verificationQr,
                    );
                
                $request->markGenerated($user);
                $request->sign($user);

                /*
                |--------------------------------------------------------------------------
                | Store final document
                |--------------------------------------------------------------------------
                */

                $file =
                    $this->storeDocument(
                        request: $request,
                        user: $user,
                        contents: $signedPdf,
                        uuid: $fileUuid,
                    );

                /*
                |--------------------------------------------------------------------------
                | Mark ready for release
                |--------------------------------------------------------------------------
                */

                $request->markReadyForRelease();
                $request->save();

                $request->logs()->create([
                    'status' =>
                        DocumentRequestStatus::READY_FOR_RELEASE,

                    'remarks' =>
                        'Document was signed by the Chairperson and is ready for release.',

                    'performed_by' =>
                        $user->id,
                ]);

                return $file->fresh([
                    'documentRequest',
                ]);
            }
        );

        CreateDocumentBlockchainAnchor::dispatch($file->id);

        return $file;
    }

    /**
     * Only the Chairperson can sign documents.
     */
    protected function authorizeSigner(
        User $user
    ): void {

        if (! $user->hasRole(
            Roles::CHAIRPERSON
        )) {

            throw ValidationException::withMessages([
                'user' =>
                    'Only the Chairperson can sign documents.',
            ]);
        }
    }

    /**
     * Validate request status.
     */
    protected function validateRequest(
        DocumentRequest $request
    ): void {

        if (! $request->isForSignature()) {

            throw ValidationException::withMessages([
                'status' =>
                    'This document request is not awaiting signature.',
            ]);
        }
    }

    /**
     * Validate Chairperson signature.
     */
    protected function validateSignature(
        User $user
    ): void {

        if (! $user->signature_path) {

            throw ValidationException::withMessages([
                'signature' =>
                    'You must upload your signature before signing documents.',
            ]);
        }

        $disk = $this->disk();

        if (! Storage::disk($disk)->exists(
            $user->signature_path
        )) {

            throw ValidationException::withMessages([
                'signature' =>
                    'Your signature file could not be found.',
            ]);
        }
    }
    
    /**
     * Upload and finalize a PNPKI-signed document.
     */
    public function uploadSignedDocument(
        DocumentRequest $request,
        User $user,
        UploadedFile $file,
    ): DocumentFile {
        $this->authorization->authorizeSignDocument(
            $user,
            $request,
        );

        $contents = file_get_contents($file->getRealPath());

        if (
            $contents === false ||
            preg_match('/%PDF-\d\.\d/', substr($contents, 0, 1024)) !== 1
        ) {
            throw ValidationException::withMessages([
                'signed_document' => 'The uploaded file is not a valid PDF.',
            ]);
        }

        // Retrieve the active signing certificate of the Chairperson.
        $certificate = UserSigningCertificate::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get()
            ->first(fn (UserSigningCertificate $item) =>
                $item->isActive()
            );

        if (! $certificate) {
            throw ValidationException::withMessages([
                'signed_document' => 'You do not have an active PNPKI signing certificate.',
            ]);
        }

        // Verify the registered public certificate still exists.
        if (! Storage::disk('local')->exists($certificate->certificate_path)) {
            throw ValidationException::withMessages([
                'signed_document' => 'Your registered signing certificate could not be found.',
            ]);
        }

        // Cryptographically validate the uploaded PDF.
        $verification = $this->pnpkiVerifier->verify(
            pdfPath: $file->getRealPath(),
            expectedFingerprint: $certificate->fingerprint_sha256,
            registeredCertificatePath: Storage::disk('local')->path(
                $certificate->certificate_path
            ),
        );

        if (($verification['valid'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'signed_document' => $verification['message']
                    ?? 'The PNPKI digital signature verification failed.',
            ]);
        }

        $storedFile = DB::transaction(function () use (
            $request,
            $user,
            $contents,
            $certificate,
        ) {
            $request = $this->lockRequest($request);

            $this->validateRequest($request);

            $this->authorization->authorizeSignDocument(
                $user,
                $request,
            );

            // Lock and recheck the certificate before finalizing.
            $certificate = UserSigningCertificate::query()
                ->whereKey($certificate->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $certificate->isActive()) {
                throw ValidationException::withMessages([
                    'signed_document' => 'Your signing certificate is no longer active.',
                ]);
            }

            $request->loadMissing([
                'documentType',
                'resident',
                'requestedFor',
                'barangay',
            ]);

            $this->documentNumberService->ensure($request);

            $uuid = $this->getVerificationUuid($request);

            $documentFile = $this->storeDocument(
                request: $request,
                user: $user,
                contents: $contents,
                uuid: $uuid,
            );

            $request->markGenerated($user);
            $request->sign($user);
            $request->markReadyForRelease();
            $request->save();

            $request->logs()->create([
                'status' => DocumentRequestStatus::READY_FOR_RELEASE,
                'remarks' => 'Document digitally signed and uploaded. PNPKI signature verification passed.',
                'performed_by' => $user->id,
            ]);

            return $documentFile->fresh(['documentRequest']);
        });

        // Anchor only the verified, finalized document.
        CreateDocumentBlockchainAnchor::dispatch($storedFile->id);

        return $storedFile;
    }

    /**
     * Store the final signed PDF.
     */
    protected function storeDocument(
        DocumentRequest $request,
        User $user,
        string $contents,
        string $uuid,
    ): DocumentFile {

        $disk = $this->disk();

        $filename = sprintf(
            '%s-%s.pdf',
            $request->request_no,
            Str::slug(
                $request->documentType->name
            )
        );

        $path = sprintf(
            'documents/%s/%s',
            $request->request_no,
            $filename
        );

        Storage::disk($disk)->put(
            $path,
            $contents
        );

        return $request->files()->create([
            'uuid' => $uuid,

            'disk' => $disk,

            'path' => $path,

            'filename' => $filename,

            'mime_type' => 'application/pdf',

            'size' =>
                Storage::disk($disk)->size(
                    $path
                ),

            'checksum' =>
                hash(
                    'sha256',
                    $contents
                ),

            'created_by' =>
                $user->id,
        ]);
    }

    /**
     * Lock the request to prevent double signing.
     */
    protected function lockRequest(
        DocumentRequest $request
    ): DocumentRequest {

        return DocumentRequest::query()
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function disk(): string
    {
        return config(
            'filesystems.document_disk',
            'local'
        );
    }

    /**
     * Get or create the document verification UUID.
     */
    protected function getVerificationUuid(
        DocumentRequest $request
    ): string {

        if (! $request->verification_uuid) {
            $request->verification_uuid = (string) Str::uuid();
            $request->save();
        }

        return $request->verification_uuid;
    }
}