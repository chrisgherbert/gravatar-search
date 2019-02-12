<?php

namespace App;

class DotComUrl extends Url {

	public function get_comments_endpoint(){

		// Get bare host - this usually (always?) is the "site" variable in the wordpress.com endpoints
		$parse = parse_url($this->clean_url);
		$host = $parse['host'];

		// Remove `.files.` - this is where .com sites stores their files, rather than the usual /uploads directory


		return "https://public-api.wordpress.com/rest/v1.1/sites/$host/comments";

	}

	/**
	 * Remove '.files.' from site urls
	 * @return string  Site url
	 */
	protected function clean_url($url){

		$url = parent::clean_url($url);

		return str_replace('.files.', '.', $url);

	}

}