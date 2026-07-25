<?php

namespace Modules\Tenancy\Application\Services;

class TenantContext
{
    /**
     * The active tenant (organization) ID.
     */
    protected static ?int $organizationId = null;

    /**
     * Set the active tenant ID.
     */
    public static function set(int $organizationId): void
    {
        self::$organizationId = $organizationId;
    }

    /**
     * Get the active tenant ID.
     */
    public static function get(): ?int
    {
        return self::$organizationId;
    }

    /**
     * Check if a tenant ID is set.
     */
    public static function has(): bool
    {
        return self::$organizationId !== null;
    }

    /**
     * Clear the active tenant ID.
     */
    public static function clear(): void
    {
        self::$organizationId = null;
    }
}
