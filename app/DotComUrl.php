<?php

namespace App;

class DotComUrl extends Url {

	public function get_comments_endpoint(){

		// Get bare host - this usually (always?) is the "site" variable in the wordpress.com endpoints
		$parse = parse_url($this->clean_url);
		$host = $parse['host'];

		return "https://public-api.wordpress.com/rest/v1.1/sites/$host/comments";

	}

}