<?php

declare(strict_types=1);

namespace EtchFonts\Fonts;

final class FontFilenameParser
{
    private const WEIGHT_PATTERNS = [
        '200' => '/[ \-]?(200|((extra|ultra)\-?light))/i',
        '800' => '/[ \-]?(800|((extra|ultra)\-?bold))/i',
        '600' => '/[ \-]?(600|([ds]emi(\-?bold)?))/i',
        '100' => '/[ \-]?(100|thin)/i',
        '300' => '/[ \-]?(300|light)/i',
        '400' => '/[ \-]?(400|normal|regular|book)/i',
        '500' => '/[ \-]?(500|medium)/i',
        '700' => '/[ \-]?(700|bold)/i',
        '900' => '/[ \-]?(900|black|heavy)/i',
        'var' => '/[ \-]?(VariableFont|\[wght\])/i',
    ];

    public function parse(string $filename): array
    {
        $result = [
            'family' => $filename,
            'weight' => '400',
            'style' => 'normal',
            'is_variable' => false,
        ];

        if (preg_match('/[ \-]?(italic|oblique)/i', $result['family'], $matches) === 1) {
            $result['family'] = (string) preg_replace('/[ \-]?(italic|oblique)/i', '', $result['family']);
            $result['style'] = strtolower((string) ($matches[1] ?? 'italic')) === 'oblique' ? 'oblique' : 'italic';
        }

        foreach (self::WEIGHT_PATTERNS as $weight => $pattern) {
            if (preg_match($pattern, $result['family']) !== 1) {
                continue;
            }

            $result['family'] = (string) preg_replace($pattern, '', $result['family']);

            if ($weight === 'var') {
                $result['is_variable'] = true;
            } else {
                $result['weight'] = (string) $weight;
            }

            break;
        }

        $result['family'] = (string) preg_replace('/[ \-]?webfont$/i', '', $result['family']);
        $result['family'] = trim($result['family'], " -_\t\n\r\0\x0B");

        return $result;
    }
}
