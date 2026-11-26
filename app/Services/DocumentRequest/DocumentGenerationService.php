<?php

namespace App\Services\DocumentRequest;

use App\Models\DocumentRequest;
use App\Models\User;

use App\Services\DocumentTemplateRenderer;

use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentGenerationService
{
    public function __construct(
        protected DocumentTemplateRenderer $renderer
    ) {
    }

    public function generate(
        DocumentRequest $request,
        ?User $signer = null,
        ?string $verificationQr = null,
        ?string $signerName = null,
    ): string {

        $request->loadMissing([
            'documentType',
            'documentTemplate',
            'resident',
            'requestedFor',
            'barangay',
            'fields.documentTypeField',
        ]);

        $template =
            $request->documentTemplate;

        if (! $template) {

            throw ValidationException::withMessages([
                'template' =>
                    'No document template has been configured for this document request.',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | SIGNATURE
        |--------------------------------------------------------------------------
        */

        $signatureData = null;

        if ($signer?->signature_path) {

            $disk = $this->disk();

            if (
                ! Storage::disk($disk)->exists(
                    $signer->signature_path
                )
            ) {

                throw ValidationException::withMessages([
                    'signature' =>
                        'The signer signature file could not be found.',
                ]);
            }

            $signatureContents =
                Storage::disk($disk)->get(
                    $signer->signature_path
                );

            $signatureData =
                'data:image/png;base64,' .
                base64_encode(
                    $signatureContents
                );
        }


        /*
        |--------------------------------------------------------------------------
        | RENDER DYNAMIC TEMPLATE CONTENT
        |--------------------------------------------------------------------------
        */

        $content =
            $this->renderer->render(
                $request
            );


        /*
        |--------------------------------------------------------------------------
        | SYSTEM DOCUMENT LAYOUT
        |--------------------------------------------------------------------------
        */

        $html = view(
            'documents.document',
            [
                'request' =>
                    $request,

                'content' =>
                    $content,

                'preview' =>
                    false,

                'signature_data' =>
                    $signatureData,

                'signer_name' => $signerName ?? $signer?->name,

                'verification_qr' =>
                    $verificationQr,

                /*
                 * Supply actual barangay logo values
                 * once the existing Barangay logo fields
                 * are confirmed.
                 */
                'logo_left' =>
                    null,

                'logo_right' =>
                    null,
            ]
        )->render();


        /*
        |--------------------------------------------------------------------------
        | GENERATE PDF
        |--------------------------------------------------------------------------
        */

        return Pdf::loadHTML(
            $html
        )
            ->setPaper(
                $template->page_size,
                $template->orientation
            )
            ->output();
    }

    protected function disk(): string
    {
        return config(
            'filesystems.document_disk',
            'local'
        );
    }
}