<?php
namespace DriveVault\CF7\Google;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight Native HTTP Client for Google APIs without external SDKs.
 */
class HttpClient {

	const MAX_RETRIES  = 2;
	const BACKOFF_MS   = 500;
	const DEFAULT_TIMEOUT = 30;

	/**
	 * Access Token.
	 */
	protected string $access_token = '';

	/**
	 * Set bearer access token for subsequent requests.
	 *
	 * @param string $token
	 */
	public function set_access_token( string $token ): void {
		$this->access_token = $token;
	}

	/**
	 * Get currently set access token.
	 *
	 * @return string
	 */
	public function get_access_token(): string {
		return $this->access_token;
	}

	/**
	 * Perform GET request.
	 *
	 * @param string $url
	 * @param array  $params Query params.
	 * @param array  $headers Additional headers.
	 * @return array|\WP_Error
	 */
	public function get( string $url, array $params = array(), array $headers = array() ): array|\WP_Error {
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		return $this->request( 'GET', $url, null, $headers );
	}

	/**
	 * Perform POST request.
	 *
	 * @param string $url
	 * @param mixed  $body Body payload (array or string).
	 * @param array  $headers Additional headers.
	 * @return array|\WP_Error
	 */
	public function post( string $url, mixed $body = null, array $headers = array() ): array|\WP_Error {
		return $this->request( 'POST', $url, $body, $headers );
	}

	/**
	 * Perform PUT request.
	 *
	 * @param string $url
	 * @param mixed  $body
	 * @param array  $headers
	 * @return array|\WP_Error
	 */
	public function put( string $url, mixed $body = null, array $headers = array() ): array|\WP_Error {
		return $this->request( 'PUT', $url, $body, $headers );
	}

	/**
	 * Perform DELETE request.
	 *
	 * @param string $url
	 * @param array  $headers
	 * @return array|\WP_Error
	 */
	public function delete( string $url, array $headers = array() ): array|\WP_Error {
		return $this->request( 'DELETE', $url, null, $headers );
	}

	/**
	 * Core request execution with exponential backoff retry.
	 *
	 * @param string $method
	 * @param string $url
	 * @param mixed  $body
	 * @param array  $extra_headers
	 * @return array|\WP_Error Response with 'status', 'headers', 'body'.
	 */
	public function request( string $method, string $url, mixed $body = null, array $extra_headers = array() ): array|\WP_Error {
		$headers = array(
			'Accept' => 'application/json',
		);

		if ( ! empty( $this->access_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->access_token;
		}

		if ( is_array( $body ) ) {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$encoded_body            = wp_json_encode( $body );
		} else {
			$encoded_body = $body;
		}

		$headers = array_merge( $headers, $extra_headers );

		$args = array(
			'method'      => $method,
			'headers'     => $headers,
			'body'        => $encoded_body,
			'timeout'     => self::DEFAULT_TIMEOUT,
			'httpversion' => '1.1',
		);

		$attempts = 0;
		$response = null;

		while ( $attempts <= self::MAX_RETRIES ) {
			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$attempts++;
				if ( $attempts > self::MAX_RETRIES ) {
					return $response;
				}
				usleep( (int) ( self::BACKOFF_MS * pow( 2, $attempts - 1 ) * 1000 ) );
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );

			// Retry on rate limit (429) or transient server errors (500, 502, 503).
			if ( in_array( $code, array( 429, 500, 502, 503 ), true ) && $attempts < self::MAX_RETRIES ) {
				$attempts++;
				usleep( (int) ( self::BACKOFF_MS * pow( 2, $attempts - 1 ) * 1000 ) );
				continue;
			}

			break;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$parsed = json_decode( $raw, true );

		return array(
			'status'  => $status,
			'headers' => wp_remote_retrieve_headers( $response ),
			'body'    => null !== $parsed ? $parsed : $raw,
			'raw'     => $raw,
		);
	}
}
