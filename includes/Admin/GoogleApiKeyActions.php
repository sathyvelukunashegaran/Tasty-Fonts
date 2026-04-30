<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

defined('ABSPATH') || exit;

use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\SettingsRepository;
use WP_Error;

/**
 * @phpstan-type Payload array<string, mixed>
 */
final class GoogleApiKeyActions
{
    use ActionValueHelpers;

    private readonly GoogleApiKeyValidator $validator;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly GoogleFontsClient $googleClient,
        private readonly LogRepository $log,
        ?GoogleApiKeyValidator $validator = null
    ) {
        $this->validator = $validator ?? new GoogleApiKeyValidator($this->googleClient);
    }

    /**
     * @return Payload
     */
    public function status(): array
    {
        $status = $this->settings->getGoogleApiKeyStatus();
        $hasGoogleApiKey = $this->settings->hasGoogleApiKey();
        $message = $hasGoogleApiKey
            ? __('Google Fonts API key is stored.', 'tasty-fonts')
            : __('Google Fonts API key is not stored.', 'tasty-fonts');

        return [
            'message' => $message,
            'has_google_api_key' => $hasGoogleApiKey,
            'google_api_key_status' => $this->stringValue($status, 'state', $hasGoogleApiKey ? 'unknown' : 'empty'),
            'google_api_key_status_message' => $this->stringValue($status, 'message'),
            'google_api_key_checked_at' => $this->intValue($status, 'checked_at'),
        ];
    }

    /**
     * @return Payload|WP_Error
     */
    public function save(string $googleApiKey): array|WP_Error
    {
        $googleApiKey = trim(sanitize_text_field($googleApiKey));

        if ($googleApiKey === '') {
            return new WP_Error(
                'tasty_fonts_google_api_key_empty',
                __('A Google Fonts API key is required.', 'tasty-fonts')
            );
        }

        $validation = $this->validator->validate(
            $googleApiKey,
            'tasty_fonts_google_api_key_invalid',
            __('Google Fonts API key could not be validated.', 'tasty-fonts')
        );

        if (is_wp_error($validation)) {
            $this->log->add(
                __('Google Fonts API key validation failed.', 'tasty-fonts'),
                $this->logContext(
                    LogRepository::CATEGORY_SETTINGS,
                    'google_api_key_validation_failed',
                    [
                        'outcome' => 'error',
                        'status_label' => __('Validation failed', 'tasty-fonts'),
                        'source' => __('Settings', 'tasty-fonts'),
                        'details' => [
                            ['label' => __('Google API key', 'tasty-fonts'), 'value' => __('Not saved', 'tasty-fonts')],
                        ],
                    ]
                )
            );
            return $validation;
        }

        $this->settings->saveSettings(['google_api_key' => $googleApiKey]);
        $this->settings->saveGoogleApiKeyStatus($validation['state'], $validation['message']);
        $this->googleClient->clearCatalogCache();

        $message = __('Google Fonts API key validated.', 'tasty-fonts');
        $this->log->add($message, $this->logContext(
            LogRepository::CATEGORY_SETTINGS,
            'google_api_key_validated',
            [
                'outcome' => 'success',
                'status_label' => __('Validated', 'tasty-fonts'),
                'source' => __('Settings', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Google API key', 'tasty-fonts'), 'value' => __('Saved', 'tasty-fonts')],
                ],
            ]
        ));

        return array_merge(
            $this->status(),
            ['message' => $message]
        );
    }

}
