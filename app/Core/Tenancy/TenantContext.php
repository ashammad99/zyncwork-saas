<?php

namespace App\Core\Tenancy;

/**
 * The tenant the current request or queued job is acting for.
 *
 * Set from the authenticated user (TenancyServiceProvider), from a queued
 * job's payload, or explicitly with run(). Nothing falls back to "all
 * tenants": with no tenant set, tenant-owned models refuse to query or write.
 */
class TenantContext
{
    private ?int $tenantId = null;

    /** @var array<int, int|null> */
    private array $stack = [];

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function check(): bool
    {
        return $this->tenantId !== null;
    }

    public function idOrFail(): int
    {
        return $this->tenantId ?? throw TenantIsolationException::missingContext();
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    /** Switch tenant, remembering the previous one for pop(). */
    public function push(?int $tenantId): void
    {
        $this->stack[] = $this->tenantId;
        $this->tenantId = $tenantId;
    }

    public function pop(): void
    {
        $this->tenantId = array_pop($this->stack);
    }

    /**
     * Run a callback as the given tenant, restoring the previous tenant afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $tenantId, callable $callback): mixed
    {
        $this->push($tenantId);

        try {
            return $callback();
        } finally {
            $this->pop();
        }
    }
}
