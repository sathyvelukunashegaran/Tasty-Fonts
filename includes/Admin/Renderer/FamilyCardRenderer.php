<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Support\FontUtils;

final class FamilyCardRenderer extends AbstractSectionRenderer
{
    use FamilyCardRendererSupport;

    public function render(array $view): void
    {
        $this->renderTemplate('family-card.php', $view);
    }

    public function renderAdobeFamilyCard(array $family): void
    {
        $familyName = (string) ($family['family'] ?? '');

        if ($familyName === '') {
            return;
        }

        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        $axisSummaryLabels = $this->buildVariationAxisSummaryLabels((array) ($family['faces'] ?? []));
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($family);
        ?>
        <article class="tasty-fonts-adobe-family-card">
            <div class="tasty-fonts-adobe-family-head">
                <strong><?php echo esc_html($familyName); ?></strong>
                <span class="tasty-fonts-badge"><?php esc_html_e('Adobe', 'tasty-fonts'); ?></span>
                <span class="tasty-fonts-badge <?php echo esc_attr((string) ($fontTypeDescriptor['badge_class'] ?? '')); ?>">
                    <?php echo esc_html((string) ($fontTypeDescriptor['label'] ?? '')); ?>
                </span>
            </div>
            <?php if ($faceSummaryLabels !== []): ?>
                <div class="tasty-fonts-face-pills">
                    <?php foreach ($faceSummaryLabels as $label): ?>
                        <span class="tasty-fonts-face-pill"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($axisSummaryLabels !== []): ?>
                <div class="tasty-fonts-face-pills">
                    <?php foreach ($axisSummaryLabels as $label): ?>
                        <span class="tasty-fonts-face-pill is-muted"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    public function renderFamilyRow(
        array $family,
        array $roles,
        array $familyFallbacks,
        array $familyFontDisplays,
        array $familyFontDisplayOptions,
        string $previewText,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = [],
        bool $monospaceRoleEnabled = false,
        array $classOutputOptions = []
    ): void {
        $familyName = (string) ($family['family'] ?? '');
        $familySlug = (string) ($family['slug'] ?? FontUtils::slugify($familyName));
        $isHeading = ($roles['heading'] ?? '') === $familyName;
        $isBody = ($roles['body'] ?? '') === $familyName;
        $isMonospace = $monospaceRoleEnabled && (($roles['monospace'] ?? '') === $familyName);
        $assignedRoleKeys = array_values(
            array_filter(
                [
                    $isHeading ? 'heading' : null,
                    $isBody ? 'body' : null,
                    $isMonospace ? 'monospace' : null,
                ]
            )
        );
        $isRoleFamily = $assignedRoleKeys !== [];
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($family);
        $sourceTokens = array_values(array_unique(array_filter((array) ($family['delivery_filter_tokens'] ?? []), 'strlen')));
        $categoryTokens = array_values(array_unique(array_filter((array) ($family['font_category_tokens'] ?? []), 'strlen')));
        if (!empty($fontTypeDescriptor['has_variable'])) {
            $categoryTokens[] = 'variable';
        }
        $categoryTokens = array_values(array_unique($categoryTokens));
        $fontCategoryLabel = $this->formatLibraryCategoryLabel((string) ($family['font_category'] ?? ''));
        $deleteBlockedMessage = $this->buildDeleteBlockedMessage($familyName, $assignedRoleKeys);
        $deleteBlockedMessages = [];
        $deleteBlockedSelections = [
            ['heading'],
            ['body'],
            ['heading', 'body'],
        ];

        if ($monospaceRoleEnabled) {
            $deleteBlockedSelections[] = ['monospace'];
            $deleteBlockedSelections[] = ['heading', 'monospace'];
            $deleteBlockedSelections[] = ['body', 'monospace'];
            $deleteBlockedSelections[] = ['heading', 'body', 'monospace'];
        }

        foreach ($deleteBlockedSelections as $selection) {
            $deleteBlockedMessages[$this->buildRoleSelectionKey($selection)] = $this->buildDeleteBlockedMessage($familyName, $selection);
        }

        $savedFallback = array_key_exists($familyName, $familyFallbacks)
            ? FontUtils::sanitizeFallback((string) $familyFallbacks[$familyName])
            : FontUtils::defaultFallbackForCategory((string) ($family['font_category'] ?? ''));
        $savedFontDisplay = (string) ($familyFontDisplays[$familyName] ?? '');
        $currentFontDisplay = $savedFontDisplay !== '' ? $savedFontDisplay : 'inherit';
        $publishState = (string) ($family['publish_state'] ?? 'published');
        $activeDelivery = is_array($family['active_delivery'] ?? null) ? $family['active_delivery'] : [];
        $activeDeliveryId = (string) ($family['active_delivery_id'] ?? '');
        $availableDeliveries = is_array($family['available_deliveries'] ?? null) ? (array) $family['available_deliveries'] : [];
        $deliveryCount = count($availableDeliveries);
        $activeDeliveryLabel = $this->translateProfileLabel((string) ($activeDelivery['label'] ?? ''));
        $activeDeliveryLabel = $activeDeliveryLabel !== '' ? $activeDeliveryLabel : __('Unavailable', 'tasty-fonts');
        $supportsFontDisplayOverride = strtolower(trim((string) ($activeDelivery['provider'] ?? ''))) !== 'adobe';
        $defaultStack = FontUtils::buildFontStack($familyName, $savedFallback);
        $previewLabel = $isMonospace ? __('Code Preview', 'tasty-fonts') : __('Preview', 'tasty-fonts');
        $inlinePreviewText = $this->buildFacePreviewText($previewText, $familyName, $isMonospace, false);
        $facePreviewText = $this->buildFacePreviewText($previewText, $familyName, $isMonospace, true);
        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels((array) ($family['faces'] ?? []));
        $visibleFaceSummaryLabels = array_slice($faceSummaryLabels, 0, 4);
        $hiddenFaceSummaryCount = max(0, count($faceSummaryLabels) - count($visibleFaceSummaryLabels));
        $faceCount = count((array) ($family['faces'] ?? []));
        $activeFaces = is_array($family['faces'] ?? null) ? (array) $family['faces'] : [];
        $familyCssVariableSnippets = $this->buildFamilyCssVariableSnippets(
            $familyName,
            $defaultStack,
            $assignedRoleKeys,
            $roles,
            (string) ($family['font_category'] ?? ''),
            $categoryAliasOwners,
            $extendedVariableOptions
        );
        $familyCssClassSnippets = $this->buildFamilyCssClassSnippets(
            $familyName,
            $assignedRoleKeys,
            (string) ($family['font_category'] ?? ''),
            $categoryAliasOwners,
            $classOutputOptions
        );
        $canChangePublishState = $publishState !== 'role_active';
        $isExpanded = false;
        $detailsId = 'tasty-fonts-family-details-' . sanitize_html_class($familySlug !== '' ? $familySlug : FontUtils::slugify($familyName));
        $templateView = get_defined_vars();
        $templateView['trainingWheelsOff'] = $this->trainingWheelsOff;

        $this->render($templateView);
    }

    public function renderMigrateDeliveryButton(string $familyName, array $profile, string $className = 'button'): void
    {
        $provider = strtolower(trim((string) ($profile['provider'] ?? '')));
        $variants = array_values(
            array_filter(
                array_map(static fn (mixed $variant): string => is_scalar($variant) ? trim((string) $variant) : '', (array) ($profile['variants'] ?? [])),
                'strlen'
            )
        );
        ?>
        <button
            type="button"
            class="<?php echo esc_attr(trim($className)); ?>"
            data-migrate-delivery
            data-migrate-provider="<?php echo esc_attr($provider); ?>"
            data-migrate-family="<?php echo esc_attr($familyName); ?>"
            data-migrate-variants="<?php echo esc_attr(implode(',', $variants)); ?>"
            title="<?php echo esc_attr__('Open the import panel with this CDN delivery pre-filled for self-hosting.', 'tasty-fonts'); ?>"
        >
            <?php esc_html_e('Migrate to Self-hosted', 'tasty-fonts'); ?>
        </button>
        <?php
    }

    public function renderDeliveryProfileCard(
        string $familyName,
        string $familySlug,
        string $activeDeliveryId,
        string $publishState,
        array $profile
    ): void {
        $profileId = (string) ($profile['id'] ?? '');
        $profileLabel = $this->translateProfileLabel((string) ($profile['label'] ?? ''));
        $profileProvider = (string) ($profile['provider'] ?? '');
        $profileIsActive = $profileId === $activeDeliveryId;
        $profileVariantCount = $this->countProfileVariants($profile);
        $profileDeleteBlocked = $profileIsActive && $publishState === 'role_active'
            ? __('Switch the live delivery or remove this family from the active roles before deleting this delivery profile.', 'tasty-fonts')
            : '';
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($profile);
        ?>
        <article class="tasty-fonts-detail-card tasty-fonts-detail-card--delivery">
            <div class="tasty-fonts-detail-card-head">
                <div class="tasty-fonts-detail-card-copy">
                    <div class="tasty-fonts-detail-card-title-row">
                        <h5 class="tasty-fonts-detail-card-title"><?php echo esc_html($profileLabel); ?></h5>
                        <?php if ($profileIsActive): ?>
                            <span class="tasty-fonts-badge is-success"><?php esc_html_e('Live', 'tasty-fonts'); ?></span>
                        <?php else: ?>
                            <span class="tasty-fonts-badge"><?php esc_html_e('Saved', 'tasty-fonts'); ?></span>
                        <?php endif; ?>
                        <span class="tasty-fonts-badge <?php echo esc_attr((string) ($fontTypeDescriptor['badge_class'] ?? '')); ?>">
                            <?php echo esc_html((string) ($fontTypeDescriptor['label'] ?? '')); ?>
                        </span>
                    </div>
                    <p class="tasty-fonts-detail-card-summary"><?php echo esc_html($this->buildProfileRequestSummary($profile)); ?></p>
                </div>
                <div class="tasty-fonts-detail-actions">
                    <?php if ($this->isMigratableCdnProfile($profile)): ?>
                        <?php $this->renderMigrateDeliveryButton($familyName, $profile, 'button button-small'); ?>
                    <?php endif; ?>
                    <button
                        type="button"
                        class="button button-small tasty-fonts-button-danger <?php echo $profileDeleteBlocked !== '' ? 'is-disabled' : ''; ?>"
                        data-delete-delivery-profile
                        data-family-slug="<?php echo esc_attr($familySlug); ?>"
                        data-family-name="<?php echo esc_attr($familyName); ?>"
                        data-delivery-id="<?php echo esc_attr($profileId); ?>"
                        data-delivery-label="<?php echo esc_attr($profileLabel); ?>"
                        aria-disabled="<?php echo esc_attr($profileDeleteBlocked !== '' ? 'true' : 'false'); ?>"
                        <?php disabled($profileDeleteBlocked !== ''); ?>
                        title="<?php echo esc_attr($profileDeleteBlocked !== '' ? $profileDeleteBlocked : __('Delete only this delivery profile and keep the family.', 'tasty-fonts')); ?>"
                        <?php if ($profileDeleteBlocked !== '') : ?>
                            data-delete-blocked="<?php echo esc_attr($profileDeleteBlocked); ?>"
                        <?php endif; ?>
                    >
                        <?php esc_html_e('Delete Delivery', 'tasty-fonts'); ?>
                    </button>
                </div>
            </div>

            <dl class="tasty-fonts-detail-meta">
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Provider', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html($this->buildFamilySourceLabel($profileProvider)); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Request Path', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html($this->buildProfileRequestSummary($profile)); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Variants', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html(sprintf(_n('%d variant', '%d variants', $profileVariantCount, 'tasty-fonts'), $profileVariantCount)); ?></dd>
                </div>
            </dl>
        </article>
        <?php
    }

    private function countProfileVariants(array $profile): int
    {
        $faces = is_array($profile['faces'] ?? null) ? (array) $profile['faces'] : [];

        if ($faces !== []) {
            return count(HostedImportSupport::variantsFromFaces($faces));
        }

        return count((array) ($profile['variants'] ?? []));
    }

    public function renderFaceDetailCard(
        string $familyName,
        string $familySlug,
        string $defaultStack,
        string $facePreviewText,
        int $faceCount,
        array $assignedRoleKeys,
        string $fontCategory,
        array $categoryAliasOwners,
        array $extendedVariableOptions,
        array $activeDelivery,
        array $face,
        bool $isMonospace = false
    ): void {
        $faceWeight = (string) ($face['weight'] ?? '400');
        $faceStyle = (string) ($face['style'] ?? 'normal');
        $faceSource = (string) ($face['source'] ?? 'local');
        $faceUnicodeRange = (string) ($face['unicode_range'] ?? '');
        $faceStorageSummary = $this->buildFaceStorageSummary($face);
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($face);
        $canDeleteVariant = $this->canDeleteFaceVariant($activeDelivery);
        $deleteVariantBlockedMessage = ($faceCount <= 1 && $assignedRoleKeys !== [])
            ? $this->buildDeleteLastVariantBlockedMessage($familyName, $assignedRoleKeys)
            : '';
        $formats = array_keys((array) ($face['files'] ?? []));
        $paths = (array) ($face['paths'] ?? []);
        $faceTitle = $this->buildFaceTitle($faceWeight, $faceStyle);
        $faceCssSnippets = $this->buildFaceCssCopySnippets($familyName, $faceWeight, $faceStyle, $extendedVariableOptions);
        ?>
        <article class="tasty-fonts-detail-card tasty-fonts-detail-card--face">
            <div class="tasty-fonts-detail-card-head">
                <div class="tasty-fonts-detail-card-copy">
                    <div class="tasty-fonts-detail-card-title-row">
                        <h5 class="tasty-fonts-detail-card-title"><?php echo esc_html($faceTitle); ?></h5>
                        <span class="tasty-fonts-badge"><?php echo esc_html($this->buildFamilySourceLabel($faceSource)); ?></span>
                        <span class="tasty-fonts-badge <?php echo esc_attr((string) ($fontTypeDescriptor['badge_class'] ?? '')); ?>">
                            <?php echo esc_html((string) ($fontTypeDescriptor['label'] ?? '')); ?>
                        </span>
                    </div>
                    <p class="tasty-fonts-detail-card-summary"><?php echo esc_html($faceStorageSummary); ?></p>
                </div>
                <div class="tasty-fonts-detail-actions">
                    <form method="post" class="tasty-fonts-delete-form tasty-fonts-delete-form--variant">
                        <?php wp_nonce_field('tasty_fonts_delete_variant'); ?>
                        <input type="hidden" name="tasty_fonts_delete_variant" value="1">
                        <input type="hidden" name="tasty_fonts_family_slug" value="<?php echo esc_attr($familySlug); ?>">
                        <input type="hidden" name="tasty_fonts_face_weight" value="<?php echo esc_attr($faceWeight); ?>">
                        <input type="hidden" name="tasty_fonts_face_style" value="<?php echo esc_attr($faceStyle); ?>">
                        <input type="hidden" name="tasty_fonts_face_source" value="<?php echo esc_attr($faceSource); ?>">
                        <input type="hidden" name="tasty_fonts_face_unicode_range" value="<?php echo esc_attr($faceUnicodeRange); ?>">
                        <button
                            type="submit"
                            class="button button-small tasty-fonts-button-danger <?php echo $deleteVariantBlockedMessage !== '' || !$canDeleteVariant ? 'is-disabled' : ''; ?>"
                            data-delete-variant="1"
                            data-delete-family-name="<?php echo esc_attr($familyName); ?>"
                            data-delete-face-weight="<?php echo esc_attr($faceWeight); ?>"
                            data-delete-face-style="<?php echo esc_attr($faceStyle); ?>"
                            aria-disabled="<?php echo esc_attr($deleteVariantBlockedMessage !== '' || !$canDeleteVariant ? 'true' : 'false'); ?>"
                            title="<?php echo esc_attr($deleteVariantBlockedMessage !== '' ? $deleteVariantBlockedMessage : ($canDeleteVariant ? __('Delete this variant from the active delivery and keep the rest of the family.', 'tasty-fonts') : __('Adobe-hosted variants are managed by Adobe Fonts and cannot be deleted individually here.', 'tasty-fonts'))); ?>"
                            <?php if ($deleteVariantBlockedMessage !== '' || !$canDeleteVariant) : ?>
                                data-delete-blocked="<?php echo esc_attr($deleteVariantBlockedMessage !== '' ? $deleteVariantBlockedMessage : __('Adobe-hosted variants are managed by Adobe Fonts and cannot be deleted individually here.', 'tasty-fonts')); ?>"
                            <?php endif; ?>
                        >
                            <?php esc_html_e('Delete Variant', 'tasty-fonts'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <div
                class="tasty-fonts-face-preview <?php echo $isMonospace ? 'is-monospace' : ''; ?>"
                data-font-preview-family="<?php echo esc_attr($familyName); ?>"
                style="font-family:<?php echo esc_attr($defaultStack); ?>; font-weight:<?php echo esc_attr($faceWeight); ?>; font-style:<?php echo esc_attr($faceStyle); ?>;"
            ><?php echo esc_html($facePreviewText); ?></div>

            <dl class="tasty-fonts-detail-meta">
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Style', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html(ucfirst(FontUtils::normalizeStyle($faceStyle))); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Storage', 'tasty-fonts'); ?></dt>
                    <dd><?php echo esc_html($faceStorageSummary); ?></dd>
                </div>
                <div class="tasty-fonts-detail-meta-item">
                    <dt><?php esc_html_e('Formats', 'tasty-fonts'); ?></dt>
                    <dd class="tasty-fonts-detail-chip-row">
                        <?php foreach ($formats as $format): ?>
                            <span class="tasty-fonts-chip"><?php echo esc_html(strtoupper((string) $format)); ?></span>
                        <?php endforeach; ?>
                    </dd>
                </div>
            </dl>

            <div class="tasty-fonts-detail-files tasty-fonts-detail-files--css">
                <span class="tasty-fonts-detail-files-label"><?php esc_html_e('CSS', 'tasty-fonts'); ?></span>
                <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                    <?php $this->renderFaceVariableCopyPill(__('Family', 'tasty-fonts'), (string) ($faceCssSnippets['family'] ?? ''), __('Family CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Weight', 'tasty-fonts'), (string) ($faceCssSnippets['weight'] ?? ''), __('Weight CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Style', 'tasty-fonts'), (string) ($faceCssSnippets['style'] ?? ''), __('Style CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Snippet', 'tasty-fonts'), (string) ($faceCssSnippets['snippet'] ?? ''), __('CSS snippet copied.', 'tasty-fonts')); ?>
                </div>
            </div>

            <?php if ($paths !== []): ?>
                <div class="tasty-fonts-detail-files tasty-fonts-detail-files--paths">
                    <span class="tasty-fonts-detail-files-label"><?php esc_html_e('Files', 'tasty-fonts'); ?></span>
                    <div class="tasty-fonts-detail-file-list">
                        <?php foreach ($paths as $format => $path): ?>
                            <div class="tasty-fonts-file-path">
                                <strong><?php echo esc_html(strtoupper((string) $format)); ?>:</strong>
                                <div class="tasty-fonts-code"><?php echo esc_html(FontUtils::compactRelativePath((string) $path)); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }
}
