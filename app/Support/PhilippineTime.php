<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The single boundary for user-facing Philippine date and time handling.
 *
 * Persisted timestamps stay in UTC (the application/storage timezone). Every
 * business date, report boundary and displayed timestamp is expressed in
 * Asia/Manila through this class so the two conventions cannot drift again.
 */
final class PhilippineTime
{
    public const TIMEZONE = 'Asia/Manila';

    public const STORAGE_TIMEZONE = 'UTC';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /**
     * Convert a persisted timestamp to Philippine time.
     *
     * Date-only values must not pass through this method: they have no instant
     * semantics and converting them would shift the calendar date.
     */
    public static function at(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE);
        }

        return CarbonImmutable::parse($value, self::STORAGE_TIMEZONE)->setTimezone(self::TIMEZONE);
    }

    public static function format(DateTimeInterface|string|null $value, string $format = 'Y-m-d H:i'): ?string
    {
        return self::at($value)?->format($format);
    }

    /**
     * ISO-8601 value for <time datetime="..."> so browsers receive the same
     * instant and offset that the user sees.
     */
    public static function iso(DateTimeInterface|string|null $value): ?string
    {
        return self::at($value)?->toIso8601String();
    }
}
