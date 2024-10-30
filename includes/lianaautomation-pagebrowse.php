<?php
/**
 * LianaAutomation page browse event handler
 *
 * PHP Version 7.4
 *
 * @package  LianaAutomation
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * Page browse tracking function
 *
 * Page browse tracking functionality, requires liana_t cookie
 *
 * @return bool
 */
function lianaautomation_sitetracking_send() {
	// liana_t tracking cookie handling.
	if ( ! isset( $_COOKIE['liana_t'] ) ) {
		// liana_t cookie not found, unable to track. Bailing out.
		return false;
	}
	$liana_t = sanitize_key( $_COOKIE['liana_t'] );

	global $wp;
	$current_url = home_url( add_query_arg( array(), $wp->request ) );

	// Get the liana_pv queryparameter value.
	$liana_pv = isset( $_GET['liana_pv'] ) ? sanitize_text_field( $_GET['liana_pv'] ) : null;

	// Retrieve Liana Options values (Array of All Options).
	$lianaautomation_sitetracking_options = get_option( 'lianaautomation_sitetracking_options' );

	if ( empty( $lianaautomation_sitetracking_options ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_sitetracking_options was empty' );
			// phpcs:enable
		}
		return false;
	}

	// The user id, integer.
	if ( empty( $lianaautomation_sitetracking_options['lianaautomation_user'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_sitetracking_options lianaautomation_user was empty' );
			// phpcs:enable
		}
		return false;
	}
	$user = $lianaautomation_sitetracking_options['lianaautomation_user'];

	// Hexadecimal secret string.
	if ( empty( $lianaautomation_sitetracking_options['lianaautomation_key'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_sitetracking_options lianaautomation_key was empty' );
			// phpcs:enable
		}
		return false;
	}
	$secret = $lianaautomation_sitetracking_options['lianaautomation_key'];

	// The base url for our API installation.
	if ( empty( $lianaautomation_sitetracking_options['lianaautomation_url'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_sitetracking_options lianaautomation_url was empty' );
			// phpcs:enable
		}
		return false;
	}
	$url = $lianaautomation_sitetracking_options['lianaautomation_url'];

	// The realm of our API installation, all caps alphanumeric string.
	if ( empty( $lianaautomation_sitetracking_options['lianaautomation_realm'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_sitetracking_options lianaautomation_realm was empty' );
			// phpcs:enable
		}
		return false;
	}
	$realm = $lianaautomation_sitetracking_options['lianaautomation_realm'];

	// The channel ID of our automation.
	if ( empty( $lianaautomation_sitetracking_options['lianaautomation_channel'] ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:disable WordPress.PHP.DevelopmentFunctions
			error_log( 'lianaautomation_sitetracking_options lianaautomation_channel was empty' );
			// phpcs:enable
		}
		return false;
	}
	$channel = $lianaautomation_sitetracking_options['lianaautomation_channel'];

	/**
	* General variables
	*/
	$base_path    = 'rest';             // Base path of the api end points.
	$content_type = 'application/json'; // Content will be send as json.
	$method       = 'POST';             // Method is always POST.

	/**
	 * Send a API request to LianaAutomation
	 *
	 * This function will add the required headers and
	 * calculates the signature for the authorization header
	 *
	 * @param string $path The path of the end point
	 * @param array  $data The content body (data) of the request
	 *
	 * @return mixed
	 */
	$path = 'v1/import';

	// Create the data array.
	$identity = array(
		'token' => $liana_t,
	);
	if ( ! empty( $liana_pv ) ) {
		$identity['pv_uid'] = $liana_pv;
	}

	$data = array(
		'channel'       => $channel,
		'no_duplicates' => false,
		'data'          => array(
			array(
				'identity' => $identity,
				'events'   => array(
					array(
						'verb'  => 'pbr',
						'items' => array(
							'url' => $current_url,
						),
					),
				),
			),
		),
	);

	// Encode our body content data.
	$data = wp_json_encode( $data );
	// Get the current datetime in ISO 8601.
	$date = gmdate( 'c' );
	// md5 hash our body content.
	$content_md5 = md5( $data );
	// Create our signature.
	$signature_content = implode(
		"\n",
		array(
			$method,
			$content_md5,
			$content_type,
			$date,
			$data,
			"/{$base_path}/{$path}",
		),
	);

	$signature = hash_hmac( 'sha256', $signature_content, $secret );
	// Create the authorization header value.
	$auth = "{$realm} {$user}:" . $signature;

	// Create our full stream context with all required headers.
	$ctx = stream_context_create(
		array(
			'http' => array(
				'method'  => $method,
				'header'  => implode(
					"\r\n",
					array(
						"Authorization: {$auth}",
						"Date: {$date}",
						"Content-md5: {$content_md5}",
						"Content-Type: {$content_type}",
					)
				),
				'content' => $data,
			),
		)
	);

	// Build a full path, open a data stream, and decode the json response.
	$full_path = "{$url}/{$base_path}/{$path}";

	$fp = fopen( $full_path, 'rb', false, $ctx );

	// If LianaAutomation API settings is invalid or endpoint is not working properly, bail out.
	if ( ! $fp ) {
		return false;
	}
	$response = stream_get_contents( $fp );
	$response = json_decode( $response, true );
}

add_action( 'wp_head', 'lianaautomation_sitetracking_send', 10, 2 );
