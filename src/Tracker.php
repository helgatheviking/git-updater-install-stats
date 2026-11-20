<?php
/**
 * Git Updater Install Stats
 *
 * @author    helgatheviking
 * @license   GPL-3.0-or-later
 * @link      https://github.com/helgatheviking/git-updater-install-stats
 * @package   git-updater-install-stats
 */

namespace HelgaTheViking\Git_Updater\Install_Stats;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Tracker
 *
 * Records successful `git-updater/v1/update-api` requests. Client sites are
 * identified by the URL WordPress puts in its default User-Agent
 * (`WordPress/6.8; https://example.com`), falling back to the IP address,
 * and only a salted hash of that identifier is stored.
 */
class Tracker {
	/**
	 * REST route to track.
	 *
	 * @var string
	 */
	private const ROUTE = '/git-updater/v1/update-api';

	/**
	 * Load relevant action/filter hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'rest_request_after_callbacks', [ $this, 'maybe_track' ], 10, 3 );
	}

	/**
	 * Record the request if it is a successful update-api response.
	 *
	 * @param mixed                $response Result from the endpoint callback.
	 * @param array<string, mixed> $handler  Route handler.
	 * @param WP_REST_Request      $request  Request.
	 *
	 * @return mixed Unchanged response.
	 */
	public function maybe_track( $response, $handler, $request ) {
		if ( self::ROUTE !== untrailingslashit( $request->get_route() ) ) {
			return $response;
		}

		$slug = $request->get_param( 'slug' );
		if ( ! is_string( $slug ) || '' === $slug || ! $this->is_success( $response ) ) {
			return $response;
		}

		$site_id = $this->get_site_id( $request );
		if ( '' !== $site_id ) {
			Table::record( sanitize_title_with_dashes( $slug ), hash_hmac( 'sha256', $site_id, wp_salt( 'auth' ) ) );
		}

		return $response;
	}

	/**
	 * Whether the endpoint returned real update data.
	 *
	 * @param mixed $response Result from the endpoint callback.
	 *
	 * @return bool
	 */
	private function is_success( $response ) {
		if ( $response instanceof WP_Error ) {
			return false;
		}
		if ( $response instanceof WP_REST_Response ) {
			if ( $response->is_error() ) {
				return false;
			}
			$response = $response->get_data();
		}

		// Git Updater reports failures as `[ 'error' => ... ]` with a 200 status.
		return is_array( $response ) && ! isset( $response['error'] );
	}

	/**
	 * Build a normalized site identifier from the request.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return string Empty if nothing usable was found.
	 */
	private function get_site_id( $request ) {
		$user_agent = (string) $request->get_header( 'user_agent' );
		if ( preg_match( '#^WordPress/[^;]*;\s*(https?://\S+)#i', $user_agent, $matches ) ) {
			$url = strtolower( untrailingslashit( preg_replace( '#^https?://(www\.)?#i', '', $matches[1] ) ) );
			if ( '' !== $url ) {
				return 'url:' . $url;
			}
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return '' !== $ip ? 'ip:' . $ip : '';
	}
}
