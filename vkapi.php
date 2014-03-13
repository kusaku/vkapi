<?php
namespace VK;

/**
 * VKAPI class for vk.com social network
 *
 * @package server API methods
 * @link    http://vk.com/dev
 * @autor   Oleg Illarionov, Cyril Arkhipenko
 * @version 1.0
 */
class VKAPI {

	private $api_secret;
	private $app_id;
	private $api_url;
	private $api_version;

	public function __construct($cfg) {
		// set default
		$cfg += array(
			'api_url'     => 'api.vk.com/method/',
			'api_version' => '5.14',
		);

		$this->app_id     = $cfg['app_id'];
		$this->api_secret = $cfg['api_secret'];
		if (!strstr($cfg['api_url'], 'http://')) {
			$cfg['api_url'] = 'https://' . $cfg['api_url'];
		}
		$this->api_url     = $cfg['api_url'];
		$this->api_version = $cfg['api_version'];
	}

	private function get_access_token($set_access_token = null) {
		static $access_token;
		if ($set_access_token) {
			$access_token = $set_access_token;
		}
		if (!$access_token) {
			$params['client_id']     = $this->app_id;
			$params['client_secret'] = $this->api_secret;
			$params['v']             = $this->api_version;
			$params['grant_type']    = 'client_credentials';

			$query = 'https://oauth.vk.com/access_token' . '?' . http_build_query($params);

			$context = stream_context_create(
				array(
					'http' =>
						array(
							'timeout'       => 5,
							'ignore_errors' => true
						)
				));

			$response = json_decode(file_get_contents($query, false, $context), true);

			if (isset($response['error'])) {
				throw new \Exception("{$response['error_description']} ({$response['error']})");
			}

			$access_token = $response['access_token'];
		}

		return $access_token;
	}

	public function set_access_token($access_token) {
		return $this->get_access_token($access_token);
	}

	public function exec($method, $params = array(), $secure = false) {
		$params['app_id']    = $this->app_id;
		$params['v']         = $this->api_version;
		$params['timestamp'] = time();
		$params['format']    = 'json';
		$params['random']    = rand(0, 10000);

		if ($secure) {
			$params['client_secret'] = $this->api_secret;
			$params['access_token']  = $this->get_access_token();
		}

		$params['sig'] = $this->sign($params);

		$query = $this->api_url . $method . '?' . http_build_query($params);

		$context = stream_context_create(
			array(
				'http' =>
					array(
						'timeout'       => 5,
						'ignore_errors' => true
					)
			));

		$response = json_decode(file_get_contents($query, false, $context), true);

		if (isset($response['error'])) {
			throw new \Exception("{$response['error']['error_msg']}", $response['error']['error_code']);
		}

		return $response;
	}

	public function sign($params) {
		ksort($params);
		$sig = '';
		foreach ($params as $k => $v) {
			$sig .= $k . '=' . $v;
		}
		$sig .= $this->api_secret;

		return md5($sig);
	}

	public function validate($params) {
		if (isset($params['sig'])) {
			$sig = $params['sig'];
			unset($params['sig']);

			return $sig == $this->sign($params);
		}

		return false;
	}

	public function response($data) {
		return json_encode(
			array(
				'response' => $data
			));
	}

	public function error($error_code, $error_msg, $critical = false) {
		return json_encode(
			array(
				'error' => array(
					'error_code' => $error_code,
					'error_msg'  => $error_msg,
					'critical'   => $critical
				)
			));
	}

	public function upload($url, $files) {
		$boundary = uniqid('!', true);
		$header   = "Content-Type: multipart/form-data; boundary={$boundary}";
		$content  = "--{$boundary}";

		foreach ($files as $name => $path) {
			$file_contents = file_get_contents($path);
			$file_name     = basename($path);
			$mime_type     = mime_content_type($path);
			$content .= "\n";
			$content .= "Content-Disposition: file; name=\"{$name}\"; filename=\"{$file_name}\"\n";
			$content .= "Content-Type: {$mime_type}\n";
			$content .= "Content-Transfer-Encoding: binary\n\n";
			$content .= $file_contents;
			$content .= "\n";
			$content .= "--{$boundary}";
		}

		$content .= "--\n";

		$context = stream_context_create(
			array(
				'http' => array(
					'method'  => 'POST',
					'header'  => $header,
					'content' => $content,
				)
			));

		$response = json_decode(file_get_contents($url, false, $context), true);

		if (isset($response['error'])) {
			throw new \Exception("{$response['error']['error_msg']}", $response['error']['error_code']);
		}

		return $response;
	}
}
