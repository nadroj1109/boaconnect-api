<?php

namespace App\Services\DocumentRequest;

use App\Enums\DocumentRequestStatus;

use App\Models\DocumentRequest;
use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentApprovalService
{
    /**
     * Mark a pending request as under review.
     */
    // public function review(
    //     DocumentRequest $request,
    //     User $user,
    // ): DocumentRequest {
    //     return DB::transaction(function () use (
    //         $request,
    //         $user,
    //     ) {

    //         $request = $this->lockRequest($request);

    //         if (! $request->isPending()) {
    //             throw ValidationException::withMessages([
    //                 'status' =>
    //                     'Only pending requests can be placed under review.',
    //             ]);
    //         }

    //         $request->markUnderReview();

    //         $request->save();

    //         $request->logs()->create([
    //             'status' =>
    //                 DocumentRequestStatus::UNDER_REVIEW,

    //             'remarks' =>
    //                 'Document request placed under review.',

    //             'performed_by' =>
    //                 $user->id,
    //         ]);

    //         return $request->fresh([
    //             'documentType',
    //             'resident',
    //             'requestedFor',
    //             'payments',
    //             'files',
    //             'logs',
    //         ]);
    //     });
    // }

    /**
     * Approve a request that is currently under review.
     */
    public function approve(
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

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending requests can be approved.',
                ]);
            }

            $request->approve($user);

            if ($remarks !== null) {
                $request->remarks = $remarks;
            }

            $request->save();

            $request->logs()->create([
                'status' =>
                    DocumentRequestStatus::APPROVED,

                'remarks' =>
                    $remarks ??
                    'Document request approved.',

                'performed_by' =>
                    $user->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Continue the workflow
            |--------------------------------------------------------------------------
            */

            if ($request->requiresPayment()) {

                $request->markAwaitingPayment();

                $request->save();

                $request->logs()->create([
                    'status' =>
                        DocumentRequestStatus::AWAITING_PAYMENT,

                    'remarks' =>
                        'Request approved and is awaiting payment.',

                    'performed_by' =>
                        $user->id,
                ]);

            } else {

                $request->markForSignature();

                $request->save();

                $request->logs()->create([
                    'status' =>
                        DocumentRequestStatus::FOR_SIGNATURE,

                    'remarks' =>
                        'Document request is ready for Chairperson review and signature.',

                    'performed_by' =>
                        $user->id,
                ]);
            }

            return $request->fresh([
                'documentType',
                'resident',
                'requestedFor',
                'payments',
                'files',
                'logs',
            ]);
        });
    }

    // Update for Signature
    public function forSignature(
        DocumentRequest $request,
        User $user,
    ): DocumentRequest {

        return DB::transaction(function () use (
            $request,
            $user,
        ) {

            $request = $this->lockRequest(
                $request
            );

            if (! $request->isPaymentReceived()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only requests with received payment can be marked for signature.',
                ]);
            }

            $request->markForSignature();
            $request->save();

            $request->logs()->create([
                'status' =>
                    DocumentRequestStatus::FOR_SIGNATURE,

                'remarks' =>
                    'Payment was confirmed. Document request is ready for Chairperson review and signature.',

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
            ]);
        });
    }

    /**
     * Reject a request that is currently under review.
     */
    public function reject(
        DocumentRequest $request,
        User $user,
        string $remarks,
    ): DocumentRequest {
        return DB::transaction(function () use (
            $request,
            $user,
            $remarks,
        ) {

            $request = $this->lockRequest($request);

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending requests can be rejected.',
                ]);
            }

            $request->reject();

            $request->remarks = $remarks;

            $request->save();

            $request->logs()->create([
                'status' =>
                    DocumentRequestStatus::REJECTED,

                'remarks' =>
                    $remarks,

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
            ]);
        });
    }

    /**
     * Lock the request for the duration of the transaction.
     */
    protected function lockRequest(
        DocumentRequest $request,
    ): DocumentRequest {
        return DocumentRequest::query()
            ->whereKey($request->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}