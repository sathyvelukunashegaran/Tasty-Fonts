<?php

declare(strict_types=1);

namespace TastyFonts\Admin;

use TastyFonts\Google\GoogleFontsClient;
use TastyFonts\Repository\LogRepository;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\SettingsRepository;
use WP_Error;

/**
 * @phpstan-type Payload array<array-key, mixed>
 */
final class GoogleApiKeyActions
{
    use ActionValueHelpers;

    private readonly GoogleApiKeyValidator $validator;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly GoogleApiKeyRepository $apiKeyRepo,
        private readonly GoogleFontsClient $googleClient,
        private readonly AdminActionRunner $runner,
        ?GoogleApiKeyValidator $validator = null
    ) {
        $this->validator = $validator ?? new GoogleApiKeyValidator($this->googleClient);
    }

    /**
     * @return Payload
     */
    public function status(): array
    {
        $status = $this->apiKeyRepo->getStatus();
        $hasGoogleApiKey = $this->apiKeyRepo->has();
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
            $this->runner->run(
                fn() => $validation,
                [
                    'category' => LogRepository::CATEGORY_SETTINGS,
                    'event' => 'google_api_key_validation_failed',
                    'status_label' => __('Validation failed', 'tasty-fonts'),
                    'source' => __('Settings', 'tasty-fonts'),
                    'message' => __('Google Fonts API key validation failed.', 'tasty-fonts'),
                    'details' => [
                        ['label' => __('Google API key', 'tasty-fonts'), 'value' => __('Not saved', 'tasty-fonts')],
                    ],
                ]
            );

            return $validation;
        }

        $this->settings->saveSettings(['google_api_key' => $googleApiKey]);
        $this->apiKeyRepo->saveStatus($validation['state'], $validation['message']);
        $this->googleClient->clearCatalogCache();

        return $this->runner->run(
            function (): array {
                return array_merge(
                    $this->status(),
                    ['message' => __('Google Fonts API key validated.', 'tasty-fonts')]
                );
            },
            [
                'category' => LogRepository::CATEGORY_SETTINGS,
                'event' => 'google_api_key_validated',
                'status_label' => __('Validated', 'tasty-fonts'),
                'source' => __('Settings', 'tasty-fonts'),
                'message' => __('Google Fonts API key validated.', 'tasty-fonts'),
                'details' => [
                    ['label' => __('Google API key', 'tasty-fonts'), 'value' => __('Saved', 'tasty-fonts')],
                ],
            ]
        );
    }
}
