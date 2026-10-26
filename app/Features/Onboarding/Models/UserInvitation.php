<?php

namespace App\Features\Onboarding\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to join the current tenant with a given role (InvitationService).
 *
 * Only the SHA-256 of the emailed token is stored. An invite is open until it
 * is accepted, revoked or past expires_at; each of those is final.
 */
class UserInvitation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'email',
        'role',
        'department_id',
        'invited_by',
        'token_hash',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
