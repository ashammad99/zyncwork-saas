<?php

namespace App\Features\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer organisation. Not tenant-owned itself: tenant users can never
 * query this table through a scope, and only the platform side manages it.
 */
class Tenant extends Model
{
    protected $fillable = ['name'];
}
