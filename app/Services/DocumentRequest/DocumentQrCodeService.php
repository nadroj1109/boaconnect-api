<?php

namespace App\Services\DocumentRequest;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

class DocumentQrCodeService
{
    /**
     * Generate a QR code as a PNG data URI.
     */
    public function generate(
        string $verificationUrl,
    ): string {
        $renderer = new GDLibRenderer(300);

        $writer = new Writer($renderer);

        $png = $writer->writeString(
            $verificationUrl
        );

        return 'data:image/png;base64,' .
            base64_encode($png);
    }
}