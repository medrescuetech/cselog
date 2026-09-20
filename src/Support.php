<?php

declare(strict_types=1);

namespace CseLog;

/** Formatting, timezone and geometry helpers. */
final class Support
{
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function timezone(): string
    {
        return Config::get('APP_TIMEZONE', 'Australia/Brisbane') ?? 'UTC';
    }

    /** Converts a stored UTC datetime to the site timezone for display. */
    public static function local(?string $utc, string $format = 'D j M, H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }

        $dt = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));

        return $dt->setTimezone(new \DateTimeZone(self::timezone()))->format($format);
    }

    /** Converts a local datetime-local input value to a UTC storage string. */
    public static function toUtc(string $localValue): string
    {
        $dt = new \DateTimeImmutable($localValue, new \DateTimeZone(self::timezone()));

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function localInputValue(?string $utc = null): string
    {
        $dt = new \DateTimeImmutable($utc ?? 'now', new \DateTimeZone('UTC'));

        return $dt->setTimezone(new \DateTimeZone(self::timezone()))->format('Y-m-d\TH:i');
    }

    public static function durationSeconds(string $fromUtc, ?string $toUtc = null): int
    {
        $from = strtotime($fromUtc . ' UTC') ?: 0;
        $to = $toUtc === null ? time() : (strtotime($toUtc . ' UTC') ?: 0);

        return max(0, $to - $from);
    }

    public static function humanDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /** grey | amber | red, based on how long an entry has been open. */
    public static function ageLevel(int $seconds): string
    {
        $amber = Config::int('ALERT_AMBER_HOURS', 2) * 3600;
        $red = Config::int('ALERT_RED_HOURS', 4) * 3600;

        if ($seconds >= $red) {
            return 'red';
        }

        return $seconds >= $amber ? 'amber' : 'grey';
    }

    /**
     * Ray-casting point-in-polygon over a GeoJSON Polygon ring in pixel space.
     *
     * @param array<int, array<int, array<int, float>>> $rings
     */
    public static function pointInPolygon(float $x, float $y, array $rings): bool
    {
        $inside = false;
        foreach ($rings as $ringIndex => $ring) {
            $hit = false;
            $count = count($ring);
            for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
                [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
                [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];

                if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                    $hit = !$hit;
                }
            }

            // First ring is the outer boundary; later rings are holes.
            if ($ringIndex === 0) {
                $inside = $hit;
            } elseif ($hit) {
                return false;
            }
        }

        return $inside;
    }

    /** Distance in pixels between two points. */
    public static function distance(float $x1, float $y1, float $x2, float $y2): float
    {
        return sqrt((($x1 - $x2) ** 2) + (($y1 - $y2) ** 2));
    }

    /** Case-insensitive similarity 0..1, used for duplicate-location warnings. */
    public static function nameSimilarity(string $a, string $b): float
    {
        $a = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $a) ?? '');
        $b = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $b) ?? '');
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    public static function slug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? ''));

        return trim($slug, '-');
    }
}
