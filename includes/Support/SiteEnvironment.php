<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

final class SiteEnvironment
{
    private const LOCAL_HOST_SUFFIXES = [
        '.ddev.site',
        '.invalid',
        '.lndo.site',
        '.local',
        '.localhost',
        '.test',
    ];

    private const TLS_TRUST_ERROR_MARKERS = [
        'cURL error 60',
        'self signed certificate',
        'ssl certificate',
        'unable to get local issuer certificate',
        'unable to verify the first certificate',
    ];

    public static function currentEnvironmentType(): string
    {
        if (function_exists('wp_get_environment_type')) {
            return strtolower(trim(FontUtils::scalarStringValue(wp_get_environment_type())));
        }

        if (defined('WP_ENVIRONMENT_TYPE')) {
            return strtolower(trim(FontUtils::scalarStringValue(constant('WP_ENVIRONMENT_TYPE'))));
        }

        return '';
    }

    public static function isLikelyLocalEnvironment(string $siteUrl = '', string $environmentType = ''): bool
    {
        $environmentType = strtolower(trim($environmentType));

        if ($environmentType === 'local') {
            return true;
        }

        $host = strtolower(trim((string) (wp_parse_url($siteUrl, PHP_URL_HOST) ?? '')));

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        foreach (self::LOCAL_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    public static function isLoopbackTlsTrustError(string $message): bool
    {
        $message = strtolower(trim($message));

        if ($message === '') {
            return false;
        }

        foreach (self::TLS_TRUST_ERROR_MARKERS as $marker) {
            if (str_contains($message, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }
}
