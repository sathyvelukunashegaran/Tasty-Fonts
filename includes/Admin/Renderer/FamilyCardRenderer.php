<?php

declare(strict_types=1);

namespace TastyFonts\Admin\Renderer;

defined('ABSPATH') || exit;

use TastyFonts\Admin\DeliveryProfileLabelHelper;
use TastyFonts\Fonts\HostedImportSupport;
use TastyFonts\Support\FontUtils;

/**
 * @phpstan-import-type CatalogFamily from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type CatalogFace from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type DeliveryProfile from \TastyFonts\Fonts\CatalogService
 * @phpstan-import-type RoleSet from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFallbackMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-import-type FamilyFontDisplayMap from \TastyFonts\Repository\SettingsRepository
 * @phpstan-type FamilyCardView array<string, mixed>
 * @phpstan-type FamilyFontDisplayOptions list<array<string, mixed>>
 * @phpstan-type CategoryAliasOwners array<string, string>
 * @phpstan-type RendererFlagOptions array<string, mixed>
 */
final class FamilyCardRenderer extends AbstractSectionRenderer
{
    use FamilyCardRendererSupport;

    /**
     * @param FamilyCardView $view
     */
    public function render(array $view): void
    {
        $this->renderTemplate('family-card.php', $view);
    }

    /**
     * @param CatalogFamily $family
     */
    public function renderAdobeFamilyCard(array $family): void
    {
        $familyName = $this->stringValue($family, 'family');

        if ($familyName === '') {
            return;
        }

        $faces = $this->normalizeFaceList($family['faces'] ?? []);
        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels($faces);
        $axisSummaryLabels = $this->buildVariationAxisSummaryLabels($faces);
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($family);
        ?>
        <article class="tasty-fonts-adobe-family-card">
            <div class="tasty-fonts-adobe-family-head">
                <strong><?php echo esc_html($familyName); ?></strong>
                <span class="tasty-fonts-badge"><?php esc_html_e('Adobe', 'tasty-fonts'); ?></span>
                <span class="tasty-fonts-badge <?php echo esc_attr($this->stringValue($fontTypeDescriptor, 'badge_class')); ?>">
                    <?php echo esc_html($this->stringValue($fontTypeDescriptor, 'label')); ?>
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

    /**
     * @param CatalogFamily $family
     * @param RoleSet $roles
     * @param FamilyFallbackMap $familyFallbacks
     * @param FamilyFontDisplayMap $familyFontDisplays
     * @param FamilyFontDisplayOptions $familyFontDisplayOptions
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $extendedVariableOptions
     * @param RendererFlagOptions $classOutputOptions
     * @param RoleSet|null $roleUsageRoles
     */
    public function renderFamilyCardDetails(
        array $family,
        array $roles,
        array $familyFallbacks,
        array $familyFontDisplays,
        array $familyFontDisplayOptions,
        string $previewText,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = [],
        bool $monospaceRoleEnabled = false,
        array $classOutputOptions = [],
        ?array $roleUsageRoles = null
    ): void {
        $view = $this->buildFamilyTemplateView(
            $family,
            $roles,
            $familyFallbacks,
            $familyFontDisplays,
            $familyFontDisplayOptions,
            $previewText,
            $categoryAliasOwners,
            $extendedVariableOptions,
            $monospaceRoleEnabled,
            $classOutputOptions,
            $roleUsageRoles
        );
        $view['includeDetails'] = true;
        $this->renderTemplate('family-card-details.php', $view);
    }

    /**
     * @param CatalogFamily $family
     * @param RoleSet $roles
     * @param FamilyFallbackMap $familyFallbacks
     * @param FamilyFontDisplayMap $familyFontDisplays
     * @param FamilyFontDisplayOptions $familyFontDisplayOptions
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $extendedVariableOptions
     * @param RendererFlagOptions $classOutputOptions
     * @param RoleSet|null $roleUsageRoles
     */
    public function renderFamilySummaryRow(
        array $family,
        array $roles,
        array $familyFallbacks,
        array $familyFontDisplays,
        array $familyFontDisplayOptions,
        string $previewText,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = [],
        bool $monospaceRoleEnabled = false,
        array $classOutputOptions = [],
        ?array $roleUsageRoles = null
    ): void {
        $view = $this->buildFamilyTemplateView(
            $family,
            $roles,
            $familyFallbacks,
            $familyFontDisplays,
            $familyFontDisplayOptions,
            $previewText,
            $categoryAliasOwners,
            $extendedVariableOptions,
            $monospaceRoleEnabled,
            $classOutputOptions,
            $roleUsageRoles
        );
        $view['trainingWheelsOff'] = $this->trainingWheelsOff;
        $view['includeDetails'] = false;

        $this->render($view);
    }

    /**
     * @param CatalogFamily $family
     * @param RoleSet $roles
     * @param FamilyFallbackMap $familyFallbacks
     * @param FamilyFontDisplayMap $familyFontDisplays
     * @param FamilyFontDisplayOptions $familyFontDisplayOptions
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $extendedVariableOptions
     * @param RendererFlagOptions $classOutputOptions
     */
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
        $templateView = $this->buildFamilyTemplateView(
            $family,
            $roles,
            $familyFallbacks,
            $familyFontDisplays,
            $familyFontDisplayOptions,
            $previewText,
            $categoryAliasOwners,
            $extendedVariableOptions,
            $monospaceRoleEnabled,
            $classOutputOptions
        );
        $templateView['trainingWheelsOff'] = $this->trainingWheelsOff;
        $templateView['includeDetails'] = true;

        $this->render($templateView);
    }

    /**
     * @param CatalogFamily $family
     * @param RoleSet $roles
     * @param FamilyFallbackMap $familyFallbacks
     * @param FamilyFontDisplayMap $familyFontDisplays
     * @param FamilyFontDisplayOptions $familyFontDisplayOptions
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $extendedVariableOptions
     * @param RendererFlagOptions $classOutputOptions
     * @param RoleSet|null $roleUsageRoles
     * @return FamilyCardView
     */
    private function buildFamilyTemplateView(
        array $family,
        array $roles,
        array $familyFallbacks,
        array $familyFontDisplays,
        array $familyFontDisplayOptions,
        string $previewText,
        array $categoryAliasOwners = [],
        array $extendedVariableOptions = [],
        bool $monospaceRoleEnabled = false,
        array $classOutputOptions = [],
        ?array $roleUsageRoles = null
    ): array {
        $familyName = $this->stringValue($family, 'family');
        $familySlug = $this->stringValue($family, 'slug', FontUtils::slugify($familyName));
        $isHeading = $roles['heading'] === $familyName;
        $isBody = $roles['body'] === $familyName;
        $isMonospace = $monospaceRoleEnabled && ($roles['monospace'] === $familyName);
        $roleUsageRoles = is_array($roleUsageRoles) ? $roleUsageRoles : $roles;
        $isLiveHeading = $this->stringValue($roleUsageRoles, 'heading') === $familyName;
        $isLiveBody = $this->stringValue($roleUsageRoles, 'body') === $familyName;
        $isLiveMonospace = $monospaceRoleEnabled && ($this->stringValue($roleUsageRoles, 'monospace') === $familyName);
        $fontCategory = strtolower(trim($this->stringValue($family, 'font_category')));
        $assignedRoleKeys = array_values(
            array_filter(
                [
                    $isHeading ? 'heading' : null,
                    $isBody ? 'body' : null,
                    $isMonospace ? 'monospace' : null,
                ]
            )
        );
        $liveRoleKeys = array_values(
            array_filter(
                [
                    $isLiveHeading ? 'heading' : null,
                    $isLiveBody ? 'body' : null,
                    $isLiveMonospace ? 'monospace' : null,
                ]
            )
        );
        $isRoleFamily = $liveRoleKeys !== [];
        $isDraftRoleFamily = $assignedRoleKeys !== [];
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($family);
        $sourceTokens = $this->normalizeStringList($family['delivery_filter_tokens'] ?? []);
        $categoryTokens = $this->normalizeStringList($family['font_category_tokens'] ?? []);
        if (!empty($fontTypeDescriptor['has_variable'])) {
            $categoryTokens[] = 'variable';
        }
        $categoryTokens = array_values(array_unique($categoryTokens));
        $normalizedCategoryTokens = array_map(static fn (string $token): string => strtolower(trim($token)), $categoryTokens);
        $usesMonospacePreview = $isMonospace
            || $fontCategory === 'monospace'
            || in_array('monospace', $normalizedCategoryTokens, true)
            || in_array('mono', $normalizedCategoryTokens, true);
        $fontCategoryLabel = $this->formatLibraryCategoryLabel($this->stringValue($family, 'font_category'));
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
            ? FontUtils::sanitizeFallback($familyFallbacks[$familyName])
            : FontUtils::defaultFallbackForCategory($this->stringValue($family, 'font_category'));
        $savedFontDisplay = array_key_exists($familyName, $familyFontDisplays) ? $familyFontDisplays[$familyName] : '';
        $currentFontDisplay = $savedFontDisplay !== '' ? $savedFontDisplay : 'inherit';
        $publishState = $this->stringValue($family, 'publish_state', 'published');
        $activeDelivery = FontUtils::normalizeStringKeyedMap($family['active_delivery'] ?? []);
        $activeDeliveryId = $this->stringValue($family, 'active_delivery_id');
        $availableDeliveries = FontUtils::normalizeListOfStringKeyedMaps($family['available_deliveries'] ?? []);
        $deliveryCount = count($availableDeliveries);
        $activeDeliveryLabel = $this->buildDeliveryProfileDisplayLabel(
            $activeDelivery,
            $availableDeliveries,
            DeliveryProfileLabelHelper::FORMAT_AUTO
        );
        $activeDeliveryLabel = $activeDeliveryLabel !== '' ? $activeDeliveryLabel : __('Unavailable', 'tasty-fonts');
        $supportsFontDisplayOverride = strtolower(trim($this->stringValue($activeDelivery, 'provider'))) !== 'adobe';
        $defaultStack = FontUtils::buildFontStack($familyName, $savedFallback);
        $previewLabel = $usesMonospacePreview ? __('Code Preview', 'tasty-fonts') : __('Preview', 'tasty-fonts');
        $inlinePreviewText = $this->buildFacePreviewText($previewText, $familyName, $usesMonospacePreview, false);
        $facePreviewText = $this->buildFacePreviewText($previewText, $familyName, $usesMonospacePreview, true);
        $familyFaces = $this->normalizeFaceList($family['faces'] ?? []);
        $faceSummaryLabels = $this->buildFamilyFaceSummaryLabels($familyFaces);
        $visibleFaceSummaryLabels = array_slice($faceSummaryLabels, 0, 4);
        $hiddenFaceSummaryCount = max(0, count($faceSummaryLabels) - count($visibleFaceSummaryLabels));
        $faceCount = count($familyFaces);
        $activeFaces = $familyFaces;
        $familyCssVariableSnippets = $this->buildFamilyCssVariableSnippets(
            $familyName,
            $defaultStack,
            $assignedRoleKeys,
            $roles,
            $this->stringValue($family, 'font_category'),
            $categoryAliasOwners,
            $extendedVariableOptions
        );
        $familyCssClassSnippets = $this->buildFamilyCssClassSnippets(
            $familyName,
            $assignedRoleKeys,
            $this->stringValue($family, 'font_category'),
            $categoryAliasOwners,
            $classOutputOptions
        );
        $canChangePublishState = $publishState !== 'role_active';
        $quickPublishDisabledReason = '';
        $quickPublishEnabled = $canChangePublishState
            && $familySlug !== ''
            && in_array($publishState, ['published', 'library_only'], true);
        $quickPublishTargetState = '';

        if ($quickPublishEnabled) {
            $quickPublishTargetState = $publishState === 'published' ? 'library_only' : 'published';
        } elseif ($publishState === 'role_active') {
            $quickPublishDisabledReason = __('Publish state is auto-managed while this family is used in live roles.', 'tasty-fonts');
        } elseif ($familySlug === '') {
            $quickPublishDisabledReason = __('Quick publish is unavailable because the family identifier is missing.', 'tasty-fonts');
        } else {
            $quickPublishDisabledReason = __('Open Details or refresh before changing publish state.', 'tasty-fonts');
        }

        $quickPublishAction = [
            'enabled' => $quickPublishEnabled,
            'current_state' => $publishState,
            'target_state' => $quickPublishTargetState,
            'label' => $quickPublishEnabled
                ? ($quickPublishTargetState === 'published'
                    ? __('Quick publish this family', 'tasty-fonts')
                    : __('Quick unpublish this family', 'tasty-fonts'))
                : __('Quick publish unavailable', 'tasty-fonts'),
            'help' => $quickPublishEnabled
                ? __('Toggle publish state immediately. Open Details for explicit state controls.', 'tasty-fonts')
                : $quickPublishDisabledReason,
            'disabled_reason' => $quickPublishDisabledReason,
        ];
        $quickDeliveryAction = $this->buildQuickDeliveryAction($availableDeliveries, $activeDeliveryId, $activeDelivery);
        $isExpanded = false;
        $detailsId = 'tasty-fonts-family-details-' . sanitize_html_class($familySlug !== '' ? $familySlug : FontUtils::slugify($familyName));
        $templateView = get_defined_vars();

        return $templateView;
    }

    /**
     * @param DeliveryProfile $profile
     */
    public function renderMigrateDeliveryButton(string $familyName, array $profile, string $className = 'button'): void
    {
        $provider = strtolower(trim($this->stringValue($profile, 'provider')));
        $variants = $this->normalizeStringList($profile['variants'] ?? []);
        $profileLabel = $this->translateProfileLabel($this->stringValue($profile, 'label'));
        ?>
        <button
            type="button"
            class="<?php echo esc_attr(trim($className)); ?>"
            data-migrate-delivery
            data-migrate-provider="<?php echo esc_attr($provider); ?>"
            data-migrate-family="<?php echo esc_attr($familyName); ?>"
            data-migrate-variants="<?php echo esc_attr(implode(',', $variants)); ?>"
            aria-label="<?php echo esc_attr(sprintf(__('Migrate %1$s delivery for %2$s to self-hosted.', 'tasty-fonts'), $profileLabel, $familyName)); ?>"
            title="<?php echo esc_attr__('Open the import panel with this CDN delivery pre-filled for self-hosting.', 'tasty-fonts'); ?>"
        >
            <?php esc_html_e('Self-host', 'tasty-fonts'); ?>
        </button>
        <?php
    }

    /**
     * @param DeliveryProfile $profile
     * @param list<DeliveryProfile> $siblingProfiles
     */
    public function renderDeliveryProfileCard(
        string $familyName,
        string $familySlug,
        string $activeDeliveryId,
        string $publishState,
        array $profile,
        array $siblingProfiles = []
    ): void {
        $profileId = $this->stringValue($profile, 'id');
        $profileLabel = $this->translateProfileLabel($this->stringValue($profile, 'label'));
        $profileActionLabel = $this->buildDeliveryProfileDisplayLabel($profile, $siblingProfiles);
        $profileActionLabel = $profileActionLabel !== '' ? $profileActionLabel : $profileLabel;
        $profileIsActive = $profileId === $activeDeliveryId;
        $profileVariantCount = $this->countProfileVariants($profile);
        $profileVariantLabel = sprintf(_n('%d variant', '%d variants', $profileVariantCount, 'tasty-fonts'), $profileVariantCount);
        $isCustomCssProfile = $this->isCustomCssProfile($profile);
        $customCssSourceUrl = $isCustomCssProfile ? $this->customCssSourceUrl($profile) : '';
        $customCssDeliveryModeLabel = $isCustomCssProfile ? $this->customCssDeliveryModeLabel($profile) : '';
        $customCssDeliveryModeDescription = $isCustomCssProfile ? $this->customCssDeliveryModeDescription($profile) : '';
        $customCssLastVerifiedAt = $isCustomCssProfile ? $this->customCssLastVerifiedAt($profile) : '';
        $customCssLastVerifiedLabel = $customCssLastVerifiedAt !== '' ? $this->formatCustomCssTimestamp($customCssLastVerifiedAt) : '';
        $profileDeleteBlocked = $profileIsActive && $publishState === 'role_active'
            ? __('Switch the live delivery or remove this family from the active roles before deleting this delivery profile.', 'tasty-fonts')
            : '';
        $fontTypeDescriptorEntry = $profile;
        if (isset($profile['format']) && !isset($profile['formats'])) {
            $profileFormat = FontUtils::resolveProfileFormat($profile);
            $fontTypeDescriptorEntry['formats'] = [
                $profileFormat => [
                    'available' => true,
                    'label' => ucfirst($profileFormat),
                ],
            ];
        }
        $fontTypeDescriptor = $this->buildFontTypeDescriptor($fontTypeDescriptorEntry);
        ?>
        <article class="tasty-fonts-detail-card tasty-fonts-detail-card--delivery <?php echo $profileIsActive ? 'is-active-delivery' : ''; ?>" <?php echo $profileIsActive ? 'aria-current="true"' : ''; ?>>
            <div class="tasty-fonts-detail-card-head">
                <div class="tasty-fonts-detail-card-copy">
                    <div class="tasty-fonts-detail-card-title-row">
                        <h5 class="tasty-fonts-detail-card-title"><?php echo esc_html($profileLabel); ?></h5>
                        <?php if ($profileIsActive): ?>
                            <span class="tasty-fonts-badge is-role"><?php esc_html_e('Active delivery', 'tasty-fonts'); ?></span>
                        <?php endif; ?>
                        <span class="tasty-fonts-badge <?php echo esc_attr($this->stringValue($fontTypeDescriptor, 'badge_class')); ?>">
                            <?php echo esc_html($this->stringValue($fontTypeDescriptor, 'label')); ?>
                        </span>
                        <span class="tasty-fonts-badge tasty-fonts-detail-card-count"><?php echo esc_html($profileVariantLabel); ?></span>
                    </div>
                    <p class="screen-reader-text tasty-fonts-detail-card-summary"><?php echo esc_html($this->buildProfileRequestSummary($profile)); ?></p>
                </div>
                <div class="tasty-fonts-detail-actions">
                    <?php if (!$profileIsActive && $profileId !== '' && count($siblingProfiles) > 1): ?>
                        <button
                            type="button"
                            class="button button-small tasty-fonts-delivery-choice-button"
                            data-family-delivery-choice
                            data-family-slug="<?php echo esc_attr($familySlug); ?>"
                            data-delivery-id="<?php echo esc_attr($profileId); ?>"
                            data-delivery-label="<?php echo esc_attr($profileActionLabel); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('Use %1$s delivery for %2$s.', 'tasty-fonts'), $profileActionLabel, $familyName)); ?>"
                        >
                            <?php esc_html_e('Use', 'tasty-fonts'); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($this->isMigratableCdnProfile($profile)): ?>
                        <?php $this->renderMigrateDeliveryButton($familyName, $profile, 'button button-small'); ?>
                    <?php endif; ?>
                    <button
                        type="button"
                        class="button button-small tasty-fonts-button-danger tasty-fonts-delivery-delete-button <?php echo $profileDeleteBlocked !== '' ? 'is-disabled' : ''; ?>"
                        data-delete-delivery-profile
                        data-family-slug="<?php echo esc_attr($familySlug); ?>"
                        data-family-name="<?php echo esc_attr($familyName); ?>"
                        data-delivery-id="<?php echo esc_attr($profileId); ?>"
                        data-delivery-label="<?php echo esc_attr($profileActionLabel); ?>"
                        aria-disabled="<?php echo esc_attr($profileDeleteBlocked !== '' ? 'true' : 'false'); ?>"
                        <?php disabled($profileDeleteBlocked !== ''); ?>
                        title="<?php echo esc_attr($profileDeleteBlocked !== '' ? $profileDeleteBlocked : __('Delete only this delivery profile and keep the family.', 'tasty-fonts')); ?>"
                        <?php if ($profileDeleteBlocked !== '') : ?>
                            data-delete-blocked="<?php echo esc_attr($profileDeleteBlocked); ?>"
                        <?php endif; ?>
                        aria-label="<?php echo esc_attr(sprintf(__('Delete %1$s delivery for %2$s.', 'tasty-fonts'), $profileActionLabel, $familyName)); ?>"
                    >
                        <span class="screen-reader-text"><?php esc_html_e('Delete', 'tasty-fonts'); ?></span>
                    </button>
                </div>
            </div>

            <?php if ($isCustomCssProfile): ?>
                <dl class="tasty-fonts-detail-meta tasty-fonts-detail-meta--delivery">
                    <div class="tasty-fonts-detail-meta-item tasty-fonts-detail-meta-item--source-url">
                        <dt><?php esc_html_e('Source CSS URL', 'tasty-fonts'); ?></dt>
                        <dd>
                            <code class="tasty-fonts-code"><?php echo esc_html($customCssSourceUrl); ?></code>
                            <span class="tasty-fonts-detail-meta-note"><?php esc_html_e('Read-only source history from the original import.', 'tasty-fonts'); ?></span>
                        </dd>
                    </div>
                    <div class="tasty-fonts-detail-meta-item">
                        <dt><?php esc_html_e('Delivery Mode', 'tasty-fonts'); ?></dt>
                        <dd>
                            <span><?php echo esc_html($customCssDeliveryModeLabel); ?></span>
                            <span class="tasty-fonts-detail-meta-note"><?php echo esc_html($customCssDeliveryModeDescription); ?></span>
                        </dd>
                    </div>
                    <?php if ($customCssLastVerifiedAt !== '' && $customCssLastVerifiedLabel !== ''): ?>
                        <div class="tasty-fonts-detail-meta-item">
                            <dt><?php esc_html_e('Last Verified', 'tasty-fonts'); ?></dt>
                            <dd><time datetime="<?php echo esc_attr($customCssLastVerifiedAt); ?>"><?php echo esc_html($customCssLastVerifiedLabel); ?></time></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </article>
        <?php
    }

    /**
     * @param DeliveryProfile $profile
     */
    private function countProfileVariants(array $profile): int
    {
        $faces = $this->normalizeFaceList($profile['faces'] ?? []);

        if ($faces !== []) {
            return count(HostedImportSupport::variantsFromFaces($faces));
        }

        return count((array) ($profile['variants'] ?? []));
    }

    /**
     * @param mixed $faces
     * @return list<CatalogFace>
     */
    private function normalizeFaceList(mixed $faces): array
    {
        return FontUtils::normalizeFaceList($faces);
    }

    /**
     * @param list<string> $assignedRoleKeys
     * @param CategoryAliasOwners $categoryAliasOwners
     * @param RendererFlagOptions $extendedVariableOptions
     * @param DeliveryProfile $activeDelivery
     * @param CatalogFace $face
     */
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
        $faceWeight = $this->stringValue($face, 'weight', '400');
        $faceStyle = $this->stringValue($face, 'style', 'normal');
        $faceSource = $this->stringValue($face, 'source', 'local');
        $faceUnicodeRange = $this->stringValue($face, 'unicode_range');
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
                        <span class="tasty-fonts-badge <?php echo esc_attr($this->stringValue($fontTypeDescriptor, 'badge_class')); ?>">
                            <?php echo esc_html($this->stringValue($fontTypeDescriptor, 'label')); ?>
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
                            <span class="tasty-fonts-chip"><?php echo esc_html(strtoupper(is_string($format) ? $format : FontUtils::scalarStringValue($format))); ?></span>
                        <?php endforeach; ?>
                    </dd>
                </div>
            </dl>

            <div class="tasty-fonts-detail-files tasty-fonts-detail-files--css">
                <span class="tasty-fonts-detail-files-label"><?php esc_html_e('CSS', 'tasty-fonts'); ?></span>
                <div class="tasty-fonts-role-stacks tasty-fonts-role-stacks--selection">
                    <?php $this->renderFaceVariableCopyPill(__('Family', 'tasty-fonts'), $this->stringValue($faceCssSnippets, 'family'), __('Family CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Weight', 'tasty-fonts'), $this->stringValue($faceCssSnippets, 'weight'), __('Weight CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Style', 'tasty-fonts'), $this->stringValue($faceCssSnippets, 'style'), __('Style CSS copied.', 'tasty-fonts')); ?>
                    <?php $this->renderFaceVariableCopyPill(__('Snippet', 'tasty-fonts'), $this->stringValue($faceCssSnippets, 'snippet'), __('CSS snippet copied.', 'tasty-fonts')); ?>
                </div>
            </div>

            <?php if ($paths !== []): ?>
                <div class="tasty-fonts-detail-files tasty-fonts-detail-files--paths">
                    <span class="tasty-fonts-detail-files-label"><?php esc_html_e('Files', 'tasty-fonts'); ?></span>
                    <div class="tasty-fonts-detail-file-list">
                        <?php foreach ($paths as $format => $path): ?>
                            <div class="tasty-fonts-file-path">
                                <strong><?php echo esc_html(strtoupper(is_string($format) ? $format : FontUtils::scalarStringValue($format))); ?>:</strong>
                                <div class="tasty-fonts-code"><?php echo esc_html(FontUtils::compactRelativePath(FontUtils::scalarStringValue($path))); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function stringValue(array $values, int|string $key, string $default = ''): string
    {
        if (!array_key_exists($key, $values)) {
            return $default;
        }

        $value = FontUtils::scalarStringValue($values[$key]);

        return $value !== '' ? $value : $default;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $stringValue = FontUtils::scalarStringValue($item);

            if ($stringValue === '') {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return array_values(array_unique($normalized));
    }
}
