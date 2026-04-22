<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use PhpSoftBox\Filter\PhoneFilter;

final class PhoneHandler extends AbstractTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'phone';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw             = (string) $value;
        $withCountryCode = (bool) ($options['with_country_code_to'] ?? $options['withCountryCodeTo'] ?? false);
        $normalized      = new PhoneFilter(
            prepareForDb: true,
            withCountryCode: $withCountryCode,
            keepOriginalOnError: true,
        )($raw);

        return $normalized ?? $raw;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $raw             = (string) $value;
        $withCountryCode = (bool) ($options['with_country_code_from'] ?? $options['withCountryCodeFrom'] ?? false);
        $normalized      = new PhoneFilter(
            prepareForDb: false,
            withCountryCode: $withCountryCode,
            keepOriginalOnError: true,
        )($raw);

        return $normalized ?? $raw;
    }
}
