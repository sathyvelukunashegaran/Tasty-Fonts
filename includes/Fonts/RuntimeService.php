<?php

declare(strict_types=1);

namespace TastyFonts\Fonts;

defined('ABSPATH') || exit;

use TastyFonts\Adobe\AdobeProjectClient;
use TastyFonts\Admin\AdminAccessService;
use TastyFonts\Admin\AdminController;
use TastyFonts\Api\RestController;
use TastyFonts\Integrations\AcssIntegrationService;
use TastyFonts\Integrations\BricksIntegrationService;
use TastyFonts\Integrations\OxygenIntegrationService;
use TastyFonts\Repository\SettingsRepository;
use TastyFonts\Support\FontUtils;
use WP_Theme_JSON_Data;

/**
 * @phpstan-import-type CatalogFamily from CatalogService
 * @phpstan-import-type RuntimeFamilyList from RuntimeAssetPlanner
 * @phpstan-import-type StylesheetDescriptor from RuntimeAssetPlanner
 * @phpstan-import-type NormalizedSettings from SettingsRepository
 * @phpstan-type EditorSettings array<string, mixed>
 * @phpstan-type StylesheetList list<StylesheetDescriptor>
 * @phpstan-type CssLineList list<string>
 */
final class RuntimeService
{
    /**
     * Create the runtime service.
     *
     * @since 1.4.0
     *
     * @param RuntimeAssetPlanner $planner Planner that resolves runtime stylesheets and editor presets.
     * @param AssetService $assets Asset service for generated CSS handles and preload URLs.
     * @param AdobeProjectClient $adobe Adobe client used to version Adobe-hosted stylesheet handles.
     */
    public function __construct(
        private readonly RuntimeAssetPlanner $planner,
        private readonly AssetService $assets,
        private readonly CssBuilder $cssBuilder,
        private readonly AdobeProjectClient $adobe,
        private readonly SettingsRepository $settings,
        private readonly AcssIntegrationService $acssIntegration,
        private readonly BricksIntegrationService $bricksIntegration,
        private readonly OxygenIntegrationService $oxygenIntegration,
        private readonly ?CatalogService $catalog = null,
        private readonly ?RoleFamilyCatalogBuilder $roleFamilyCatalogBuilder = null,
        private readonly ?AdminAccessService $adminAccess = null
    ) {
    }

    /**
     * Enqueue frontend font assets for the live site.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueFrontend(): void
    {
        $hasEtchCanvasRequest = $this->hasEtchCanvasRequest();

        if (!$this->sitewideDeliveryEnabled() && !$hasEtchCanvasRequest) {
            return;
        }

        $this->assets->enqueue('tasty-fonts-frontend');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets());

        if ($hasEtchCanvasRequest) {
            $this->enqueueEtchCanvasBridge();
        }
    }

    public function enqueueBricksFrontendOverride(): void
    {
        if (is_admin() || !$this->sitewideDeliveryEnabled()) {
            return;
        }

        $this->enqueueBricksQuotedVariableFixScript();

        $settings = $this->settings->getSettings();

        if (!$this->bricksIntegration->managedFrontendStylesActive($settings)) {
            return;
        }

        $css = implode('', array_values(array_unique(array_filter(
            $this->bricksIntegration->getManagedFrontendStyles(),
            static fn (string $style): bool => $style !== ''
        ))));

        if ($css === '') {
            return;
        }

        wp_register_style('tasty-fonts-bricks-runtime-override', false, [], TASTY_FONTS_VERSION);
        wp_enqueue_style('tasty-fonts-bricks-runtime-override');
        wp_add_inline_style('tasty-fonts-bricks-runtime-override', $css);
    }

    public function enqueueEtchFrontendOverride(): void
    {
        if (is_admin() || !$this->etchIntegrationEnabled()) {
            return;
        }

        $css = $this->buildRoleBridgeCss('frontend');
        if ($css === '') {
            return;
        }

        wp_register_style('tasty-fonts-etch-runtime-override', false, [], TASTY_FONTS_VERSION);
        wp_enqueue_style('tasty-fonts-etch-runtime-override');
        wp_add_inline_style('tasty-fonts-etch-runtime-override', $css);
    }

    public function enqueueBricksBuilder(): void
    {
        if (!$this->isBricksBuilderRequest()) {
            return;
        }

        if ($this->bricksBuilderPreviewEnabled()) {
            $this->assets->enqueue('tasty-fonts-bricks-builder');
            $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets(), 'bricks-builder');
        }

        if (!$this->bricksSelectorEnabled() || !$this->bricksIntegration->isAvailable()) {
            return;
        }

        $this->enqueueBricksBuilderSelectorGroupingScript($this->planner->getRuntimeFamilies());
    }

    /**
     * Output preload and preconnect hints for active runtime font assets.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function outputPreloadHints(): void
    {
        if (is_admin() || $this->hasEtchCanvasRequest() || !$this->sitewideDeliveryEnabled()) {
            return;
        }

        foreach ($this->planner->getPreconnectOrigins() as $origin) {
            if (trim($origin) === '') {
                continue;
            }

            echo '<link rel="preconnect" href="' . esc_url($origin) . '" crossorigin>' . "\n";
        }

        if ($this->assets->isInlineDeliveryEnabled()) {
            return;
        }

        $preloadMode = $this->preloadLinkDeliveryMode();
        $preloadUrls = $this->planner->getPrimaryFontPreloadUrls();

        if (in_array($preloadMode, ['headers', 'both'], true)) {
            $this->sendPreloadLinkHeaders($this->getPreloadLinkHeaderValues($preloadUrls));
        }

        if ($preloadMode === 'headers') {
            return;
        }

        foreach ($preloadUrls as $url) {
            if (trim($url) === '') {
                continue;
            }

            echo '<link rel="preload" href="' . esc_url($url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }

    /**
     * Enqueue font assets for the Etch canvas runtime.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueEtchCanvas(): void
    {
        if (!$this->etchIntegrationEnabled()) {
            return;
        }

        $this->assets->enqueue('tasty-fonts-etch');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets());
    }

    /**
     * Enqueue font assets inside the block editor.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueBlockEditor(): void
    {
        $this->assets->enqueue('tasty-fonts-editor');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets());
    }

    /**
     * Enqueue content styles that Gutenberg hoists into the editor canvas iframe.
     *
     * @since 1.4.0
     *
     * @return void
     */
    public function enqueueBlockEditorContent(): void
    {
        if (!is_admin()) {
            return;
        }

        $this->assets->enqueue('tasty-fonts-editor-content');
        $this->enqueueExternalStylesheets($this->planner->getExternalStylesheets(), 'editor-content');
        $this->enqueueAcssRuntimeStylesheet('editor-content');
    }

    /**
     * Enqueue preview fonts for the plugin's admin screens.
     *
     * @since 1.4.0
     *
     * @param string $hookSuffix Current admin hook suffix.
     * @return void
     */
    public function enqueueAdminScreenFonts(string $hookSuffix): void
    {
        if (!\TastyFonts\Admin\AdminController::isPluginAdminHook($hookSuffix)) {
            return;
        }

        $this->assets->enqueueFontFacesOnly('tasty-fonts-admin-fonts');
        $this->enqueueExternalStylesheets($this->planner->getAdminPreviewStylesheets());
    }

    /**
     * Inject plugin-managed font families into the editor theme JSON settings.
     *
     * @since 1.4.0
     *
     * @param WP_Theme_JSON_Data $themeJson Theme JSON object passed through the WordPress filter.
     * @return WP_Theme_JSON_Data Updated theme JSON data.
     */
    public function injectEditorFontPresets(WP_Theme_JSON_Data $themeJson): WP_Theme_JSON_Data
    {
        $fontFamilies = $this->planner->getEditorFontFamilies();

        if ($fontFamilies === []) {
            return $themeJson;
        }

        $themeJsonUpdate = [
            'settings' => [
                'typography' => [
                    'fontFamilies' => $fontFamilies,
                ],
            ],
        ];

        $schemaVersion = $this->resolveThemeJsonSchemaVersion($themeJson);

        if ($schemaVersion !== null) {
            $themeJsonUpdate['version'] = $schemaVersion;
        }

        return $themeJson->update_with($themeJsonUpdate);
    }

    /**
     * @param EditorSettings $editorSettings
     * @return EditorSettings
     */
    public function filterBlockEditorSettings(array $editorSettings, mixed $editorContext = null): array
    {
        $styles = [];
        $runtimeFamilies = $this->planner->getRuntimeFamilies();
        $settings = $this->settings->getSettings();

        if ($this->hasManagedAcssRuntimeMapping()) {
            $styles = array_merge($styles, $this->acssIntegration->getManagedEditorStyles());
        }

        if ($this->bricksIntegration->managedEditorStylesActive($settings)) {
            $styles = array_merge($styles, $this->bricksIntegration->getManagedEditorStyles());
        } elseif ($this->bricksSelectorEnabled() && $this->bricksIntegration->isAvailable()) {
            $styles = array_merge($styles, $this->bricksIntegration->getEditorStyles($runtimeFamilies));
        }

        if ($this->builderIntegrationEnabled('oxygen') && $this->oxygenIntegration->isAvailable()) {
            $styles = array_merge($styles, $this->oxygenIntegration->getEditorStyles($runtimeFamilies));
        }

        if ($styles === [] && $this->shouldBuildEditorRoleBridge($settings)) {
            $styles[] = $this->buildRoleBridgeCss('editor');
        }

        $styles = array_values(array_unique(array_filter($styles, static fn (string $style): bool => $style !== '')));

        if ($styles === []) {
            return $editorSettings;
        }

        $editorSettings['styles'] = is_array($editorSettings['styles'] ?? null) ? $editorSettings['styles'] : [];
        $editorSettings['styles'][] = ['css' => implode("\n", $styles)];

        return $editorSettings;
    }

    /**
     * @param list<string> $fonts
     * @return list<string>
     */
    public function filterBricksStandardFonts(array $fonts): array
    {
        if (!$this->bricksSelectorEnabled() || !$this->bricksIntegration->isAvailable()) {
            return $fonts;
        }

        return $this->bricksIntegration->filterStandardFonts($fonts, $this->planner->getRuntimeFamilies());
    }

    public function registerOxygenCompatibilityShim(): void
    {
        if (!$this->builderIntegrationEnabled('oxygen') || !$this->oxygenIntegration->isAvailable()) {
            return;
        }

        $this->oxygenIntegration->registerCompatibilityShim($this->planner->getRuntimeFamilies());
    }

    public function filterExternalStylesheetTag(string $html, string $handle, string $href, string $media): string
    {
        if (!$this->isExternalStylesheetHandle($handle) || str_contains($html, ' crossorigin=')) {
            return $html;
        }

        return preg_replace('/(?=\s*\/?>$)/', ' crossorigin="anonymous"', $html, 1) ?: $html;
    }

    /**
     * Build HTTP Link header values for the current primary font preload candidates.
     *
     * @since 1.10.0
     *
     * @param array<int, string>|null $preloadUrls Optional preload URLs to convert into Link header values.
     * @return list<string> Link header values for the configured preload assets.
     */
    public function getPreloadLinkHeaderValues(?array $preloadUrls = null): array
    {
        $preloadUrls = is_array($preloadUrls) ? $preloadUrls : $this->planner->getPrimaryFontPreloadUrls();
        $headers = [];

        foreach ($preloadUrls as $url) {
            if (trim($url) === '') {
                continue;
            }

            $headers[] = sprintf(
                '<%s>; rel=preload; as=font; type="font/woff2"; crossorigin',
                esc_url_raw($url)
            );
        }

        return array_values(array_unique($headers));
    }

    private function sitewideDeliveryEnabled(): bool
    {
        return !empty($this->settings->getSettings()['auto_apply_roles']);
    }

    private function enqueueEtchCanvasBridge(): void
    {
        if (!$this->etchIntegrationEnabled()) {
            return;
        }

        $stylesheetUrls = $this->getCanvasStylesheetUrls();
        $inlineCss = $this->getCanvasInlineCss();
        $quickRoles = $this->buildEtchCanvasQuickRolesPayload();

        if ($stylesheetUrls === [] && $inlineCss === [] && empty($quickRoles['enabled'])) {
            return;
        }

        wp_enqueue_script(
            'tasty-fonts-canvas-contracts',
            TASTY_FONTS_URL . 'assets/js/canvas-contracts.js',
            [],
            TASTY_FONTS_VERSION,
            true
        );

        wp_enqueue_script(
            'tasty-fonts-admin-contracts',
            TASTY_FONTS_URL . 'assets/js/admin-contracts.js',
            [],
            TASTY_FONTS_VERSION,
            true
        );

        if (!empty($quickRoles['enabled'])) {
            wp_enqueue_style(
                'tasty-fonts-canvas',
                TASTY_FONTS_URL . 'assets/css/tasty-canvas.css',
                [],
                TASTY_FONTS_VERSION
            );
        }

        wp_enqueue_script(
            'tasty-fonts-canvas',
            TASTY_FONTS_URL . 'assets/js/tasty-canvas.js',
            ['tasty-fonts-canvas-contracts', 'tasty-fonts-admin-contracts'],
            TASTY_FONTS_VERSION,
            true
        );

        wp_localize_script(
            'tasty-fonts-canvas',
            'TastyFontsCanvas',
            [
                'stylesheetUrl' => $stylesheetUrls[0] ?? '',
                'stylesheetUrls' => $stylesheetUrls,
                'inlineCss' => $inlineCss,
                'quickRoles' => $quickRoles,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEtchCanvasQuickRolesPayload(): array
    {
        if ($this->catalog === null) {
            return ['enabled' => false];
        }

        $adminAccess = $this->adminAccess ?? new AdminAccessService($this->settings);

        if (!$adminAccess->canCurrentUserAccess()) {
            return ['enabled' => false];
        }

        $settings = $this->settings->getSettings();

        if (empty($settings['etch_quick_roles_panel_enabled'])) {
            return ['enabled' => false];
        }

        $catalog = $this->catalog->getCatalog();
        $builder = $this->roleFamilyCatalogBuilder ?? new RoleFamilyCatalogBuilder();
        $roleFamilyCatalog = $builder->build($catalog, $settings);

        $familyNames = array_keys($roleFamilyCatalog);
        $roles = $this->settings->getRoles($familyNames);
        $appliedRoles = $this->settings->getAppliedRoles($familyNames);

        return [
            'enabled' => true,
            'restUrl' => rest_url(RestController::API_NAMESPACE . '/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'routes' => RestController::routeMap(),
            'adminUrl' => add_query_arg(
                ['page' => AdminController::MENU_SLUG],
                admin_url('admin.php')
            ),
            'roles' => $roles,
            'appliedRoles' => $appliedRoles,
            'applyEverywhere' => !empty($settings['auto_apply_roles']),
            'monospaceRoleEnabled' => !empty($settings['monospace_role_enabled']),
            'variableFontsEnabled' => !empty($settings['variable_fonts_enabled']),
            'roleFamilyCatalog' => $roleFamilyCatalog,
            'strings' => [
                'title' => __('Tasty Fonts', 'tasty-fonts'),
                'subtitle' => __('Quick roles', 'tasty-fonts'),
                'close' => __('Close panel', 'tasty-fonts'),
                'heading' => __('Heading', 'tasty-fonts'),
                'body' => __('Body', 'tasty-fonts'),
                'monospace' => __('Monospace', 'tasty-fonts'),
                'fallbackOnly' => __('Fallback only', 'tasty-fonts'),
                'saving' => __('Saving…', 'tasty-fonts'),
                'saved' => __('Saved', 'tasty-fonts'),
                'failed' => __('Could not save roles.', 'tasty-fonts'),
                'pendingPublish' => __('Saved as draft. Publish roles to update the live site.', 'tasty-fonts'),
                'publish' => __('Publish Site-Wide', 'tasty-fonts'),
                'publishing' => __('Publishing…', 'tasty-fonts'),
                'published' => __('Published site-wide.', 'tasty-fonts'),
                'publishFailed' => __('Could not publish roles.', 'tasty-fonts'),
                'openAdmin' => __('Open Tasty Fonts', 'tasty-fonts'),
                'alreadyLive' => __('These roles are already live site-wide.', 'tasty-fonts'),
                'refreshingCanvas' => __('Refreshing the Etch canvas…', 'tasty-fonts'),
                'noFamilies' => __('No Tasty Fonts families are available yet.', 'tasty-fonts'),
            ],
        ];
    }

    /**
     * @param RuntimeFamilyList $runtimeFamilies
     */
    private function enqueueBricksBuilderSelectorGroupingScript(array $runtimeFamilies): void
    {
        $familyNames = $this->bricksIntegration->getSelectorFamilyNames($runtimeFamilies);

        if ($familyNames === []) {
            return;
        }

        $script = $this->buildBricksBuilderSelectorGroupingScript($familyNames);

        if ($script === '') {
            return;
        }

        wp_add_inline_script('bricks-builder', $script, 'before');
    }

    private function enqueueBricksQuotedVariableFixScript(): void
    {
        if (!$this->builderIntegrationEnabled('bricks') || !$this->bricksIntegration->isAvailable()) {
            return;
        }

        $script = $this->buildBricksQuotedVariableFixScript();

        if ($script === '') {
            return;
        }

        wp_add_inline_script('bricks-scripts', $script, 'after');
    }

    private function buildBricksQuotedVariableFixScript(): string
    {
        return <<<'JS'
(function () {
    if (typeof document === 'undefined' || !document.documentElement) {
        return;
    }

    var quotedPropertyPattern = /(font-family\s*:\s*)["'](var\(--[^"']+\))["']/g;
    var quotedValuePattern = /^["'](var\(--[^"']+\))["']$/;

    function normalizeCssText(cssText) {
        return typeof cssText === 'string' ? cssText.replace(quotedPropertyPattern, '$1$2') : '';
    }

    function normalizeFontFamilyValue(value) {
        if (typeof value !== 'string') {
            return '';
        }

        var trimmed = value.trim();
        var matches = trimmed.match(quotedValuePattern);

        return matches ? matches[1] : trimmed;
    }

    function normalizeStyleDeclaration(style) {
        if (!style || typeof style.getPropertyValue !== 'function') {
            return;
        }

        var original = style.getPropertyValue('font-family');

        if (typeof original !== 'string' || original.trim() === '') {
            return;
        }

        var normalized = normalizeFontFamilyValue(original);

        if (normalized === '' || normalized === original.trim()) {
            return;
        }

        style.setProperty('font-family', normalized, style.getPropertyPriority('font-family'));
    }

    function normalizeStyleTag(node) {
        if (!node || node.tagName !== 'STYLE') {
            return;
        }

        var original = node.textContent || '';
        var normalized = normalizeCssText(original);

        if (normalized !== original) {
            node.textContent = normalized;
        }
    }

    function normalizeCssRules(rules) {
        if (!rules) {
            return;
        }

        Array.prototype.forEach.call(rules, function (rule) {
            if (!rule) {
                return;
            }

            if (rule.style) {
                normalizeStyleDeclaration(rule.style);
            }

            if (rule.cssRules && rule.cssRules.length) {
                normalizeCssRules(rule.cssRules);
            }
        });
    }

    function normalizeStyleSheets() {
        Array.prototype.forEach.call(document.styleSheets || [], function (sheet) {
            try {
                normalizeCssRules(sheet.cssRules || sheet.rules || []);
            } catch (error) {
                // Ignore cross-origin stylesheets.
            }
        });
    }

    function normalizeInlineStyles() {
        if (typeof document.querySelectorAll !== 'function') {
            return;
        }

        document.querySelectorAll('[style*="font-family"]').forEach(function (element) {
            if (element && element.style) {
                normalizeStyleDeclaration(element.style);
            }
        });
    }

    function normalizeTree(root) {
        if (!root) {
            return;
        }

        if (root.tagName === 'STYLE') {
            normalizeStyleTag(root);
            return;
        }

        if (typeof root.querySelectorAll !== 'function') {
            return;
        }

        root.querySelectorAll('style').forEach(normalizeStyleTag);
    }

    function normalizeAll() {
        normalizeTree(document);
        normalizeStyleSheets();
        normalizeInlineStyles();
    }

    normalizeAll();

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'characterData') {
                var parent = mutation.target && mutation.target.parentElement;

                if (parent && parent.tagName === 'STYLE') {
                    normalizeStyleTag(parent);
                }
            }

            Array.prototype.forEach.call(mutation.addedNodes || [], normalizeTree);
        });

        normalizeStyleSheets();
        normalizeInlineStyles();
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true,
        attributeFilter: ['style']
    });

    var repairPasses = 0;
    var repairTimer = window.setInterval(function () {
        normalizeAll();
        repairPasses += 1;

        if (repairPasses >= 40) {
            window.clearInterval(repairTimer);
        }
    }, 250);
})();
JS;
    }

    /**
     * @param list<string> $familyNames
     */
    private function buildBricksBuilderSelectorGroupingScript(array $familyNames): string
    {
        $encodedFamilies = wp_json_encode($familyNames, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encodedFamilies === false) {
            return '';
        }

        return <<<JS
(function () {
    if (typeof window === 'undefined' || !window.bricksData || !window.bricksData.fonts) {
        return;
    }

    var tastyFamilies = {$encodedFamilies};

    if (!Array.isArray(tastyFamilies) || tastyFamilies.length === 0) {
        return;
    }

    var data = window.bricksData;
    var fonts = data.fonts;
    var standard = Array.isArray(fonts.standard) ? fonts.standard : [];
    var existingCustom = fonts.custom && typeof fonts.custom === 'object' && !Array.isArray(fonts.custom) ? fonts.custom : {};
    var tastyLookup = {};
    var tastyEntries = {};
    var customEntries = {};
    var remainingStandard = [];
    var hasExistingCustom = false;

    tastyFamilies.forEach(function (familyName) {
        if (typeof familyName !== 'string') {
            return;
        }

        familyName = familyName.trim();

        if (!familyName || tastyLookup[familyName]) {
            return;
        }

        tastyLookup[familyName] = true;
    });

    standard.forEach(function (familyName) {
        if (typeof familyName !== 'string') {
            return;
        }

        familyName = familyName.trim();

        if (!familyName) {
            return;
        }

        if (!tastyLookup[familyName]) {
            remainingStandard.push(familyName);
        }
    });

    Object.keys(existingCustom).forEach(function (key) {
        var entry = existingCustom[key];
        var label = entry && typeof entry.family === 'string' ? entry.family.trim() : key.trim();

        if (!label) {
            return;
        }

        if (tastyLookup[label] || tastyLookup[key]) {
            tastyEntries[label] = entry && typeof entry === 'object' ? entry : { family: label };
            return;
        }

        hasExistingCustom = true;
        customEntries[key] = entry;
    });

    Object.keys(tastyLookup).forEach(function (familyName) {
        if (tastyEntries[familyName]) {
            return;
        }

        tastyEntries[familyName] = { family: familyName };
    });

    if (!Object.keys(tastyEntries).length) {
        return;
    }

    fonts.standard = remainingStandard;
    fonts.custom = Object.assign({}, tastyEntries, customEntries);

    if (data.i18n && typeof data.i18n === 'object') {
        data.i18n.fontsCustom = hasExistingCustom ? 'Tasty Fonts + Custom' : 'Tasty Fonts';
    }
})();
JS;
    }

    /**
     * @param StylesheetList $stylesheets
     */
    private function enqueueExternalStylesheets(array $stylesheets, string $handleSuffix = ''): void
    {
        foreach ($stylesheets as $stylesheet) {
            $handle = $this->stylesheetHandle($stylesheet, $handleSuffix);
            $url = $stylesheet['url'];

            if ($handle === '' || $url === '') {
                continue;
            }

            wp_enqueue_style($handle, $url, [], $this->stylesheetVersion($stylesheet));
        }
    }

    private function enqueueAcssRuntimeStylesheet(string $handleSuffix): void
    {
        $stylesheet = $this->getEditorParityAcssRuntimeStylesheet();

        if ($stylesheet === []) {
            return;
        }

        $handle = $this->stylesheetHandle(
            [
                'handle' => 'tasty-fonts-acss-runtime',
                'url' => '',
                'provider' => 'acss',
                'type' => 'runtime',
            ],
            $handleSuffix
        );

        wp_enqueue_style($handle, $stylesheet['url'], [], $stylesheet['ver'] !== '' ? $stylesheet['ver'] : false);
    }

    /**
     * @return list<string>
     */
    private function getCanvasStylesheetUrls(): array
    {
        $urls = [];
        $generatedUrl = $this->assets->getVersionedStylesheetUrl();

        if ($generatedUrl) {
            $urls[] = $generatedUrl;
        }

        foreach ($this->planner->getExternalStylesheets() as $stylesheet) {
            $url = $stylesheet['url'];

            if ($url !== '') {
                $urls[] = $url;
            }
        }

        $acssStylesheet = $this->getEditorParityAcssRuntimeStylesheet();

        if ($acssStylesheet !== []) {
            $urls[] = $acssStylesheet['url'];
        }

        return array_values(array_unique(array_filter($urls, static fn (string $url): bool => $url !== '')));
    }

    /**
     * @return CssLineList
     */
    private function getCanvasInlineCss(): array
    {
        $styles = [];

        if ($this->hasManagedAcssRuntimeMapping()) {
            $styles = array_merge($styles, $this->acssIntegration->getManagedEditorStyles());
        }

        $roleBridgeCss = $this->buildRoleBridgeCss('canvas');

        if ($roleBridgeCss !== '') {
            $styles[] = $roleBridgeCss;
        }

        return array_values(array_unique(array_filter($styles, static fn (string $style): bool => $style !== '')));
    }

    /**
     * Mirror the live Automatic.css runtime stylesheet into editor surfaces when sitewide role
     * delivery is active and Automatic.css is available.
     *
     * This stays intentionally narrow:
     * - it reuses the same live ACSS runtime stylesheet instead of generating editor-only CSS
     * - it runs only while sitewide roles are applied, so saved-only drafts do not affect Gutenberg
     * - it does not take ownership of ACSS typography settings when the sync toggle is off
     *
     * When sync is active, the managed ACSS editor style bridge still layers on top for the final
     * font-family handoff. When sync is inactive, this mirror exists only to keep Gutenberg and
     * builder canvases closer to the live ACSS runtime cascade.
     *
     * @return array{handle:string,url:string,ver:string}|array{}
     */
    private function getEditorParityAcssRuntimeStylesheet(): array
    {
        if ($this->hasManagedAcssRuntimeMapping()) {
            return $this->acssIntegration->getRuntimeStylesheet();
        }

        $settings = $this->settings->getSettings();

        if (empty($settings['auto_apply_roles']) || !$this->acssIntegration->isAvailable()) {
            return [];
        }

        return $this->acssIntegration->getRuntimeStylesheet();
    }

    private function hasManagedAcssRuntimeMapping(): bool
    {
        $settings = $this->settings->getSettings();

        return !empty($settings['auto_apply_roles'])
            && ($settings['acss_font_role_sync_enabled'] ?? null) === true
            && !empty($settings['acss_font_role_sync_applied'])
            && $this->acssIntegration->isAvailable();
    }

    private function etchIntegrationAvailable(): bool
    {
        $available = class_exists(\Etch\Services\StylesheetService::class);

        if (function_exists('apply_filters')) {
            $available = (bool) apply_filters('tasty_fonts_etch_integration_available', $available);
        }

        return $available;
    }

    private function etchIntegrationEnabled(): bool
    {
        $settings = $this->settings->getSettings();

        return $this->etchIntegrationAvailable()
            && (($settings['etch_integration_enabled'] ?? null) !== false);
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function shouldBuildMinimalRoleBridge(array $settings): bool
    {
        return !empty($settings['auto_apply_roles'])
            && !empty($settings['minimal_output_preset_enabled'])
            && !$this->hasManagedAcssRuntimeMapping();
    }

    /**
     * @param NormalizedSettings $settings
     */
    private function shouldBuildEditorRoleBridge(array $settings): bool
    {
        return $this->shouldBuildMinimalRoleBridge($settings);
    }

    private function buildRoleBridgeCss(string $context): string
    {
        $settings = $this->settings->getSettings();

        if (!$this->shouldBuildMinimalRoleBridge($settings)) {
            return '';
        }

        if (in_array($context, ['frontend', 'canvas'], true) && !$this->etchIntegrationEnabled()) {
            return '';
        }

        $css = $this->cssBuilder->buildRoleUsageRulesOnlySnippet(
            $this->settings->getAppliedRoles($this->planner->getRuntimeFamilies()),
            !empty($settings['monospace_role_enabled']),
            array_replace($settings, ['minimal_output_preset_enabled' => false])
        );

        if ($css === '') {
            return '';
        }

        if ($context === 'editor') {
            $css = str_replace(
                'h1, h2, h3, h4, h5, h6 {',
                'body :is(h1, h2, h3, h4, h5, h6, .editor-post-title, .wp-block-post-title) {',
                $css
            );
            $css = str_replace('code, pre {', 'body :is(code, pre) {', $css);
        }

        return preg_replace('/(font-(?:family|variation-settings|weight):\s*[^;]+);/', '$1 !important;', $css) ?? $css;
    }

    /**
     * @param StylesheetDescriptor $stylesheet
     */
    private function stylesheetVersion(array $stylesheet): string
    {
        $provider = strtolower(trim($stylesheet['provider']));

        return $provider === 'adobe'
            ? $this->adobe->getEnqueueVersion()
            : TASTY_FONTS_VERSION;
    }

    /**
     * @param StylesheetDescriptor $stylesheet
     */
    private function stylesheetHandle(array $stylesheet, string $handleSuffix = ''): string
    {
        $handle = trim($stylesheet['handle']);

        if ($handle === '' || $handleSuffix === '') {
            return $handle;
        }

        $suffix = strtolower(trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', $handleSuffix), '-'));

        return $suffix === '' ? $handle : $handle . '-' . $suffix;
    }

    private function isExternalStylesheetHandle(string $handle): bool
    {
        foreach (['google', 'bunny', 'adobe'] as $provider) {
            if (str_starts_with($handle, 'tasty-fonts-' . $provider . '-')) {
                return true;
            }
        }

        return false;
    }

    private function builderIntegrationEnabled(string $builder): bool
    {
        $settings = $this->settings->getSettings();
        $key = match ($builder) {
            'bricks' => 'bricks_integration_enabled',
            'oxygen' => 'oxygen_integration_enabled',
            default => '',
        };

        if ($key === '') {
            return false;
        }

        $value = $settings[$key] ?? null;

        return $value !== false;
    }

    private function bricksSelectorEnabled(): bool
    {
        return $this->bricksIntegration->selectorsEnabled($this->settings->getSettings());
    }

    private function bricksBuilderPreviewEnabled(): bool
    {
        return $this->bricksIntegration->builderPreviewEnabled($this->settings->getSettings());
    }

    private function isBricksBuilderRequest(): bool
    {
        if (function_exists('bricks_is_builder')) {
            return (bool) bricks_is_builder();
        }

        return isset($_GET['brickspreview']) || isset($_GET['bricks']);
    }

    private function hasEtchCanvasRequest(): bool
    {
        if (is_admin()) {
            return false;
        }

        $etch = isset($_GET['etch']) && is_scalar($_GET['etch'])
            ? sanitize_text_field(wp_unslash((string) $_GET['etch']))
            : '';

        if ($etch === '' || !$this->canUseEtchCanvasQueryParameter() || !$this->etchIntegrationEnabled()) {
            return false;
        }

        return (bool) apply_filters('tasty_fonts_allow_etch_canvas_query', true, $etch);
    }

    private function canUseEtchCanvasQueryParameter(): bool
    {
        return is_user_logged_in() && current_user_can('edit_posts');
    }

    private function preloadLinkDeliveryMode(): string
    {
        $mode = strtolower(trim(FontUtils::scalarStringValue(apply_filters('tasty_fonts_preload_link_delivery_mode', 'html'))));

        return in_array($mode, ['html', 'headers', 'both'], true) ? $mode : 'html';
    }

    /**
     * @param list<string> $headers
     */
    private function sendPreloadLinkHeaders(array $headers): void
    {
        if ($headers === [] || headers_sent()) {
            return;
        }

        foreach ($headers as $value) {
            header('Link: ' . $value, false);
        }
    }

    private function resolveThemeJsonSchemaVersion(WP_Theme_JSON_Data $themeJson): ?int
    {
        $existingData = $themeJson->get_data();
        $schemaVersion = $existingData['version'] ?? null;

        if (is_int($schemaVersion) && $schemaVersion > 0) {
            return $schemaVersion;
        }

        if (is_string($schemaVersion) && ctype_digit($schemaVersion) && (int) $schemaVersion > 0) {
            return (int) $schemaVersion;
        }

        return null;
    }
}
