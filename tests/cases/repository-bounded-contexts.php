<?php

declare(strict_types=1);

use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\AdobeProjectRepositoryInterface;
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\FamilyMetadataRepositoryInterface;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\GoogleApiKeyRepositoryInterface;
use TastyFonts\Repository\RoleRepository;
use TastyFonts\Repository\RoleRepositoryInterface;
use TastyFonts\Repository\SettingsRepository;

// ---------------------------------------------------------------------------
// Repository interface seams (bounded contexts)
// ---------------------------------------------------------------------------

$tests['repository_interfaces_bounded_contexts_support_contract_checks_and_in_memory_doubles'] = static function (): void {
    resetTestState();

    $googleRepo = new GoogleApiKeyRepository();
    $adobeRepo = new AdobeProjectRepository();
    $roleRepo = new RoleRepository();
    $familyMetadataRepo = new FamilyMetadataRepository();

    assertTrueValue($googleRepo instanceof GoogleApiKeyRepositoryInterface, 'GoogleApiKeyRepository should implement GoogleApiKeyRepositoryInterface.');
    assertTrueValue($adobeRepo instanceof AdobeProjectRepositoryInterface, 'AdobeProjectRepository should implement AdobeProjectRepositoryInterface.');
    assertTrueValue($roleRepo instanceof RoleRepositoryInterface, 'RoleRepository should implement RoleRepositoryInterface.');
    assertTrueValue($familyMetadataRepo instanceof FamilyMetadataRepositoryInterface, 'FamilyMetadataRepository should implement FamilyMetadataRepositoryInterface.');

    $googleDouble = new class() implements GoogleApiKeyRepositoryInterface {
        public function has(): bool { return true; }
        public function getStatus(): array { return ['state' => 'valid', 'message' => 'ok', 'checked_at' => 1]; }
        public function saveStatus(string $state, string $message = ''): array { return ['state' => $state, 'message' => $message, 'checked_at' => 1]; }
        public function clear(): void {}
    };

    $activityState = ['enabled' => true, 'project' => 'abc123'];
    $adobeDouble = new class($activityState) implements AdobeProjectRepositoryInterface {
        /** @var array<string, mixed> */
        private array $state;

        /** @param array<string, mixed> $state */
        public function __construct(array &$state) { $this->state = &$state; }
        public function isEnabled(): bool { return !empty($this->state['enabled']); }
        public function getProjectId(): string { return (string) ($this->state['project'] ?? ''); }
        public function getStatus(): array { return ['state' => 'valid', 'message' => '', 'checked_at' => 1]; }
        public function saveProject(string $projectId, bool $enabled = true): array { $this->state['project'] = $projectId; $this->state['enabled'] = $enabled; return $this->state; }
        public function saveStatus(string $state, string $message = ''): array { return ['state' => $state, 'message' => $message, 'checked_at' => 1]; }
        public function clear(): array { $this->state = ['enabled' => false, 'project' => '']; return $this->state; }
    };

    $adobeDouble->saveProject('project-2', false);
    assertFalseValue($adobeDouble->isEnabled(), 'In-memory AdobeProjectRepositoryInterface double should be usable as a seam.');
    assertSameValue('project-2', $adobeDouble->getProjectId(), 'In-memory AdobeProjectRepositoryInterface double should preserve saved values.');

    $defaultRoles = [
        'heading' => 'Inter',
        'body' => 'Lora',
        'monospace' => '',
        'heading_fallback' => 'sans-serif',
        'body_fallback' => 'serif',
        'monospace_fallback' => 'monospace',
        'heading_weight' => '400',
        'body_weight' => '400',
        'monospace_weight' => '400',
        'heading_axes' => [],
        'body_axes' => [],
        'monospace_axes' => [],
    ];
    $roleDouble = new class($defaultRoles) implements RoleRepositoryInterface {
        /** @var array<string, mixed> */
        private array $roles;

        /** @param array<string, mixed> $roles */
        public function __construct(array $roles) { $this->roles = $roles; }
        public function getRoles(array $catalog): array { return $this->roles; }
        public function saveRoles(array $input, array $catalog): array { $this->roles = array_merge($this->roles, $input); return $this->roles; }
        public function getAppliedRoles(array $catalog): array { return $this->roles; }
        public function ensureAppliedRolesInitialized(array $catalog): array { return $this->roles; }
        public function saveAppliedRoles(array $roles, array $catalog): array { $this->roles = array_merge($this->roles, $roles); return $this->roles; }
        public function clearDisabledCapabilityRoleData(bool $clearVariableAxes, bool $clearMonospaceRole, array $catalog): array { return ['roles' => $this->roles, 'applied_roles' => $this->roles]; }
        public function setAutoApplyRoles(bool $enabled): array { return ['auto_apply_roles' => $enabled]; }
        public function previewImportedRoles(array $roles): array { return array_merge($this->roles, $roles); }
        public function replaceImportedRoles(array $roles): array { $this->roles = array_merge($this->roles, $roles); return $this->roles; }
    };

    $familyMetadataDouble = new class() implements FamilyMetadataRepositoryInterface {
        /** @var array<string, string> */
        private array $fallbacks = [];
        /** @var array<string, string> */
        private array $fontDisplays = [];

        public function getFallback(string $familySlug, string $default = 'sans-serif'): string { return $this->fallbacks[$familySlug] ?? $default; }
        public function saveFallback(string $familySlug, string $fallback): array { $this->fallbacks[$familySlug] = $fallback; return $this->fallbacks; }
        public function getFontDisplay(string $familySlug, string $default = ''): string { return $this->fontDisplays[$familySlug] ?? $default; }
        public function saveFontDisplay(string $familySlug, string $display): array { $this->fontDisplays[$familySlug] = $display; return $this->fontDisplays; }
        public function resetFallbacks(): array { $this->fallbacks = []; return ['family_fallbacks' => []]; }
        public function resetAll(): array { $this->fallbacks = []; $this->fontDisplays = []; return ['family_fallbacks' => [], 'family_font_displays' => []]; }
    };

    assertTrueValue($googleDouble->has(), 'In-memory GoogleApiKeyRepositoryInterface double should be usable as a seam.');
    assertSameValue('Recursive', $roleDouble->saveRoles(['heading' => 'Recursive'], [])['heading'], 'In-memory RoleRepositoryInterface double should be usable as a seam.');
    $familyMetadataDouble->saveFallback('inter', 'Arial, sans-serif');
    $familyMetadataDouble->saveFontDisplay('inter', 'swap');
    assertSameValue('Arial, sans-serif', $familyMetadataDouble->getFallback('inter'), 'In-memory FamilyMetadataRepositoryInterface double should preserve fallback values.');
    assertSameValue('swap', $familyMetadataDouble->getFontDisplay('inter'), 'In-memory FamilyMetadataRepositoryInterface double should preserve font-display values.');
};

// ---------------------------------------------------------------------------
// GoogleApiKeyRepository
// ---------------------------------------------------------------------------

$tests['google_api_key_repository_has_returns_false_when_no_key_stored'] = static function (): void {
    resetTestState();

    $repo = new GoogleApiKeyRepository();

    assertFalseValue($repo->has(), 'has() should return false when no API key is stored.');
};

$tests['google_api_key_repository_getStatus_returns_default_when_empty'] = static function (): void {
    resetTestState();

    $repo = new GoogleApiKeyRepository();
    $status = $repo->getStatus();

    assertSameValue('empty', $status['state'], 'getStatus() state should be empty when no key is stored.');
    assertSameValue('', $status['message'], 'getStatus() message should be empty when no key is stored.');
    assertSameValue(0, $status['checked_at'], 'getStatus() checked_at should be 0 when no key is stored.');
};

$tests['google_api_key_repository_saveStatus_normalizes_and_persists'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'test-key-123',
        'google_api_key_status' => 'empty',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ];

    $repo = new GoogleApiKeyRepository();
    $status = $repo->saveStatus('valid', 'Key is valid.');

    assertSameValue('valid', $status['state'], 'saveStatus() should persist the normalized state.');
    assertSameValue('Key is valid.', $status['message'], 'saveStatus() should persist the status message.');
    assertSameValue(true, $status['checked_at'] > 0, 'saveStatus() should record a timestamp.');
};

$tests['google_api_key_repository_saveStatus_empty_state_clears_message_and_timestamp'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => '',
        'google_api_key_status' => 'unknown',
        'google_api_key_status_message' => 'old message',
        'google_api_key_checked_at' => 1234567890,
    ];

    $repo = new GoogleApiKeyRepository();
    $status = $repo->saveStatus('empty');

    assertSameValue('empty', $status['state'], 'saveStatus(\'empty\') should reset state to empty.');
    assertSameValue('', $status['message'], 'saveStatus(\'empty\') should clear the message.');
    assertSameValue(0, $status['checked_at'], 'saveStatus(\'empty\') should zero the timestamp.');
};

$tests['google_api_key_repository_clear_removes_option'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'key-to-clear',
        'google_api_key_status' => 'valid',
        'google_api_key_status_message' => 'ok',
        'google_api_key_checked_at' => time(),
    ];

    $repo = new GoogleApiKeyRepository();
    assertTrueValue($repo->has(), 'has() should return true before clear.');

    $repo->clear();

    assertFalseValue($repo->has(), 'has() should return false after clear.');
    $status = $repo->getStatus();
    assertSameValue('empty', $status['state'], 'getStatus() state should be empty after clear.');
};

$tests['google_api_key_repository_encrypt_decrypt_roundtrip_when_sodium_available'] = static function (): void {
    resetTestState();

    $repo = new GoogleApiKeyRepository();
    $googleApiKeyData = invokePrivateMethod($repo, 'persistGoogleApiKeyData', [[
        'google_api_key' => 'my-secret-api-key',
        'google_api_key_status' => 'unknown',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ]]);

    assertSameValue('my-secret-api-key', $googleApiKeyData['google_api_key'], 'Round-trip should preserve the original API key.');
};

$tests['google_api_key_repository_plaintext_fallback_when_salts_missing'] = static function (): void {
    resetTestState();

    global $optionStore;

    $keyMaterial = 'saved-plaintext-key';

    $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => $keyMaterial,
        'google_api_key_status' => 'valid',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => time(),
    ];

    $repo = new GoogleApiKeyRepository();
    assertTrueValue($repo->has(), 'has() should return true with plaintext key.');
    $status = $repo->getStatus();
    assertSameValue('valid', $status['state'], 'getStatus() should read plaintext key status.');

    $repo->saveData($repo->getData());
    $stored = $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] ?? [];

    if (array_key_exists('google_api_key_encrypted', $stored)) {
        return;
    }

    assertSameValue($keyMaterial, $stored['google_api_key'] ?? '', 'When encryption is unavailable, saveData() should persist plaintext key data.');
};

$tests['google_api_key_repository_plaintext_to_encrypted_upgrade_on_settings_save'] = static function (): void {
    resetTestState();

    global $optionStore;

    if (!function_exists('sodium_crypto_secretbox') || !function_exists('sodium_crypto_secretbox_open')) {
        return;
    }

    $repo = new GoogleApiKeyRepository();
    $canEncrypt = invokePrivateMethod($repo, 'deriveGoogleApiKeyEncryptionKey', []) !== '';

    if (!$canEncrypt) {
        return;
    }

    $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'upgrade-me',
        'google_api_key_status' => 'valid',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => time(),
    ];

    $settingsRepo = new SettingsRepository($repo);
    $settingsRepo->saveSettings(['font_display' => 'swap']);

    $stored = $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] ?? [];
    assertSameValue(false, array_key_exists('google_api_key', $stored), 'saveSettings() should upgrade plaintext API key storage to encrypted when encryption is available.');
    assertSameValue(true, str_starts_with((string) ($stored['google_api_key_encrypted'] ?? ''), 'secretbox:'), 'Encrypted API key should be stored with secretbox prefix.');
};

$tests['google_api_key_repository_status_state_transitions'] = static function (): void {
    resetTestState();

    global $optionStore;

    $optionStore[GoogleApiKeyRepository::OPTION_GOOGLE_API_KEY_DATA] = [
        'google_api_key' => 'k',
        'google_api_key_status' => 'unknown',
        'google_api_key_status_message' => '',
        'google_api_key_checked_at' => 0,
    ];

    $repo = new GoogleApiKeyRepository();

    $status = $repo->saveStatus('valid');
    assertSameValue('valid', $status['state'], 'Status should transition unknown → valid.');

    $status = $repo->saveStatus('invalid');
    assertSameValue('invalid', $status['state'], 'Status should transition valid → invalid.');

    $status = $repo->saveStatus('valid', 'Revalidated');
    assertSameValue('valid', $status['state'], 'Status should transition invalid → revalidated.');
};

// ---------------------------------------------------------------------------
// AdobeProjectRepository
// ---------------------------------------------------------------------------

$tests['adobe_project_repository_save_and_retrieve_project'] = static function (): void {
    resetTestState();

    $repo = new AdobeProjectRepository();

    assertFalseValue($repo->isEnabled(), 'isEnabled() should return false before any project is saved.');

    $repo->saveProject(' AbC-123 ', true);
    assertTrueValue($repo->isEnabled(), 'isEnabled() should return true after saving a project with enabled=true.');
    assertSameValue('abc123', $repo->getProjectId(), 'getProjectId() should return normalized project ID.');

    $status = $repo->getStatus();
    assertSameValue('unknown', $status['state'], 'getStatus() state should be unknown for newly saved project.');
};

$tests['adobe_project_repository_getStatus_for_unsaved_project'] = static function (): void {
    resetTestState();

    $repo = new AdobeProjectRepository();
    $status = $repo->getStatus();

    assertSameValue('empty', $status['state'], 'getStatus() state should be empty when no project saved.');
    assertSameValue(0, $status['checked_at'], 'getStatus() checked_at should be 0 when no project saved.');
};

$tests['adobe_project_repository_saveStatus_normalizes_state'] = static function (): void {
    resetTestState();

    $repo = new AdobeProjectRepository();
    $repo->saveProject('test-project-id', true);

    $status = $repo->saveStatus('valid', 'Project validated.');
    assertSameValue('valid', $status['state'], 'saveStatus() should persist valid state.');
    assertSameValue('Project validated.', $status['message'], 'saveStatus() should persist the message.');
    assertSameValue(true, $status['checked_at'] > 0, 'saveStatus() should record a timestamp.');
};

$tests['adobe_project_repository_clear_removes_all_project_keys'] = static function (): void {
    resetTestState();

    $repo = new AdobeProjectRepository();
    $repo->saveProject('some-project', true);
    $repo->saveStatus('valid', 'ok');

    $repo->clear();

    assertFalseValue($repo->isEnabled(), 'isEnabled() should return false after clear.');
    assertSameValue('', $repo->getProjectId(), 'getProjectId() should return empty after clear.');
    $status = $repo->getStatus();
    assertSameValue('empty', $status['state'], 'getStatus() state should be empty after clear.');
};

$tests['adobe_project_repository_isEnabled_false_when_no_project'] = static function (): void {
    resetTestState();

    $repo = new AdobeProjectRepository();

    assertFalseValue($repo->isEnabled(), 'isEnabled() should return false when no project is saved.');
};

// ---------------------------------------------------------------------------
// RoleRepository
// ---------------------------------------------------------------------------

$tests['role_repository_getRoles_returns_defaults_with_empty_catalog'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $roles = $repo->getRoles([]);

    assertSameValue('', $roles['heading'], 'Default heading should be empty.');
    assertSameValue('', $roles['body'], 'Default body should be empty.');
    assertSameValue('', $roles['monospace'], 'Default monospace should be empty.');
    // Fallbacks are set to their defaults
    assertSameValue(true, $roles['heading_fallback'] !== '', 'heading_fallback should have a default value.');
    assertSameValue(true, $roles['body_fallback'] !== '', 'body_fallback should have a default value.');
};

$tests['role_repository_saveRoles_and_getRoles_roundtrip'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $saved = $repo->saveRoles([
        'heading' => 'Inter',
        'body' => 'Roboto',
    ], []);

    assertSameValue('Inter', $saved['heading'], 'saveRoles() should persist the heading role.');
    assertSameValue('Roboto', $saved['body'], 'saveRoles() should persist the body role.');

    $retrieved = $repo->getRoles([]);
    assertSameValue('Inter', $retrieved['heading'], 'getRoles() should return saved heading.');
    assertSameValue('Roboto', $retrieved['body'], 'getRoles() should return saved body.');
};

$tests['role_repository_getAppliedRoles_initializes_from_draft_when_auto_apply'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles(['heading' => 'Lato', 'body' => 'Open Sans'], []);
    $repo->setAutoApplyRoles(true);

    $applied = $repo->getAppliedRoles([]);
    assertSameValue('Lato', $applied['heading'], 'Applied roles should copy draft heading when auto-apply is on.');
    assertSameValue('Open Sans', $applied['body'], 'Applied roles should copy draft body when auto-apply is on.');
};

$tests['role_repository_ensureAppliedRolesInitialized_populates_empty_applied_roles'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles(['heading' => 'Inter'], []);
    $repo->setAutoApplyRoles(true);

    $applied = $repo->ensureAppliedRolesInitialized([]);
    assertSameValue('Inter', $applied['heading'], 'ensureAppliedRolesInitialized() should populate from draft roles.');
};

$tests['role_repository_saveAppliedRoles_updates_the_applied_roles'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles(['heading' => 'Default', 'body' => 'Default'], []);
    $repo->setAutoApplyRoles(true);

    $saved = $repo->saveAppliedRoles(['heading' => 'Updated'], []);
    assertSameValue('Updated', $saved['heading'], 'saveAppliedRoles() should update heading.');
    assertSameValue('Default', $saved['body'], 'saveAppliedRoles() should preserve unchanged body.');
};

$tests['role_repository_clearDisabledCapabilityRoleData_clears_variable_axes'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles([
        'heading' => 'Inter',
        'heading_axes' => ['wght' => '400'],
        'body_axes' => ['wght' => '700'],
    ], []);

    $result = $repo->clearDisabledCapabilityRoleData(true, false, []);
    assertSameValue([], $result['roles']['heading_axes'], 'Variable axes should be cleared when capability is disabled.');
    assertSameValue([], $result['roles']['body_axes'], 'Body axes should be cleared when capability is disabled.');
};

$tests['role_repository_clearDisabledCapabilityRoleData_clears_monospace_role'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles([
        'monospace' => 'JetBrains Mono',
        'monospace_weight' => '400',
        'monospace_axes' => ['wght' => '400'],
    ], []);

    $result = $repo->clearDisabledCapabilityRoleData(false, true, []);
    assertSameValue('', $result['roles']['monospace'], 'Monospace role should be cleared when capability is disabled.');
    assertSameValue('', $result['roles']['monospace_weight'], 'Monospace weight should be cleared.');
    assertSameValue([], $result['roles']['monospace_axes'], 'Monospace axes should be cleared.');
};

$tests['role_repository_previewImportedRoles_normalizes_without_persisting'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles(['heading' => 'Current'], []);

    $preview = $repo->previewImportedRoles(['heading' => 'Imported', 'body' => 'Also Imported']);
    assertSameValue('Imported', $preview['heading'], 'previewImportedRoles() should normalize imported heading.');
    assertSameValue('Also Imported', $preview['body'], 'previewImportedRoles() should normalize imported body.');

    $stored = $repo->getRoles([]);
    assertSameValue('Current', $stored['heading'], 'previewImportedRoles() should not persist.');
};

$tests['role_repository_replaceImportedRoles_persists_overwriting'] = static function (): void {
    resetTestState();

    $repo = new RoleRepository();
    $repo->saveRoles(['heading' => 'Current', 'body' => 'Current Body'], []);

    $result = $repo->replaceImportedRoles(['heading' => 'Replaced']);
    assertSameValue('Replaced', $result['heading'], 'replaceImportedRoles() should persist the replacement.');

    $stored = $repo->getRoles([]);
    assertSameValue('Replaced', $stored['heading'], 'getRoles() after replaceImportedRoles() should return replaced heading.');
};

// ---------------------------------------------------------------------------
// FamilyMetadataRepository
// ---------------------------------------------------------------------------

$tests['family_metadata_repository_save_and_retrieve_family_fallback'] = static function (): void {
    resetTestState();

    $repo = new FamilyMetadataRepository();
    $fallback = $repo->getFallback('inter');
    assertSameValue('sans-serif', $fallback, 'Default fallback should be sans-serif for unknown family.');

    $repo->saveFallback('inter', 'Arial, sans-serif');
    assertSameValue('Arial, sans-serif', $repo->getFallback('inter'), 'getFallback() should return saved value.');
};

$tests['family_metadata_repository_getFallback_returns_default_when_none_saved'] = static function (): void {
    resetTestState();

    $repo = new FamilyMetadataRepository();
    assertSameValue('sans-serif', $repo->getFallback('unknown-family'), 'Default should be sans-serif.');
    assertSameValue('monospace', $repo->getFallback('unknown', 'monospace'), 'Custom default should be used when provided.');
};

$tests['family_metadata_repository_save_and_retrieve_family_font_display'] = static function (): void {
    resetTestState();

    $repo = new FamilyMetadataRepository();
    assertSameValue('', $repo->getFontDisplay('inter'), 'Default font-display should be empty string.');

    $repo->saveFontDisplay('inter', 'auto');
    assertSameValue('auto', $repo->getFontDisplay('inter'), 'getFontDisplay() should return saved value.');
};

$tests['family_metadata_repository_getFontDisplay_returns_default_when_none_saved'] = static function (): void {
    resetTestState();

    $repo = new FamilyMetadataRepository();
    assertSameValue('', $repo->getFontDisplay('unknown'), 'Default should be empty.');
    assertSameValue('swap', $repo->getFontDisplay('unknown', 'swap'), 'Custom default should be used.');
};

$tests['family_metadata_repository_resetAll_clears_both_maps'] = static function (): void {
    resetTestState();

    $repo = new FamilyMetadataRepository();
    $repo->saveFallback('inter', 'Arial, sans-serif');
    $repo->saveFontDisplay('inter', 'auto');

    $repo->resetAll();

    assertSameValue('sans-serif', $repo->getFallback('inter'), 'Fallback should reset to default after resetAll().');
    assertSameValue('', $repo->getFontDisplay('inter'), 'Font-display should reset to default after resetAll().');
};

$tests['family_metadata_repository_invalid_font_display_rejected'] = static function (): void {
    resetTestState();

    $repo = new FamilyMetadataRepository();
    $repo->saveFontDisplay('test-family', 'invalid-value');

    assertSameValue('', $repo->getFontDisplay('test-family'), 'Invalid font-display should not be saved.');
};
