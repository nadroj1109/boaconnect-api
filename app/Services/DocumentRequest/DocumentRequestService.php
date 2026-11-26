<?php

namespace App\Services\DocumentRequest;

use App\Constants\Roles;
use App\Enums\DocumentRequestSource;
use App\Enums\DocumentRequestStatus;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestAttachment;
use App\Models\DocumentRequestLog;
use App\Models\DocumentType;
use App\Models\Resident;
use App\Models\User;
use App\Services\DocumentRequest\DocumentRequestAttachmentService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;

class DocumentRequestService
{
    public function __construct(
        protected DocumentRequestAttachmentService $attachmentService,
    ) {
    }

    /**
     * Create a new document request.
     */
    public function create(
        User $user,
        Resident $requester,
        Resident $requestedFor,
        int $documentTypeId,
        int $barangayId,
        string $purpose,
        string $deliveryMethod,
        ?string $remarks = null,
        array $fields = [],
        array $attachments = [],
        DocumentRequestSource $source = DocumentRequestSource::ONLINE
    ): DocumentRequest {

        return DB::transaction(
            function () use (
                $user,
                $requester,
                $requestedFor,
                $documentTypeId,
                $barangayId,
                $purpose,
                $deliveryMethod,
                $remarks,
                $fields,
                $attachments,
                $source,
            ) {

                $documentType = DocumentType::query()
                    ->with([
                        'documentTemplate',
                        'fields',
                        'requirements',
                    ])
                    ->where('is_active', true)
                    ->findOrFail(
                        $documentTypeId
                    );

                if (
                    ! $documentType->documentTemplate
                ) {
                    throw ValidationException::withMessages([
                        'document_type' =>
                            'No document template has been configured for this document type.',
                    ]);
                }

                $request = DocumentRequest::create([
                    'request_no' => $this->generateRequestNumber(),
                    'source' => $source,
                    'barangay_id' => $barangayId,
                    'resident_id' => $requester->id,
                    'requested_for_resident_id' => $requestedFor->id,
                    'document_type_id' => $documentType->id,
                    'document_template_id' => $documentType->documentTemplate->id,
                    'purpose' => $purpose,
                    'remarks' => $remarks,
                    'status' => DocumentRequestStatus::PENDING,
                    'delivery_method' => $deliveryMethod,
                    'requested_at' => now(),
                ]);

                $this->storeFields(
                    $request,
                    $documentType,
                    $fields
                );

                $this->storeAttachments(
                    $request,
                    $documentType,
                    $attachments,
                    $user
                );

                $this->log(
                    $request,
                    DocumentRequestStatus::PENDING
                );

                return $request;
            }
        );
    }

    /**
     * Find a document request by its ID.
     */
    public function find(int $id): DocumentRequest
    {
        return DocumentRequest::query()
            ->with([
                'documentType',
                'documentTemplate',
                'resident',
                'requestedFor',
                'payments',
                'files',
                'approver',
                'generator',
                'signer',
                'releaser',
                'logs',
                'fields.documentTypeField',
                'attachments.requirement',
                'attachments.uploadedBy',
            ])
            ->findOrFail($id);
    }

    /**
     * Find a document request for administrative viewing.
     *
     * Super Administrators and Administrators can view
     * requests across all barangays.
     *
     * Chairpersons, Secretaries, and Staff are restricted
     * to their assigned barangay.
     */
    public function findForAdmin(
        User $user,
        int $id
    ): DocumentRequest {

        $query = DocumentRequest::query()
            ->with([
                'documentType',
                'documentTemplate',
                'resident',
                'requestedFor',
                'payments.receivedBy',
                'files.blockchainAnchors',
                'approver',
                'generator',
                'signer',
                'releaser',
                // 'logs.performedBy',
                'logs' => function ($query) {
                    $query
                        ->with('performedBy')
                        ->orderBy('created_at');
                },
                'fields.documentTypeField',
                'attachments.requirement',
                'attachments.uploadedBy',
            ]);

        /*
         * Restrict administrative users to their
         * assigned barangay.
         */
        if (! $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ADMINISTRATOR,
        ])) {

            $barangayId =
                $user->resident?->barangay_id
                ?? $user->barangay_id;

            if (! $barangayId) {

                throw ValidationException::withMessages([
                    'barangay' =>
                        'The user is not associated with a barangay.',
                ]);

            }

            $query->where(
                'barangay_id',
                $barangayId
            );
        }

        return $query->findOrFail($id);
    }

    /**
     * Find a document request by its request number.
     */
    public function findByRequestNumber(
        string $requestNo
    ): DocumentRequest {
        return DocumentRequest::query()
            ->with([
                'documentType',
                'documentTemplate',
                'resident',
                'requestedFor',
                'payments',
                'files',
                'approver',
                'generator',
                'signer',
                'releaser',
                'logs',
                'fields.documentTypeField',
            ])
            ->where(
                'request_no',
                $requestNo
            )
            ->firstOrFail();
    }

    /**
     * Get requests belonging to a resident.
     */
    public function forResident(
        Resident $resident,
        int $perPage = 15
    ): LengthAwarePaginator {
        return DocumentRequest::query()
            ->with([
                'documentType',
                'documentTemplate',
                'requestedFor',
                'payments',
            ])
            ->where(function ($query) use ($resident) {

                $query
                    ->where(
                        'resident_id',
                        $resident->id
                    )
                    ->orWhere(
                        'requested_for_resident_id',
                        $resident->id
                    );

            })
            ->latest('requested_at')
            ->paginate($perPage);
    }

    /**
     * Get document requests for administrative listing.
     *
     * Super Administrators and Administrators can view
     * requests across all barangays.
     *
     * Chairpersons, Secretaries, and Staff are restricted
     * to their assigned barangay.
     */
    public function paginate(
        User $user,
        array $filters,
    ): LengthAwarePaginator {
        $query = DocumentRequest::query()
            ->with([
                'documentType',
                'documentTemplate',
                'resident',
                'requestedFor',
            ]);

        /*
        * Restrict administrative users to their
        * assigned barangay.
        */
        if (! $user->hasAnyRole([
            Roles::SUPER_ADMIN,
            Roles::ADMINISTRATOR,
        ])) {

            $barangayId = $user->resident?->barangay_id ?? $user->barangay_id;

            if (! $barangayId) {
                throw ValidationException::withMessages([
                    'barangay' =>
                        'The user is not associated with a barangay.',
                ]);
            }

            $query->where(
                'barangay_id',
                $barangayId
            );
        }

        $query->when(
            $filters['search'] ?? null,
            function ($query, $search) {

                $query->where(function ($query) use ($search) {

                    $query
                        ->where(
                            'request_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'purpose',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'resident',
                            function ($query) use ($search) {

                                $query->where(
                                    'first_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$search}%"
                                );

                            }
                        )
                        ->orWhereHas(
                            'requestedFor',
                            function ($query) use ($search) {

                                $query->where(
                                    'first_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    "%{$search}%"
                                );

                            }
                        )
                        ->orWhereHas(
                            'documentType',
                            function ($query) use ($search) {

                                $query
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        "%{$search}%"
                                    );

                            }
                        );

                });

            }
        );

        $query->when(
            $filters['status'] ?? null,
            fn ($query, $status) =>
                $query->where('status', $status)
        );

        $query->when(
            $filters['document_type_id'] ?? null,
            fn ($query, $documentTypeId) =>
                $query->where(
                    'document_type_id',
                    $documentTypeId
                )
        );

        return $query
            ->orderBy(
                $filters['sort'],
                $filters['direction']
            )
            ->paginate(
                $filters['per_page']
            );
    }

    protected function generateRequestNumber(): string
    {
        $year = now()->year;

        $lastRequest = DocumentRequest::query()
            ->whereYear('created_at', $year)
            ->where('request_no', 'like', "DR-{$year}-%")
            ->orderByDesc('id')
            ->first();

        $nextNumber = $lastRequest
            ? ((int) substr($lastRequest->request_no, -6)) + 1
            : 1;

        return sprintf(
            'DR-%d-%06d',
            $year,
            $nextNumber
        );
    }

    protected function log(
        DocumentRequest $request,
        DocumentRequestStatus $status,
        ?string $remarks = null,
        ?int $performedBy = null,
    ): DocumentRequestLog {
        return DocumentRequestLog::create([
            'document_request_id' =>
                $request->id,

            'status' =>
                $status,

            'remarks' =>
                $remarks,

            'performed_by' =>
                $performedBy,
        ]);
    }

    protected function storeFields(
        DocumentRequest $request,
        DocumentType $documentType,
        array $submittedFields
    ): void {
        foreach (
            $documentType->fields
            as $field
        ) {

            if (! $field->is_active) {
                continue;
            }

            $value =
                $submittedFields[
                    $field->field_key
                ] ?? null;

            if (
                $field->is_required &&
                ($value === null || $value === '')
            ) {
                throw ValidationException::withMessages([
                    "fields.{$field->field_key}" =>
                        "{$field->name} is required.",
                ]);
            }

            $request->fields()->create([
                'document_type_field_id' =>
                    $field->id,

                'value' =>
                    is_array($value)
                        ? json_encode($value)
                        : $value,
            ]);
        }
    }

    protected function storeAttachments(
        DocumentRequest $request,
        DocumentType $documentType,
        array $attachments,
        User $user,
    ): void {
        foreach (
            $documentType->requirements
            as $requirement
        ) {

            if (! $requirement->is_active) {
                continue;
            }

            $file =
                $attachments[$requirement->id]
                ?? null;

            if (
                $requirement->is_required &&
                ! $file
            ) {
                throw ValidationException::withMessages([
                    "attachments.{$requirement->id}" =>
                        "{$requirement->name} is required.",
                ]);
            }

            if (! $file) {
                continue;
            }

            $this->validateAttachment(
                $file,
                $requirement
            );

            $this->attachmentService->upload(
                documentRequest: $request,
                user: $user,
                file: $file,
                requirementId: $requirement->id,
            );
        }
    }

    protected function validateAttachment(
        UploadedFile $file,
        $requirement
    ): void {
        $allowedExtensions = collect(
            explode(
                ',',
                $requirement->allowed_file_types ?? ''
            )
        )
            ->map(
                fn ($type) =>
                    strtolower(
                        trim($type)
                    )
            )
            ->filter()
            ->values();

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if (
            $allowedExtensions->isNotEmpty() &&
            ! $allowedExtensions->contains(
                $extension
            )
        ) {
            throw ValidationException::withMessages([
                "attachments.{$requirement->id}" =>
                    "{$requirement->name} only accepts: " .
                    $allowedExtensions->implode(', ') .
                    '.',
            ]);
        }

        if (
            $requirement->max_file_size &&
            $file->getSize() >
                ($requirement->max_file_size * 1024)
        ) {
            throw ValidationException::withMessages([
                "attachments.{$requirement->id}" =>
                    "{$requirement->name} must not exceed " .
                    $requirement->max_file_size .
                    ' KB.',
            ]);
        }
    }
}