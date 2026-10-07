<?php

namespace App\Features\Projects\Admin\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\TaskAdmin\Models\Task;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToTenant;
    use HasFactory;

    // Factory lives at database/factories/ProjectFactory.php, not the path Laravel guesses.
    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    protected $fillable = [
        'department_id',
        'owner_id',
        'name',
        'description',
        'status',
        'deadline',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role', 'joined_at'])
            // Pivot rows carry the tenant; a composite foreign key rejects cross-tenant links.
            ->withPivotValue('tenant_id', $this->tenant_id);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
