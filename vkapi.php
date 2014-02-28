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

	var $api_secret;
	var $app_id;
	var $api_url;

	function __construct($app_id, $api_secret, $api_url = 'api.vk.com/method/') {
		$this->app_id     = $app_id;
		$this->api_secret = $api_secret;
		if (!strstr($api_url, 'http://')) {
			$api_url = 'http://' . $api_url;
		}
		$this->api_url = $api_url;
	}

	function exec($method, $params = false) {
		if (!$params) {
			$params = array();
		}
		$params['api_id']    = $this->app_id;
		$params['v']         = '5.11';
		$params['timestamp'] = time();
		$params['format']    = 'json';
		$params['random']    = rand(0, 10000);

		ksort($params);

		$sig = '';
		foreach ($params as $k => $v) {
			$sig .= $k . '=' . $v;
		}
		$sig .= $this->api_secret;
		$params['sig'] = md5($sig);

		$query = $this->api_url . $method . '?' . http_build_query($params);
		$res   = file_get_contents($query);

		return json_decode($res, true);
	}
}
