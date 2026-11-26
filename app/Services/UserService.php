<?php

namespace App\Services;

use App\Constants\Roles;
use App\Models\BarangayOfficial;
use App\Models\Resident;
use App\Models\User;
use App\Mail\AccountActivationMail;
use App\Services\Auth\ActivationService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(

        protected ActivationService $activationService

    ) {}

    /*
    |--------------------------------------------------------------------------
    | List Users
    |--------------------------------------------------------------------------
    */

    public function paginate(array $filters): LengthAwarePaginator
    {
        return User::query()

            ->with([
                'resident',
                'resident.barangayOfficials.position',
                'roles',
                'officialPosition',
            ])

            ->whereDoesntHave(
                'roles',
                function ($query) {
                    $query->where(
                        'name',
                        Roles::SUPER_ADMIN
                    );
                }
            )

            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {

                    $query->where(function ($query) use ($search) {

                        $query
                            ->where(
                                'display_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'mobile_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'resident',
                                function ($resident) use ($search) {

                                    $resident
                                        ->where(
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
                            );

                    });

                }
            )

            ->when(
                isset($filters['status']),
                fn ($query) =>
                    $query->where(
                        'is_active',
                        $filters['status']
                    )
            )

            // ->when(
            //     $filters['role'] ?? null,
            //     function ($query, $role) {

            //         $query->role($role);

            //     }
            // )

            ->when(
                $filters['role_id'] ?? null,
                function ($query, $roleId) {
                    $query->whereHas(
                        'roles',
                        function ($roleQuery) use ($roleId) {
                            $roleQuery->where(
                                'roles.id',
                                $roleId
                            );
                        }
                    );
                }
            )

            ->orderBy(
                $filters['sort'],
                $filters['direction']
            )

            ->paginate(
                $filters['per_page']
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(User $user): User
    {
        return $user->load([
            'resident',
            'resident.barangayOfficials.position',
            'roles',
            'officialPosition',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {

            $accountType = $data['account_type'];
            $accountSetup = $data['account_setup'] ?? null;
            $roles = $data['roles'];

            /*
            |--------------------------------------------------------------------------
            | Existing Resident — Staff Account
            |--------------------------------------------------------------------------
            */

            if (
                $accountType === 'barangay' &&
                $accountSetup === 'existing_resident'
            ) {
                if (!$this->hasRoleId($roles, Roles::STAFF)) {
                    throw ValidationException::withMessages([
                        'roles' => 'Existing Resident setup is only available for Staff accounts.',
                    ]);
                }

                $resident = Resident::query()
                    ->whereKey($data['resident_id'])
                    ->whereHas('household', function ($query) use ($data) {
                        $query->where('barangay_id', $data['barangay_id']);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$resident) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'The selected resident does not belong to the selected barangay.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate user links
                |--------------------------------------------------------------------------
                */

                if ($resident->user()->exists()) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'The selected resident already has a user account.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Remove frontend-only and resident identity fields
                |--------------------------------------------------------------------------
                */

                unset(
                    $data['account_type'],
                    $data['account_setup'],
                    $data['roles'],
                    $data['resident_id'],
                    $data['barangay_official_id'],
                    $data['official_position_id'],
                    $data['first_name'],
                    $data['middle_name'],
                    $data['last_name'],
                    $data['ext_name']
                );

                $activationToken = $this->activationService->generateToken();

                $user = User::create([
                    ...$data,

                    'display_name' => $resident->full_name,
                    'resident_id' => $resident->id,
                    'barangay_id' => $data['barangay_id'],
                    'official_position_id' => null,
                    'onboarding_required' => false,

                    'password' => Hash::make($this->generatePassword()),
                    'is_active' => false,
                    'activation_token' => $activationToken,
                    'activation_expires_at' => $this->activationService->expiration(),
                ]);

                $user->syncRoles($roles);

                $this->activationService->sendActivation($user);

                return $user->load([
                    'resident',
                    'roles',
                    'officialPosition',
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Existing Barangay Official
            |--------------------------------------------------------------------------
            */

            if (
                $accountType === 'barangay' &&
                $accountSetup === 'existing'
            ) {

                $official =
                    BarangayOfficial::query()
                        ->with([
                            'resident',
                            'barangay',
                        ])
                        ->where(
                            'id',
                            $data['barangay_official_id']
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Verify the selected official belongs to
                | the selected barangay.
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $official->barangay_id !==
                    (int) $data['barangay_id']
                ) {
                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official does not belong to the selected barangay.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Verify the official has a resident.
                |--------------------------------------------------------------------------
                */

                if (!$official->resident) {
                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official is not linked to a resident.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate User accounts.
                |--------------------------------------------------------------------------
                */

                $resident = Resident::query()
                    ->whereKey($official->resident_id)
                    ->lockForUpdate()
                    ->first();

                if (!$resident) {
                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official is not linked to a valid resident.',
                    ]);
                }

                if ($resident->user()->exists()) {
                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official already has a user account.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Remove provisioning-only fields.
                |--------------------------------------------------------------------------
                */

                unset(
                    $data['account_type'],
                    $data['account_setup'],
                    $data['barangay_official_id'],
                    $data['official_position_id'],
                    $data['first_name'],
                    $data['middle_name'],
                    $data['last_name'],
                    $data['ext_name'],
                    $data['roles']
                );

                $plainPassword =
                    $this->generatePassword();

                $activationToken =
                    $this->activationService
                        ->generateToken();

                $activationExpires =
                    $this->activationService
                        ->expiration();

                $userData = [
                    ...$data,

                    /*
                    |--------------------------------------------------------------------------
                    | Existing Resident
                    |--------------------------------------------------------------------------
                    */

                    'resident_id' =>
                        $official->resident_id,

                    'barangay_id' =>
                        $official->barangay_id,

                    /*
                    |--------------------------------------------------------------------------
                    | Existing official already has a
                    | permanent position record.
                    |--------------------------------------------------------------------------
                    */

                    'official_position_id' =>
                        null,

                    /*
                    |--------------------------------------------------------------------------
                    | No onboarding required because
                    | the Resident/Official already exists.
                    |--------------------------------------------------------------------------
                    */

                    'onboarding_required' =>
                        false,

                    'password' =>
                        Hash::make(
                            $plainPassword
                        ),

                    'is_active' =>
                        false,

                    'activation_token' =>
                        $activationToken,

                    'activation_expires_at' =>
                        $activationExpires,
                ];

                /*
                |--------------------------------------------------------------------------
                | Use the existing Resident's identity
                | as the User display name.
                |--------------------------------------------------------------------------
                */

                $userData['display_name'] =
                    $official->resident->full_name;

                $user =
                    User::create($userData);

                $user->syncRoles($roles);

                $this->activationService
                    ->sendActivation($user);

                return $user->load([
                    'resident',
                    'roles',
                    'officialPosition',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | New Person / Administrative User
            |--------------------------------------------------------------------------
            */

            unset(
                $data['account_type'],
                $data['account_setup'],
                $data['roles'],
                $data['barangay_official_id']
            );

            $isStaff = $accountType === 'barangay' && $this->hasRoleId($roles, Roles::STAFF);

            $plainPassword =
                $this->generatePassword();

            $activationToken =
                $this->activationService
                    ->generateToken();

            $activationExpires =
                $this->activationService
                    ->expiration();

            $displayName = trim(
                implode(
                    ' ',
                    array_filter([
                        $data['first_name'] ?? null,
                        $data['middle_name'] ?? null,
                        $data['last_name'] ?? null,
                        $data['ext_name'] ?? null,
                    ])
                )
            );

            $userData = [
                ...$data,

                'display_name' =>
                    $displayName,

                'password' =>
                    Hash::make(
                        $plainPassword
                    ),

                'is_active' =>
                    false,

                'activation_token' =>
                    $activationToken,

                'activation_expires_at' =>
                    $activationExpires,

                'onboarding_required' =>
                    $accountType === 'barangay',

                'resident_id' =>
                    null,

                'official_position_id' =>
                    $accountType === 'barangay' && !$isStaff
                        ? $data['official_position_id']
                        : null,
            ];

            $user =
                User::create($userData);

            $user->syncRoles($roles);

            $this->activationService
                ->sendActivation($user);

            return $user->load([
                'resident',
                'roles',
                'officialPosition',
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    public function update(
        User $user,
        array $data
    ): User {

        return DB::transaction(function () use (
            $user,
            $data
        ) {

            /*
            |--------------------------------------------------------------------------
            | Determine requested account configuration
            |--------------------------------------------------------------------------
            */

            $accountType =
                $data['account_type'];

            $accountSetup =
                $data['account_setup']
                ?? null;

            $roles =
                $data['roles'];

            $barangayOfficialId =
                $data['barangay_official_id']
                ?? null;

            /*
            |--------------------------------------------------------------------------
            | Determine current account configuration
            |--------------------------------------------------------------------------
            */

            $user->loadMissing([
                'resident.barangayOfficials',
                'roles',
            ]);

            $currentIsNewPerson =
                (bool) $user->onboarding_required;

            $currentOfficial = $user->resident
                ?->barangayOfficials
                ->first(
                    fn ($official) => $official->is_active
                );

            $currentIsExistingOfficial =
                $user->resident_id !== null &&
                $currentOfficial !== null &&
                !$currentIsNewPerson;

            $currentIsStaff =
                $user->hasRole(Roles::STAFF);

            $currentIsExistingResident =
                $user->resident_id !== null &&
                $currentOfficial === null &&
                !$currentIsNewPerson &&
                $currentIsStaff;

            $currentIsAdministrative =
                !$currentIsNewPerson &&
                !$currentIsExistingOfficial &&
                !$currentIsExistingResident;

            /*
            |--------------------------------------------------------------------------
            | Prevent changing account architecture
            |--------------------------------------------------------------------------
            |
            | Edit should modify an existing account.
            | It should not convert:
            |
            | Administrative → Barangay
            | New Person → Existing Official
            | Existing Official → New Person
            |
            */

            if (
                $currentIsAdministrative &&
                $accountType !== 'administrative'
            ) {

                throw ValidationException::withMessages([
                    'account_type' =>
                        'An administrative user cannot be changed into a barangay user.',
                ]);

            }

            if (
                $currentIsNewPerson &&
                (
                    $accountType !== 'barangay' ||
                    $accountSetup !== 'new'
                )
            ) {

                throw ValidationException::withMessages([
                    'account_type' =>
                        'A barangay user undergoing onboarding must remain a new-person account.',
                ]);

            }

            if (
                $currentIsExistingResident &&
                (
                    $accountType !== 'barangay' ||
                    $accountSetup !== 'existing_resident' ||
                    !$this->hasRoleId($roles, Roles::STAFF)
                )
            ) {
                throw ValidationException::withMessages([
                    'account_type' =>
                        'A Staff account linked to an existing resident must remain an Existing Resident account.',
                ]);
            }

            if (
                $currentIsExistingOfficial &&
                (
                    $accountType !== 'barangay' ||
                    $accountSetup !== 'existing'
                )
            ) {

                throw ValidationException::withMessages([
                    'account_type' =>
                        'A user linked to an existing barangay official must remain an existing-official account.',
                ]);

            }

            $residentId = $data['resident_id'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | Remove frontend-only fields
            |--------------------------------------------------------------------------
            */

            unset(
                $data['account_type'],
                $data['account_setup'],
                $data['roles'],
                $data['barangay_official_id']
            );

            /*
            |--------------------------------------------------------------------------
            | Existing Resident — Staff Account Update
            |--------------------------------------------------------------------------
            */

            if (
                $accountType === 'barangay' &&
                $accountSetup === 'existing_resident'
            ) {
                if (!$this->hasRoleId($roles, Roles::STAFF)) {
                    throw ValidationException::withMessages([
                        'roles' => 'Existing Resident setup is only available for Staff accounts.',
                    ]);
                }

                $resident = Resident::query()
                    ->whereKey($residentId)
                    ->whereHas('household', function ($query) use ($data) {
                        $query->where('barangay_id', $data['barangay_id']);
                    })
                    ->lockForUpdate()
                    ->first();

                if (!$resident) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'The selected resident does not belong to the selected barangay.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Do not allow a different resident to be linked during Edit.
                |--------------------------------------------------------------------------
                */

                if (
                    $user->resident_id !== null &&
                    (int) $user->resident_id !== (int) $resident->id
                ) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'An existing Staff account cannot be reassigned to another resident.',
                    ]);
                }

                $existingUser = $resident->user;

                if (
                    $existingUser &&
                    (int) $existingUser->id !== (int) $user->id
                ) {
                    throw ValidationException::withMessages([
                        'resident_id' => 'The selected resident already has another user account.',
                    ]);
                }

                unset(
                    $data['account_type'],
                    $data['account_setup'],
                    $data['roles'],
                    $data['resident_id'],
                    $data['barangay_official_id'],
                    $data['official_position_id'],
                    $data['first_name'],
                    $data['middle_name'],
                    $data['last_name'],
                    $data['ext_name']
                );

                $userData = [
                    ...$data,

                    'display_name' => $resident->full_name,
                    'resident_id' => $resident->id,
                    'barangay_id' => $data['barangay_id'],
                    'official_position_id' => null,
                    'onboarding_required' => false,
                ];

                if (!empty($userData['password'])) {
                    $userData['password'] = Hash::make($userData['password']);
                } else {
                    unset($userData['password']);
                }

                $user->update($userData);
                $user->syncRoles($roles);

                return $user->load([
                    'resident',
                    'resident.barangayOfficials.position',
                    'roles',
                    'officialPosition',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Existing Barangay Official
            |--------------------------------------------------------------------------
            */

            if (
                $accountType === 'barangay' &&
                $accountSetup === 'existing'
            ) {

                $official =
                    BarangayOfficial::query()
                        ->with([
                            'resident',
                            'barangay',
                        ])
                        ->where(
                            'id',
                            $barangayOfficialId
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->first();

                if (!$official) {

                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official is not available.',
                    ]);

                }

                /*
                |--------------------------------------------------------------------------
                | Verify selected barangay
                |--------------------------------------------------------------------------
                */

                if (
                    (int) $official->barangay_id !==
                    (int) $data['barangay_id']
                ) {

                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official does not belong to the selected barangay.',
                    ]);

                }

                /*
                |--------------------------------------------------------------------------
                | Verify Resident
                |--------------------------------------------------------------------------
                */

                if (!$official->resident) {

                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official is not linked to a resident.',
                    ]);

                }

                if (
                    $user->resident_id !== null &&
                    (int) $user->resident_id !== (int) $official->resident_id
                ) {
                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'An existing official account cannot be reassigned to another resident.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate User accounts
                |--------------------------------------------------------------------------
                */

                $existingUser =
                    $official->resident->user;

                if (
                    $existingUser &&
                    $existingUser->id !== $user->id
                ) {

                    throw ValidationException::withMessages([
                        'barangay_official_id' =>
                            'The selected barangay official already has a user account.',
                    ]);

                }

                /*
                |--------------------------------------------------------------------------
                | Existing Official User Data
                |--------------------------------------------------------------------------
                */

                $userData = [
                    ...$data,

                    'resident_id' =>
                        $official->resident_id,

                    'barangay_id' =>
                        $official->barangay_id,

                    /*
                    |--------------------------------------------------------------------------
                    | Official position is authoritative in
                    | barangay_officials.official_position_id.
                    |--------------------------------------------------------------------------
                    */

                    'official_position_id' => null,

                    'onboarding_required' => false,

                    'display_name' =>
                        $official->resident->full_name,
                ];

                /*
                |--------------------------------------------------------------------------
                | Password
                |--------------------------------------------------------------------------
                */

                if (
                    !empty(
                        $userData['password']
                    )
                ) {

                    $userData['password'] =
                        Hash::make(
                            $userData['password']
                        );

                } else {

                    unset(
                        $userData['password']
                    );

                }

                /*
                |--------------------------------------------------------------------------
                | Update User
                |--------------------------------------------------------------------------
                */

                $user->update(
                    $userData
                );

                /*
                |--------------------------------------------------------------------------
                | Sync System Roles
                |--------------------------------------------------------------------------
                */

                $user->syncRoles(
                    $roles
                );

                return $user->load([
                    'resident',
                    'resident.barangayOfficials.position',
                    'roles',
                    'officialPosition',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Administrative User
            |--------------------------------------------------------------------------
            */

            if (
                $accountType === 'administrative'
            ) {

                $displayName =
                    trim(
                        implode(
                            ' ',
                            array_filter([
                                $data['first_name'] ?? null,
                                $data['middle_name'] ?? null,
                                $data['last_name'] ?? null,
                                $data['ext_name'] ?? null,
                            ])
                        )
                    );

                $userData = [
                    ...$data,

                    'display_name' =>
                        $displayName,

                    'resident_id' => null,

                    'barangay_id' => null,

                    'official_position_id' => null,

                    'onboarding_required' => false,
                ];

                /*
                |--------------------------------------------------------------------------
                | Password
                |--------------------------------------------------------------------------
                */

                if (
                    !empty(
                        $userData['password']
                    )
                ) {

                    $userData['password'] =
                        Hash::make(
                            $userData['password']
                        );

                } else {

                    unset(
                        $userData['password']
                    );

                }

                $user->update(
                    $userData
                );

                $user->syncRoles(
                    $roles
                );

                return $user->load([
                    'resident',
                    'resident.barangayOfficials.position',
                    'roles',
                    'officialPosition',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Barangay User — New Person
            |--------------------------------------------------------------------------
            */

            $displayName =
                trim(
                    implode(
                        ' ',
                        array_filter([
                            $data['first_name'] ?? null,
                            $data['middle_name'] ?? null,
                            $data['last_name'] ?? null,
                            $data['ext_name'] ?? null,
                        ])
                    )
                );

            $userData = [
                ...$data,

                'display_name' =>
                    $displayName,

                /*
                |--------------------------------------------------------------------------
                | New Person has not yet completed onboarding.
                |--------------------------------------------------------------------------
                */

                'resident_id' => null,

                'official_position_id' =>
                    $this->hasRoleId($roles, Roles::STAFF)
                        ? null
                        : ($data['official_position_id'] ?? null),

                'onboarding_required' => true,
            ];

            /*
            |--------------------------------------------------------------------------
            | Password
            |--------------------------------------------------------------------------
            */

            if (
                !empty(
                    $userData['password']
                )
            ) {

                $userData['password'] =
                    Hash::make(
                        $userData['password']
                    );

            } else {

                unset(
                    $userData['password']
                );

            }

            $user->update(
                $userData
            );

            $user->syncRoles(
                $roles
            );

            return $user->load([
                'resident',
                'resident.barangayOfficials.position',
                'roles',
                'officialPosition',
            ]);

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(User $user): void
    {
        DB::transaction(function () use ($user) {

            $user->tokens()->delete();

            $user->delete();

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Password
    |--------------------------------------------------------------------------
    */

    public function resetPassword(
        User $user,
        string $password = 'Password@123'
    ): User {

        $user->update([

            'password' => Hash::make($password),

        ]);

        $user->tokens()->delete();

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Activate / Deactivate
    |--------------------------------------------------------------------------
    */

    public function toggleStatus(User $user): User
    {
        $user->update([

            'is_active' => ! $user->is_active,

        ]);

        if (! $user->is_active) {

            $user->tokens()->delete();

        }

        return $user->fresh([
            'resident',
            'roles',
        ]);
    }

    private function hasRoleId(
        array $roleIds,
        string $roleName
    ): bool {
        $roleIds = collect($roleIds)
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
                ->where('name', $roleName)
                ->exists();
    }

    public function generatePassword(
        int $length = 8,
        bool $uppercase = true,
        bool $lowercase = true,
        bool $numbers = true,
        // bool $symbols = true
    ): string {

        $sets = [];

        if ($uppercase) {
            $sets[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        if ($lowercase) {
            $sets[] = 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($numbers) {
            $sets[] = '0123456789';
        }

        // if ($symbols) {
        //     $sets[] = '!@#$%^&*()-_=+[]{}?';
        // }

        if (empty($sets)) {
            return '';
        }

        $password = [];

        // Ensure at least one character from each enabled set
        foreach ($sets as $set) {
            $password[] = $set[random_int(0, strlen($set) - 1)];
        }

        $allCharacters = implode('', $sets);

        while (count($password) < $length) {
            $password[] = $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }

}