<?php

namespace App\Features\TenantExport\Models;

use App\Core\Tenancy\BelongsToTenant;
use App\Features\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A full data export of one tenant, requested by one of its admins. Only that
 * admin may download it (TenantExportController::download).
 */
class TenantExport extends Model
{
    use BelongsToTenant;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const ACTIVE_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING];

    // Everything is set by TenantExportService and the build job, never from a request.
    protected $fillable = [];

    protected $hidden = ['file_path', 'error'];

    protected function casts(): array
    {
        return [
            'row_counts' => 'array',
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_READY
            && $this->file_path !== null
            && $this->expires_at?->isFuture();
    }
}
