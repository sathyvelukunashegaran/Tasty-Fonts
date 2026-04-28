# Tasty Fonts Domain Context

## Terms

### Admin action

A transport-neutral mutation or command that changes Tasty Fonts state from the admin UI, REST endpoints, or WP-CLI. Admin action modules keep validation, side effects, activity logging, and payload shape local while adapters handle form posts, REST requests, or CLI arguments.

### Catalog provider discovery

The provider-specific discovery step that turns local storage scans or hosted provider metadata into synthetic catalog families. Discovery adapters produce catalog-shaped family records; `CatalogService` remains responsible for merging, normalizing, caching, filtering, and counting the catalog.
