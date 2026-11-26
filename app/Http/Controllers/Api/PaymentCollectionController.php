<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentCollectionResource;

use App\Services\DocumentRequest\PaymentCollectionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use App\Traits\ApiResponse;

class PaymentCollectionController extends Controller
{
    use ApiResponse;
    
    public function __construct(
        protected PaymentCollectionService $paymentCollectionService,
    ) {
    }

    /**
     * Display paginated payment collections.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'payment_method' => [
                'nullable',
                Rule::in(array_map(
                    fn ($case) => $case->value,
                    PaymentMethod::cases()
                )),
            ],

            'provider' => [
                'nullable',
                Rule::in(array_map(
                    fn ($case) => $case->value,
                    PaymentProvider::cases()
                )),
            ],

            'status' => [
                'nullable',
                Rule::in(array_map(
                    fn ($case) => $case->value,
                    PaymentStatus::cases()
                )),
            ],

            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:10',
                'max:100',
            ],
        ]);

        $payments = $this->paymentCollectionService->paginate(
            $request->user(),
            $validated
        );

        return PaymentCollectionResource::collection($payments);
    }

    /**
     * Display payment collection summary.
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'payment_method' => [
                'nullable',
                Rule::in(array_column(
                    PaymentMethod::cases(),
                    'value'
                )),
            ],

            'provider' => [
                'nullable',
                Rule::in(array_column(
                    PaymentProvider::cases(),
                    'value'
                )),
            ],
        ]);

        $summary = $this->paymentCollectionService->summary(
            $request->user(),
            $validated
        );

        return $this->success(
            $summary,
            'Payment collection summary retrieved successfully.'
        );
    }

    public function show(int $id)
    {
        $payment = $this->paymentCollectionService->show(
            request()->user(),
            $id
        );

        return $this->success(
            new PaymentCollectionResource($payment)
        );
    }
}
