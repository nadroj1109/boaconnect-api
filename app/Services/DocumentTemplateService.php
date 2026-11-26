<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DocumentTemplateService
{
    public function index(
        array $params = []
    ): LengthAwarePaginator {
        $query = DocumentTemplate::query();

        if (!empty($params['search'])) {
            $search = $params['search'];

            $query->where(function ($query) use ($search) {
                $query
                    ->where(
                        'name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'description',
                        'like',
                        "%{$search}%"
                    );
            });
        }

        return $query
            ->orderBy(
                $params['sort'] ?? 'name',
                $params['direction'] ?? 'asc'
            )
            ->paginate(
                $params['per_page'] ?? 10
            );
    }

    public function store(
        array $data
    ): DocumentTemplate {
        return DocumentTemplate::create($data);
    }

    public function show(
        DocumentTemplate $documentTemplate
    ): DocumentTemplate {
        return $documentTemplate->load([
            'documentTypes.fields',
        ]);
    }

    public function update(
        DocumentTemplate $documentTemplate,
        array $data
    ): DocumentTemplate {
        $documentTemplate->update($data);

        return $documentTemplate->refresh();
    }

    public function destroy(
        DocumentTemplate $documentTemplate
    ): void {
        $documentTemplate->delete();
    }

    public function available()
    {
        return DocumentTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}