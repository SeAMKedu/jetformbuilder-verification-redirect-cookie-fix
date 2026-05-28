<?php
/**
 * JetFormBuilder verification redirect cookie fix
 *
 * Fixes cases where JetFormBuilder email verification redirects lose token context
 * before the final success/failure page can be determined.
 *
 * Usage:
 * 1. Copy this snippet into Code Snippets or your theme/plugin.
 * 2. Adjust the CONFIG section.
 * 3. Test both verification flows carefully.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CONFIG
 */
function jfb_cookie_redirect_fix_config() {
	return array(

		/**
		 * Custom post type created by the verified form.
		 */
		'post_type' => 'your_post_type',

		/**
		 * Meta key where the JetFormBuilder token ID is stored.
		 */
		'token_id_meta_key' => '_jfb_verification_token_id',

		/**
		 * Page slugs.
		 */
		'publish_page_slug' => 'your-publish-page',
		'reply_page_slug'   => 'your-reply-page',
		'failed_page_slug'  => 'verification-failed',

		/**
		 * Redirect targets.
		 * Use paths beginning and ending with slash.
		 */
		'publish_success_path' => '/publish-success/',
		'reply_success_path'   => '/reply-success/',
		'failed_path'          => '/verification-failed/',

		/**
		 * Cookie settings.
		 */
		'cookie_prefix'          => 'jfb_fix_',
		'cookie_lifetime'        => 600,
		'reply_consumed_lifetime' => 30 * DAY_IN_SECONDS,
	);
}

/**
 * Helper: get config value.
 */
function jfb_cookie_redirect_fix_get( $key ) {
	$config = jfb_cookie_redirect_fix_config();
	return isset( $config[ $key ] ) ? $config[ $key ] : null;
}

/**
 * Helper: clear cookie.
 */
function jfb_cookie_redirect_fix_clear_cookie( $name ) {
	setcookie(
		$name,
		'',
		time() - 3600,
		COOKIEPATH ?: '/',
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);

	unset( $_COOKIE[ $name ] );
}

/**
 * Capture JetFormBuilder token ID into a short-lived cookie.
 */
add_action( 'init', function () {

	if ( is_admin() ) {
		return;
	}

	if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
		return;
	}

	if ( empty( $_GET['jfb_token_id'] ) ) {
		return;
	}

	$token_id = absint( $_GET['jfb_token_id'] );

	if ( ! $token_id ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	$cookie_prefix = jfb_cookie_redirect_fix_get( 'cookie_prefix' );
	$cookie_life   = (int) jfb_cookie_redirect_fix_get( 'cookie_lifetime' );

	$publish_cookie = $cookie_prefix . 'publish_token_id';
	$reply_cookie   = $cookie_prefix . 'reply_token_id';

	$reply_page_slug = jfb_cookie_redirect_fix_get( 'reply_page_slug' );

	if ( strpos( $request_uri, '/' . $reply_page_slug ) !== false ) {
		$cookie_name = $reply_cookie;
	} else {
		$cookie_name = $publish_cookie;
	}

	setcookie(
		$cookie_name,
		(string) $token_id,
		time() + $cookie_life,
		COOKIEPATH ?: '/',
		COOKIE_DOMAIN,
		is_ssl(),
		true
	);

	$_COOKIE[ $cookie_name ] = (string) $token_id;

}, 1 );

/**
 * Handle final redirect correction.
 */
add_action( 'template_redirect', function () {

	if ( is_admin() ) {
		return;
	}

	$cookie_prefix  = jfb_cookie_redirect_fix_get( 'cookie_prefix' );
	$publish_cookie = $cookie_prefix . 'publish_token_id';
	$reply_cookie   = $cookie_prefix . 'reply_token_id';

	$publish_page_slug = jfb_cookie_redirect_fix_get( 'publish_page_slug' );
	$reply_page_slug   = jfb_cookie_redirect_fix_get( 'reply_page_slug' );
	$failed_page_slug  = jfb_cookie_redirect_fix_get( 'failed_page_slug' );

	$failed_path          = jfb_cookie_redirect_fix_get( 'failed_path' );
	$publish_success_path = jfb_cookie_redirect_fix_get( 'publish_success_path' );
	$reply_success_path   = jfb_cookie_redirect_fix_get( 'reply_success_path' );

	/**
	 * FLOW 1:
	 * Verified form creates/publishes a custom post type item.
	 */
	if ( is_page( $publish_page_slug ) ) {

		if ( empty( $_COOKIE[ $publish_cookie ] ) ) {
			return;
		}

		$token_id = absint( $_COOKIE[ $publish_cookie ] );

		jfb_cookie_redirect_fix_clear_cookie( $publish_cookie );
		jfb_cookie_redirect_fix_clear_cookie( $reply_cookie );

		if ( ! $token_id ) {
			wp_safe_redirect( home_url( $failed_path ) );
			exit;
		}

		$query = new WP_Query( array(
			'post_type'      => sanitize_key( jfb_cookie_redirect_fix_get( 'post_type' ) ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => jfb_cookie_redirect_fix_get( 'token_id_meta_key' ),
					'value' => (string) $token_id,
				),
			),
		) );

		if ( $query->have_posts() ) {
			$post_id = (int) $query->posts[0];

			$consumed_key = '_jfb_token_consumed_' . $token_id;

			if ( get_post_meta( $post_id, $consumed_key, true ) ) {
				wp_safe_redirect( home_url( $failed_path ) );
				exit;
			}

			update_post_meta( $post_id, $consumed_key, 1 );

			wp_safe_redirect( home_url( $publish_success_path ) );
			exit;
		}

		wp_safe_redirect( home_url( $failed_path ) );
		exit;
	}

	/**
	 * FLOW 2:
	 * Verified reply/contact form.
	 */
	$is_reply_page  = is_page( $reply_page_slug );
	$is_failed_page = is_page( $failed_page_slug );

	if ( ! $is_reply_page && ! $is_failed_page ) {
		return;
	}

	if ( empty( $_COOKIE[ $reply_cookie ] ) ) {
		return;
	}

	$token_id = absint( $_COOKIE[ $reply_cookie ] );

	jfb_cookie_redirect_fix_clear_cookie( $reply_cookie );
	jfb_cookie_redirect_fix_clear_cookie( $publish_cookie );

	if ( ! $token_id ) {
		if ( ! $is_failed_page ) {
			wp_safe_redirect( home_url( $failed_path . '?status=failed' ) );
			exit;
		}

		return;
	}

	$consumed_key = $cookie_prefix . 'consumed_' . $token_id;

	if ( get_transient( $consumed_key ) ) {
		if ( ! $is_failed_page ) {
			wp_safe_redirect( home_url( $failed_path . '?status=failed' ) );
			exit;
		}

		return;
	}

	global $wpdb;

	$tokens_table = $wpdb->prefix . 'jet_fb_tokens';

	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$tokens_table} WHERE id = %d LIMIT 1",
			$token_id
		),
		ARRAY_A
	);

	if ( ! $row ) {
		if ( ! $is_failed_page ) {
			wp_safe_redirect( home_url( $failed_path . '?status=failed' ) );
			exit;
		}

		return;
	}

	$used = false;

	foreach ( array( 'used', 'is_used', 'is_verified', 'verified', 'confirmed' ) as $key ) {
		if (
			array_key_exists( $key, $row )
			&& (string) $row[ $key ] !== ''
			&& (int) $row[ $key ] === 1
		) {
			$used = true;
			break;
		}
	}

	if ( ! $used ) {
		foreach ( array( 'used_at', 'verified_at', 'confirmed_at', 'updated_at' ) as $key ) {
			if (
				! empty( $row[ $key ] )
				&& $row[ $key ] !== '0000-00-00 00:00:00'
			) {
				$used = true;
				break;
			}
		}
	}

	if ( ! $used && ! empty( $row['status'] ) ) {
		$status = strtolower( (string) $row['status'] );

		if ( in_array( $status, array( 'used', 'verified', 'success', 'completed', 'confirmed' ), true ) ) {
			$used = true;
		}
	}

	if ( $used ) {
		set_transient(
			$consumed_key,
			1,
			(int) jfb_cookie_redirect_fix_get( 'reply_consumed_lifetime' )
		);

		wp_safe_redirect( home_url( $reply_success_path ) );
		exit;
	}

	if ( ! $is_failed_page ) {
		wp_safe_redirect( home_url( $failed_path . '?status=failed' ) );
		exit;
	}

}, 99 );
