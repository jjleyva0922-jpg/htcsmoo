<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ACCOUNT_PENDING = 'pending';

    public const ACCOUNT_ACTIVE = 'active';

    public const ACCOUNT_SUSPENDED = 'suspended';

    public const ACCOUNT_STATUSES = [self::ACCOUNT_PENDING, self::ACCOUNT_ACTIVE, self::ACCOUNT_SUSPENDED];

    public const ROLES = ['employee', 'dept_head', 'vp_finance', 'vp_admin', 'provost', 'president', 'smo'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'school_id',
        'department', // Virtual compatibility input; persisted as department_id.
        'department_id',
        'email',
        'password',
        'role',
        'is_approved',
        'account_status',
        'account_version',
    ];

    /**
     * The department/college the user belongs to.
     */
    public function academicDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Keep the existing presentation API while department_id remains the only
     * persisted department value.
     */
    public function getDepartmentAttribute(): ?string
    {
        return $this->academicDepartment?->dept_code;
    }

    public function setDepartmentAttribute(?string $code): void
    {
        $code = filled($code) ? trim($code) : null;
        $this->attributes['department_id'] = $code === null
            ? null
            : Department::query()->where('dept_code', $code)->value('id');
        $this->unsetRelation('academicDepartment');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'account_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $user->account_version ??= 0;

        });
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->account_status === self::ACCOUNT_ACTIVE;
    }

    public function setIsApprovedAttribute(bool $approved): void
    {
        $this->attributes['account_status'] = $approved
            ? self::ACCOUNT_ACTIVE
            : (($this->attributes['account_status'] ?? null) === self::ACCOUNT_ACTIVE
                ? self::ACCOUNT_SUSPENDED
                : self::ACCOUNT_PENDING);
    }

    public function scopeWithAccountStatus(Builder $query, string $status): Builder
    {
        return $query->where('account_status', $status);
    }

    public function scopeInDepartment(Builder $query, string $code): Builder
    {
        return $query->whereHas(
            'academicDepartment',
            fn (Builder $departments) => $departments->where('dept_code', $code),
        );
    }

    public function hasActiveAccess(): bool
    {
        return $this->account_status === self::ACCOUNT_ACTIVE;
    }

    // Check if user is an Admin
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    // Check if user is SMO In-charge
    public function isSMO()
    {
        return $this->role === 'smo';
    }

    // Check if user is an Employee
    public function isEmployee()
    {
        return $this->role === 'employee';
    }

    public function isDeptHead()
    {
        return $this->role === 'dept_head';
    }

    public function isVP()
    {
        return $this->role === 'vp';
    }

    public function isProvost()
    {
        return $this->role === 'provost';
    }

    public function isPresident()
    {
        return $this->role === 'president';
    }
}
