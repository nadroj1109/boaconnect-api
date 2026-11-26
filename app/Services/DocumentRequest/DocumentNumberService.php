<?php

namespace App\Services\DocumentRequest;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
    /**
     * Assign a document number if one does not already exist.
     *
     * The DocumentType row lock serializes number generation
     * for requests of the same document type.
     */
    public function ensure(DocumentRequest $request): string
    {
        return DB::transaction(function () use ($request) {

            $lockedRequest = DocumentRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->document_no) {
                $request->document_no = $lockedRequest->document_no;

                return $lockedRequest->document_no;
            }

            $documentType = DocumentType::query()
                ->whereKey($lockedRequest->document_type_id)
                ->lockForUpdate()
                ->firstOrFail();

            $code = strtoupper(trim($documentType->code));

            $code = preg_replace('/[^A-Z0-9]+/', '-', $code);
            $code = trim($code, '-');

            if ($code === '') {
                throw new \RuntimeException(
                    'The document type must have a valid code before generating a document number.'
                );
            }

            $year = now()->year;

            $prefix = "{$code}-{$year}-";

            $lastDocumentNumber = DocumentRequest::withTrashed()
                ->where('document_type_id', $documentType->id)
                ->where('document_no', 'like', $prefix . '%')
                ->orderByDesc('id')
                ->value('document_no');

            $nextNumber = $lastDocumentNumber
                ? ((int) Str::afterLast($lastDocumentNumber, '-')) + 1
                : 1;

            $documentNumber = sprintf(
                '%s%06d',
                $prefix,
                $nextNumber
            );

            $lockedRequest->document_no = $documentNumber;
            $lockedRequest->save();

            $request->document_no = $documentNumber;

            return $documentNumber;
        });
    }
}
