<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Support\FontUtils;
use TastyFonts\Support\VariantTokenService;

/**
 * Pure planner for hosted import variant deltas.
 *
 * @phpstan-type HostedVariantPlan array{import: list<string>, skipped: list<string>}
 */
final class HostedImportVariantPlanner
{
    private readonly VariantTokenService $variantTokens;

    public function __construct(?VariantTokenService $variantTokens = null)
    {
        $this->variantTokens = $variantTokens ?? new VariantTokenService();
    }

    /**
     * @param array<int|string, mixed> $requestedVariants
     * @param array<string, mixed>|null $existingProfile
     * @param (callable(list<string>): mixed)|null $normalizeRequested
     * @return HostedVariantPlan
     */
    public function plan(array $requestedVariants, ?array $existingProfile, ?callable $normalizeRequested = null): array
    {
        $existingKeys = [];

        foreach (FontUtils::normalizeListOfStringKeyedMaps($existingProfile['faces'] ?? []) as $face) {
            $existingKeys[HostedImportSupport::faceKeyFromFace($face)] = true;
        }

        $normalizedInput = $this->normalizeVariantList($requestedVariants);
        $normalizedRequested = $normalizeRequested === null
            ? $normalizedInput
            : $this->normalizeVariantList($normalizeRequested($normalizedInput));
        $toImport = [];
        $skipped = [];

        foreach ($normalizedRequested as $variant) {
            $faceKey = HostedImportSupport::faceKeyFromVariant($variant);

            if ($faceKey === null) {
                continue;
            }

            if (isset($existingKeys[$faceKey])) {
                $skipped[] = $variant;
                continue;
            }

            $toImport[] = $variant;
        }

        return [
            'import' => array_values(array_unique($toImport)),
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    /**
     * @param mixed $variants
     * @return list<string>
     */
    private function normalizeVariantList(mixed $variants): array
    {
        if (!is_array($variants)) {
            return [];
        }

        $normalized = [];

        foreach ($variants as $variant) {
            if (!is_scalar($variant)) {
                continue;
            }

            $normalized[] = (string) $variant;
        }

        return $this->variantTokens->normalizeVariantTokens($normalized);
    }
}
