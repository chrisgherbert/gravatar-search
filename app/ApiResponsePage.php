<?php

namespace App;

use Illuminate\Support\Facades\Cache;
use \Curl\Curl;

class ApiResponsePage {

	const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/11.1.1 Safari/605.1.15';

	protected $endpoint;
	protected $page;
	protected $per_page;

	public function __construct($endpoint, $page, $per_page = 100){
		$this->endpoint = $endpoint;
		$this->page = $page;
		$this->per_page = $per_page;
	}

	public function get_data(){

		if ($data = Cache::get($this->get_cache_key())){
			return $data;
		}

		$curl = new Curl;

		// Set user agent
		$curl->setUserAgent(static::USER_AGENT);

		// Follow server redirects
		$curl->setOpt(CURLOPT_FOLLOWLOCATION, true);

		$curl->get($this->endpoint, [
			'page' => $this->page,
			'per_page' => $this->per_page
		]);

		if ($curl->error){
			return false;
		}
		else {
			$data = $curl->response;
			Cache::put($this->get_cache_key(), $data, 10080); // Cache for one week
			return $data;
		}

	}

	///////////////
	// Protected //
	///////////////

	protected function get_cache_key(){
		return "api_response_{$this->endpoint}_{$this->page}_{$this->per_page}";
	}

	////////////
	// Static //
	////////////

	public static function get_total_pages($endpoint, $per_page = 100){

		$curl = static::get_curl();

		// Need to tell the server that we want these non-standard header values
		$curl->setHeader("Access-Control-Expose-Headers", "X-WP-Total, X-WP-TotalPages");

		$curl->get($endpoint, [
			'per_page' => $per_page
		]);

		if ($curl->error){
			error_log('Error: ' . $curl->errorCode . ': ' . $curl->errorMessage);
			return false;
		}
		else {

			$headers = $curl->responseHeaders;

			return $headers['x-wp-totalpages'];

		}

	}

	protected static function get_curl(){

		$curl = new Curl;

		// Set user agent
		$curl->setUserAgent(static::USER_AGENT);

		// Follow server redirects
		$curl->setOpt(CURLOPT_FOLLOWLOCATION, true);

		return $curl;

	}

}