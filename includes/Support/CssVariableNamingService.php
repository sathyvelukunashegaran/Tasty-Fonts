<?php

declare(strict_types=1);

namespace TastyFonts\Support;

defined('ABSPATH') || exit;

/**
 * @phpstan-type VariantVariableNames array{family: string, numeric: string, named: string}
 */
final class CssVariableNamingService
{
    public function fontVariableName(string $family): string
    {
        $slug = $this->slugify($family);

        return $slug !== '' ? '--font-' . $slug : '';
    }

    public function fontVariableReference(string $family): string
    {
        $property = $this->fontVariableName($family);

        return $property !== '' ? 'var(' . $property . ')' : '';
    }

    public function weightVariableName(string|int $weight): string
    {
        $value = $this->concreteWeightValue($weight);

        return $value !== '' ? '--weight-' . $value : '';
    }

    public function weightSemanticVariableName(string|int $weight): string
    {
        $value = $this->concreteWeightValue($weight);
        $slug = $value !== '' ? $this->weightNameSlug($value) : '';

        return $slug !== '' ? '--weight-' . $slug : '';
    }

    public function weightVariableReference(string|int $weight, bool $preferSemantic = true): string
    {
        $property = $preferSemantic
            ? $this->weightSemanticVariableName($weight)
            : $this->weightVariableName($weight);

        if ($property === '') {
            $property = $preferSemantic
                ? $this->weightVariableName($weight)
                : $this->weightSemanticVariableName($weight);
        }

        return $property !== '' ? 'var(' . $property . ')' : '';
    }

    /**
     * @return VariantVariableNames
     */
    public function variantVariableNames(string $family, string|int $weight, string $style): array
    {
        $familyVariable = $this->fontVariableName($family);

        if ($familyVariable === '') {
            return [
                'family' => '',
                'numeric' => '',
                'named' => '',
            ];
        }

        $numeric = $familyVariable
            . '-'
            . $this->weightVariableSegment($weight)
            . $this->styleVariableSuffix($style);
        $namedWeight = $this->weightNameSlug($weight);
        $named = $namedWeight !== ''
            ? $familyVariable . '-' . $namedWeight . $this->styleVariableSuffix($style)
            : $numeric;

        return [
            'family' => $familyVariable,
            'numeric' => $numeric,
            'named' => $named,
        ];
    }

    private function concreteWeightValue(string|int $weight): string
    {
        return match ($this->normalizeWeight($weight)) {
            'normal' => '400',
            'bold' => '700',
            default => preg_match('/^\d{1,4}$/', $this->normalizeWeight($weight)) === 1
                ? $this->normalizeWeight($weight)
                : '',
        };
    }

    private function weightVariableSegment(string|int $weight): string
    {
        return str_replace('..', '-', $this->normalizeWeight($weight));
    }

    private function styleVariableSuffix(string $style): string
    {
        $normalizedStyle = $this->normalizeStyle($style);

        return $normalizedStyle === 'normal' ? '' : '-' . $normalizedStyle;
    }

    private function slugify(string $value): string
    {
        return FontUtils::slugify($value);
    }

    private function normalizeStyle(string $style): string
    {
        return FontUtils::normalizeStyle($style);
    }

    private function normalizeWeight(string|int $weight): string
    {
        return FontUtils::normalizeWeight($weight);
    }

    private function weightNameSlug(string|int $weight): string
    {
        return FontUtils::weightNameSlug($weight);
    }
}
