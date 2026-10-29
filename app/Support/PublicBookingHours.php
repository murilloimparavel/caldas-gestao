<?php

namespace App\Support;

final class PublicBookingHours
{
    /** @return list<array{starts_at: string, ends_at: string}> */
    public static function periods(mixed $day): array
    {
        if (! is_array($day) || ($day['enabled'] ?? true) === false) {
            return [];
        }

        $periods = array_key_exists('display_periods', $day) ? $day['display_periods'] : [$day];
        $valid = [];
        foreach (is_array($periods) ? $periods : [] as $period) {
            if (! is_array($period)) {
                continue;
            }
            $start = $period['starts_at'] ?? $period['start'] ?? null;
            $end = $period['ends_at'] ?? $period['end'] ?? null;
            if (! is_string($start) || ! is_string($end) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) || $start >= $end) {
                continue;
            }
            $valid[] = ['starts_at' => $start, 'ends_at' => $end];
        }

        return $valid;
    }
}
