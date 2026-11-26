<?php

namespace App\Services\DocumentRequest;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestAttachment;
use App\Models\User;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentRequestAttachmentService
{
    public function upload(
        DocumentRequest $documentRequest,
        User $user,
        UploadedFile $file,
        int $requirementId,
    ): DocumentRequestAttachment {

        $uuid = (string) Str::uuid();

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $filename =
            $file->getClientOriginalName();

        $path = sprintf(
            'document-requests/%d/%s.%s',
            $documentRequest->id,
            $uuid,
            $extension,
        );

        $disk = config(
            'filesystems.document_disk'
        );

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        try {
            return DB::transaction(
                function () use (
                    $documentRequest,
                    $user,
                    $file,
                    $uuid,
                    $filename,
                    $path,
                    $requirementId,
                ) {
                    return DocumentRequestAttachment::create([
                        'uuid' =>
                            $uuid,

                        'document_request_id' =>
                            $documentRequest->id,

                        'document_type_requirement_id' =>
                            $requirementId,

                        'filename' =>
                            $filename,

                        'path' =>
                            $path,

                        'mime_type' =>
                            $file->getMimeType(),

                        'size' =>
                            $file->getSize(),

                        'uploaded_by' =>
                            $user->id,
                    ]);
                }
            );
        } catch (\Throwable $e) {

            Storage::disk($disk)
                ->delete($path);

            throw $e;
        }
    }

    public function delete(
        DocumentRequestAttachment $attachment,
    ): void {

        DB::transaction(
            function () use (
                $attachment,
            ) {

                Storage::disk(
                    config(
                        'filesystems.document_disk'
                    )
                )->delete(
                    $attachment->path
                );

                $attachment->delete();
            }
        );
    }

    public function exists(
        DocumentRequestAttachment $attachment,
    ): bool {

        return Storage::disk(
            config(
                'filesystems.document_disk'
            )
        )->exists(
            $attachment->path
        );
    }
}