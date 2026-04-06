<?php

declare(strict_types=1);

namespace TastyFonts\Google;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedCssParser;

final class GoogleCssParser
{
    public function parse(string $css, string $expectedFamily = ''): array
    {
        return (new HostedCssParser('google'))->parse($css, $expectedFamily);
    }
}
