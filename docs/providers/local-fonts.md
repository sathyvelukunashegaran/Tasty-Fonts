# Local Fonts

Upload local files or rescan the WordPress uploads directory for existing font files.

## Use This Page When

- you want to self-host fonts you already own or generated yourself
- you copied files into `wp-content/uploads/fonts/` outside the plugin UI
- you need to confirm supported formats and storage behavior

## Steps

### 1. Upload Files From The Dashboard

Use the local upload flow when you want the plugin to validate and store files for you.

Supported local formats:

- `WOFF2`
- `WOFF`
- `TTF`
- `OTF`

### 2. Rescan The Uploads Directory

Use rescan when files already exist somewhere under:

`wp-content/uploads/fonts/`

The scanner can discover supported local font formats stored in that tree.

### 3. Confirm The Family In The Library

After upload or rescan:

- confirm the family appears in the `Font Library`
- review its detected variants
- set a fallback stack if needed
- decide whether it should stay `Published` or `In Library Only`

## Variants and File Details

The plugin validates each uploaded file before writing it to storage. Validation checks:

- the file extension is on the supported allowlist (`WOFF2`, `WOFF`, `TTF`, `OTF`)
- the upload origin passes WordPress native upload verification
- the file moves successfully into the uploads directory with the correct filesystem permissions

Each accepted file becomes a **variant** in the library. Variants are grouped under a family detected from the filename or font metadata. If the plugin cannot detect a family name, the filename is used as a fallback.

Self-hosted local files are stored at:

`wp-content/uploads/fonts/<family-slug>/<filename>`

## Common Issues

**File rejected on upload** — confirm the format is `WOFF2`, `WOFF`, `TTF`, or `OTF`. EOT and SVG are not supported.

**Family does not appear after rescan** — the scanner looks for supported formats anywhere under `wp-content/uploads/fonts/`. If files are nested more than one level inside a subdirectory that is itself not under `fonts/`, they may not be discovered. Move files into `wp-content/uploads/fonts/` directly or into a shallow subdirectory.

**Variants detected under the wrong family** — this can happen when font file metadata uses a different family name than the filename. Open the family in the library, verify the detected variants, and use the delete action to remove misassigned variants if needed.

**Runtime CSS does not include the family** — confirm the family is `Published` or `In Use` in the library and that the active delivery profile is set to local self-hosted delivery.

## Notes

- Local uploads feed the same library used by Google, Bunny, and Adobe sources.
- Local families are self-hosted by nature; they do not depend on remote stylesheet delivery.
- Generated runtime CSS will only serve a family once the family is runtime-visible and used by the applicable output path.

## Related Docs

- [Getting Started](../getting-started.md)
- [Font Library](../font-library.md)
- [Generated CSS](../troubleshooting/generated-css.md)
- [Concepts](../concepts.md)
