<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

/**
 * Provider-specific hosted import configuration.
 */
final class HostedImportProviderConfig
{
    public function __construct(
        public readonly string $providerRoot,
        public readonly string $source,
        public readonly string $expectedHost,
        public readonly int $maxFontFileBytes,
        public readonly string $familyDirErrorCode,
        public readonly string $familyDirErrorMessage,
        public readonly string $invalidHostCode,
        public readonly string $invalidHostMessage,
        public readonly string $invalidExtensionCode,
        public readonly string $invalidExtensionMessage,
        public readonly string $downloadFailedCode,
        public readonly string $downloadFailedMessage,
        public readonly string $emptyFileCode,
        public readonly string $emptyFileMessage,
        public readonly string $fileTooLargeCode,
        public readonly string $fileTooLargeMessage,
        public readonly string $invalidTypeCode,
        public readonly string $invalidTypeMessage,
        public readonly string $writeFailedCode,
        public readonly string $writeFailedMessage,
        public readonly string $noFacesCode,
        public readonly string $noFacesMessage,
        public readonly string $emptyManifestCode,
        public readonly string $emptyManifestMessage,
        public readonly string $missingFamilyCode,
        public readonly string $missingFamilyMessage,
        public readonly string $skippedExistingMessage,
        public readonly string $skippedCdnMessage,
        public readonly string $selfHostedSuccessMessage,
        public readonly string $cdnSuccessMessage
    ) {}
}
