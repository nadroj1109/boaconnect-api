<?php

namespace App\Services\Complaint;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintAttachmentService
{
    public function upload(
        Complaint $complaint,
        User $user,
        UploadedFile $file,
    ): ComplaintAttachment {
        $uuid = (string) Str::uuid();

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $filename =
            $file->getClientOriginalName();

        $path = sprintf(
            'complaints/%d/%s.%s',
            $complaint->id,
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
            return DB::transaction(function () use (
                $complaint,
                $user,
                $file,
                $uuid,
                $filename,
                $path,
            ) {
                return ComplaintAttachment::create([
                    'uuid' =>
                        $uuid,

                    'complaint_id' =>
                        $complaint->id,

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
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    public function delete(
        ComplaintAttachment $attachment,
    ): void {
        DB::transaction(function () use (
            $attachment,
        ) {
            Storage::disk(
                config('filesystems.document_disk')
            )->delete(
                $attachment->path
            );

            $attachment->delete();
        });
    }

    public function exists(
        ComplaintAttachment $attachment,
    ): bool {
        return Storage::disk(
            config('filesystems.document_disk')
        )->exists(
            $attachment->path
        );
    }
}