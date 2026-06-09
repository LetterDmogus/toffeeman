<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'status', 'position_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    use HasRoles {
        hasPermissionTo as spatieHasPermissionTo;
    }

    protected $appends = ['role'];

    /**
     * Get the first role name of the user.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Determine if the user has the given permission.
     *
     * @param  string|Permission  $permission
     * @param  string|null  $guardName
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        try {
            if ($this->spatieHasPermissionTo($permission, $guardName)) {
                return true;
            }
        } catch (\Exception $e) {
            // Spatie exception for missing permission in DB
        }

        if ($this->position) {
            return $this->position->hasPermissionTo($permission, $guardName);
        }

        if ($this->employee && $this->employee->position) {
            return $this->employee->position->hasPermissionTo($permission, $guardName);
        }

        return false;
    }

    /**
     * Get the position associated with the user.
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the employee profile associated with the user.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
