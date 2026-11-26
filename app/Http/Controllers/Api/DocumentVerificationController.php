<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DocumentRequest\DocumentVerificationService;
use App\Traits\ApiResponse;

class DocumentVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentVerificationService $verification,
    ) {
    }

    public function show(string $uuid)
    {
        $result = $this->verification->verify($uuid);

        return $this->success(
            $result,
            $result['message']
        );
    }
}
