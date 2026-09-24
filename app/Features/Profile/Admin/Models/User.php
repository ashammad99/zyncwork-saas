<?php

namespace App\Features\Profile\Admin\Models;

use App\Features\Auth\Models\User as BaseUser;

class User extends BaseUser
{
    protected $table = 'users';

    // Add Admin-specific profile logic here if needed
}
