<?php

namespace Tests\Unit;

use App\Enums\DocumentRequestStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Jobs\CreatePaymentBlockchainAnchor;
use App\Models\DocumentPayment;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\DocumentRequest\DocumentPaymentService;
use App\Services\Payments\PayMongoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class DocumentPaymentBlockchainDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'audit.enabled' => false,
        ]);

        // Ensure the test uses its own in-memory SQLite connection.
        \DB::purge('sqlite');
        \DB::setDefaultConnection('sqlite');

        $this->createTestSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Test Certificate');
            $table->decimal('fee', 10, 2)->default(100);
            $table->boolean('requires_payment')->default(true);
            $table->boolean('allow_online_payment')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no')->unique();
            $table->string('status', 30);
            $table->unsignedBigInteger('document_type_id');
            $table->text('purpose')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_request_id');
            $table->decimal('amount', 10, 2);
            $table->string('provider', 30);
            $table->string('payment_method', 30)->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('checkout_session_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('reference_no')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->string('status', 30);
            $table->longText('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_request_id');
            $table->string('status', 30);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();
        });
    }

    private function makeAwaitingPaymentRequest(): DocumentRequest
    {
        $type = DocumentType::create([
            'name' => 'Test Certificate',
            'fee' => 100,
            'requires_payment' => true,
            'allow_online_payment' => true,
        ]);

        return DocumentRequest::create([
            'request_no' => 'TEST-' . uniqid(),
            'document_type_id' => $type->id,
            'purpose' => 'Automated payment dispatch test',
            'status' => DocumentRequestStatus::AWAITING_PAYMENT,
        ]);
    }

    private function makePendingPayment(
        DocumentRequest $request,
        PaymentProvider $provider,
        PaymentMethod $method,
    ): DocumentPayment {
        return DocumentPayment::create([
            'document_request_id' => $request->id,
            'amount' => 100,
            'provider' => $provider,
            'payment_method' => $method,
            'status' => PaymentStatus::PENDING,
            'checkout_session_id' => $provider === PaymentProvider::PAYMONGO
                ? 'cs_test_' . uniqid()
                : null,
        ]);
    }

    public function test_successful_paymongo_confirmation_dispatches_payment_anchor_job(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();
        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        $service = app(DocumentPaymentService::class);

        $result = $service->markPaid(
            $payment,
            'paymongo_test_reference_001',
            'REF-001',
            ['test' => true],
        );

        $this->assertTrue($result->isPaid());
        $this->assertNotNull($result->paid_at);

        Queue::assertPushed(
            CreatePaymentBlockchainAnchor::class,
            fn ($job) => $job->documentPaymentId === $result->id,
        );
    }

    public function test_successful_cash_payment_recording_dispatches_payment_anchor_job(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();
        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::MANUAL,
            PaymentMethod::OTC,
        );

        $receiver = User::create([
            'first_name' => 'Test',
            'last_name' => 'Secretary',
            'name' => 'Test Secretary',
            'email' => 'secretary-' . uniqid() . '@example.test',
            'password' => 'test-password',
        ]);

        $result = app(DocumentPaymentService::class)->recordCashPayment(
            $payment,
            $receiver,
            'CASH-REF-001',
        );

        $this->assertTrue($result->isPaid());
        $this->assertNotNull($result->paid_at);
        $this->assertSame($receiver->id, $result->received_by);

        Queue::assertPushed(
            CreatePaymentBlockchainAnchor::class,
            fn ($job) => $job->documentPaymentId === $result->id,
        );
    }

    public function test_failed_payment_attempt_does_not_dispatch_payment_anchor_job(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();
        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        $payment->status = PaymentStatus::FAILED;
        $payment->save();

        try {
            app(DocumentPaymentService::class)->markPaid(
                $payment,
                'paymongo_test_reference_002',
            );

            $this->fail('Expected a validation exception for a failed payment.');
        } catch (ValidationException) {
            // Expected: failed payments cannot be marked as paid.
        }

        Queue::assertNothingPushed();
    }

    public function test_confirm_paymongo_payment_dispatches_anchor_after_successful_checkout(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();

        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        $checkoutSessionId = $payment->checkout_session_id;

        $paymongo = Mockery::mock(PayMongoService::class);

        $paymongo
            ->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with($checkoutSessionId)
            ->andReturn([
                'id' => $checkoutSessionId,
                'attributes' => [
                    'metadata' => [
                        'document_payment_id' => (string) $payment->id,
                    ],
                    'reference_number' => 'CHECKOUT-REF-001',
                    'payments' => [
                        [
                            'id' => 'pay_test_success_001',
                            'attributes' => [
                                'status' => 'paid',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(PayMongoService::class, $paymongo);

        $result = app(DocumentPaymentService::class)
            ->confirmPayMongoPayment($payment);

        $this->assertTrue($result->isPaid());
        $this->assertSame(
            'pay_test_success_001',
            $result->provider_reference,
        );
        $this->assertSame(
            'CHECKOUT-REF-001',
            $result->reference_no,
        );
        $this->assertNotNull($result->paid_at);

        Queue::assertPushed(
            CreatePaymentBlockchainAnchor::class,
            fn ($job) => $job->documentPaymentId === $result->id,
        );
    }

    public function test_unpaid_paymongo_checkout_does_not_dispatch_anchor(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();

        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        $checkoutSessionId = $payment->checkout_session_id;

        $paymongo = Mockery::mock(PayMongoService::class);

        $paymongo
            ->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with($checkoutSessionId)
            ->andReturn([
                'id' => $checkoutSessionId,
                'attributes' => [
                    'metadata' => [
                        'document_payment_id' => (string) $payment->id,
                    ],
                    'reference_number' => 'CHECKOUT-REF-002',
                    'payments' => [
                        [
                            'id' => 'pay_test_pending_001',
                            'attributes' => [
                                'status' => 'awaiting_payment_method',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(PayMongoService::class, $paymongo);

        try {
            app(DocumentPaymentService::class)
                ->confirmPayMongoPayment($payment);

            $this->fail(
                'Expected an exception because the PayMongo payment is not paid.'
            );
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'not been completed',
                $exception->getMessage(),
            );
        }

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->fresh()->status,
        );

        Queue::assertNothingPushed();
    }

    public function test_checkout_session_id_mismatch_does_not_dispatch_anchor(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();

        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        $paymongo = Mockery::mock(PayMongoService::class);

        $paymongo
            ->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with($payment->checkout_session_id)
            ->andReturn([
                'id' => 'cs_test_wrong_session',
                'attributes' => [
                    'metadata' => [
                        'document_payment_id' => (string) $payment->id,
                    ],
                    'payments' => [
                        [
                            'id' => 'pay_test_wrong_session',
                            'attributes' => ['status' => 'paid'],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(PayMongoService::class, $paymongo);

        try {
            app(DocumentPaymentService::class)
                ->confirmPayMongoPayment($payment);

            $this->fail('Expected the mismatched checkout session to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'could not be verified',
                $exception->getMessage(),
            );
        }

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->fresh()->status,
        );

        Queue::assertNothingPushed();
    }

    public function test_checkout_payment_metadata_mismatch_does_not_dispatch_anchor(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();

        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        $paymongo = Mockery::mock(PayMongoService::class);

        $paymongo
            ->shouldReceive('retrieveCheckoutSession')
            ->once()
            ->with($payment->checkout_session_id)
            ->andReturn([
                'id' => $payment->checkout_session_id,
                'attributes' => [
                    'metadata' => [
                        'document_payment_id' => '999999',
                    ],
                    'payments' => [
                        [
                            'id' => 'pay_test_wrong_metadata',
                            'attributes' => ['status' => 'paid'],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(PayMongoService::class, $paymongo);

        try {
            app(DocumentPaymentService::class)
                ->confirmPayMongoPayment($payment);

            $this->fail('Expected the mismatched payment metadata to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'does not match this payment record',
                $exception->getMessage(),
            );
        }

        $this->assertSame(
            PaymentStatus::PENDING,
            $payment->fresh()->status,
        );

        Queue::assertNothingPushed();
    }

    public function test_already_paid_paymongo_confirmation_does_not_retrieve_checkout_or_dispatch_again(): void
    {
        Queue::fake();

        $request = $this->makeAwaitingPaymentRequest();

        $payment = $this->makePendingPayment(
            $request,
            PaymentProvider::PAYMONGO,
            PaymentMethod::GCASH,
        );

        // Simulate a payment that has already been confirmed.
        $payment->status = PaymentStatus::PAID;
        $payment->provider_reference = 'pay_test_already_paid';
        $payment->reference_no = 'REF-ALREADY-PAID';
        $payment->paid_at = now();
        $payment->save();

        // An already-paid confirmation should return early without contacting PayMongo.
        $paymongo = Mockery::mock(PayMongoService::class);

        $paymongo
            ->shouldNotReceive('retrieveCheckoutSession');

        $this->app->instance(PayMongoService::class, $paymongo);

        $result = app(DocumentPaymentService::class)
            ->confirmPayMongoPayment($payment);

        $this->assertTrue($result->isPaid());
        $this->assertNotNull($result->paid_at);
        $this->assertSame(
            'pay_test_already_paid',
            $result->provider_reference,
        );

        Queue::assertNothingPushed();
    }
}