<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\StoreComplaintAttachmentRequest;
use App\Http\Resources\ComplaintAttachmentResource;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\Complaint\ComplaintAttachmentService;
use App\Services\Complaint\ComplaintAuthorizationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ComplaintAttachmentService $attachmentService,
        protected ComplaintAuthorizationService $authorization,
    ) {
    }

    public function store(
        StoreComplaintAttachmentRequest $request,
        Complaint $complaint,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeUploadAttachment(
                $user,
                $complaint
            );

        $attachment =
            $this->attachmentService->upload(
                complaint: $complaint,
                user: $user,
                file: $request->file('file'),
            );

        $attachment->load([
            'uploadedBy',
        ]);

        return $this->success(
            new ComplaintAttachmentResource(
                $attachment
            ),
            'Complaint attachment uploaded successfully.'
        );
    }

    public function view(
        Request $request,
        Complaint $complaint,
        ComplaintAttachment $attachment,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeViewAttachment(
                $user,
                $complaint
            );

        $this->ensureAttachmentBelongsToComplaint(
            $complaint,
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
        Complaint $complaint,
        ComplaintAttachment $attachment,
    ): StreamedResponse {
        $user = $request->user();

        $this->authorization
            ->authorizeViewAttachment(
                $user,
                $complaint
            );

        $this->ensureAttachmentBelongsToComplaint(
            $complaint,
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
        Complaint $complaint,
        ComplaintAttachment $attachment,
    ) {
        $user = $request->user();

        $this->authorization
            ->authorizeDeleteAttachment(
                $user,
                $complaint
            );

        $this->ensureAttachmentBelongsToComplaint(
            $complaint,
            $attachment
        );

        $this->attachmentService->delete(
            $attachment
        );

        return $this->success(
            null,
            'Complaint attachment deleted successfully.'
        );
    }

    protected function ensureAttachmentBelongsToComplaint(
        Complaint $complaint,
        ComplaintAttachment $attachment,
    ): void {
        if (
            $attachment->complaint_id !==
            $complaint->id
        ) {
            abort(
                404,
                'Complaint attachment not found.'
            );
        }
    }
}
