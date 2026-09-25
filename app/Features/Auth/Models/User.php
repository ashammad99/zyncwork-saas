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

class User extends Authenticatable
{
    use BelongsToTenant;

    use HasFactory, Notifiable;

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
