<?php

namespace App\Services\DocumentRequest;

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentDeliveryMethod;

use App\Models\DocumentRequest;
use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentReleaseService
{
    /**
     * Release a document request administratively.
     *
     * Used by Secretary/Staff and other authorized users.
     */
    public function release(
        DocumentRequest $request,
        User $user,
        ?string $remarks = null,
    ): DocumentRequest {
        return DB::transaction(function () use (
            $request,
            $user,
            $remarks,
        ) {

            $request = $this->lockRequest($request);

            $this->ensureReadyForRelease($request);

            $this->ensureDocumentExists($request);

            $request->release($user);

            $request->save();

            $request->logs()->create([
                'status' =>
                    DocumentRequestStatus::RELEASED,

                'remarks' =>
                    $remarks ??
                    'Document released to the resident.',

                'performed_by' =>
                    $user->id,
            ]);

            return $request->fresh([
                'documentType',
                'resident',
                'requestedFor',
                'payments',
                'files',
                'logs',
                'releaser',
            ]);
        });
    }

    /**
     * Release a document after a resident
     * successfully downloads it.
     */
    public function releaseAfterDownload(
        DocumentRequest $request,
        User $user,
    ): DocumentRequest {
        return DB::transaction(function () use (
            $request,
            $user,
        ) {

            $request = $this->lockRequest($request);

            if (! $request->isReadyForRelease()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only documents that are ready for release can be released.',
                ]);
            }

            if (
                $request->delivery_method !==
                DocumentDeliveryMethod::DOWNLOAD
            ) {
                throw ValidationException::withMessages([
                    'delivery_method' =>
                        'Only documents configured for digital download can be released this way.',
                ]);
            }

            $file = $request->files()
                ->latest()
                ->first();

            if (! $file || ! $file->exists()) {
                throw ValidationException::withMessages([
                    'document' =>
                        'The generated document could not be found.',
                ]);
            }

            $request->release($user);

            $request->save();

            $request->logs()->create([
                'status' =>
                    DocumentRequestStatus::RELEASED,

                'remarks' =>
                    'Document downloaded and released to the resident.',

                'performed_by' =>
                    $user->id,
            ]);

            return $request->fresh([
                'documentType',
                'resident',
                'requestedFor',
                'payments',
                'files',
                'logs',
                'releaser',
            ]);
        });
    }

    /**
     * Ensure the document is ready for release.
     */
    protected function ensureReadyForRelease(
        DocumentRequest $request
    ): void {
        if (! $request->isReadyForRelease()) {
            throw ValidationException::withMessages([
                'status' =>
                    'Only documents that are ready for release can be released.',
            ]);
        }
    }

    /**
     * Make sure a generated document actually exists.
     */
    protected function ensureDocumentExists(
        DocumentRequest $request
    ): void {
        $file = $request->files()
            ->latest()
            ->first();

        if (! $file || ! $file->exists()) {
            throw ValidationException::withMessages([
                'document' =>
                    'The generated document could not be found.',
            ]);
        }
    }

    /**
     * Lock the request for the duration of the transaction.
     */
    protected function lockRequest(
        DocumentRequest $request
    ): DocumentRequest {
        return DocumentRequest::query()
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}