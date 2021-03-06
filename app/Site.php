<?php

namespace App;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;

class Site {

	public $url;

	public $emails = [];
	public $wait_between_requests = 0.2; // time in seconds to wait before next request
	public $comments_endpoint;
	public $users_endpoint;
	public $newest_first = true;
	public $skip_invalid_hash = true;

	protected $api_response_page_class = 'App\ApiResponsePage';
	protected $url_class = 'App\Url';

	public function __construct($url){

		// Basic info
		$this->url = $url;

		// Endpoints
		$endpoints = $this->get_endpoints();

		if (isset($endpoints['comments_endpoint'])){
			$this->comments_endpoint = $endpoints['comments_endpoint'];
		}

		if (isset($endpoints['users_endpoint'])){
			$this->users_endpoint = $endpoints['users_endpoint'];
		}

	}

	public function add_email($email){

		$this->emails[] = [
			'email' => $email,
			'hash' => md5($email)
		];

	}

	public function add_emails(array $emails){

		foreach ($emails as $email){
			$this->add_email($email);
		}

	}

	////////////////////
	// Setters/Config //
	////////////////////

	public function set_wait_time($wait_time){
		$this->wait_between_requests = $wait_time;
	}

	public function set_newest_first($newest_first){
		if ($newest_first){
			$this->newest_first = true;
		}
	}

	/////////////
	// Getters //
	/////////////

	public function get_endpoints(){

		$endpoints_obj = new $this->url_class($this->url);

		return [
			'comments_endpoint' => $endpoints_obj->get_comments_endpoint(),
			'users_endpoint' => $endpoints_obj->get_users_endpoint()
		];

	}

	public function get_matching_comments(){

		// Get total number of pages
		$total_pages = $this->api_response_page_class::get_total_pages($this->comments_endpoint);

		$matching_comments = [];

		// Start from the earliest comments
		$pages = range($total_pages, 1);

		foreach ($pages as $page_num){

			echo "Processing page $page_num" . "\r\n";

			$api_page = new $this->api_response_page_class($this->comments_endpoint, $page_num);

			$data = $api_page->get_data();

			$matches = $this->check_comments_for_email($data);

			$matching_comments += $matches;

		}

	}

	public function save_all_users_to_csv($filename = false){

		// If filename isn't manually set, create one from the hostname and the date
		if (!$filename){
			$url_parts = parse_url($this->url);
			$hostname = $url_parts['host'] ?? '';
			$filename = $hostname . '-' . date('Y-m-d', strtotime('now')) . '.csv';
		}

		// Get total number of pages
		$total_pages = $this->api_response_page_class::get_total_pages($this->users_endpoint);

		// Start from the earliest users
		$pages = range($total_pages, 1);

		// Create CSV file

		$csv = Writer::createFromString('');

		$header = ['name', 'link', 'id', 'description', 'hash', 'email'];

		$csv->insertOne($header);

		foreach ($pages as $page_num){

			echo "Processing page $page_num" . "\r\n";

			// Get comments data
			$api_page = new $this->api_response_page_class($this->users_endpoint, $page_num);
			$data = $api_page->get_data();

			// No data? Not sure why this would ever happen, but it seems to
			if (!$data || !is_array($data)){
				echo "No data for page" . "\r\n";
				continue;
			}

			// Add users to CSV
			foreach ($data as $user){

				$gravatar_url = $user->avatar_urls->{24} ?? '';
				$email_hash = $this->extract_hash_from_gravatar($gravatar_url) ?? '';

				$email = $this->get_email_from_hash($email_hash);

				try {

					$name = html_entity_decode($user->name, ENT_COMPAT, 'UTF-8') ?? '';
					$link = $user->link;
					$id = $user->id;
					$description = $user->description ?? '';

					$csv->insertOne([
						$name,
						$link,
						$id,
						$description,
						$email_hash,
						$email ?? ''
					]);

				} catch (\Exception $e) {
					error_log($e->getMessage());
					echo '.';
				}

			}

		}

		file_put_contents($filename, $csv->getContent());

	}

	public function save_all_users(){

		// Get total number of pages
		$total_pages = $this->api_response_page_class::get_total_pages($this->users_endpoint);

		// Start from the earliest comments
		$pages = range($total_pages, 1);

		foreach ($pages as $page_num){

			echo "Processing page $page_num" . "\r\n";

			// Get comments data
			$api_page = new $this->api_response_page_class($this->users_endpoint, $page_num);
			$data = $api_page->get_data();

			// No data? Not sure why this would ever happen, but it seems to
			if (!$data || !is_array($data)){
				echo "No data for page" . "\r\n";
				continue;
			}

			// Save to DB
			foreach ($data as $user){

				$gravatar_url = $user->avatar_urls->{24} ?? '';
				$email_hash = $this->extract_hash_from_gravatar($gravatar_url) ?? '';

				$email = $this->get_email_from_hash($email_hash);

				// If you can't figure out the email, stop
				if (!$email){
					continue;
				}

				try {

					$name = html_entity_decode($user->name, ENT_COMPAT, 'UTF-8') ?? '';
					$domain = str_replace(['http://', 'http://www.', 'https://', 'https://www.'], '', $this->url);
					$description = $name . ' (' . $domain . ')';

					if ($user->description){
						$description = $description . ' - ' . $user->description;
					}

					DB::table('emails')->insert([
						'email' => $email,
						'hash' => $email_hash,
						'description' => $description,
						'type' => "Imported from $domain"
					]);

				} catch (\Exception $e) {
					error_log($e->getMessage());
					echo '.';
				}

			}

		}

	}

	protected function get_email_from_hash($hash){

		$hash_db = \DB::connection('hashes');

		$email = $hash_db->table('emails')->where('hash', $hash)->pluck('emails');

		return $email[0] ?? false;

	}

	public function save_all_comments(){

		// Get total number of pages
		$total_pages = $this->api_response_page_class::get_total_pages($this->comments_endpoint);

		// Determine order - oldest first is default

		if (!$total_pages){
			echo "Sorry, no comments found" . "\r\n";
			return false;
		}

		if ($this->newest_first){
			$pages = range(1, $total_pages);
		}
		else {
			$pages = range($total_pages, 1);
		}

		foreach ($pages as $page_num){

			echo "Processing page $page_num ({$this->url})" . "\r\n";

			// Get comments data
			$api_page = new $this->api_response_page_class($this->comments_endpoint, $page_num);
			$data = $api_page->get_data();

			// Save comments in page
			$this->save_comments_data_page($data);

		}

	}
	///////////////
	// Protected //
	///////////////

	public static function clean_json_data($data){

		// $data = trim($data);
		// $data = rtrim($data, "\0");
		// $data = stripslashes(html_entity_decode($data));
		// $data = iconv('UTF-8', 'UTF-8//IGNORE', utf8_encode($data));

		$data = json_decode(html_entity_decode(urldecode(filter_input(INPUT_GET, 'ft', FILTER_SANITIZE_STRING))));

		return json_encode($data);

	}

	protected function save_comments_data_page($data){

		// No data? Not sure why this would ever happen, but it seems to
		if (!$data){
			echo "No data for page" . "\r\n";
			return false;
		}

		// Sometimes data isn't properly converted from JSON
		if (!is_array($data)){
			$data = self::clean_json_data($data);
			if (!is_array($data)){
				echo "JSON encoding issue" . "\r\n";
				return false;
			}
		}

		// Save to DB
		foreach ($data as $comment){

			try {

				$gravatar_url = $comment->author_avatar_urls->{24} ?? '';
				$email_hash = $this->extract_hash_from_gravatar($gravatar_url) ?? '';
				$content = html_entity_decode($comment->content->rendered, ENT_COMPAT, 'UTF-8') ?? '';

				// If skipping invalid hashes, don't save to db unless there's a valid hash for the email
				if ($this->skip_invalid_hash){
					if (strlen($email_hash) !== 32){
						continue;
					}
				}

				DB::table('comments')->insert([
					'comments_endpoint' => $this->comments_endpoint,
					'comment_id' => $comment->id ?? '',
					'post_id' => $comment->post ?? '',
					'date_gmt' => $comment->date_gmt ?? '',
					'content' => $content,
					'link' => $comment->link ?? '',
					'author_name' => $comment->author_name ?? '',
					'author_url' => $comment->author_url ?? '',
					'gravatar' => $gravatar_url,
					'email_hash' => $email_hash
				]);

			} catch (\Exception $e) {
				error_log($e->getMessage());
				echo '.';
			}

		}

	}

	protected function extract_hash_from_gravatar($gravatar_url){

		if (!$gravatar_url){
			return false;
		}

		$parts = parse_url($gravatar_url);

		$path = trim($parts['path'], '/');

		$path_parts = explode('/', $path);

		if (isset($path_parts[1])){
			return $path_parts[1];
		}

	}

	protected function check_comments_for_email($comments){

		$matches = [];

		foreach ($comments as $comment){

			foreach ($this->emails as $email){

				$avatar_url = $comment->author_avatar_urls->{24} ?? '';

				if (strpos($avatar_url, $email['hash']) !== false){

					echo $this->stringify_comment($comment, $email['email']) . "\r\n";

					$matches[] = $comment;

				}

			}

		}

		return $matches;

	}

	protected function stringify_comment($comment, $email){

		$id = $comment->id ?? '';
		$post = $comment->post ?? '';
		$date = $comment->date ?? '';
		$content = strip_tags(html_entity_decode($comment->content->rendered, ENT_COMPAT, 'UTF-8') ?? '');

		return "Email: $email, ID: $id, Post: $post, Date: $date, Content: $content";

	}

}