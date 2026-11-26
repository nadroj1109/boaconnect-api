<?php

namespace App\Services\DocumentRequest;

use App\Constants\Roles;
use App\Models\DocumentFile;
use App\Models\DocumentRequest;
use App\Models\User;

use Illuminate\Auth\Access\AuthorizationException;

class DocumentAuthorizationService
{
    /**
     * Determine whether the user can view a document request.
     */
    public function canViewRequest(
        User $user,
        DocumentRequest $request,
    ): bool {
        if ($this->canManageRequests($user)) {
            return true;
        }

        return $this->isResidentOwner(
            $user,
            $request,
        );
    }

    /**
     * Determine whether the user can view a generated document.
     */
    public function canViewFile(
        User $user,
        DocumentFile $file,
    ): bool {
        $request = $file->documentRequest;

        if (! $request) {
            return false;
        }

        /*
         * Administrative users need explicit document-view
         * permission to access generated documents.
         */
        if ($this->canViewDocuments($user)) {
            return true;
        }

        /*
         * Residents can access documents belonging to their
         * own document requests.
         */
        return $this->isResidentOwner(
            $user,
            $request,
        );
    }

    /**
     * Determine whether the user can download a generated document.
     */
    public function canDownloadFile(
        User $user,
        DocumentFile $file,
    ): bool {
        $request = $file->documentRequest;

        if (! $request) {
            return false;
        }

        /*
         * Administrative download access requires the
         * dedicated document-download permission.
         */
        if ($this->canDownloadDocuments($user)) {
            return true;
        }

        /*
         * Residents can download their own generated documents.
         */
        return $this->isResidentOwner(
            $user,
            $request,
        );
    }

    /**
     * Authorize access to a document request.
     */
    public function authorizeRequest(
        User $user,
        DocumentRequest $request,
    ): void {
        if (! $this->canViewRequest(
            $user,
            $request,
        )) {
            throw new AuthorizationException(
                'You are not authorized to access this document request.'
            );
        }
    }

    /**
     * Authorize access to a generated document.
     */
    public function authorizeFile(
        User $user,
        DocumentFile $file,
    ): void {
        if (! $this->canViewFile(
            $user,
            $file,
        )) {
            throw new AuthorizationException(
                'You are not authorized to access this document.'
            );
        }
    }

    /**
     * Authorize downloading a generated document.
     */
    public function authorizeDownload(
        User $user,
        DocumentFile $file,
    ): void {
        if (! $this->canDownloadFile(
            $user,
            $file,
        )) {
            throw new AuthorizationException(
                'You are not authorized to download this document.'
            );
        }
    }

    /**
     * Determine whether the user owns the request
     * through their resident relationship.
     */
    protected function isResidentOwner(
        User $user,
        DocumentRequest $request,
    ): bool {
        $resident = $user->resident;

        if (! $resident) {
            return false;
        }

        return $request->resident_id === $resident->id
            || $request->requested_for_resident_id === $resident->id;
    }

    /**
     * Determine whether the user can manage document requests.
     */
    protected function canManageRequests(
        User $user,
    ): bool {
        return $user->hasPermissionTo(
            \App\Constants\Permissions::DOCUMENT_REQUEST_VIEW
        );
    }

    /**
     * Determine whether the user can view generated documents.
     */
    protected function canViewDocuments(
        User $user,
    ): bool {
        return $user->hasPermissionTo(
            \App\Constants\Permissions::DOCUMENT_VIEW
        );
    }

    /**
     * Determine whether the user can download generated documents.
     */
    protected function canDownloadDocuments(
        User $user,
    ): bool {
        return $user->hasPermissionTo(
            \App\Constants\Permissions::DOCUMENT_DOWNLOAD
        );
    }

        /**
     * Determine whether the user can release a digitally
     * downloadable document after successfully downloading it.
     */
    public function canReleaseAfterDownload(
        User $user,
        DocumentRequest $request,
    ): bool {
        return $this->isResidentOwner(
            $user,
            $request,
        );
    }

    /**
     * Authorize releasing a digitally downloadable document
     * after successful download.
     */
    public function authorizeReleaseAfterDownload(
        User $user,
        DocumentRequest $request,
    ): void {
        if (! $this->canReleaseAfterDownload(
            $user,
            $request,
        )) {
            throw new AuthorizationException(
                'You are not authorized to release this document.'
            );
        }
    }

    public function authorizeViewAttachment(
        User $user,
        DocumentRequest $documentRequest
    ): void {
        if (
            $user->hasAnyRole([
                Roles::SUPER_ADMIN,
                Roles::ADMINISTRATOR,
            ])
        ) {
            return;
        }

        if (
            $user->hasAnyRole([
                Roles::CHAIRPERSON,
                Roles::SECRETARY,
                Roles::STAFF,
            ])
        ) {
            $this->authorizeBarangayAccess(
                $user,
                $documentRequest
            );

            return;
        }

        $resident = $user->resident;

        if (! $resident) {
            abort(
                403,
                'Resident account is required.'
            );
        }

        if (
            $documentRequest->resident_id ===
            $resident->id
            ||
            $documentRequest->requested_for_resident_id ===
            $resident->id
        ) {
            return;
        }

        abort(
            403,
            'You are not authorized to view this document request.'
        );
    }

    /**
     * Authorize the Chairperson to sign a document.
     */
    public function authorizeSignDocument(
        User $user,
        DocumentRequest $request,
    ): void {
        if (! $user->hasRole(Roles::CHAIRPERSON)) {
            throw new AuthorizationException(
                'Only the Chairperson can sign documents.'
            );
        }

        if (! $request->barangay_id) {
            throw new AuthorizationException(
                'The document request has no assigned barangay.'
            );
        }

        $resident = $user->resident;

        if (! $resident) {
            throw new AuthorizationException(
                'A resident record is required for document signing.'
            );
        }

        $isAuthorized = $resident->barangayOfficials()
            ->where('barangay_id', $request->barangay_id)
            ->where('is_active', true)
            ->whereDate('term_start', '<=', today())
            ->where(function ($query) {
                $query->whereNull('term_end')
                    ->orWhereDate('term_end', '>=', today());
            })
            ->whereHas('position', function ($query) {
                $query->where('is_active', true)
                    ->whereIn(
                        \Illuminate\Support\Facades\DB::raw('LOWER(name)'),
                        [
                            'chairperson',
                            'barangay chairperson',
                            'punong barangay',
                        ]
                    );
            })
            ->exists();

        if (! $isAuthorized) {
            throw new AuthorizationException(
                'You are not the active Chairperson of this barangay.'
            );
        }
    }
}