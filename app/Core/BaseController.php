<?php

namespace App\Core;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use App\Core\Traits\ApiResponseTrait;

abstract class BaseController extends Controller
{
    use ApiResponseTrait;
    use AuthorizesRequests;

}
