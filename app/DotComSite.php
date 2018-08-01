<?php

namespace App;

use Illuminate\Support\Facades\DB;

class DotComSite extends Site {

	protected $api_response_page_class = 'App\DotComApiResponsePage';
	protected $url_class = 'App\DotComUrl';

	protected function save_comments_data_page($data){

		// No data? Not sure why this would ever happen, but it seems to
		if (!isset($data->comments) || !$data->comments || !is_array($data->comments)){
			echo "No data for page" . "\r\n";
			return false;
		}

		// Save to DB
		foreach ($data->comments as $comment){

			try {

				$gravatar_url = $comment->author->avatar_URL ?? '';
				$email_hash = $this->extract_hash_from_gravatar($gravatar_url) ?? '';

				DB::table('comments')->insert([
					'comments_endpoint' => $this->comments_endpoint,
					'comment_id' => $comment->ID ?? '',
					'post_id' => $comment->post->ID ?? '',
					'date_gmt' => $comment->date ?? '',
					'content' => $comment->raw_content ?? '',
					'link' => $comment->URL ?? '',
					'author_name' => $comment->author->name ?? '',
					'author_url' => $comment->author->URL ?? '',
					'gravatar' => $gravatar_url,
					'email_hash' => $email_hash
				]);

			} catch (\Exception $e) {
				error_log($e->getMessage());
				echo '.';
			}

		}

	}

}