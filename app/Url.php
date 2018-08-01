<?php

namespace App;

class Url {

	public $raw_url;
	public $clean_url;
	public $api_url;

	public function __construct($raw_url){
		$this->raw_url = $raw_url;
		$this->clean_url = $this->clean_url($this->raw_url);
		$this->api_url = $this->get_api_index($this->clean_url);
	}

	public function get_dot_com_comments_endpoint(){

		// Get bare host - this usually (always?) is the "site" variable in the wordpress.com endpoints
		$parse = parse_url($url);
		$host = $parse['host'];

		return "https://public-api.wordpress.com/rest/v1.1/sites/$host/comments";

	}

	public function get_comments_endpoint(){

		// Try to use the "official" discovery tool
		if (filter_var($this->api_url, FILTER_VALIDATE_URL)){
			$endpoint = $this->api_url . 'wp/v2/comments';
		}
		// Otherwise just build it manually using the current URL as the site URL
		else {
			$endpoint = $this->clean_url . '/wp-json/wp/v2/comments';
		}

		return $endpoint;

	}

	public function get_users_endpoint(){

		// Try to use the "official" discovery tool
		if (filter_var($this->api_url, FILTER_VALIDATE_URL)){
			$endpoint = $this->api_url . 'wp/v2/users';
		}
		// Otherwise just build it manually using the current URL as the site URL
		else {
			$endpoint = $this->clean_url . '/wp-json/wp/v2/users';
		}

		return $endpoint;

	}

	/**
	 * Get the API index URL (typically something like "http://example.com/wp-json/")
	 * @param  string $url Provided URL
	 * @return string      API index URL
	 */
	public function get_api_index($url){

		$headers = get_headers($url);

		if ($headers){

			foreach ($headers as $header){

				if (strpos($header, 'rel="https://api.w.org/"') !== false){

					echo $header . "\r\n";

					return $this->extract_api_url_from_header($header);

				}

			}

		}

	}

	///////////////
	// Protected //
	///////////////

	/**
	 * Get a "clean" URL with just the scheme, domain and path (no query, hash, etc.)
	 * @param  string $url URL to be cleaned up
	 * @return string      Clean URL
	 */
	protected function clean_url($url){

		// Add http/https if missing
		if (strpos($url, 'http://') === false && strpos($url, 'https://') === false){
			$url = 'http://' . $url;
		}

		$url = trim($url);
		$parts = parse_url($url);

		$cleaned = $parts['scheme'] . '://' . $parts['host'];

		if (isset($parts['path'])){
			$cleaned = $cleaned . $parts['path'];
			$cleaned = rtrim( $cleaned, '/\\' );
		}

		return $cleaned;

	}

	/**
	 * Hacky-looking way to extract the URL from the proper link header. I'll 
	 * do anything to avoid regex
	 */
	protected function extract_api_url_from_header($header){
		return str_replace(['Link: <', '>; rel="https://api.w.org/"'], '', $header);
	}

}