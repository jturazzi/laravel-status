<?php

namespace App\Support;

/**
 * Translates the (English) issue messages produced by jturazzi/laravel-system-info.
 * Unknown messages are returned unchanged.
 */
class IssueText
{
    /**
     * English pattern => translation key with :placeholders filled from the named groups.
     */
    private const PATTERNS = [
        '/^(?<count>\d+) failed job\(s\)$/' => ':count failed job(s)',
        '/^(?<count>\d+) pending migration\(s\)$/' => ':count pending migration(s)',
        '/^Oldest pending job has waited (?<count>\d+) (?<unit>days|hours|minutes): is a queue worker running\?$/' => 'Oldest pending job has waited :duration: is a queue worker running?',
        '/^Low disk space \((?<percent>[\d.]+)% free\)$/' => 'Low disk space (:percent% free)',
        '/^Database unreachable$/' => 'Database unreachable',
        '/^Cache unavailable$/' => 'Cache unavailable',
        '/^Queue unavailable$/' => 'Queue unavailable',
        '/^Migrations table missing$/' => 'Migrations table missing',
        '/^Application is in maintenance mode$/' => 'Application is in maintenance mode',
        '/^Storage directory is not writable$/' => 'Storage directory is not writable',
    ];

    public static function translate(string $message): string
    {
        foreach (self::PATTERNS as $pattern => $key) {
            if (! preg_match($pattern, $message, $m)) {
                continue;
            }

            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            if (isset($params['unit'])) {
                $params = ['duration' => self::duration((int) $params['count'], $params['unit'])];
            }

            return __($key, $params);
        }

        return $message;
    }

    /**
     * The collector's error for an application, in the current language.
     *
     * @param  array{error: ?string, error_i18n?: ?array{key: string, params: array<string, mixed>}}  $app
     */
    public static function error(array $app): ?string
    {
        $i18n = $app['error_i18n'] ?? null;

        return $i18n ? __($i18n['key'], $i18n['params']) : $app['error'];
    }

    /**
     * Duration in the current language, e.g. "28 days" / "28 jours".
     */
    public static function duration(int $count, string $unit): string
    {
        return __(match ($unit) {
            'days' => ':count days',
            'hours' => ':count hours',
            default => ':count min',
        }, ['count' => $count]);
    }
}
