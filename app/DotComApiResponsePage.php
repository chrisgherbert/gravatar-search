<?php

namespace App;

class DotComApiResponsePage extends ApiResponsePage {

	public static function get_total_pages($endpoint, $per_page = 100){

		$curl = static::get_curl();

		$curl->get($endpoint, [
			'per_page' => 1
		]);

		if ($curl->error){
			error_log('Error: ' . $curl->errorCode . ': ' . $curl->errorMessage);
			return false;
		}
		else {
			// .com endpoints only returns the total number of items found, 
			// not the number of pages, so we need to do some math.
			$total_found = $curl->response->found ?? false;
			return ceil($total_found / $per_page);
		}

	}

}