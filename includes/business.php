<?php
/**
 * The subscriber's business details, and the shortcodes that print them.
 *
 * Page designs never contain a business name, phone or city. They contain
 * [homeflip_business field="phone"], filled from one option that HomeFlip writes
 * when the subscriber connects their site (POST homeflip/v1/business). So one
 * design serves every customer, and a changed phone number is one write, not an
 * edit on every page.
 *
 * Only in text-editor / shortcode widgets: Elementor FREE runs shortcodes there,
 * but not in Heading or Button text (that needs Pro dynamic tags). That is why
 * the phone button is its own shortcode, [homeflip_phone_button].
 *
 * Empty on the template on purpose: it is cloned, and a business detail in it
 * would show on every customer's site. Set on the "Your Brand" admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOMEFLIP_BUSINESS_OPTION = 'homeflip_business';

function homeflip_business_fields() {
	return array(
		'name'    => 'Business name',
		'phone'   => 'Phone',
		'email'   => 'Email',
		'city'    => 'City',
		'state'   => 'State',
		'area'    => 'Service area', // "Cincinnati and Northern Kentucky"
		'address' => 'Mailing address',
	);
}

function homeflip_business( $field ) {
	$all = get_option( HOMEFLIP_BUSINESS_OPTION, array() );
	return is_array( $all ) && isset( $all[ $field ] ) ? (string) $all[ $field ] : '';
}

/**
 * [homeflip_business field="city" before="Sell your house fast in " fallback="Sell your house fast"]
 *
 * Filled: before + value + after. Empty: the fallback, for EVERYONE -- no red
 * "not set" marker on a public design (Gary, 2026-09-28: the markers made the
 * template look broken). What is missing is listed on the "Your Brand" page
 * instead. `name` falls back to the site title so it is never blank.
 */
function homeflip_business_shortcode( $atts ) {
	$atts   = shortcode_atts(
		array(
			'field'    => '',
			'before'   => '',
			'after'    => '',
			'fallback' => '',
		),
		$atts,
		'homeflip_business'
	);
	$fields = homeflip_business_fields();
	if ( ! isset( $fields[ $atts['field'] ] ) ) {
		return '';
	}
	$value = homeflip_business( $atts['field'] );
	if ( '' === $value && 'name' === $atts['field'] ) {
		$value = (string) get_option( 'blogname' );
	}
	if ( '' === $value ) {
		return esc_html( $atts['fallback'] );
	}
	$shown = 'email' === $atts['field']
		? '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>'
		: esc_html( $value );
	return esc_html( $atts['before'] ) . $shown . esc_html( $atts['after'] );
}

/**
 * [homeflip_contact] -- name, service area, address, email, phone: only the lines
 * that are filled in, so an unset detail leaves no blank gap. Name falls back to
 * the site title.
 */
function homeflip_contact_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'address' => 'no' ), $atts, 'homeflip_contact' );
	$name  = homeflip_business( 'name' );
	$name  = '' !== $name ? $name : (string) get_option( 'blogname' );
	$lines = array( '<strong>' . esc_html( $name ) . '</strong>' );
	foreach ( array( 'area', 'address', 'email', 'phone' ) as $f ) {
		if ( 'address' === $f && 'yes' !== $atts['address'] ) {
			continue;
		}
		$v = homeflip_business( $f );
		if ( '' === $v ) {
			continue;
		}
		if ( 'email' === $f ) {
			$lines[] = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
		} elseif ( 'phone' === $f ) {
			$lines[] = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>';
		} else {
			$lines[] = esc_html( $v );
		}
	}
	return implode( '<br>', $lines );
}

/** A tap-to-call button, styled with Elementor's own button classes. Nothing when no phone. */
function homeflip_phone_button_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'label' => '' ), $atts, 'homeflip_phone_button' );
	$phone = homeflip_business( 'phone' );
	if ( '' === $phone ) {
		return '';
	}
	$digits = preg_replace( '/[^0-9+]/', '', $phone );
	$label  = '' !== $atts['label'] ? $atts['label'] . ' ' . $phone : $phone;
	return '<a class="elementor-button elementor-size-md homeflip-phone-button" href="tel:' . esc_attr( $digits ) . '">'
		. '<span class="elementor-button-text">' . esc_html( $label ) . '</span></a>';
}

/**
 * [homeflip_form name="buyer_profile"] -- a form by NAME, never by ID.
 *
 * HomeFlip's own forms resolve from the homeflip_form_ids option. Anything else
 * ("seller") matches the first Forminator form whose title contains it, so a
 * hand-built form on the template is reachable too. IDs survive a clone, but a
 * design that hard-codes one breaks the day a form is rebuilt.
 */
function homeflip_form_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'name' => '' ), $atts, 'homeflip_form' );
	$name = sanitize_key( $atts['name'] );
	if ( '' === $name || ! class_exists( 'Forminator_API' ) ) {
		return '';
	}

	$ids = (array) get_option( HOMEFLIP_FORMS_OPTION, array() );
	$id  = isset( $ids[ $name ] ) ? (int) $ids[ $name ] : homeflip_find_form_id( $atts['name'] );

	if ( ! $id ) {
		return homeflip_empty_notice( 'Form "' . $atts['name'] . '"' );
	}
	return do_shortcode( '[forminator_form id="' . $id . '"]' );
}

function homeflip_find_form_id( $needle ) {
	$forms = Forminator_API::get_forms( null, 1, 100 );
	if ( is_wp_error( $forms ) ) {
		return 0;
	}
	foreach ( (array) $forms as $form ) {
		$title = isset( $form->settings['formName'] ) ? $form->settings['formName'] : $form->name;
		if ( false !== stripos( (string) $title, (string) $needle ) ) {
			return (int) $form->id;
		}
	}
	return 0;
}

function homeflip_register_business_shortcodes() {
	add_shortcode( 'homeflip_business', 'homeflip_business_shortcode' );
	add_shortcode( 'homeflip_phone_button', 'homeflip_phone_button_shortcode' );
	add_shortcode( 'homeflip_contact', 'homeflip_contact_shortcode' );
	add_shortcode( 'homeflip_form', 'homeflip_form_shortcode' );
}
add_action( 'init', 'homeflip_register_business_shortcodes' );

// Every page shows these details, so a change must reach the cached pages.
add_action( 'update_option_' . HOMEFLIP_BUSINESS_OPTION, 'homeflip_queue_purge' );
add_action( 'add_option_' . HOMEFLIP_BUSINESS_OPTION, 'homeflip_queue_purge' );

/**
 * GET/POST /wp-json/homeflip/v1/business. POST merges: send only what changed.
 * Unknown keys are dropped, so HomeFlip cannot create options by accident.
 */
function homeflip_register_business_route() {
	register_rest_route(
		'homeflip/v1',
		'/business',
		array(
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function () {
					return rest_ensure_response( (array) get_option( HOMEFLIP_BUSINESS_OPTION, array() ) );
				},
			),
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$current = (array) get_option( HOMEFLIP_BUSINESS_OPTION, array() );
					foreach ( homeflip_business_fields() as $key => $label ) {
						$val = $req->get_param( $key );
						if ( null !== $val ) {
							$current[ $key ] = 'email' === $key ? sanitize_email( $val ) : sanitize_text_field( $val );
						}
					}
					update_option( HOMEFLIP_BUSINESS_OPTION, $current, false );
					return rest_ensure_response( $current );
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'homeflip_register_business_route' );
