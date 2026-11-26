<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\DocumentTypeRequirement;
use Illuminate\Support\Collection;

class DocumentTypeRequirementService
{
    public function index(
        DocumentType $documentType
    ): Collection {
        return $documentType
            ->requirements()
            ->get();
    }

    public function store(
        DocumentType $documentType,
        array $data
    ): DocumentTypeRequirement {
        return $documentType
            ->requirements()
            ->create($data);
    }

    public function show(
        DocumentType $documentType,
        DocumentTypeRequirement $requirement
    ): DocumentTypeRequirement {
        return $requirement;
    }

    public function update(
        DocumentTypeRequirement $requirement,
        array $data
    ): DocumentTypeRequirement {
        $requirement->update($data);

        return $requirement->refresh();
    }

    public function destroy(
        DocumentTypeRequirement $requirement
    ): void {
        $requirement->delete();
    }
}