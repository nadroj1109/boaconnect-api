<?php

namespace App\Http\Requests\User;

use App\Constants\Roles;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountType = $this->input('account_type');
        $accountSetup = $this->input('account_setup');

        $isBarangay = $accountType === 'barangay';
        $isStaff = $isBarangay && $this->staffRoleSelected();

        $isNewPerson =
            $isBarangay &&
            $accountSetup === 'new';

        $isExistingOfficial =
            $isBarangay &&
            !$isStaff &&
            $accountSetup === 'existing';

        $isExistingResident =
            $isStaff &&
            $accountSetup === 'existing_resident';

        return [
            'account_type' => [
                'required',
                Rule::in(['administrative', 'barangay']),
            ],

            'account_setup' => [
                Rule::requiredIf($isBarangay),
                'nullable',
                Rule::in(
                    $isStaff
                        ? ['new', 'existing_resident']
                        : ['new', 'existing']
                ),
            ],

            'first_name' => [
                Rule::requiredIf(
                    !$isExistingOfficial && !$isExistingResident
                ),
                'nullable',
                'string',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                Rule::requiredIf(
                    !$isExistingOfficial && !$isExistingResident
                ),
                'nullable',
                'string',
                'max:100',
            ],

            'ext_name' => [
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'mobile_number' => [
                'nullable',
                'string',
                'max:20',
            ],

            'barangay_id' => [
                'nullable',
                'integer',
                'exists:barangays,id',
                Rule::requiredIf($isBarangay),
            ],

            'official_position_id' => [
                'nullable',
                'integer',
                'exists:official_positions,id',
                Rule::requiredIf(
                    $isNewPerson && !$isStaff
                ),
            ],

            'barangay_official_id' => [
                'nullable',
                'integer',
                'exists:barangay_officials,id',
                Rule::requiredIf($isExistingOfficial),
            ],

            'resident_id' => [
                'nullable',
                'integer',
                'exists:residents,id',
                Rule::requiredIf($isExistingResident),
            ],

            'roles' => [
                'required',
                'array',
                'min:1',
            ],

            'roles.*' => [
                'required',
                'integer',
                Rule::exists('roles', 'id'),
            ],
        ];
    }

    private function staffRoleSelected(): bool
    {
        $roleIds = collect($this->input('roles', []))
            ->map(fn ($role) => is_array($role)
                ? ($role['id'] ?? $role['value'] ?? null)
                : $role)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return !empty($roleIds) &&
            DB::table('roles')
                ->whereIn('id', $roleIds)
                ->where('name', Roles::STAFF)
                ->exists();
    }
}
