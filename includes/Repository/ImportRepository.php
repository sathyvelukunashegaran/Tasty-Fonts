<?php

declare(strict_types=1);

namespace EtchFonts\Repository;

final class ImportRepository
{
    public const OPTION_IMPORTS = 'etch_fonts_imports';

    public function all(): array
    {
        return $this->getOptionArray(self::OPTION_IMPORTS);
    }

    public function get(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $imports = $this->all();
        $import = $imports[$slug] ?? null;

        return is_array($import) ? $import : null;
    }

    public function upsert(array $import): void
    {
        if (empty($import['slug']) || !is_string($import['slug'])) {
            return;
        }

        $imports = $this->all();
        $imports[$import['slug']] = $import;

        update_option(self::OPTION_IMPORTS, $imports, false);
    }

    public function delete(string $slug): void
    {
        $slug = trim($slug);

        if ($slug === '') {
            return;
        }

        $imports = $this->all();

        if (!isset($imports[$slug])) {
            return;
        }

        unset($imports[$slug]);

        update_option(self::OPTION_IMPORTS, $imports, false);
    }

    private function getOptionArray(string $option): array
    {
        $value = get_option($option, []);

        return is_array($value) ? $value : [];
    }
}
