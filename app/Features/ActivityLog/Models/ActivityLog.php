<?php

namespace App\Features\ActivityLog\Models;

use App\Features\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Core\Tenancy\BelongsToTenant;

class ActivityLog extends Model
{
    use BelongsToTenant;

    use HasFactory;

    protected $fillable = [
        'user_id',
        // Set (with user_id possibly null) when platform staff acted on the tenant.
        'platform_actor',
        'action',
        'description',
        'subject_type',
        'ip_address',
        'user_agent',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
