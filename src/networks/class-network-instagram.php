<?php
/**
 * Instagram network handler for Social Planner plugin
 *
 * @package social-planner
 * @author  Lautaro Linquimán
 */

namespace Social_Planner;

use WP_Error;

/**
 * Instagram Social Planner class
 *
 * @since 1.0.0
 */
class Network_Instagram extends Network{
	/**
	 * Unique network slug.
	 *
	 * @var string
	 */
	const NETWORK_NAME = 'instagram';

	/**
	 * Settings helper link.
	 *
	 * @var string
	 */
	const HELPER_LINK = 'https://wpset.org/social-planner/setup/#instagram';

	/**
	 * Return human-readable network label.
	 */
	public static function get_label() {
		return _x( 'Instagram', 'provider label', 'social-planner' );
	}

	/**
	 * Get network helper link
	 */
	public static function get_helper() {
		$helper = sprintf(
			wp_kses(
				// translators: %s is a link for current network help guide.
				__( 'Read the <a href="%s" target="_blank">help guide</a> for configuring Facebook provider.', 'social-planner' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
			),
			esc_url( self::HELPER_LINK )
		);

		return $helper;
	}

	/**
	 * Return network required settings fields
	 */
	public static function get_fields() {
		$fields = array(
			'token' => array(
				'label'    => __( 'Access token', 'social-planner' ),
				'required' => true,
			),

			'ig_user_id' => array(
				'label'    => __( 'User ID', 'social-planner' ),
				'required' => true,
			),

			'title' => array(
				'label' => __( 'Subtitle', 'social-planner' ),
				'hint'  => __( 'Optional field. Used as an subtitle if there are multiple Instagram providers.', 'social-planner' ),
			),
		);

		return $fields;
	}

	/**
	 * Send message method
	 *
	 * @param array $message  Message data.
	 * @param array $settings Settings array from options.
	 */
	public static function send_message( $message, $settings ) {
		if ( empty( $settings['ig_user_id'] ) ) {
			return new WP_Error( 'sending', esc_html__( 'User ID parameter is not found', 'social-planner' ) );
		}

		// Get API URL path using group id from settings.
		$path = 'https://graph.facebook.com/v17.0/' . $settings['ig_user_id'];

		if ( empty( $settings['token'] ) ) {
			return new WP_Error( 'sending', esc_html__( 'Token parameter is empty', 'social-planner' ) );
		}

		// return new WP_Error("sending", message);

		$response = self::make_request( $message, $path, $settings['token'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['body'] ) ) {
			return new WP_Error( 'sending', esc_html__( 'Empty API response', 'social-planner' ) );
		}

		$response = json_decode( $response['body'], false );

		if ( ! empty( $response->id ) ) {
			return 'https://instagram.com/' . $response->id;
		}

		if ( ! empty( $response->error->message ) ) {
			return new WP_Error( 'sending', $response->error->message );
		}

		return new WP_Error( 'sending', esc_html__( 'Unknown API error', 'social-planner' ) );
	}

	/**
	 * Prepare data and send request to remote API.
	 *
	 * @param array  $message Message data.
	 * @param string $path    Remote API URL path.
	 * @param string $token   Access token from settings.
	 */
	protected static function make_request( $message, $path, $token ) {
		$body = array(
			'access_token' => $token,
		);

		if ( ! empty( $message['preview'] ) && ! empty( $message['link'] ) ) {
			$body['link'] = $message['link'];
		}

		$excerpt = self::prepare_message_excerpt( $message );

		if ( empty( $excerpt ) ) {
			return new WP_Error( 'sending', esc_html__( 'Excerpt and poster are both empty', 'social-planner' ) );
		}

		$body['message'] = $excerpt;

		$body['caption'] = $excerpt;
		
		if ( ! empty( $message['poster'] ) ) {
			$body['image_url'] = $message['poster'];
		}
		$body['image_url'] = get_the_post_thumbnail_url($message["poster_id"]);
		
		// Set final URL.
		$url = $path . '/media';

		/**
		 * Filter request body arguments using message data.
		 *
		 * @param string $body    Request body arguments.
		 * @param array  $message Message data.
		 * @param string $network Network name.
		 * @param string $url     Remote API URL.
		 *
		 * @since 1.1.12
		 * @version 1.3.0
		 */
		$body = apply_filters( 'social_planner_filter_request_body', $body, $message, self::NETWORK_NAME, $url );
		return;
		return self::send_request( $url, $body );
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
