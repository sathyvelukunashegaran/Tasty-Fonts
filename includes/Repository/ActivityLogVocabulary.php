<?php

declare(strict_types=1);

namespace TastyFonts\Repository;

defined('ABSPATH') || exit;

/**
 * Shared activity-log category and event vocabulary.
 *
 * Keep storage operations on ActivityLogRepositoryInterface and shared log
 * constants here so consumers can depend on the operation seam without
 * duplicating cross-cutting strings or requiring the concrete repository.
 * Slice-specific event names may still remain inline near their consumers.
 */
final class ActivityLogVocabulary
{
    public const CATEGORY_TRANSFER = 'transfer';
    public const CATEGORY_SETTINGS = 'settings';
    public const CATEGORY_ROLES = 'roles';
    public const CATEGORY_LIBRARY = 'library';
    public const CATEGORY_IMPORT = 'import';
    public const CATEGORY_INTEGRATION = 'integration';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_UPDATE = 'update';
    public const EVENT_SITE_TRANSFER_EXPORT = 'site_transfer_export';
    public const EVENT_SITE_TRANSFER_IMPORT_SUCCESS = 'site_transfer_import_success';
    public const EVENT_SITE_TRANSFER_IMPORT_FAILURE = 'site_transfer_import_failure';
}
