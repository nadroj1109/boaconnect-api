<?php

namespace App\Services\Complaint;

use App\Models\Complaint;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ComplaintAuthorizationService
{
    /*
    |--------------------------------------------------------------------------
    | View
    |--------------------------------------------------------------------------
    */

    public function canView(
        User $user,
        Complaint $complaint,
    ): bool {
        return $this->canAccessComplaint(
            $user,
            $complaint,
        );
    }

    public function authorizeView(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canView(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to view this complaint.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create
    |--------------------------------------------------------------------------
    */

    public function canCreateStaffComplaint(
        User $user,
    ): bool {
        return $user->can('complaints.create');
    }

    public function authorizeCreateStaffComplaint(
        User $user,
    ): void {
        if (! $this->canCreateStaffComplaint($user)) {
            throw new AuthorizationException(
                'You are not authorized to create a complaint.'
            );
        }
    }

    public function canCreateResidentComplaint(
        User $user,
    ): bool {
        return $user->resident_id !== null;
    }

    public function authorizeCreateResidentComplaint(
        User $user,
    ): void {
        if (! $this->canCreateResidentComplaint($user)) {
            throw new AuthorizationException(
                'Your account is not linked to a resident record.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function canUpdate(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.edit')
            && $this->canAccessComplaint(
                $user,
                $complaint,
            )
            && ! $complaint->isFinal();
    }

    public function authorizeUpdate(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canUpdate(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to update this complaint.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function canDelete(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.delete')
            && $this->canAccessComplaint(
                $user,
                $complaint,
            )
            && ! $complaint->isFinal();
    }

    public function authorizeDelete(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canDelete(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to delete this complaint.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Historical Records
    |--------------------------------------------------------------------------
    */

    public function canEncodeHistorical(
        User $user,
    ): bool {
        return $user->can(
            'complaints.encode_historical'
        );
    }

    public function authorizeEncodeHistorical(
        User $user,
    ): void {
        if (! $this->canEncodeHistorical($user)) {
            throw new AuthorizationException(
                'You are not authorized to encode historical complaints.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    */

    public function canReview(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.review')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeReview(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canReview(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to review this complaint.'
            );
        }
    }

    public function canAssign(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.assign')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeAssign(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canAssign(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to assign this complaint.'
            );
        }
    }

    public function canManageHearing(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.hearing')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeManageHearing(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canManageHearing(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to manage this complaint hearing.'
            );
        }
    }

    public function canResolve(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.resolve')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeResolve(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canResolve(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to resolve this complaint.'
            );
        }
    }

    public function canClose(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.close')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeClose(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canClose(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to close this complaint.'
            );
        }
    }

    public function canReject(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.reject')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeReject(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canReject(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to reject this complaint.'
            );
        }
    }

    public function canCancel(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.cancel')
            && $this->canManageComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeCancel(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canCancel(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to cancel this complaint.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    public function canUploadAttachment(
        User $user,
        Complaint $complaint,
    ): bool {
        if ($complaint->isFinal()) {
            return false;
        }

        // Resident can upload attachments
        // to their own complaint.
        if ($user->resident) {
            return $complaint->resident_id ===
                $user->resident->id;
        }

        // Barangay staff / administrators
        // still require the edit permission.
        return $user->can('complaints.edit')
            && $this->canAccessComplaint(
                $user,
                $complaint,
            );
    }

    public function authorizeUploadAttachment(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canUploadAttachment(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to upload attachments to this complaint.'
            );
        }
    }

    public function canViewAttachment(
        User $user,
        Complaint $complaint,
    ): bool {
        return $this->canAccessComplaint(
            $user,
            $complaint,
        );
    }

    public function authorizeViewAttachment(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canViewAttachment(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to access this complaint attachment.'
            );
        }
    }

    public function canDeleteAttachment(
        User $user,
        Complaint $complaint,
    ): bool {
        return $user->can('complaints.delete')
            && $this->canAccessComplaint(
                $user,
                $complaint,
            )
            && ! $complaint->isFinal();
    }

    public function authorizeDeleteAttachment(
        User $user,
        Complaint $complaint,
    ): void {
        if (! $this->canDeleteAttachment(
            $user,
            $complaint,
        )) {
            throw new AuthorizationException(
                'You are not authorized to delete this complaint attachment.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Complaint Access
    |--------------------------------------------------------------------------
    */

    protected function canAccessComplaint(
        User $user,
        Complaint $complaint,
    ): bool {
        /*
         * Residents can only access complaints
         * belonging to their own resident record.
         */
        if ($user->resident_id !== null) {
            return $user->resident_id ===
                $complaint->resident_id;
        }

        /*
         * Barangay users can only access complaints
         * belonging to their own barangay.
         *
         * Super Admin users without a barangay
         * assignment can access all complaints.
         */
        if ($user->barangay_id === null && ($user->hasRole('Super Administrator') || $user->hasRole('Administrator')) ) {
            return true;
        }

        return $user->barangay_id ===
            $complaint->barangay_id;
    }

    protected function canManageComplaint(
        User $user,
        Complaint $complaint,
    ): bool {
        return $this->canAccessComplaint(
            $user,
            $complaint,
        ) && ! $complaint->is_historical;
    }
    
    public function canViewRespondentHistory(
        User $user,
        Resident $resident,
    ): bool {
        // Super Administrator and Administrator
        if (
            $user->hasRole('Super Administrator') ||
            $user->hasRole('Administrator')
        ) {
            // Preserve the existing policy:
            // administrators without a barangay can access any resident;
            // administrators assigned to a barangay remain restricted.
            return $user->barangay_id === null
                || $user->barangay_id === $resident->barangay_id;
        }

        // Barangay officials can view complaint history
        // only for residents within their assigned barangay.
        if (
            $user->hasRole('Chairperson') ||
            $user->hasRole('Secretary') ||
            $user->hasRole('Staff')
        ) {
            return $user->barangay_id !== null
                && $user->barangay_id === $resident->barangay_id;
        }

        // Ordinary resident accounts can view only their own history.
        if ($user->resident_id !== null) {
            return $user->resident_id === $resident->id;
        }

        return false;
    }

    public function authorizeViewRespondentHistory(
        User $user,
        Resident $resident,
    ): void {

        if (
            ! $this->canViewRespondentHistory(
                $user,
                $resident
            )
        ) {
            throw new AuthorizationException(
                'You are not authorized to view this resident complaint history.'
            );
        }
    }
}