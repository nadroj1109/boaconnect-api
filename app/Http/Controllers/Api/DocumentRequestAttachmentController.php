<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentRequestAttachmentResource;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestAttachment;
use App\Services\DocumentRequest\DocumentRequestAttachmentService;
use App\Services\DocumentRequest\DocumentAuthorizationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentRequestAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DocumentRequestAttachmentService $attachmentService,
        protected DocumentAuthorizationService $authorization,
    ) {
    }

    public function view(
        Request $request,
        DocumentRequest $documentRequest,
        DocumentRequestAttachment $attachment,
    ) {
        $user = $request->user();

        $this->authorization->authorizeRequest(
            $user,
            $documentRequest
        );

        $this->ensureAttachmentBelongsToRequest(
            $documentRequest,
            $attachment
        );

        if (
            ! $this->attachmentService->exists(
                $attachment
            )
        ) {
            abort(
                404,
                'Attachment file not found.'
            );
        }

        $disk = config(
            'filesystems.document_disk'
        );

        $stream = Storage::disk(
            $disk
        )->readStream(
            $attachment->path
        );

        if ($stream === false) {
            abort(
                404,
                'Attachment file not found.'
            );
        }

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);

                fclose($stream);
            },
            200,
            [
                'Content-Type' =>
                    $attachment->mime_type,

                'Content-Disposition' =>
                    'inline; filename="' .
                    addslashes(
                        $attachment->filename
                    ) .
                    '"',

                'Cache-Control' =>
                    'private, no-store',
            ]
        );
    }

    public function download(
        Request $request,
        DocumentRequest $documentRequest,
        DocumentRequestAttachment $attachment,
    ): StreamedResponse {

        $user = $request->user();

        $this->authorization->authorizeRequest(
            $user,
            $documentRequest
        );

        $this->ensureAttachmentBelongsToRequest(
            $documentRequest,
            $attachment
        );

        if (
            ! $this->attachmentService->exists(
                $attachment
            )
        ) {
            abort(
                404,
                'Attachment file not found.'
            );
        }

        $disk = config(
            'filesystems.document_disk'
        );

        return response()->streamDownload(
            function () use (
                $disk,
                $attachment,
            ) {
                $stream = Storage::disk(
                    $disk
                )->readStream(
                    $attachment->path
                );

                if ($stream === false) {
                    return;
                }

                fpassthru($stream);

                fclose($stream);
            },
            $attachment->filename,
            [
                'Content-Type' =>
                    $attachment->mime_type,

                'Content-Disposition' =>
                    'attachment; filename="' .
                    addslashes(
                        $attachment->filename
                    ) .
                    '"',
            ]
        );
    }

    public function destroy(
        Request $request,
        DocumentRequest $documentRequest,
        DocumentRequestAttachment $attachment,
    ) {

        $user = $request->user();

        $this->authorization->authorizeRequest(
            $user,
            $documentRequest
        );

        $this->ensureAttachmentBelongsToRequest(
            $documentRequest,
            $attachment
        );

        $this->attachmentService->delete(
            $attachment
        );

        return $this->success(
            null,
            'Document request attachment deleted successfully.'
        );
    }

    protected function ensureAttachmentBelongsToRequest(
        DocumentRequest $documentRequest,
        DocumentRequestAttachment $attachment,
    ): void {
        if (
            $attachment->document_request_id !==
            $documentRequest->id
        ) {
            abort(
                404,
                'Document request attachment not found.'
            );
        }
    }
}
