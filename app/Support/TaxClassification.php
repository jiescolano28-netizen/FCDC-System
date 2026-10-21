<?php

namespace App\Support;

final class TaxClassification
{
    public const VATABLE = 'vatable';

    public const ZERO_RATED = 'zero_rated';

    public const VAT_EXEMPT = 'vat_exempt';

    public const NON_VAT = 'non_vat';

    public const MIXED = 'mixed';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::VATABLE => 'VATable Sales',
            self::ZERO_RATED => 'Zero-Rated Sales',
            self::VAT_EXEMPT => 'VAT-Exempt Sales',
            self::NON_VAT => 'Non-VAT / Outside Scope',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $classification): string
    {
        if ($classification === self::MIXED) {
            return 'Mixed';
        }

        return self::options()[$classification ?? ''] ?? self::options()[self::VATABLE];
    }

    public static function rate(?string $classification): float
    {
        return $classification === self::VATABLE ? 0.12 : 0.0;
    }

    public static function rateLabel(?string $classification): string
    {
        if ($classification === self::MIXED) {
            return 'Mixed · 12% / 0%';
        }

        return number_format(self::rate($classification) * 100, 0).'%';
    }

    /**
     * @param  array<int, string|null>  $classifications
     */
    public static function aggregate(array $classifications): string
    {
        $classifications = array_values(array_unique(array_filter(
            $classifications,
            fn (?string $classification): bool => in_array($classification, self::all(), true),
        )));

        if ($classifications === []) {
            return self::VATABLE;
        }

        return count($classifications) === 1 ? $classifications[0] : self::MIXED;
    }

    public static function isVatable(?string $classification): bool
    {
        return $classification === self::VATABLE;
    }

    public static function isValid(?string $classification): bool
    {
        return in_array($classification, self::all(), true);
    }

    public static function normalize(?string $classification): string
    {
        return self::isValid($classification) ? $classification : self::VATABLE;
    }
}
