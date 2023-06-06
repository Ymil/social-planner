<?php
/**
 * Facebook network handler for Social Planner plugin
 *
 * @package social-planner
 * @author  Anton Lukin
 */

 namespace Social_Planner;

 use WP_Error;

abstract class Network{

	/**
	 * Unique network slug.
	 *
	 * @var string
	 */
    const NETWORK_NAME = self::NETWORK_NAME;

	/**
	 * Settings helper link.
	 *
	 * @var string
	 */
	const HELPER_LINK = self::HELPER_LINK;

	/**
	 * Return human-readable network label.
	 */
	abstract public static function get_label();

	/**
	 * Get network helper link
	 */
	abstract public static function get_helper();

	/**
	 * Return network required settings fields
	 */
	abstract public static function get_fields();

	/**
	 * Send message method
	 *
	 * @param array $message  Message data.
	 * @param array $settings Settings array from options.
	 */
	abstract public static function send_message( $message, $settings );

	/**
	 * Prepare data and send request to remote API.
	 *
	 * @param array  $message Message data.
	 * @param string $path    Remote API URL path.
	 * @param string $token   Access token from settings.
	 */
	abstract protected static function make_request( $message, $path, $token );


	/**
	 * Prepare message excerpt.
	 *
	 * @param array $message List of message args.
	 */
	private static function prepare_message_excerpt( $message ) {
		$excerpt = array();

		if ( ! empty( $message['excerpt'] ) ) {
			$excerpt[] = $message['excerpt'];
		}

		// Acá es donde se pone el link en la publicación

		if ( ! empty( $message['link'] ) ) {
			$excerpt[] = $message['link'];
		}

		$excerpt = implode( "\n\n", $excerpt );

		/**
		 * Filter message excerpt right before sending.
		 *
		 * @param string $excerpt Message excerpt.
		 * @param array  $message Original message array.
		 * @param string $network Network name.
		 */
		return apply_filters( 'social_planner_prepare_excerpt', $excerpt, $message, self::NETWORK_NAME );
	}

	/**
	 * Send request to remote server.
	 *
	 * @param string $url     Remote API URL.
	 * @param array  $body    Request body.
	 * @param array  $headers Optional. Request headers.
	 */
	private static function send_request( $url, $body, $headers = null ) {
		$args = array(
			'user-agent' => 'social-planner/' . SOCIAL_PLANNER_VERSION,
			'body'       => $body,
			'timeout'    => 15,
		);

		if ( $headers ) {
			$args['headers'] = $headers;
		}

		/**
		 * Filter request arguments right before sending.
		 *
		 * @param string $args    Request arguments.
		 * @param array  $url     URL to retrieve.
		 * @param string $network Network name.
		 *
		 * @since 1.1.12
		 */
		$args = apply_filters( 'social_planner_before_request', $args, $url, self::NETWORK_NAME );

		return wp_remote_post( $url, $args );
	}
}
