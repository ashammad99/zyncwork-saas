<?php

namespace App\Core;

use App\Core\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

abstract class BaseController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesRequests;
}
