<?php

namespace App\Features\TenantExport\Exceptions;

use App\Core\Exceptions\BaseException;

/** An export can't be started right now. The message is safe to show to the admin. */
class ExportNotAllowed extends BaseException {}
