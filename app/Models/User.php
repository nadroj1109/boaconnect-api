<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Constants\Roles;
use App\Enums\VerificationChannel;
use App\Models\OfficialPosition;
use App\Models\Resident;
use App\Models\VerificationCode;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'resident_id',
        'first_name',
        'middle_name',
        'last_name',
        'ext_name',
        'display_name',
        'email',
        'mobile_number',
        'password',
        'is_active',
        'activation_token',
        'activation_expires_at',
        'activated_at',
        'barangay_id',
        'official_position_id',
        'onboarding_required',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'role_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'onboarding_required' => 'boolean',
            'activation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function hasActivated(): bool
    {
        return !is_null($this->activated_at);
    }

    public function activationExpired(): bool
    {
        return

            $this->activation_expires_at

            &&

            now()->greaterThan(

                $this->activation_expires_at

            );
    }

    public function canActivate(): bool
    {
        return

            !$this->hasActivated()

            &&

            !$this->activationExpired()

            &&

            !empty($this->activation_token);
    }

    public function getActivationUrlAttribute(): string
    {
        return config('app.frontend_url')
            . '/activate/'
            . $this->activation_token;
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function officialPosition(): BelongsTo
    {
        return $this->belongsTo(
            OfficialPosition::class,
            'official_position_id'
        );
    }

    public function signingCertificates()
    {
        return $this->hasMany(UserSigningCertificate::class);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resident
                ? $this->resident->first_name.' '.$this->resident->last_name
                : $this->display_name
        );
    }

    protected function barangay_name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->barangay
                ? $this->barangay->barangay
                : ''
        );
    }

    public function getRoleIdAttribute()
    {
        return optional($this->roles->first())->id;
    }

    public function scopeStatus(
        Builder $query,
        $status
    ): Builder {

        if (

            $status === null ||

            $status === ''

        ) {

            return $query;

        }

        return $query->where(

            'is_active',

            $status

        );

    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {

        if (blank($search)) {

            return $query;

        }

        return $query->where(function ($query) use ($search) {

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

    public function scopeRole(
        Builder $query,
        $role
    ): Builder {

        if (blank($role)) {

            return $query;

        }

        return $query->role($role);

    }

    public function scopeSort(
        Builder $query,
        ?string $column,
        ?string $direction
    ): Builder {

        $allowed = [

            'id',

            'display_name',

            'email',

            'created_at',

        ];

        $column = in_array(
            $column,
            $allowed
        )
            ? $column
            : 'created_at';

        $direction = strtolower($direction) === 'asc'
            ? 'asc'
            : 'desc';

        return $query->orderBy(
            $column,
            $direction
        );

    }

    public function requiresOnboarding(): bool
    {
        return $this->onboarding_required;
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public static function findForLogin(string $username): ?self
    {
        $field = filter_var(

            $username,

            FILTER_VALIDATE_EMAIL

        )

            ? 'email'

            : 'mobile_number';

        return static::query()

            ->with([

                'resident',

                'barangay',

            ])

            ->where($field, $username)

            ->first();
    }

    public function verifyPassword(string $password): bool
    {
        return Hash::check(

            $password,

            $this->password

        );
    }

    public function issueToken(
        string $device = 'mobile',
        bool $singleSession = false,
    ): string {

        if ($singleSession) {

            $this->tokens()->delete();

        }

        return $this
            ->createToken($device)
            ->plainTextToken;
    }

    public function revokeCurrentToken(): void
    {
        $this->currentAccessToken()?->delete();
    }

    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    public static function registerResident(
        Resident $resident,
        VerificationCode $verification,
        string $password,
    ): self {

        $email = null;

        $mobile = null;

        if (
            $verification->channel === VerificationChannel::EMAIL
        ) {

            $email = $verification->recipient;

        } else {

            $mobile = $verification->recipient;

        }

        $user = static::create([

            'resident_id' =>
                $resident->id,

            'barangay_id' =>
                $resident->barangay_id,

            'display_name' =>
                $resident->full_name,

            'email' =>
                $email,

            'mobile_number' =>
                $mobile,

            'password' =>
                Hash::make($password),

            'is_active' => 1,

        ]);

        $user->assignRole(Roles::RESIDENT);

        return $user;

    }
}
