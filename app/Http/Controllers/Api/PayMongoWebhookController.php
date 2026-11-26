<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PayMongoService;
use App\Services\Payments\PayMongoWebhookService;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    public function __construct(
        protected PayMongoService $paymongo,
        protected PayMongoWebhookService $webhooks,
    ) {
    }

    public function handle(
        Request $request
    ) {
        /*
         * Verify that the webhook really
         * came from PayMongo.
         */

        if (app()->environment('production')) {
            if (! $this->paymongo->verifyWebhook($request)) {
                abort(
                    401,
                    'Invalid webhook signature.'
                );
            }
        }

        return $this->webhooks->handle($request);
    }
}
