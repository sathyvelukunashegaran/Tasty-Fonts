<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

final class VariantTokenService
{
    public function canonicalVariantToken(string $variant): string
    {
        $variant = strtolower(trim($variant));

        if ($variant === '') {
            return '';
        }

        $compact = preg_replace('/[\s_-]+/', '', $variant);

        if (!is_string($compact) || $compact === '') {
            return '';
        }

        $weightAliases = [
            'thin' => '100',
            'hairline' => '100',
            'extralight' => '200',
            'ultralight' => '200',
            'light' => '300',
            'regular' => 'regular',
            'normal' => 'regular',
            'book' => 'regular',
            'medium' => '500',
            'semibold' => '600',
            'demibold' => '600',
            'bold' => '700',
            'extrabold' => '800',
            'ultrabold' => '800',
            'black' => '900',
            'heavy' => '900',
        ];

        if ($compact === 'italic') {
            return 'italic';
        }

        if (
            preg_match('/^([1-9]00)(italic)?$/', $compact) === 1
            || preg_match('/^([1-9]00)\.\.([1-9]00)(italic)?$/', $compact) === 1
        ) {
            return $compact;
        }

        if (isset($weightAliases[$compact])) {
            return $weightAliases[$compact];
        }

        if (str_ends_with($compact, 'italic')) {
            $weightAlias = substr($compact, 0, -6);

            if ($weightAlias === '') {
                return 'italic';
            }

            if (isset($weightAliases[$weightAlias])) {
                return $weightAliases[$weightAlias] === 'regular'
                    ? 'italic'
                    : $weightAliases[$weightAlias] . 'italic';
            }
        }

        return '';
    }

    /**
     * @return array{style: string, weight: string}|null
     */
    public function googleVariantToAxis(string $variant): ?array
    {
        $variant = $this->canonicalVariantToken($variant);

        if ($variant === '') {
            return null;
        }

        if ($variant === 'regular') {
            return ['style' => 'normal', 'weight' => '400'];
        }

        if ($variant === 'italic') {
            return ['style' => 'italic', 'weight' => '400'];
        }

        if (preg_match('/^([1-9]00)$/', $variant, $matches) === 1) {
            return ['style' => 'normal', 'weight' => $matches[1]];
        }

        if (preg_match('/^([1-9]00)italic$/', $variant, $matches) === 1) {
            return ['style' => 'italic', 'weight' => $matches[1]];
        }

        if (preg_match('/^([1-9]00)\.\.([1-9]00)$/', $variant, $matches) === 1) {
            return ['style' => 'normal', 'weight' => $matches[1] . '..' . $matches[2]];
        }

        if (preg_match('/^([1-9]00)\.\.([1-9]00)italic$/', $variant, $matches) === 1) {
            return ['style' => 'italic', 'weight' => $matches[1] . '..' . $matches[2]];
        }

        return null;
    }

    /**
     * @param list<string> $variants
     * @return list<string>
     */
    public function normalizeVariantTokens(array $variants): array
    {
        $normalized = [];

        foreach ($variants as $variant) {
            $parts = array_map('trim', explode(',', $variant));

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                $canonical = $this->canonicalVariantToken($part);

                if ($canonical === '' || $this->googleVariantToAxis($canonical) === null) {
                    continue;
                }

                $normalized[$canonical] = $canonical;
            }
        }

        if ($normalized === []) {
            $normalized = ['regular' => 'regular'];
        }

        $tokens = array_values($normalized);

        usort(
            $tokens,
            function (string $left, string $right): int {
                $leftAxis = $this->googleVariantToAxis($left) ?? ['style' => 'normal', 'weight' => '400'];
                $rightAxis = $this->googleVariantToAxis($right) ?? ['style' => 'normal', 'weight' => '400'];

                $leftStyle = $leftAxis['style'] === 'italic' ? 1 : 0;
                $rightStyle = $rightAxis['style'] === 'italic' ? 1 : 0;

                if ($leftStyle !== $rightStyle) {
                    return $leftStyle <=> $rightStyle;
                }

                return $this->weightSortValue($leftAxis['weight']) <=> $this->weightSortValue($rightAxis['weight']);
            }
        );

        return $tokens;
    }

    public function variantKey(string|int $weight, string $style, string $unicodeRange = ''): string
    {
        $range = trim($unicodeRange);

        return implode(
            '|',
            [
                $this->normalizeWeight($weight),
                $this->normalizeStyle($style),
                $range !== '' ? md5($range) : 'none',
            ]
        );
    }

    public function faceAxisKey(string|int $weight, string $style): string
    {
        return $this->normalizeWeight($weight) . '|' . $this->normalizeStyle($style);
    }

    private function normalizeStyle(string $style): string
    {
        return FontUtils::normalizeStyle($style);
    }

    private function normalizeWeight(string|int $weight): string
    {
        return FontUtils::normalizeWeight($weight);
    }

    private function weightSortValue(string|int $weight): int
    {
        return FontUtils::weightSortValue($weight);
    }
}
