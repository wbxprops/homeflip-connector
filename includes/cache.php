<?php
/**
 * Cache purge -- plan §7 job 1.
 *
 * MEASURED 2026-09-28 on the template site (post 21): homeflip_price changed
 * 95000 -> 89000 over REST, the DB held 89000, and the public page kept serving
 * $95,000 with `X-Cache: HIT`. Two full post saves did not clear it either. That
 * cache is WPMU DEV hosting's Static Server Cache, not Elementor: the test page
 * had no Elementor data at all. It self-clears roughly hourly, so a price change
 * would otherwise reach buyers up to an hour late.
 *
 * So every HomeFlip write purges, automatically. The generator never has to
 * remember to call anything; the REST route below is only a manual backstop.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purge every page cache we know about. Each layer is guarded, so on a host
 * without it (whitebox.properties, a local install) this is a no-op, not a fatal.
 *
 * @return string[] the layers actually purged, for the REST response.
 */
function homeflip_purge_caches() {
	$purged = array();

	// WPMU DEV hosting Static Server Cache. Same call as WPMU DEV staff's
	// "Clear Server Cache" admin-bar plugin; whole-site, takes no URL.
	if ( function_exists( 'wpmudev_hosting_purge_static_cache' ) ) {
		wpmudev_hosting_purge_static_cache();
		$purged[] = 'wpmudev_static';
	}

	// Elementor's generated CSS + element cache (the post 9024 problem on whitebox).
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
		$purged[] = 'elementor';
	}

	return $purged;
}

/**
 * Queue one purge for the end of the request. A single REST write touches up to
 * eleven meta keys; purging per key would clear the whole site eleven times.
 */
function homeflip_queue_purge() {
	static $queued = false;
	if ( $queued ) {
		return;
	}
	$queued = true;
	add_action( 'shutdown', 'homeflip_purge_caches' );
}

function homeflip_meta_changed( $meta_id, $post_id, $meta_key ) {
	if ( 0 !== strpos( (string) $meta_key, 'homeflip_' ) ) {
		return;
	}
	if ( HOMEFLIP_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	homeflip_queue_purge();
}
add_action( 'added_post_meta', 'homeflip_meta_changed', 10, 3 );
add_action( 'updated_post_meta', 'homeflip_meta_changed', 10, 3 );
add_action( 'deleted_post_meta', 'homeflip_meta_changed', 10, 3 );

// A full save did not clear the hosting cache either (measured), so purge on it too.
add_action( 'save_post_' . HOMEFLIP_POST_TYPE, 'homeflip_queue_purge' );

/**
 * POST /wp-json/homeflip/v1/flush -- manual backstop.
 */
function homeflip_register_flush_route() {
	register_rest_route(
		'homeflip/v1',
		'/flush',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => function () {
				return rest_ensure_response( array( 'purged' => homeflip_purge_caches() ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'homeflip_register_flush_route' );
