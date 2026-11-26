<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\DocumentFile;

use App\Services\DocumentRequest\DocumentAuthorizationService;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    public function __construct(
        protected DocumentAuthorizationService $authorization,
    ) {
    }

    /**
     * View a generated document inline.
     */
    public function view(
        Request $request,
        string $uuid,
    ): StreamedResponse {
        $file = $this->findFile($uuid);

        $this->authorization->authorizeFile(
            $request->user(),
            $file,
        );

        if (! $file->exists()) {
            abort(
                Response::HTTP_NOT_FOUND,
                'The requested document could not be found.'
            );
        }

        return response()->stream(
            function () use ($file) {

                $stream = $file->storageDisk()
                    ->readStream($file->path);

                if ($stream === false) {
                    abort(
                        Response::HTTP_NOT_FOUND,
                        'The requested document could not be read.'
                    );
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            Response::HTTP_OK,
            [
                'Content-Type' =>
                    $file->mimeType(),

                'Content-Disposition' =>
                    'inline; filename="' .
                    addslashes(
                        $file->downloadName()
                    ) .
                    '"',

                'Content-Length' =>
                    (string) $file->sizeInBytes(),

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',

                'Pragma' =>
                    'no-cache',
            ]
        );
    }

    /**
     * Download a generated document.
     */
    public function download(
        Request $request,
        string $uuid,
    ): StreamedResponse {
        $file = $this->findFile($uuid);

        $this->authorization->authorizeDownload(
            $request->user(),
            $file,
        );

        if (! $file->exists()) {
            abort(
                Response::HTTP_NOT_FOUND,
                'The requested document could not be found.'
            );
        }

        return response()->streamDownload(
            function () use ($file) {

                $stream = $file->storageDisk()
                    ->readStream($file->path);

                if ($stream === false) {
                    abort(
                        Response::HTTP_NOT_FOUND,
                        'The requested document could not be read.'
                    );
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $file->downloadName(),
            [
                'Content-Type' =>
                    $file->mimeType(),

                'Content-Length' =>
                    (string) $file->sizeInBytes(),

                'X-Content-Type-Options' =>
                    'nosniff',

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',

                'Pragma' =>
                    'no-cache',
            ]
        );
    }

    /**
     * Find a document file using its public UUID.
     */
    protected function findFile(
        string $uuid,
    ): DocumentFile {
        return DocumentFile::query()
            ->with('documentRequest')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
