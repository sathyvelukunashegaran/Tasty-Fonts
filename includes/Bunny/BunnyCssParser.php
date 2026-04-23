<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedCssParser;

final class BunnyCssParser
{
    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $css, string $expectedFamily = ''): array
    {
        return (new HostedCssParser('bunny'))->parse($css, $expectedFamily);
    }
}
