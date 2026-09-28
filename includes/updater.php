<?php
/**
 * Self-hosted updates from GitHub Releases.
 *
 * WordPress (5.8+) hands any plugin whose header carries `Update URI: <host>` to
 * the `update_plugins_<host>` filter instead of asking wordpress.org. We answer
 * from a small info.json attached to the LATEST GitHub release, so the normal
 * "Update available -> Update now" (and auto-updates) work on every site.
 *
 * The repo is PUBLIC on purpose. A private repo needs a token on every site, and
 * a token in the template is cloned to every customer (§7 standing rule). The
 * code holds no credentials, so public costs nothing.
 *
 * Release = tag vX.Y.Z with two assets:
 *   homeflip-connector.zip  (top folder homeflip-connector/)
 *   info.json               {"version":"X.Y.Z","package":"<zip url>","requires":"6.0","requires_php":"7.4"}
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOMEFLIP_UPDATE_INFO = 'https://github.com/wbxprops/homeflip-connector/releases/latest/download/info.json';

/**
 * Latest release info, cached 1 hour. A failed fetch is cached 15 minutes so
 * a GitHub outage never slows wp-admin down on every page.
 *
 * @return array|null
 */
function homeflip_latest_release() {
	// Dashboard -> Updates -> "Check again" (update-core.php?force-check=1) must
	// really ask GitHub. 0.2.2-0.4.0 only cleared WordPress's own transient, so a
	// 6-hour cache of OUR answer hid 0.4.0 from Gary on 2026-09-28.
	$forced = is_admin() && ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only
	$cached = $forced ? false : get_site_transient( 'homeflip_update_info' );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : null;
	}

	$res  = wp_remote_get( HOMEFLIP_UPDATE_INFO, array( 'timeout' => 10 ) );
	$info = null;
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $body ) && ! empty( $body['version'] ) && ! empty( $body['package'] ) ) {
			$info = $body;
		}
	}

	set_site_transient( 'homeflip_update_info', $info ? $info : 'none', $info ? HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
	return $info;
}

function homeflip_check_update( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( HOMEFLIP_PLUGIN_FILE ) !== $plugin_file ) {
		return $update;
	}
	$info = homeflip_latest_release();
	if ( ! $info ) {
		return $update;
	}

	return array(
		'slug'         => 'homeflip-connector',
		'version'      => $info['version'],
		'url'          => 'https://github.com/wbxprops/homeflip-connector',
		'package'      => $info['package'],
		'requires'     => $info['requires'] ?? '',
		'requires_php' => $info['requires_php'] ?? '',
	);
}
add_filter( 'update_plugins_github.com', 'homeflip_check_update', 10, 3 );

// "Check again" on Dashboard -> Updates should really check again.
add_action(
	'delete_site_transient_update_plugins',
	function () {
		delete_site_transient( 'homeflip_update_info' );
	}
);
