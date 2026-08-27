<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    private array $permissionAccessCache = [];

    protected $fillable = [
        'employee_id', 'name', 'email', 'phone', 'password', 'authority_level',
        'user_type', 'is_approver', 'manager_id', 'is_active', 'settings', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_approver' => 'boolean',
            'settings' => 'array',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function roles() { return $this->belongsToMany(Role::class); }
    public function manager() { return $this->belongsTo(self::class, 'manager_id'); }
    public function subordinates() { return $this->hasMany(self::class, 'manager_id'); }
    public function businessUnits() { return $this->belongsToMany(BusinessUnit::class); }
    public function departments() { return $this->belongsToMany(Department::class); }
    public function teams() { return $this->belongsToMany(Team::class); }
    public function areas() { return $this->belongsToMany(Area::class); }
    public function assignedCustomers() { return $this->belongsToMany(Customer::class)->withPivot('responsibility')->withTimestamps(); }
    public function roomMemberships() { return $this->hasMany(RoomMember::class); }
    public function assignedTasks() { return $this->belongsToMany(Task::class)->withPivot('assignment_role')->withTimestamps(); }
    public function presence() { return $this->hasOne(UserPresence::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }

    public function isMasterAdmin(): bool
    {
        return $this->authority_level === 'master_admin';
    }

    public function canApprove(): bool
    {
        return $this->is_active && $this->is_approver;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function isSales(): bool
    {
        return $this->hasRole('sales');
    }

    public function canAccess(string $permission): bool
    {
        if ($this->isMasterAdmin()) {
            return true;
        }

        // Approval access is an account capability. This keeps an account that
        // was explicitly appointed as approver from being blocked by its job role.
        if ($permission === 'approvals.view' && $this->canApprove()) {
            return true;
        }

        if (array_key_exists($permission, $this->permissionAccessCache)) {
            return $this->permissionAccessCache[$permission];
        }

        $this->loadMissing(['roles.permissions', 'roles.parentRole.permissions', 'roles.parentRole.parentRole.permissions']);

        return $this->permissionAccessCache[$permission] = $this->roles
            ->contains(fn (Role $role) => $role->grants($permission));
    }

    public function roleNames(): string
    {
        return $this->roles->pluck('name')->join(', ');
    }

    public function requiresActivityEvidence(): bool
    {
        return $this->authority_level === 'staff'
            && $this->departments()->where('activity_evidence_required', true)->exists();
    }
}
