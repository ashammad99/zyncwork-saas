<?php

namespace App\Features\Profile\User\Models;

use App\Features\Auth\Models\User as BaseUser;

class User extends BaseUser
{
    protected $table = 'users';

    // Add User-specific profile logic here if needed
}
