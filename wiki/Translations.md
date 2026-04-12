# Translations

Maintain the plugin’s translation-ready strings and POT template.

## Use This Page When

- you are changing user-facing copy
- you need to refresh translation assets
- you want to confirm the plugin’s text-domain expectations

## Steps

### 1. Use The Correct Text Domain

The plugin text domain is:

`tasty-fonts`

All translatable strings should stay aligned with that domain.

### 2. Treat The POT File As A Generated Reference

The repo includes:

`languages/tasty-fonts.pot`

Refresh it whenever user-facing strings change enough that translators need the updated extraction set.

### 3. Review User-Facing Copy Changes

When you adjust labels, button text, notices, diagnostics copy, or settings language:

- make sure the new strings are translatable
- keep terminology aligned with the current UI
- update docs when the wording change affects user guidance

## Notes

- The plugin now loads its text domain early in boot so translated strings are available before the rest of the runtime and admin hooks register.
- Translation changes often go together with README or wiki updates because terminology consistency matters for user guidance.

## Related Docs

- [Architecture](Architecture)
- [Release Process](Release-Process)
