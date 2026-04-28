<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Repository\LogRepository;
use WP_Error;

/**
 * Creates library mutation errors while preserving lightweight failure logging.
 */
final class LibraryMutationErrorFactory
{
    public function __construct(private readonly LogRepository $log)
    {
    }

    public function error(string $code, string $message): WP_Error
    {
        $this->log->add($message);

        return new WP_Error($code, $message);
    }
}
