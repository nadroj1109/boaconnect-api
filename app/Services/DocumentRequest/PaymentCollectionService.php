<?php

namespace App\Services\DocumentRequest;

use App\Enums\PaymentStatus;
use App\Models\DocumentPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentCollectionService
{
    /**
     * Retrieve paginated payment collections for the user's barangay.
     */
    public function paginate(
        User $user,
        array $filters = []
    ): LengthAwarePaginator {
        return $this->query($user, $filters)
            ->when(
                ! empty($filters['status']),
                fn (Builder $query) => $query->where(
                    'document_payments.status',
                    $filters['status']
                )
            )
            ->orderByRaw(
                'COALESCE(document_payments.paid_at, document_payments.created_at) DESC'
            )
            ->orderByDesc('document_payments.id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Retrieve collection summary for the user's barangay.
     */
    public function summary(
        User $user,
        array $filters = []
    ): array {
        // Apply date, provider, and payment method filters.
        $filteredQuery = $this->query($user, $filters);

        $totalCollected = (clone $filteredQuery)
            ->where(
                'document_payments.status',
                PaymentStatus::PAID->value
            )
            ->sum('document_payments.amount');

        $paidTransactions = (clone $filteredQuery)
            ->where(
                'document_payments.status',
                PaymentStatus::PAID->value
            )
            ->count();

        $pendingTransactions = (clone $filteredQuery)
            ->where(
                'document_payments.status',
                PaymentStatus::PENDING->value
            )
            ->count();

        $failedTransactions = (clone $filteredQuery)
            ->where(
                'document_payments.status',
                PaymentStatus::FAILED->value
            )
            ->count();

        $cancelledTransactions = (clone $filteredQuery)
            ->where(
                'document_payments.status',
                PaymentStatus::CANCELLED->value
            )
            ->count();

        // Today's collections, independent of the selected date range.
        $todayCollected = $this->query($user, $filters, false)
            ->where(
                'document_payments.status',
                PaymentStatus::PAID->value
            )
            ->whereDate('document_payments.paid_at', today())
            ->sum('document_payments.amount');

        // Current month's collections, independent of the selected date range.
        $monthCollected = $this->query($user, $filters, false)
            ->where(
                'document_payments.status',
                PaymentStatus::PAID->value
            )
            ->whereBetween('document_payments.paid_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('document_payments.amount');

        return [
            'total_collected' => (float) $totalCollected,
            'today_collected' => (float) $todayCollected,
            'month_collected' => (float) $monthCollected,

            'paid_transactions' => $paidTransactions,
            'pending_transactions' => $pendingTransactions,
            'failed_transactions' => $failedTransactions,
            'cancelled_transactions' => $cancelledTransactions,

            'total_transactions' =>
                $paidTransactions +
                $pendingTransactions +
                $failedTransactions +
                $cancelledTransactions,
        ];
    }

    /**
     * Build the barangay-scoped payment query.
     */
    protected function query(
        User $user,
        array $filters = [],
        bool $applyDateFilters = true
    ): Builder {
        abort_if(
            ! $user->barangay_id,
            403,
            'Your account is not assigned to a barangay.'
        );

        return DocumentPayment::query()
            ->select('document_payments.*')
            ->with([
                'receivedBy:id,display_name,resident_id',

                'documentRequest' => function ($query) {
                    $query->withTrashed()->select([
                        'id',
                        'request_no',
                        'barangay_id',
                        'resident_id',
                        'requested_for_resident_id',
                        'document_type_id',
                    ]);
                },

                'documentRequest.resident',

                'documentRequest.requestedFor',

                'documentRequest.documentType',
            ])
            ->whereHas(
                'documentRequest',
                fn (Builder $query) => $query->withTrashed()->where(
                    'barangay_id',
                    $user->barangay_id
                )
            )
            ->when(
                ! empty($filters['payment_method']),
                fn (Builder $query) => $query->where(
                    'payment_method',
                    $filters['payment_method']
                )
            )
            ->when(
                ! empty($filters['provider']),
                fn (Builder $query) => $query->where(
                    'provider',
                    $filters['provider']
                )
            )
            ->when(
                $applyDateFilters && ! empty($filters['date_from']),
                fn (Builder $query) => $query->whereDate(
                    \Illuminate\Support\Facades\DB::raw(
                        'COALESCE(document_payments.paid_at, document_payments.created_at)'
                    ),
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                $applyDateFilters && ! empty($filters['date_to']),
                fn (Builder $query) => $query->whereDate(
                    \Illuminate\Support\Facades\DB::raw(
                        'COALESCE(document_payments.paid_at, document_payments.created_at)'
                    ),
                    '<=',
                    $filters['date_to']
                )
            )
            ->when(
                ! empty($filters['search']),
                function (Builder $query) use ($filters) {
                    $search = $filters['search'];

                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where(
                                'document_payments.reference_no',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'document_payments.provider_reference',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'documentRequest',
                                fn (Builder $requestQuery) => $requestQuery
                                    ->where(
                                        'request_no',
                                        'like',
                                        "%{$search}%"
                                    )
                            );
                    });
                }
            );
    }

    public function show(User $user, int $paymentId): DocumentPayment
    {
        if (!$user->barangay_id) {
            abort(403, 'You are not assigned to a barangay.');
        }

        return DocumentPayment::query()
            ->with([
                'receivedBy:id,display_name,resident_id',
                'blockchainAnchors',
                'documentRequest' => function ($query) {
                    $query->withTrashed()
                        ->with([
                            'resident',
                            'requestedFor',
                            'documentType',
                        ]);
                },
            ])
            ->whereKey($paymentId)
            ->whereHas('documentRequest', function ($query) use ($user) {
                $query->withTrashed()
                    ->where('barangay_id', $user->barangay_id);
            })
            ->firstOrFail();
    }
}
