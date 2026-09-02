#!/usr/bin/env bash
set -euo pipefail

SITE_PHP="${SITE_PHP:-/usr/bin/php8.2}"
wp_site() { "$SITE_PHP" "$(command -v wp)" "$@"; }

SENTINEL_KEY='_wp71_compat_foundation_sentinel'

token_title() {
	printf '%s' "WP71 Compatibility Disposable Foundation Failure ${1}"
}

require_token() {
	if [[ ! "${1:-}" =~ ^[a-f0-9]{32}$ ]]; then
		echo 'FAIL: token must be 32 lowercase hexadecimal characters' >&2
		exit 1
	fi
}

generate_token() {
	local token
	token="$("$SITE_PHP" -r 'echo bin2hex(random_bytes(16));')"
	require_token "$token"
	printf '%s' "$token"
}

require_positive_int() {
	if [[ ! "$1" =~ ^[1-9][0-9]*$ ]]; then
		echo "FAIL: expected positive integer, got ${1}" >&2
		exit 1
	fi
}

db_query() {
	wp_site db query "$1" --skip-column-names --allow-root
}

count_token_posts() {
	local prefix title
	prefix="$(wp_site db prefix --allow-root | tr -d '[:space:]')"
	title="$(token_title "$1")"
	db_query "SELECT COUNT(*) FROM ${prefix}posts WHERE post_type = 'courses' AND post_title = '${title}';"
}

count_token_postmeta() {
	local prefix
	prefix="$(wp_site db prefix --allow-root | tr -d '[:space:]')"
	db_query "SELECT COUNT(*) FROM ${prefix}postmeta WHERE meta_key = '${SENTINEL_KEY}' AND meta_value = '${1}';"
}

count_campaign_posts() {
	local prefix
	prefix="$(wp_site db prefix --allow-root | tr -d '[:space:]')"
	db_query "SELECT COUNT(*) FROM ${prefix}posts WHERE post_type = 'courses' AND post_title LIKE 'WP71 Compatibility Disposable Foundation%';"
}

count_campaign_postmeta() {
	local prefix
	prefix="$(wp_site db prefix --allow-root | tr -d '[:space:]')"
	db_query "SELECT COUNT(*) FROM ${prefix}postmeta WHERE meta_key = '${SENTINEL_KEY}';"
}

locate_token_post_ids() {
	local prefix title
	prefix="$(wp_site db prefix --allow-root | tr -d '[:space:]')"
	title="$(token_title "$1")"
	db_query "SELECT p.ID FROM ${prefix}posts p LEFT JOIN ${prefix}postmeta m ON m.post_id = p.ID AND m.meta_key = '${SENTINEL_KEY}' AND m.meta_value = '${1}' WHERE p.post_type = 'courses' AND (p.post_title = '${title}' OR m.meta_id IS NOT NULL);"
}

locate_orphan_meta_ids() {
	local prefix
	prefix="$(wp_site db prefix --allow-root | tr -d '[:space:]')"
	db_query "SELECT m.meta_id FROM ${prefix}postmeta m LEFT JOIN ${prefix}posts p ON p.ID = m.post_id WHERE m.meta_key = '${SENTINEL_KEY}' AND m.meta_value = '${1}' AND p.ID IS NULL;"
}

emit_ids() {
	local label="$1" id
	while IFS= read -r id; do
		id="${id//[[:space:]]/}"
		[[ -z "$id" ]] && continue
		require_positive_int "$id"
		echo "${label}=${id}"
	done
}

inspect_token() {
	require_token "$1"
	echo "posts=$(count_token_posts "$1" | tr -d '[:space:]')"
	echo "postmeta=$(count_token_postmeta "$1" | tr -d '[:space:]')"
	locate_token_post_ids "$1" | emit_ids post_id
	locate_orphan_meta_ids "$1" | emit_ids orphan_meta_id
}

recover_token() {
	local id posts metas post_ids orphan_ids
	require_token "$1"
	echo "token=${1}"

	post_ids="$(locate_token_post_ids "$1")"
	while IFS= read -r id; do
		id="${id//[[:space:]]/}"
		[[ -z "$id" ]] && continue
		require_positive_int "$id"
		if ! wp_site post delete "$id" --force --allow-root; then
			echo "FAIL: wp post delete failed for post_id=${id}" >&2
			exit 1
		fi
		echo "SUCCESS: deleted post_id=${id}"
	done <<< "$post_ids"

	orphan_ids="$(locate_orphan_meta_ids "$1")"
	while IFS= read -r id; do
		id="${id//[[:space:]]/}"
		[[ -z "$id" ]] && continue
		require_positive_int "$id"
		if ! wp_site eval "if ( ! delete_metadata_by_mid( 'post', ${id} ) ) { fwrite( STDERR, 'FAIL: delete_metadata_by_mid failed for ${id}' . PHP_EOL ); exit( 1 ); } echo 'SUCCESS: deleted orphan_meta_id=${id}' . PHP_EOL;" --allow-root; then
			echo "FAIL: delete_metadata_by_mid failed for orphan_meta_id=${id}" >&2
			exit 1
		fi
	done <<< "$orphan_ids"

	posts="$(count_token_posts "$1" | tr -d '[:space:]')"
	metas="$(count_token_postmeta "$1" | tr -d '[:space:]')"
	if [[ "$posts" != "0" || "$metas" != "0" ]]; then
		echo "FAIL: token residue remains posts=${posts} postmeta=${metas}" >&2
		exit 1
	fi
}

on_exit_recover() {
	local status=$?
	trap - EXIT
	recover_token "$TOKEN"
	if [[ "$status" -ne 0 ]]; then
		exit "$status"
	fi
	exit 1
}

run_parent_verifier() {
	local campaign_posts campaign_metas token_posts token_metas
	local child_script child_output child_status expected marker_count

	campaign_posts="$(count_campaign_posts | tr -d '[:space:]')"
	campaign_metas="$(count_campaign_postmeta | tr -d '[:space:]')"
	if [[ "$campaign_posts" != "0" || "$campaign_metas" != "0" ]]; then
		echo "FAIL: dirty campaign baseline posts=${campaign_posts} postmeta=${campaign_metas}" >&2
		exit 1
	fi

	TOKEN="$(generate_token)"
	echo "token=${TOKEN}"
	echo "NOTE: EXIT-trap recovery cannot cover SIGKILL, shell destruction, or container/host termination."
	echo "Follow-up: $0 --recover ${TOKEN}"

	token_posts="$(count_token_posts "$TOKEN" | tr -d '[:space:]')"
	token_metas="$(count_token_postmeta "$TOKEN" | tr -d '[:space:]')"
	if [[ "$token_posts" != "0" || "$token_metas" != "0" ]]; then
		echo "FAIL: token already present posts=${token_posts} postmeta=${token_metas}" >&2
		exit 1
	fi

	trap 'exit 1' INT TERM
	child_script="$(cd "$(dirname "$0")" && pwd)/verify-assertion-failure-cleanup.php"

	set +e
	trap on_exit_recover EXIT
	child_output="$(wp_site eval-file "$child_script" "$TOKEN" --allow-root 2>&1)"
	child_status=$?
	set -e

	printf '%s\n' "$child_output"

	expected="FAIL: intentional assertion cleanup probe token=${TOKEN}"
	marker_count="$(printf '%s\n' "$child_output" | grep -c -x -F "$expected" || true)"

	token_posts="$(count_token_posts "$TOKEN" | tr -d '[:space:]')"
	token_metas="$(count_token_postmeta "$TOKEN" | tr -d '[:space:]')"
	campaign_posts="$(count_campaign_posts | tr -d '[:space:]')"
	campaign_metas="$(count_campaign_postmeta | tr -d '[:space:]')"

	if [[ "$child_status" != "1" ]]; then
		echo "FAIL: child status expected 1, got ${child_status}" >&2
		exit 1
	fi
	if [[ "$marker_count" != "1" ]]; then
		echo "FAIL: expected exactly one complete marker line, got ${marker_count}" >&2
		exit 1
	fi
	if [[ "$token_posts" != "0" || "$token_metas" != "0" || "$campaign_posts" != "0" || "$campaign_metas" != "0" ]]; then
		echo "FAIL: pre-recovery residue token_posts=${token_posts} token_postmeta=${token_metas} campaign_posts=${campaign_posts} campaign_postmeta=${campaign_metas}" >&2
		exit 1
	fi

	trap - EXIT
	echo "PASS: assertion failure cleanup child-status=1 post-residue=0 meta-residue=0"
	exit 0
}

case "${1:-}" in
	--inspect)
		inspect_token "${2:-}"
		;;
	--recover)
		recover_token "${2:-}"
		echo "FAIL: recovery mode always exits nonzero" >&2
		exit 1
		;;
	"")
		run_parent_verifier
		;;
	*)
		echo "FAIL: usage: $0 --inspect TOKEN | --recover TOKEN" >&2
		exit 1
		;;
esac
