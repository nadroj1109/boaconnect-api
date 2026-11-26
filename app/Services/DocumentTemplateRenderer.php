<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Services\DocumentFieldOptionService;

class DocumentTemplateRenderer
{
    public function __construct(
        protected DocumentFieldOptionService $fieldOptions
    ) {
    }

    public function render(
        DocumentRequest $request
    ): string {
        $request->loadMissing([
            'documentTemplate',
            'resident',
            'requestedFor.household',
            'fields.documentTypeField',
        ]);

        $template =
            $request->documentTemplate;

        if (! $template) {
            throw new \RuntimeException(
                'No document template is assigned to this request.'
            );
        }

        $variables =
            $this->buildVariables($request);

        $templateContent = html_entity_decode(
            $template->content,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $templateContent = str_replace(
            [
                "\u{00A0}",
                "\u{202F}",
            ],
            ' ',
            $templateContent
        );

        $templateContent = $this->normalizeQuillAlignment(
            $templateContent
        );

        // \Log::info(
        //     'Document Template Raw Placeholder',
        //     [
        //         'content' => $template->content,

        //         'encoded' => json_encode(
        //             $template->content
        //         ),

        //         'business_position' =>
        //             strpos(
        //                 $template->content,
        //                 'fields.business_name'
        //             ),
        //     ]
        // );

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/u',
            function ($matches) use ($variables) {

                $key =
                    $matches[1];

                $value =
                    $variables[$key] ?? '';

                return e(
                    $this->normalizeValue($value)
                );
            },
            $templateContent
        );
    }

    protected function buildVariables(
        DocumentRequest $request
    ): array {
        $variables = [];

        /*
        |--------------------------------------------------------------------------
        | Request
        |--------------------------------------------------------------------------
        */

        $variables['request.id'] =
            $request->id;

        $variables['request.request_no'] =
            $request->request_no;

        $variables['request.document_no'] =
            $request->document_no;

        $variables['request.purpose'] =
            $request->purpose;

        $variables['request.requested_at'] =
            $request->requested_at?->format(
                'F d, Y'
            );

        $variables['request.approved_at'] =
            $request->approved_at?->format(
                'F d, Y'
            );

        $variables['request.generated_at'] =
            $request->generated_at?->format(
                'F d, Y'
            );

        $variables['request.signed_at'] =
            $request->signed_at?->format(
                'F d, Y'
            );

        /*
        |--------------------------------------------------------------------------
        | Barangay
        |--------------------------------------------------------------------------
        */

        $barangay = $request->barangay;

        if ($barangay) {
            $variables['barangay.id'] =
                $barangay->id;

            $variables['barangay.name'] =
                $barangay->barangay;

            $variables['barangay.barangay'] =
                $barangay->barangay;

            $variables['barangay.municipality'] =
                $barangay->city?->city;

            $variables['barangay.city'] =
                $barangay->city?->city;

            $variables['barangay.province'] =
                $barangay->province?->province;
        }

        /*
        |--------------------------------------------------------------------------
        | Resident
        |--------------------------------------------------------------------------
        */

        if ($request->resident) {

            $resident =
                $request->resident;

            $variables['resident.id'] =
                $resident->id;

            $variables['resident.name'] =
                $resident->name;

            $variables['resident.full_name'] =
                trim(
                    implode(
                        ' ',
                        array_filter([
                            $resident->first_name,
                            $resident->middle_name,
                            $resident->last_name,
                            $resident->ext_name,
                        ])
                    )
                );

            $variables['resident.first_name'] =
                $resident->first_name;

            $variables['resident.middle_name'] =
                $resident->middle_name;

            $variables['resident.last_name'] =
                $resident->last_name;

            $variables['resident.ext_name'] =
                $resident->ext_name;

            $variables['resident.address'] =
                $this->buildResidentAddress(
                    $resident
                );

            $variables['resident.mobile_no'] =
                $resident->mobile_no;

            $variables['resident.email_address'] =
                $resident->email_address;

            $variables['resident.sex'] =
                $resident->sex;

            $variables['resident.gender'] =
                $resident->gender;

            $variables['resident.civil_status'] =
                $resident->civil_status;

            $variables['resident.date_of_birth'] =
                $resident->date_of_birth
                    ?->format('F d, Y');

            $variables['resident.age'] =
                $resident->date_of_birth
                    ? $resident->date_of_birth->age
                    : null;
        }

        /*
        |--------------------------------------------------------------------------
        | Household
        |--------------------------------------------------------------------------
        */

        $household =
            $request->requestedFor?->household;

        if ($household) {

            $variables['household.id'] =
                $household->id;

            $variables['household.household_no'] =
                $household->household_no;

            $variables['household.name'] =
                $household->household_name;

            $variables['household.address'] =
                $household->complete_address;

            $variables['household.complete_address'] =
                $household->complete_address;

            $variables['household.no_of_family'] =
                $household->no_of_family;

            $variables['household.no_of_hh_member'] =
                $household->no_of_hh_member;

            $variables['household.monthly_income'] =
                $household->monthly_income;
        }

        /*
        |--------------------------------------------------------------------------
        | Dynamic Document Fields
        |--------------------------------------------------------------------------
        */

        // \Log::info(
        //     'Document Template Fields',
        //     [
        //         'request_id' =>
        //             $request->id,

        //         'fields' =>
        //             $request->fields
        //                 ->map(fn ($field) => [
        //                     'id' =>
        //                         $field->id,

        //                     'field_key' =>
        //                         $field->documentTypeField
        //                             ?->field_key,

        //                     'value' =>
        //                         $field->value,
        //                 ])
        //                 ->toArray(),
        //     ]
        // );

        foreach (
            $request->fields
            as $field
        ) {

            $documentTypeField =
                $field->documentTypeField;

            $key =
                $documentTypeField?->field_key;

            if (! $key) {
                continue;
            }

            $variables[
                "fields.{$key}"
            ] = $this->fieldOptions->resolveValue(
                $documentTypeField,
                $field->value
            );
        }

        return $variables;
    }

    protected function buildResidentAddress(
        $resident
    ): string {
        return collect([
            $resident->house_no,
            $resident->street_name,
            $resident->subdivision,
            $resident->sitio_purok,
        ])
            ->filter(
                fn ($value) =>
                    $value !== null &&
                    $value !== ''
            )
            ->implode(', ');
    }

    protected function normalizeValue(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return implode(
                ', ',
                array_map(
                    fn ($item) =>
                        $this->normalizeValue($item),
                    $value
                )
            );
        }

        return (string) $value;
    }

    protected function normalizeQuillAlignment(
        string $content
    ): string {
        $content = preg_replace(
            '/class="([^"]*\bql-align-center\b[^"]*)"/',
            'class="$1" style="text-align: center;"',
            $content
        );

        $content = preg_replace(
            '/class="([^"]*\bql-align-right\b[^"]*)"/',
            'class="$1" style="text-align: right;"',
            $content
        );

        $content = preg_replace(
            '/class="([^"]*\bql-align-justify\b[^"]*)"/',
            'class="$1" style="text-align: justify;"',
            $content
        );

        return $content;
    }
}