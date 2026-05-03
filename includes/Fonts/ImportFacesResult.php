<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

final class ImportFacesResult
{
    /**
     * @param list<array<string, mixed>> $faces
     */
    public function __construct(
        public readonly array $faces,
        public readonly int $files
    ) {}
}
