<?php

namespace App\Features\Auth\Models;

use App\Features\Departments\Admin\Models\Department;
use App\Features\Notification\Admin\Models\Notification;
use App\Features\Projects\Admin\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Core\Tenancy\BelongsToTenant;
use App\Mail\GeneralMail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class User extends Authenticatable implements MustVerifyEmail
{
    use BelongsToTenant;

    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * A changed email address is unproven until its owner clicks the new link
     * (EnsureEmailIsVerified blocks the account meanwhile). Done here so every
     * path that changes an email (profile, admin edit, anything later) is covered.
     */
    protected static function booted(): void
    {
        static::updating(function (User $user) {
            if ($user->isDirty('email') && ! $user->isDirty('email_verified_at')) {
                $user->email_verified_at = null;
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('email') && ! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();
            }
        });
    }

    /** Sent with the app's own mail template (GeneralMail), queued like every other email. */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );

        Mail::to($this->getEmailForVerification())->send(new GeneralMail(
            mailSubject: 'Verify your email address',
            contentView: 'emails.auth.verify-email-body',
            data: [
                'name' => $this->name,
                'actionUrl' => $url,
                'expiresInMinutes' => (int) config('auth.verification.expire', 60),
            ]
        ));
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'profile_image',
        'last_login_at',
        'last_login_ip',
        'daily_capacity_hours',
        'department_id',

    ];

    protected $hidden = [
        'password',
        'remember_token',
        'updated_at',
        'last_login_at',
        'last_login_ip',
    ];

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function collaboratedProjects()
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role', 'joined_at'])
            // Pivot rows carry the tenant; a composite foreign key rejects cross-tenant links.
            ->withPivotValue('tenant_id', $this->tenant_id);
    }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(\App\Features\TaskAdmin\Models\Task::class, 'assignee_id');
    }

}
