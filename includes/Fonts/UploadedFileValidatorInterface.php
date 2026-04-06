<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

interface UploadedFileValidatorInterface
{
    /**
     * Determine whether a file path belongs to a valid HTTP upload.
     *
     * @since 1.4.0
     *
     * @param string $tmpName Temporary file path provided by WordPress.
     * @return bool True when the file can be treated as an uploaded file.
     */
    public function isUploadedFile(string $tmpName): bool;
}
