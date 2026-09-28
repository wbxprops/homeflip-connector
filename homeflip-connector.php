<?php
/**
 * Plugin Name:       HomeFlip Connector
 * Description:       Receives property data from the HomeFlip CRM and renders it into any
 *                    page builder via shortcodes. Registers the `property` post type and
 *                    the HomeFlip meta fields, exposed to the REST API.
 * Version:           0.2.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            HomeFlip
 * License:           GPL-2.0-or-later
 * Text Domain:       homeflip
 * Update URI:        https://github.com/wbxprops/homeflip-connector
 *
 * ---------------------------------------------------------------------------
 * WHY SHORTCODES AND NOT A WIDGET REWRITE
 * ---------------------------------------------------------------------------
 * The page layout (`_elementor_data`) and the property data (post meta) are two
 * different rows in wp_postmeta. The subscriber owns the layout and edits it freely
 * in Elementor forever. HomeFlip owns the data rows and republishes them at will.
 * Because they are different rows, neither writer can clobber the other.
 *
 * The alternative -- splicing values into the widget tree -- makes HomeFlip and the
 * subscriber two writers of one field, which has no clean merge. It also targets
 * widgets positionally, so a subscriber who adds their own carousel silently
 * redirects HomeFlip's write into it.
 *
 * ---------------------------------------------------------------------------
 * THE TWO REST GOTCHAS
 * ---------------------------------------------------------------------------
 * 1. A post type needs `'supports' => ['custom-fields']` or its registered meta is
 *    silently absent from the REST API. No error -- the `meta` key just never appears.
 * 2. Meta keys beginning with `_` are protected and refuse REST writes without an
 *    explicit auth_callback. These keys deliberately do not start with `_`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOMEFLIP_POST_TYPE   = 'property';
const HOMEFLIP_PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/forms.php';
require_once __DIR__ . '/includes/updater.php';

/**
 * The HomeFlip-owned fields.
 *
 * `render` names the formatter used by the matching shortcode. Formats mirror
 * wp-page-generator.py exactly (money / money_k / num), so swapping a live page from
 * spliced widgets to shortcodes changes the mechanism without changing the page.
 *
 * Everything is stored as a string because wp_postmeta.meta_value is longtext and
 * WordPress has no column types. Formatting happens at render.
 */
function homeflip_fields() {
	return array(
		'homeflip_property_id' => array( 'label' => 'CRM property ID', 'render' => 'raw' ),
		'homeflip_price'       => array( 'label' => 'Purchase price',  'render' => 'money' ),
		'homeflip_arv_low'     => array( 'label' => 'ARV low',         'render' => 'money_k' ),
		'homeflip_arv_high'    => array( 'label' => 'ARV high',        'render' => 'money_k' ),
		'homeflip_rent'        => array( 'label' => 'Rent estimate',   'render' => 'money' ),
		'homeflip_beds'        => array( 'label' => 'Bedrooms',        'render' => 'num' ),
		'homeflip_baths'       => array( 'label' => 'Bathrooms',       'render' => 'decimal' ),
		'homeflip_sqft'        => array( 'label' => 'Square feet',     'render' => 'num' ),
		'homeflip_year'        => array( 'label' => 'Year built',      'render' => 'year' ),
		'homeflip_status'      => array( 'label' => 'Status',          'render' => 'raw' ),
		'homeflip_updated'     => array( 'label' => 'Last push',       'render' => 'raw' ),
	);
}

/* -------------------------------------------------------------------------- */
/* Post type                                                                   */
/* -------------------------------------------------------------------------- */

/**
 * Register the `property` post type.
 *
 * GUARDED. whitebox.properties already has a `property` type registered by something
 * else -- post 9086 predates this plugin. Registering it twice would clobber the live
 * site's labels and rewrite rules. If it already exists we only add what we need.
 */
function homeflip_register_post_type() {
	if ( post_type_exists( HOMEFLIP_POST_TYPE ) ) {
		// Someone else owns the type. Make sure meta can still reach REST.
		add_post_type_support( HOMEFLIP_POST_TYPE, 'custom-fields' );
		return;
	}

	register_post_type(
		HOMEFLIP_POST_TYPE,
		array(
			'labels'       => array(
				'name'          => __( 'Properties', 'homeflip' ),
				'singular_name' => __( 'Property', 'homeflip' ),
				'add_new_item'  => __( 'Add New Property', 'homeflip' ),
				'edit_item'     => __( 'Edit Property', 'homeflip' ),
			),
			'public'       => true,
			'has_archive'  => true,
			'menu_icon'    => 'dashicons-admin-home',
			'rewrite'      => array( 'slug' => 'property' ),

			// REST is how HomeFlip writes. Without show_in_rest there is nothing to push to.
			'show_in_rest' => true,

			// 'custom-fields' is REQUIRED for registered meta to appear in REST.
			// Omit it and every meta write silently no-ops.
			'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'excerpt' ),
		)
	);
}
add_action( 'init', 'homeflip_register_post_type' );

/* -------------------------------------------------------------------------- */
/* Meta                                                                        */
/* -------------------------------------------------------------------------- */

function homeflip_register_meta() {
	foreach ( homeflip_fields() as $key => $spec ) {
		register_post_meta(
			HOMEFLIP_POST_TYPE,
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}
}
add_action( 'init', 'homeflip_register_meta' );

/* -------------------------------------------------------------------------- */
/* Formatters -- mirror wp-page-generator.py                                   */
/* -------------------------------------------------------------------------- */

function homeflip_format( $value, $how ) {
	if ( '' === $value || null === $value ) {
		return '';
	}

	switch ( $how ) {
		case 'money':   // money()    -> $95,000
			return '$' . number_format( (float) $value, 0 );

		case 'money_k': // money_k()  -> $185k
			return '$' . rtrim( rtrim( number_format( (float) $value / 1000, 2, '.', '' ), '0' ), '.' ) . 'k';

		case 'num':     // num()      -> 1,450
			return number_format( (float) $value, 0 );

		case 'decimal': // baths      -> 2 or 2.5
			return rtrim( rtrim( number_format( (float) $value, 1, '.', '' ), '0' ), '.' );

		case 'year':
			return (string) (int) $value;

		default:
			return (string) $value;
	}
}

/**
 * Empty values are VISIBLE TO EDITORS and silent for visitors.
 *
 * The project's own post-mortem: "every stale value tonight was a field nobody knew
 * was unfilled." A shortcode that renders nothing looks identical to a shortcode that
 * rendered correctly, so an unfilled field would ship to buyers unnoticed. Logged-in
 * editors get a marker; the public page stays clean.
 */
function homeflip_empty_notice( $label ) {
	if ( current_user_can( 'edit_posts' ) ) {
		return '<span class="homeflip-empty" style="color:#b32d2e;font-size:.85em;">['
			. esc_html( $label ) . ' not set]</span>';
	}
	return '';
}

function homeflip_value( $key, $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( ! $post_id ) {
		return '';
	}
	return (string) get_post_meta( $post_id, $key, true );
}

/* -------------------------------------------------------------------------- */
/* Shortcodes                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * One shortcode per high-frequency field.
 *
 * Deliberately NOT here: comp blocks and galleries. Those are set once when a page is
 * built and effectively never change, so they stay as native Elementor widgets the
 * subscriber can edit. Only the values that actually get republished -- price, ARV,
 * rent, status -- need to be safe against a republish.
 */
function homeflip_field_shortcode( $key ) {
	$fields = homeflip_fields();
	$spec   = $fields[ $key ];
	$raw    = homeflip_value( $key );

	if ( '' === $raw ) {
		return homeflip_empty_notice( $spec['label'] );
	}
	return esc_html( homeflip_format( $raw, $spec['render'] ) );
}

function homeflip_register_shortcodes() {
	$simple = array(
		'homeflip_price'  => 'homeflip_price',
		'homeflip_rent'   => 'homeflip_rent',
		'homeflip_beds'   => 'homeflip_beds',
		'homeflip_baths'  => 'homeflip_baths',
		'homeflip_sqft'   => 'homeflip_sqft',
		'homeflip_year'   => 'homeflip_year',
		'homeflip_status' => 'homeflip_status',
	);

	foreach ( $simple as $tag => $key ) {
		add_shortcode(
			$tag,
			function () use ( $key ) {
				return homeflip_field_shortcode( $key );
			}
		);
	}

	// ARV is a range across two fields: "$185k - $210k".
	add_shortcode(
		'homeflip_arv',
		function () {
			$lo = homeflip_value( 'homeflip_arv_low' );
			$hi = homeflip_value( 'homeflip_arv_high' );

			if ( '' === $lo || '' === $hi ) {
				return homeflip_empty_notice( 'ARV range' );
			}
			return esc_html(
				homeflip_format( $lo, 'money_k' ) . ' - ' . homeflip_format( $hi, 'money_k' )
			);
		}
	);

	// Escape hatch so a new CRM field does not require a plugin release.
	add_shortcode(
		'homeflip_field',
		function ( $atts ) {
			$atts = shortcode_atts(
				array(
					'key'    => '',
					'format' => 'raw',
				),
				$atts,
				'homeflip_field'
			);
			if ( '' === $atts['key'] ) {
				return '';
			}
			$raw = homeflip_value( 'homeflip_' . ltrim( $atts['key'], '_' ) );
			if ( '' === $raw ) {
				return homeflip_empty_notice( $atts['key'] );
			}
			return esc_html( homeflip_format( $raw, $atts['format'] ) );
		}
	);
}
add_action( 'init', 'homeflip_register_shortcodes' );

/* -------------------------------------------------------------------------- */
/* Admin: read-only view of what HomeFlip pushed                               */
/* -------------------------------------------------------------------------- */

/**
 * Read-only ON PURPOSE. The CRM is the source of truth for these values, and the
 * SMS blast reads the CRM. An editable field here would let the page say $89,000
 * while the blast tells buyers $95,000, with nothing reporting the divergence.
 */
function homeflip_add_meta_box() {
	add_meta_box(
		'homeflip-data',
		__( 'HomeFlip data', 'homeflip' ),
		'homeflip_render_meta_box',
		HOMEFLIP_POST_TYPE,
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'homeflip_add_meta_box' );

function homeflip_render_meta_box( $post ) {
	echo '<p style="margin-top:0;color:#666;">Pushed from the HomeFlip CRM. Edit in HomeFlip, not here.</p>';
	echo '<table style="width:100%;font-size:12px;">';

	foreach ( homeflip_fields() as $key => $spec ) {
		$raw  = get_post_meta( $post->ID, $key, true );
		$show = ( '' === $raw )
			? '<em style="color:#b32d2e;">not set</em>'
			: esc_html( homeflip_format( $raw, $spec['render'] ) );

		printf(
			'<tr><td style="padding:2px 6px 2px 0;color:#666;">%s</td><td style="padding:2px 0;">%s</td></tr>',
			esc_html( $spec['label'] ),
			$show
		);
	}
	echo '</table>';
}
