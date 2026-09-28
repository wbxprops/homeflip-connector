<?php
/**
 * Your Brand: everything a customer sets up, on ONE screen (wp-admin menu "Your Brand").
 *
 * Gary, 2026-09-28: the starter pages looked unfinished (site address as the
 * title, no logo, no photo, red "not set" markers). A customer should not have
 * to find Settings -> General, the Customizer, Elementor Site Settings and our
 * business option separately. This page writes all of them:
 *
 *   Site title / tagline  -> blogname / blogdescription
 *   Logo / browser icon   -> theme_mod custom_logo / option site_icon
 *   Business details      -> homeflip_business (the [homeflip_business] shortcodes)
 *   Brand colors          -> Elementor kit Global Colors (every page follows)
 *   Homepage photo        -> homeflip_hero_image (behind the Home headline)
 *
 * Placeholders (logo + house photo from assets/) are put in place once on a fresh
 * site, so the template looks finished before anyone touches it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const HOMEFLIP_HERO_OPTION         = 'homeflip_hero_image';

/* -------------------------------------------------------------------------- */
/* Placeholders                                                                */
/* -------------------------------------------------------------------------- */

/** Copy a bundled asset into the Media Library. @return int attachment id, 0 on failure */
function homeflip_import_asset( $file, $title ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$src = __DIR__ . '/../assets/' . $file;
	if ( ! file_exists( $src ) ) {
		return 0;
	}
	$tmp = wp_tempnam( $file );
	copy( $src, $tmp );
	$id = media_handle_sideload(
		array(
			'name'     => $file,
			'tmp_name' => $tmp,
		),
		0,
		$title
	);
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return 0;
	}
	return (int) $id;
}

/** Once per site: placeholder logo + homepage photo, only where nothing is set. */
function homeflip_install_placeholders() {
	if ( get_option( 'homeflip_placeholders_done' ) ) {
		return;
	}
	if ( ! get_theme_mod( 'custom_logo' ) ) {
		$logo = homeflip_import_asset( 'logo-placeholder.png', 'Placeholder logo' );
		if ( $logo ) {
			set_theme_mod( 'custom_logo', $logo );
		}
	}
	if ( ! get_option( HOMEFLIP_HERO_OPTION ) ) {
		$hero = homeflip_import_asset( 'hero-house.jpg', 'Homepage photo' );
		if ( $hero ) {
			update_option( HOMEFLIP_HERO_OPTION, $hero, false );
		}
	}
	update_option( 'homeflip_placeholders_done', 1, false );
}

/**
 * 0.4.2 reworded the placeholder logo ("...under Your Brand"). Sites still showing
 * the OLD placeholder get the new one; a logo the customer uploaded is never touched.
 */
function homeflip_refresh_placeholder_logo() {
	if ( (int) get_option( 'homeflip_placeholder_logo_v' ) >= 2 || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$old = (int) get_theme_mod( 'custom_logo' );
	if ( $old && 'Placeholder logo' === get_the_title( $old ) ) {
		$new = homeflip_import_asset( 'logo-placeholder.png', 'Placeholder logo' );
		if ( $new ) {
			set_theme_mod( 'custom_logo', $new );
			wp_delete_attachment( $old, true );
			homeflip_queue_purge();
		}
	}
	update_option( 'homeflip_placeholder_logo_v', 2, false );
}
add_action( 'admin_init', 'homeflip_refresh_placeholder_logo', 30 );

/* -------------------------------------------------------------------------- */
/* Brand colors (Elementor kit)                                                */
/* -------------------------------------------------------------------------- */

function homeflip_kit_color( $which, $fallback ) {
	$kit_id   = (int) get_option( 'elementor_active_kit' );
	$settings = $kit_id ? get_post_meta( $kit_id, '_elementor_page_settings', true ) : array();
	foreach ( (array) ( $settings['system_colors'] ?? array() ) as $c ) {
		if ( ( $c['_id'] ?? '' ) === $which && ! empty( $c['color'] ) ) {
			return $c['color'];
		}
	}
	return $fallback;
}

function homeflip_set_kit_color( $which, $hex ) {
	$kit_id = (int) get_option( 'elementor_active_kit' );
	if ( ! $kit_id || ! $hex ) {
		return;
	}
	$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
	$settings = is_array( $settings ) ? $settings : array();
	$colors   = (array) ( $settings['system_colors'] ?? array() );
	$found    = false;
	foreach ( $colors as $i => $c ) {
		if ( ( $c['_id'] ?? '' ) === $which ) {
			$colors[ $i ]['color'] = $hex;
			$found                 = true;
		}
	}
	if ( ! $found ) {
		$colors[] = array(
			'_id'   => $which,
			'title' => ucfirst( $which ),
			'color' => $hex,
		);
	}
	$settings['system_colors'] = $colors;
	update_post_meta( $kit_id, '_elementor_page_settings', $settings );
}

/* -------------------------------------------------------------------------- */
/* Front end: homepage photo                                                   */
/* -------------------------------------------------------------------------- */

/**
 * The Home hero is a container with class `homeflip-hero`. Its photo is CSS, not
 * an Elementor setting, so changing the photo here never touches the page (which
 * the customer owns). The overlay is the brand color, so text stays readable on
 * any photo.
 */
function homeflip_hero_css() {
	// Home pages built by 0.4.x hold an (now always empty) testimonials band.
	echo '<style id="homeflip-front">.homeflip-t-section{display:none!important}</style>' . "\n";

	$id  = (int) get_option( HOMEFLIP_HERO_OPTION );
	$url = $id ? wp_get_attachment_image_url( $id, 'full' ) : '';
	if ( ! $url ) {
		return;
	}
	$hex = ltrim( homeflip_kit_color( 'primary', '#1E3A5F' ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$rgb = array_map( 'hexdec', str_split( substr( $hex . '000000', 0, 6 ), 2 ) );
	$ov  = 'rgba(' . implode( ',', $rgb ) . ',.78)';
	printf(
		"<style id=\"homeflip-hero\">.homeflip-hero{background-image:linear-gradient(%1\$s,%1\$s),url('%2\$s')!important;background-size:cover!important;background-position:center!important}</style>\n",
		esc_attr( $ov ),
		esc_url( $url )
	);
}
add_action( 'wp_head', 'homeflip_hero_css', 99 );

/**
 * [homeflip_testimonials] -- RETIRED 2026-09-28 (Gary: testimonials are not a
 * setting; customers add them in Elementor like any other content). Kept as a
 * no-op so a page built by 0.4.x never prints the raw shortcode.
 */
add_action(
	'init',
	function () {
		add_shortcode( 'homeflip_testimonials', '__return_empty_string' );
	}
);

/* -------------------------------------------------------------------------- */
/* The admin page                                                              */
/* -------------------------------------------------------------------------- */

function homeflip_setup_menu() {
	// Not "HomeFlip" and not the house icon: the Properties menu already uses the
	// house, and customers read this as THEIR brand settings (Gary, 2026-09-28).
	add_menu_page(
		'Your Brand',
		'Your Brand',
		'manage_options',
		'homeflip-setup',
		'homeflip_setup_page',
		'dashicons-art',
		3
	);
}
add_action( 'admin_menu', 'homeflip_setup_menu' );

function homeflip_setup_assets( $hook ) {
	if ( 'toplevel_page_homeflip-setup' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		"jQuery(function($){
			$('.hf-color').wpColorPicker();
			$('.hf-pick').on('click',function(e){
				e.preventDefault();
				var box=$(this).closest('.hf-media'),frame=wp.media({title:$(this).data('title'),library:{type:'image'},multiple:false});
				frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();
					box.find('input[type=hidden]').val(a.id);
					box.find('img').attr('src',(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url)).show();});
				frame.open();});
			$('.hf-clear').on('click',function(e){e.preventDefault();var box=$(this).closest('.hf-media');
				box.find('input[type=hidden]').val('');box.find('img').hide();});
		});"
	);
}
add_action( 'admin_enqueue_scripts', 'homeflip_setup_assets' );

function homeflip_media_field( $name, $id, $title, $help ) {
	$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	printf(
		'<div class="hf-media"><img src="%1$s" style="max-width:320px;max-height:140px;display:%2$s;margin-bottom:8px;background:#f0f0f1;padding:6px;border-radius:4px"><br>'
		. '<input type="hidden" name="%3$s" value="%4$s">'
		. '<button class="button hf-pick" data-title="%5$s">Choose image</button> <button class="button-link hf-clear">Remove</button>'
		. '<p class="description">%6$s</p></div>',
		esc_url( $src ),
		$src ? 'block' : 'none',
		esc_attr( $name ),
		esc_attr( $id ? $id : '' ),
		esc_attr( $title ),
		esc_html( $help )
	);
}

function homeflip_setup_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'homeflip_setup' );
	$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below

	update_option( 'blogname', sanitize_text_field( $p['blogname'] ?? '' ) );
	update_option( 'blogdescription', sanitize_text_field( $p['blogdescription'] ?? '' ) );

	set_theme_mod( 'custom_logo', absint( $p['custom_logo'] ?? 0 ) );
	update_option( 'site_icon', absint( $p['site_icon'] ?? 0 ) );
	update_option( HOMEFLIP_HERO_OPTION, absint( $p['hero_image'] ?? 0 ), false );

	$biz = (array) get_option( HOMEFLIP_BUSINESS_OPTION, array() );
	foreach ( homeflip_business_fields() as $key => $label ) {
		$val         = $p['biz'][ $key ] ?? '';
		$biz[ $key ] = 'email' === $key ? sanitize_email( $val ) : sanitize_text_field( $val );
	}
	update_option( HOMEFLIP_BUSINESS_OPTION, $biz, false );

	foreach ( array( 'primary', 'accent' ) as $which ) {
		$hex = sanitize_hex_color( $p['color'][ $which ] ?? '' );
		if ( $hex ) {
			homeflip_set_kit_color( $which, $hex );
		}
	}


	homeflip_purge_caches(); // colors live in Elementor's generated CSS
	add_settings_error( 'homeflip', 'saved', 'Saved. Your website is updated.', 'updated' );
}

function homeflip_setup_page() {
	if ( isset( $_POST['homeflip_setup_save'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in homeflip_setup_save
		homeflip_setup_save();
	}
	$biz    = (array) get_option( HOMEFLIP_BUSINESS_OPTION, array() );
	$filled = count( array_filter( $biz ) );
	$total  = count( homeflip_business_fields() );
	$help   = array(
		'name'    => 'Shown on every page and in the text-message opt-in.',
		'phone'   => 'Becomes the tap-to-call button.',
		'email'   => '',
		'city'    => 'Used in the homepage headline: "Sell your house fast in ___".',
		'state'   => '',
		'area'    => 'For example: Cincinnati and Northern Kentucky.',
		'address' => 'Shown on the Privacy Policy and Terms pages.',
	);
	?>
	<div class="wrap" style="max-width:900px">
		<h1>Your Brand</h1>
		<p style="font-size:14px">Everything your website needs, in one place. Changes show on your site as soon as you save.
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">View your site &rarr;</a></p>
		<?php settings_errors( 'homeflip' ); ?>
		<form method="post">
			<?php wp_nonce_field( 'homeflip_setup' ); ?>

			<h2>1. Your brand</h2>
			<table class="form-table" role="presentation">
				<tr><th>Website title</th><td><input class="regular-text" name="blogname" value="<?php echo esc_attr( get_option( 'blogname' ) ); ?>">
					<p class="description">Usually your business name. Shows in the browser tab and in Google.</p></td></tr>
				<tr><th>Tagline</th><td><input class="regular-text" name="blogdescription" value="<?php echo esc_attr( get_option( 'blogdescription' ) ); ?>"></td></tr>
				<tr><th>Logo</th><td><?php homeflip_media_field( 'custom_logo', (int) get_theme_mod( 'custom_logo' ), 'Choose your logo', 'Shows at the top of every page. A wide image with a transparent background looks best.' ); ?></td></tr>
				<tr><th>Browser icon</th><td><?php homeflip_media_field( 'site_icon', (int) get_option( 'site_icon' ), 'Choose a square icon', 'The small square icon in the browser tab. At least 512 x 512 pixels.' ); ?></td></tr>
				<tr><th>Main color</th><td><input class="hf-color" name="color[primary]" value="<?php echo esc_attr( homeflip_kit_color( 'primary', '#1E3A5F' ) ); ?>">
					<p class="description">Headlines and the homepage banner.</p></td></tr>
				<tr><th>Button color</th><td><input class="hf-color" name="color[accent]" value="<?php echo esc_attr( homeflip_kit_color( 'accent', '#E8772E' ) ); ?>">
					<p class="description">Buttons and check marks. Pick something that stands out.</p></td></tr>
			</table>

			<h2>2. Your business <span style="font-weight:400;color:#646970;font-size:14px">(<?php echo (int) $filled; ?> of <?php echo (int) $total; ?> filled in)</span></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( homeflip_business_fields() as $key => $label ) : ?>
					<tr><th><?php echo esc_html( $label ); ?></th><td>
						<input class="regular-text" type="<?php echo 'email' === $key ? 'email' : 'text'; ?>" name="biz[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $biz[ $key ] ?? '' ); ?>">
						<?php if ( ! empty( $help[ $key ] ) ) : ?><p class="description"><?php echo esc_html( $help[ $key ] ); ?></p><?php endif; ?>
					</td></tr>
				<?php endforeach; ?>
			</table>

			<h2>3. Homepage photo</h2>
			<table class="form-table" role="presentation">
				<tr><th>Photo</th><td><?php homeflip_media_field( 'hero_image', (int) get_option( HOMEFLIP_HERO_OPTION ), 'Choose a homepage photo', 'The large photo behind your homepage headline. A wide photo of a house works best. Your main color is laid over it so the words stay easy to read.' ); ?></td></tr>
			</table>


			<p class="submit"><button type="submit" name="homeflip_setup_save" value="1" class="button button-primary button-hero">Save and update my website</button></p>
		</form>
	</div>
	<?php
}
