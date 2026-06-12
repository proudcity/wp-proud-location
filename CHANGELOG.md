# Changelog

## 2026-06-11

- Fix lat/lng not recomputed when an existing location's address is edited (primary bug #2840)
- Add `apply_geocode()` public method exposing geocode logic for unit testing
- Add `address_changed()` helper to detect address field changes against stored meta
- Add `geocode_address()` helper that returns null on any API failure, preserving previous coordinates
- Add `wp_is_post_revision()` and `DOING_AUTOSAVE` guards to avoid burning Geocoding API quota on revisions and autosaves
- Fix duplicate `lat` check in emptiness test (second clause was `lat` instead of `lng`)
- Remove leftover `print_r($geo)` debug output
- Add `key=` parameter to geocode URL (was missing; Google was returning REQUEST_DENIED)
- Fix `address_string()` line 297: was concatenating `$location['address']` twice instead of `$location['address2']`
- Fix `get_option()` second argument typo at lines 278, 284, 285: `true` (treated as literal default) changed to `''`

References: https://github.com/proudcity/wp-proudcity/issues/2840
