<?php
/**
 * The buyer forms -- plan §7 job 4, §13 "Forms".
 *
 * Built ONCE, on the template site, through Forminator's PHP API
 * (Forminator_API::add_form). A clone copies the database, so every customer
 * site gets the same form IDs and page designs can reference
 * [forminator_form id="X"] safely. Never one form per property.
 *
 *   Buyer Profile   -> contacts row (contact_type investor_buyer)
 *   Next Purchase   -> one buyer_criteria_sets row: what they want to buy NEXT.
 *                      A buyer can submit it any number of times; the email
 *                      links each one back to the same contact.
 *
 * Answer VALUES are the CRM's storage keys (homeflip-crm
 * src/lib/wholesale/criteria.ts) so a submission maps with no translating.
 * Change one there, change it here.
 *
 * Field format was read from Forminator 1.57.3's own source (library/fields/*),
 * not guessed: its import format is undocumented.
 *
 * No integrations are connected here, deliberately. A connection is a sign-in,
 * and a sign-in in the template is cloned to every customer (§7 standing rule).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOMEFLIP_FORMS_OPTION = 'homeflip_form_ids';

/**
 * Test-site opt-in wording (§13). [Business Name] stays literal on the template;
 * it is filled in per customer. Swap for the full version before real customers.
 */
function homeflip_optin_text() {
	return 'By checking this box, I agree to receive text messages and emails from [Business Name] '
		. 'about new deals. Msg &amp; data rates may apply. Reply STOP to opt out.';
}

/* -------------------------------------------------------------------------- */
/* Field builders                                                              */
/* -------------------------------------------------------------------------- */

/** @param array $pairs value => label */
function homeflip_options( $pairs ) {
	$out = array();
	foreach ( $pairs as $value => $label ) {
		$out[] = array(
			'label' => $label,
			'value' => (string) $value,
			'limit' => '',
			'key'   => forminator_unique_key(),
		);
	}
	return $out;
}

/** One field per row: Forminator calls a row a "wrapper". */
function homeflip_row( $field ) {
	return array(
		'wrapper_id' => 'wrapper-' . forminator_unique_key(),
		'fields'     => array( array_merge( array( 'cols' => '12' ), $field ) ),
	);
}

/** Show $field only when $element_id holds any of $values. */
function homeflip_show_when( $field, $element_id, $values ) {
	$field['conditions'] = array();
	foreach ( $values as $v ) {
		$field['conditions'][] = array(
			'element_id' => $element_id,
			'rule'       => 'is',
			'value'      => $v,
		);
	}
	$field['condition_action'] = 'show';
	$field['condition_rule']   = 'any';
	return $field;
}

function homeflip_f_name() {
	return array(
		'element_id'     => 'name-1',
		'type'           => 'name',
		'field_label'    => 'Name',
		'multiple_name'  => 'true',
		'prefix'         => 'false',
		'mname'          => 'false',
		'fname'          => 'true',
		'lname'          => 'true',
		'fname_label'    => 'First name',
		'lname_label'    => 'Last name',
		'fname_required' => 'true',
		'lname_required' => 'true',
		'layout_columns' => '2',
	);
}

function homeflip_f_email( $required = true ) {
	return array(
		'element_id'  => 'email-1',
		'type'        => 'email',
		'field_label' => 'Email',
		'required'    => $required ? 'true' : 'false',
		'validation'  => true,
	);
}

function homeflip_f_phone( $required ) {
	return array(
		'element_id'  => 'phone-1',
		'type'        => 'phone',
		'field_label' => 'Mobile phone',
		'required'    => $required ? 'true' : 'false',
		'validation'  => 'none',
		'limit'       => 20,
	);
}

function homeflip_f_text( $id, $label, $required = false, $placeholder = '' ) {
	return array(
		'element_id'  => $id,
		'type'        => 'text',
		'field_label' => $label,
		'placeholder' => $placeholder,
		'required'    => $required ? 'true' : 'false',
		'input_type'  => 'line',
	);
}

function homeflip_f_textarea( $id, $label, $required = false, $placeholder = '' ) {
	return array(
		'element_id'  => $id,
		'type'        => 'textarea',
		'field_label' => $label,
		'placeholder' => $placeholder,
		'required'    => $required ? 'true' : 'false',
	);
}

function homeflip_f_number( $id, $label, $placeholder = '' ) {
	return array(
		'element_id'   => $id,
		'type'         => 'number',
		'field_label'  => $label,
		'placeholder'  => $placeholder,
		'required'     => 'false',
		'calculations' => 'false',
		'limit_min'    => '0',
	);
}

function homeflip_f_choice( $id, $type, $label, $pairs, $required = false ) {
	return array(
		'element_id'  => $id,
		'type'        => $type, // radio | checkbox
		'value_type'  => $type,
		'field_label' => $label,
		'layout'      => 'vertical',
		'required'    => $required ? 'true' : 'false',
		'options'     => homeflip_options( $pairs ),
	);
}

function homeflip_f_date( $id, $label, $required ) {
	return array(
		'element_id'  => $id,
		'type'        => 'date',
		'field_label' => $label,
		'field_type'  => 'picker',
		'date_format' => 'mm/dd/yy',
		'placeholder' => 'Choose a date',
		'past_dates'  => 'disable',
		'required'    => $required ? 'true' : 'false',
	);
}

/** Opt-in is NOT required: consent can never be a condition of signing up. */
function homeflip_f_optin() {
	return array(
		'element_id'          => 'consent-1',
		'type'                => 'consent',
		'field_label'         => 'Text + email opt-in',
		'required'            => 'false',
		'consent_description' => homeflip_optin_text(),
	);
}

/** The page the form was submitted from: CRM contacts.marketing_consent_url. */
function homeflip_f_page_url() {
	return array(
		'element_id'    => 'hidden-1',
		'type'          => 'hidden',
		'field_label'   => 'Page URL',
		'default_value' => 'embed_url',
	);
}

/* -------------------------------------------------------------------------- */
/* Shared answer lists -- CRM keys                                              */
/* -------------------------------------------------------------------------- */

function homeflip_purchase_methods() {
	return array(
		'cash'           => 'Cash',
		'hard_money'     => 'Hard money',
		'private_lender' => 'Private lender',
		'bank'           => 'Bank financing',
	);
}

function homeflip_strategies() {
	return array(
		'flip'      => 'Fix and flip',
		'rental'    => 'Rental',
		'brrrr'     => 'BRRRR',
		'wholesale' => 'Wholesale',
	);
}

/* -------------------------------------------------------------------------- */
/* The two forms                                                               */
/* -------------------------------------------------------------------------- */

function homeflip_form_definitions() {
	$not_cash = array( 'hard_money', 'private_lender', 'bank' );

	$profile = array(
		homeflip_f_name(),
		homeflip_f_email(),
		homeflip_f_phone( true ),
		homeflip_f_text( 'text-1', 'Company name', false ),
		homeflip_f_choice(
			'radio-1',
			'radio',
			'What is your experience level?',
			array(
				'new'     => 'New investor',
				'novice'  => 'Novice (1-3 deals)',
				'veteran' => 'Veteran (3+ deals)',
			),
			true
		),
		homeflip_f_number( 'number-1', 'How many houses do you buy a year?', 'e.g. 4' ),
		homeflip_f_choice( 'radio-2', 'radio', 'How do you pay for most deals?', homeflip_purchase_methods(), true ),
		homeflip_show_when( homeflip_f_text( 'text-2', 'Name of your bank / lender' ), 'radio-2', $not_cash ),
		homeflip_f_choice( 'checkbox-1', 'checkbox', 'What do you buy?', homeflip_strategies(), true ),
		homeflip_f_textarea( 'textarea-1', 'Where do you buy?', true, 'Cities, neighborhoods or zip codes' ),
		homeflip_f_textarea( 'textarea-2', 'Anything else we should know?' ),
		homeflip_f_optin(),
		homeflip_f_page_url(),
	);

	$next = array(
		homeflip_f_name(),
		homeflip_f_email(),
		homeflip_f_phone( false ),
		homeflip_f_text( 'text-1', 'Give this buy box a name', false, 'e.g. Flips on the west side under $150K' ),
		homeflip_f_choice( 'checkbox-1', 'checkbox', 'Strategy for this purchase', homeflip_strategies(), true ),
		homeflip_f_choice(
			'checkbox-2',
			'checkbox',
			'Property type',
			array(
				'sfr'        => 'Single family',
				'multi'      => 'Multi-family',
				'condo'      => 'Condo / townhome',
				'land'       => 'Land',
				'commercial' => 'Commercial',
			)
		),
		homeflip_f_choice(
			'checkbox-3',
			'checkbox',
			'How much rehab will you take on?',
			array(
				'turnkey'  => 'Turnkey / no rehab',
				'light'    => 'Paint & carpet',
				'standard' => 'Standard rehab',
				'gut'      => 'Full gut',
			)
		),
		homeflip_f_textarea( 'textarea-1', 'Where do you want to buy?', true, 'Cities, neighborhoods or zip codes' ),
		homeflip_f_number( 'number-1', 'Minimum purchase price', '$' ),
		homeflip_f_number( 'number-2', 'Maximum purchase price', '$' ),
		homeflip_f_number( 'number-3', 'Most you want to be all in (price + rehab)', '$' ),
		homeflip_f_number( 'number-4', 'Highest ARV you will buy', '$' ),
		homeflip_f_number( 'number-5', 'Minimum bedrooms' ),
		homeflip_f_number( 'number-6', 'Minimum bathrooms' ),
		homeflip_f_number( 'number-7', 'Minimum square feet' ),
		homeflip_f_date( 'date-1', 'When do you want to have this deal by?', true ),
		homeflip_f_choice(
			'radio-1',
			'radio',
			'How quickly can you close?',
			array(
				'asap'      => 'ASAP',
				'2_3_weeks' => '2-3 weeks',
				'30'        => '30 days',
				'45'        => '45 days',
				'60_plus'   => '60+ days',
			)
		),
		homeflip_f_choice( 'radio-2', 'radio', 'How will you pay for this one?', homeflip_purchase_methods() ),
		homeflip_show_when(
			homeflip_f_choice(
				'radio-3',
				'radio',
				'Are you approved with your lender?',
				array(
					'will_apply' => 'Will apply',
					'in_process' => 'Application in process',
					'approved'   => 'Approved',
				)
			),
			'radio-2',
			$not_cash
		),
		homeflip_show_when( homeflip_f_text( 'text-2', 'Name of your bank / lender' ), 'radio-2', $not_cash ),
		homeflip_f_choice(
			'radio-4',
			'radio',
			'Anything stopping you from buying right now?',
			array(
				'none'       => 'Nothing, ready to go',
				'lender'     => 'Need approval from my lender',
				'partner'    => 'Need to talk to my partner',
				'sell_first' => 'Need to sell the property I have now',
				'other'      => 'Something else',
			)
		),
		homeflip_show_when( homeflip_f_text( 'text-3', 'What is it?' ), 'radio-4', array( 'other' ) ),
		homeflip_f_textarea( 'textarea-2', 'Any other features you are looking for?' ),
		homeflip_f_optin(),
		homeflip_f_page_url(),
	);

	return array(
		'buyer_profile' => array(
			'title'  => 'Buyer Profile',
			'submit' => 'Join the buyers list',
			'thanks' => 'Thanks! You are on the list. We will reach out when a deal fits what you buy.',
			'fields' => $profile,
		),
		'next_purchase' => array(
			'title'  => 'Next Purchase (Buyer Criteria)',
			'submit' => 'Save my buy box',
			'thanks' => 'Got it. We will send you deals that match this buy box.',
			'fields' => $next,
		),
	);
}

/* -------------------------------------------------------------------------- */
/* Create once                                                                 */
/* -------------------------------------------------------------------------- */

/**
 * Bump when homeflip_form_definitions() or homeflip_form_settings() change. Existing
 * forms are then rebuilt IN PLACE (same ID) on the next admin load, which is how a
 * field change reaches every cloned customer site with a plugin update.
 *   1 = 0.2.0: first build (submit text set in the pre-1.x format -- rendered blank)
 *   2 = 0.2.1: settings in the current format (submitData, submission-behaviour)
 */
const HOMEFLIP_FORMS_VERSION = 2;

/**
 * Settings in Forminator's CURRENT format, read from 1.57.3's own
 * form-templates/template-blank.php. The top-level `custom-submit-text` used by
 * WPMU DEV's API example is the pre-migration format and renders a blank button.
 */
function homeflip_form_settings( $def ) {
	return array(
		'formName'             => $def['title'],
		'form-type'            => 'default',
		'submission-behaviour' => 'behaviour-thankyou',
		'thankyou-message'     => $def['thanks'],
		'submitData'           => array(
			'custom-submit-text'          => $def['submit'],
			'custom-invalid-form-message' => 'Please fix the highlighted fields.',
		),
		'enable-ajax'          => 'true',
		'validation'           => 'on_submit',
		'validation-inline'    => true,
		'fields-style'         => 'open',
		'basic-fields-style'   => 'open',
		'form-expire'          => 'no_expire',
	);
}

/**
 * Rebuild an existing form in place.
 *
 * Forminator_API::update_form REPLACES settings wholesale (no defaults merged) and
 * REPLACES notifications with whatever is passed, empty included -- so a bare call
 * silently deletes the admin "new submission" email. Carry both over explicitly.
 */
function homeflip_rebuild_form( $id, $def ) {
	$model = Forminator_API::get_form( $id );
	if ( is_wp_error( $model ) ) {
		return $model;
	}
	$settings = array_merge( (array) $model->settings, homeflip_form_settings( $def ) );
	// Version-1 keys in the old format. Left in, Forminator's migration can copy
	// them back over submitData (class-migration.php).
	unset( $settings['use-custom-submit'], $settings['custom-submit-text'], $settings['thankyou'] );

	$notifications = is_array( $model->notifications ) ? $model->notifications : array();

	return Forminator_API::update_form(
		$id,
		array_map( 'homeflip_row', $def['fields'] ),
		$settings,
		'',
		$notifications
	);
}

/**
 * Create any form that does not exist yet, and rebuild existing ones when
 * HOMEFLIP_FORMS_VERSION moves. IDs are kept in an option, which the clone copies,
 * so a cloned site sees its forms as present and creates nothing. A form someone
 * deleted is recreated (with a new ID) on the next admin load.
 *
 * @return array key => form id
 */
function homeflip_ensure_forms() {
	if ( ! class_exists( 'Forminator_API' ) ) {
		return array();
	}

	$ids = get_option( HOMEFLIP_FORMS_OPTION, array() );
	$ids = is_array( $ids ) ? $ids : array();

	$built   = (int) get_option( 'homeflip_forms_version', 1 );
	$rebuild = $built < HOMEFLIP_FORMS_VERSION;
	$ok      = true;

	foreach ( homeflip_form_definitions() as $key => $def ) {
		$exists = ! empty( $ids[ $key ] ) && ! is_wp_error( Forminator_API::get_form( $ids[ $key ] ) );

		if ( $exists ) {
			if ( $rebuild && is_wp_error( homeflip_rebuild_form( $ids[ $key ], $def ) ) ) {
				$ok = false;
			}
			continue;
		}

		$id = Forminator_API::add_form(
			$def['title'],
			array_map( 'homeflip_row', $def['fields'] ),
			homeflip_form_settings( $def )
		);
		if ( is_wp_error( $id ) ) {
			$ok = false;
			continue;
		}
		$ids[ $key ] = (int) $id;
		update_option( HOMEFLIP_FORMS_OPTION, $ids, false );
	}

	// Only record the version once every form is on it, so a failure retries.
	if ( $ok ) {
		update_option( 'homeflip_forms_version', HOMEFLIP_FORMS_VERSION, false );
	}

	return $ids;
}

/**
 * Run in wp-admin, not on activation: Forminator may load after this plugin, and
 * a lock stops two admin tabs racing into duplicate forms.
 */
function homeflip_maybe_ensure_forms() {
	if ( ! current_user_can( 'manage_options' ) || ! class_exists( 'Forminator_API' ) ) {
		return;
	}
	if ( get_transient( 'homeflip_forms_lock' ) ) {
		return;
	}
	set_transient( 'homeflip_forms_lock', 1, 60 );
	homeflip_ensure_forms();
	delete_transient( 'homeflip_forms_lock' );
}
add_action( 'admin_init', 'homeflip_maybe_ensure_forms' );

/**
 * GET /wp-json/homeflip/v1/forms -- which forms exist, their IDs and fields.
 * How HomeFlip (and a page design) learns the form IDs on a site.
 */
function homeflip_register_forms_route() {
	register_rest_route(
		'homeflip/v1',
		'/forms',
		array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'callback'            => function () {
				$out = array();
				foreach ( (array) get_option( HOMEFLIP_FORMS_OPTION, array() ) as $key => $id ) {
					$fields = class_exists( 'Forminator_API' ) ? Forminator_API::get_form_fields( $id ) : array();
					$list   = array();
					if ( ! is_wp_error( $fields ) ) {
						foreach ( (array) $fields as $f ) {
							$list[] = array(
								'id'    => $f->slug,
								'type'  => (string) $f->type,
								'label' => (string) $f->field_label,
							);
						}
					}
					$out[ $key ] = array(
						'id'        => (int) $id,
						'shortcode' => '[forminator_form id="' . (int) $id . '"]',
						'fields'    => $list,
					);
				}
				return rest_ensure_response( $out );
			},
		)
	);
}
add_action( 'rest_api_init', 'homeflip_register_forms_route' );
