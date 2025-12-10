<?php

namespace App\Enums;

use DateTime;
use DateTimeZone;
use ReflectionClass;

class TimeZones extends BaseEnum
{
    const UTC = 'UTC';

    const AFRICA_CAIRO = 'Africa/Cairo';

    const AFRICA_JOHANNESBURG = 'Africa/Johannesburg';

    const AFRICA_LAGOS = 'Africa/Lagos';

    const AFRICA_NAIROBI = 'Africa/Nairobi';

    const AMERICA_NEW_YORK = 'America/New_York';

    const AMERICA_LOS_ANGELES = 'America/Los_Angeles';

    const AMERICA_CHICAGO = 'America/Chicago';

    const AMERICA_DENVER = 'America/Denver';

    const AMERICA_TORONTO = 'America/Toronto';

    const AMERICA_SAO_PAULO = 'America/Sao_Paulo';

    const ASIA_DUBAI = 'Asia/Dubai';

    const ASIA_HONG_KONG = 'Asia/Hong_Kong';

    const ASIA_SHANGHAI = 'Asia/Shanghai';

    const ASIA_TOKYO = 'Asia/Tokyo';

    const ASIA_SINGAPORE = 'Asia/Singapore';

    const ASIA_KOLKATA = 'Asia/Kolkata';

    const AUSTRALIA_SYDNEY = 'Australia/Sydney';

    const AUSTRALIA_MELBOURNE = 'Australia/Melbourne';

    const AUSTRALIA_PERTH = 'Australia/Perth';

    const EUROPE_LONDON = 'Europe/London';

    const EUROPE_PARIS = 'Europe/Paris';

    const EUROPE_BERLIN = 'Europe/Berlin';

    const EUROPE_MADRID = 'Europe/Madrid';

    const EUROPE_ROME = 'Europe/Rome';

    const EUROPE_MOSCOW = 'Europe/Moscow';

    const EUROPE_LISBON = 'Europe/Lisbon';

    const EUROPE_BUCHAREST = 'Europe/Bucharest';

    const EUROPE_BRUSSELS = 'Europe/Brussels';

    const PACIFIC_AUCKLAND = 'Pacific/Auckland';

    const PACIFIC_FIJI = 'Pacific/Fiji';

    const PACIFIC_HONOLULU = 'Pacific/Honolulu';

    /**
     * Retourne toutes les timezones triées : Europe d'abord
     *
     * @return array<string, string>
     */
    public static function allWithLabels(): array
    {
        $reflector = new ReflectionClass(static::class);
        $timezones = array_values($reflector->getConstants());

        // Tri : Europe/* en premier
        usort($timezones, function ($a, $b): int {
            $aStr = is_string($a) ? $a : '';
            $bStr = is_string($b) ? $b : '';
            $aEurope = str_starts_with($aStr, 'Europe/');
            $bEurope = str_starts_with($bStr, 'Europe/');

            if ($aEurope && ! $bEurope) {
                return -1;
            }
            if ($bEurope && ! $aEurope) {
                return 1;
            }

            return strcmp($aStr, $bStr);
        });

        $result = [];
        foreach ($timezones as $tz) {
            if (is_string($tz)) {
                $result[] = [
                    'value' => $tz,
                    'label' => self::label($tz),
                ];
            }
        }

        return $result;
    }

    /**
     * Retourne un label lisible : "Europe/Brussels — UTC+01:00"
     */
    public static function label(string $tz): string
    {
        $dtz = new DateTimeZone($tz);
        $now = new DateTime('now', $dtz);
        $offset = $dtz->getOffset($now);

        $sign = $offset < 0 ? '-' : '+';
        $hours = str_pad((string) floor(abs($offset) / 3600), 2, '0', STR_PAD_LEFT);

        $formattedOffset = "UTC{$sign}{$hours}";

        return "{$tz} — {$formattedOffset}";
    }
}
