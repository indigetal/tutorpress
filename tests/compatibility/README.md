# Compatibility fixtures (WordPress 7.1 campaign)

Local `wp eval-file` foundation on `tutorpress.local`. Not E2E or workflow coverage.

## Runtime synchronization (stop on mismatch)

Before any preflight or browser evidence, confirm the active plugin directory is this checkout or a mount/symlink to it; record that confirmation and stop on mismatch.

Narrow `docker cp` supplies only this follow-on's missing compatibility executables. It does not establish product-tree synchronization and is not a substitute for the current-checkout/mount check above.

From the current checkout on the host, create only the active `tests/compatibility/` directory and copy these five files individually (`docker exec` without `-it`):

```bash
docker exec devkinsta_fpm mkdir -p /www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility
docker cp tests/compatibility/bootstrap.php devkinsta_fpm:/www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/bootstrap.php
docker cp tests/compatibility/verify-wp-7-1-environment.php devkinsta_fpm:/www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-wp-7-1-environment.php
docker cp tests/compatibility/verify-disposable-lifecycle.php devkinsta_fpm:/www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-disposable-lifecycle.php
docker cp tests/compatibility/verify-assertion-failure-cleanup.php devkinsta_fpm:/www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-assertion-failure-cleanup.php
docker cp tests/compatibility/verify-assertion-failure-cleanup.sh devkinsta_fpm:/www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-assertion-failure-cleanup.sh
```

Compare host `shasum -a 256` and container `sha256sum` for all five files. Any missing file or digest mismatch is a stop condition.

```bash
shasum -a 256 tests/compatibility/bootstrap.php tests/compatibility/verify-wp-7-1-environment.php tests/compatibility/verify-disposable-lifecycle.php tests/compatibility/verify-assertion-failure-cleanup.php tests/compatibility/verify-assertion-failure-cleanup.sh
docker exec devkinsta_fpm sha256sum /www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/bootstrap.php /www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-wp-7-1-environment.php /www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-disposable-lifecycle.php /www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-assertion-failure-cleanup.php /www/kinsta/public/tutorpress/wp-content/plugins/tutorpress/tests/compatibility/verify-assertion-failure-cleanup.sh
```

Copy, hash, and `wp_site` probes from the host use `docker exec` without `-it`. Interactive human shell (recreate `wp_site` here):

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

Active-runtime environment preflight, from `/www/kinsta/public/tutorpress`. This is not a status-`0` gate:

```bash
wp_site eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-wp-7-1-environment.php --allow-root
```

Independent active-path check using the same rule as the environment fixture (`WP_PLUGIN_DIR` / `tutorpress.php` / fixture `realpath` as `__FILE__` / `strpos`). Do not grep preflight output for `TutorPress plugin dir:` or `Fixture:`. Require the environment fixture under the active plugin directory; stop on mismatch:

```bash
wp_site eval '$plugin_file = WP_PLUGIN_DIR . "/tutorpress/tutorpress.php"; $plugin_dir = realpath( dirname( $plugin_file ) ); $fixture = realpath( WP_PLUGIN_DIR . "/tutorpress/tests/compatibility/verify-wp-7-1-environment.php" ); if ( ! ( is_file( $plugin_file ) && $plugin_dir && $fixture && 0 === strpos( $fixture, $plugin_dir . DIRECTORY_SEPARATOR ) ) ) { fwrite( STDERR, "FAIL: fixture is not under the active TutorPress plugin directory\n" ); exit( 1 ); } echo "active-path-ok ".$fixture."\n";' --allow-root
```

## Command order

1. Confirm the active plugin directory is the current checkout or its synchronized mount; stop on mismatch.
2. Narrow-copy the five compatibility executables above and require five matching SHA-256 digests.
3. Recreate `wp_site` in this shell.
4. Run the sanity checks above.
5. Run the documented environment preflight command (not a status-`0` gate) and the independent active-path check; stop on active-path mismatch.
6. Run the disposable lifecycle fixture twice; after each run, both campaign residue queries must be `0`.
7. Run the parent verifier once with no flags.
8. Run native browser placement in a fresh tab.

## Fixtures

Unique prefix: `WP71 Compatibility Disposable Foundation`. Sentinel: `_wp71_compat_foundation_sentinel`. Register created IDs and force-delete them in `finally`. Cleanup is idempotent and process-local.

Protected research fixtures (no save/delete): courses `7001`, `9788`, `7558`, `6633`, `6446`; topic `7002`; assignments `7118`, `7166`; bundle `7560`; quiz `9718`; attempt `43`; attachment `9203`; H5P rows `2`/`4`. `_edit_lock` heartbeats are not content writes.

## Residue (expect `0` after each lifecycle run)

```bash
wp_site db query "SELECT COUNT(*) FROM $(wp_site db prefix --allow-root)posts WHERE post_type = 'courses' AND post_title LIKE 'WP71 Compatibility Disposable Foundation%';" --skip-column-names --allow-root
wp_site db query "SELECT COUNT(*) FROM $(wp_site db prefix --allow-root)postmeta WHERE meta_key = '_wp71_compat_foundation_sentinel';" --skip-column-names --allow-root
```

## Disposable lifecycle

From `/www/kinsta/public/tutorpress`, run twice:

```bash
wp_site eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-disposable-lifecycle.php --allow-root
```

Each run must exit `0` and print exactly:

```text
PASS: lifecycle create/read id={id} actor={actor} title={title}
PASS: lifecycle cleanup
```

IDs and titles must differ across the two runs. After each run, both campaign residue queries above must return `0`. Do not delete campaign-wide rows.

## Assertion-failure cleanup verifier

From `/www/kinsta/public/tutorpress`, no flags:

```bash
bash wp-content/plugins/tutorpress/tests/compatibility/verify-assertion-failure-cleanup.sh
```

Child title: `WP71 Compatibility Disposable Foundation Failure {token}`. Sentinel `_wp71_compat_foundation_sentinel` equals `{token}`. Locators use the exact title or exact sentinel key+value, `post_type = 'courses'`, and positive IDs only. Recovery uses `wp post delete --force` and `delete_metadata_by_mid()` and requires `SUCCESS:` lines. `--recover {token}` always exits nonzero. EXIT recovery cannot cover `SIGKILL`, shell destruction, or container/host termination; follow-up is `$0 --recover {token}`.

Clean acceptance requires internally observed child status `1`, exactly one complete line `FAIL: intentional assertion cleanup probe token={token}` (`grep -c -x -F` / `^FAIL:`), all four pre-recovery counts `0` (exact-title `courses`, exact sentinel key+value, campaign title `LIKE`, campaign sentinel key), no recovery API calls (`SUCCESS: deleted` absent; EXIT disarmed), parent `PASS: assertion failure cleanup child-status=1 post-residue=0 meta-residue=0`, and parent status `0`. Recovery during this clean run is evidence failure.

The parent stays nonzero for a dirty campaign baseline, unexpected child status or marker, any pre-recovery residue, recovery execution, and recovery failure.

## Browser

Fresh tab, native `fetch`, no `window.__wp71` instrumentation. Reject instrumented evidence.

## Stop conditions

Stop on sync/mount mismatch, copied-file hash mismatch, CLI-versus-site PHP mismatch, active-path mismatch, leftover residue, child status other than `1`, marker count other than one, parent status other than `0` on a clean acceptance run, or any save/delete of a protected record.
