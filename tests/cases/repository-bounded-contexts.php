<?php

declare(strict_types=1);

use TastyFonts\Repository\AdobeProjectRepository;
use TastyFonts\Repository\FamilyMetadataRepository;
use TastyFonts\Repository\GoogleApiKeyRepository;
use TastyFonts\Repository\RoleRepository;

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
