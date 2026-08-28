# Compatibility fixtures (WordPress 7.1 campaign)

Local `wp eval-file` foundation on `tutorpress.local`. Not E2E or workflow coverage.

## Runtime synchronization (stop on mismatch)

Before any preflight or browser evidence, confirm the active plugin directory is this checkout or a mount/symlink to it; record that confirmation and stop on mismatch.

```bash
docker exec -it devkinsta_fpm bash
cd /www/kinsta/public/tutorpress
SITE_PHP="${SITE_PHP:-/usr/bin/php8.2}"
wp_site() { "$SITE_PHP" "$(command -v wp)" "$@"; }
wp_site --info --allow-root
wp_site core is-installed --allow-root
wp_site eval 'echo "wp-eval-ok ".PHP_VERSION."\n";' --allow-root
```

`wp_site` uses configurable `SITE_PHP` matching the site web PHP. Current DevKinsta example only: `wp82() { /usr/bin/php8.2 "$(command -v wp)" "$@"; }`. Stop if `wp_site` PHP differs from the site runtime.

## Command order

1. Recreate `wp_site` in this shell.
2. Confirm synchronization and the active plugin path.
3. Run the sanity checks above.
4. Run the read-only environment preflight.
5. Run the disposable lifecycle fixture, then residue counts.
6. Run native browser placement in a fresh tab.

## Fixtures

Unique prefix: `WP71 Compatibility Disposable Foundation`. Sentinel: `_wp71_compat_foundation_sentinel`. Register created IDs and force-delete them in `finally`. Cleanup is idempotent and process-local.

Protected research fixtures (no save/delete): courses `7001`, `9788`, `7558`, `6633`, `6446`; topic `7002`; assignments `7118`, `7166`; bundle `7560`; quiz `9718`; attempt `43`; attachment `9203`; H5P rows `2`/`4`. `_edit_lock` heartbeats are not content writes.

## Residue (expect `0` after each lifecycle run)

```bash
wp_site db query "SELECT COUNT(*) FROM $(wp_site db prefix --allow-root)posts WHERE post_type = 'courses' AND post_title LIKE 'WP71 Compatibility Disposable Foundation%';" --skip-column-names --allow-root
wp_site db query "SELECT COUNT(*) FROM $(wp_site db prefix --allow-root)postmeta WHERE meta_key = '_wp71_compat_foundation_sentinel';" --skip-column-names --allow-root
```

## Browser

Fresh tab, native `fetch`, no `window.__wp71` instrumentation. Reject instrumented evidence.

## Stop conditions

Stop on sync/mount mismatch, CLI-versus-site PHP mismatch, leftover residue, or any save/delete of a protected record.
