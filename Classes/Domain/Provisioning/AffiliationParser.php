<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Domain\Provisioning;

final class AffiliationParser
{
    /**
     * @return list<string>
     */
    public function parse(?string $rawAffiliations): array
    {
        if ($rawAffiliations === null || trim($rawAffiliations) === '') {
            return [];
        }

        $items = array_map(
            static function (string $value): string {
                $value = trim($value);
                if ($value === '') {
                    return '';
                }

                return preg_replace('/@.*/', '', $value) ?? $value;
            },
            explode(';', $rawAffiliations)
        );

        $items = array_values(array_filter($items, static fn(string $value): bool => $value !== ''));

        return array_values(array_unique($items));
    }
}
