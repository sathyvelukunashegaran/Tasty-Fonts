<?php

declare(strict_types=1);

namespace TastyFonts\Bunny;

use TastyFonts\Fonts\HostedCssParser;

final class BunnyCssParser
{
    public function parse(string $css, string $expectedFamily = ''): array
    {
        return (new HostedCssParser('bunny'))->parse($css, $expectedFamily);
    }
}
