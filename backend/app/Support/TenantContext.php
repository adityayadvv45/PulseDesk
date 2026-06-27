<?php

namespace App\Support;

/**
 * Holds the current tenant (organization) id for the request lifecycle.
 * Derived from the authenticated user — NEVER from client input — so a user
 * from Org A can never read or write Org B's data.
 */
class TenantContext
{
    protected static ?int $organizationId = null;

    public static function set(?int $organizationId): void
    {
        static::$organizationId = $organizationId;
    }

    public static function id(): ?int
    {
        return static::$organizationId;
    }

    public static function clear(): void
    {
        static::$organizationId = null;
    }
}
