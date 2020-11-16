<?php

class SimpleJsonRequest
{
	private $redis;
	const TIME_EXPIRATION = 3600;
	const HOSTNAME = "127.0.0.1";
	const PORT = 637;

	/**
	 * TODO: we need to figure out how we will create the keys to save
	 * each data in their respective space
	 * i'm thinking to hash the contents of the data variables
	 * but that's too uncertain because we don't know what type of data we're using
	 * we only know what kind of operation is going to happen
	 *
	 * If we use the url that can be tricky because we can have different forms of URL for the
	 * same type of data - that's what I think, I'm not 100% sure
	 */

	function __construct()
	{
		$this->redis = new Redis();
		// let's keep the connection parameters simple for now
		$this->redis->connect($this->HOSTNAME, $this->PORT);
	}

	private static function makeRequest(string $method, string $url, array $parameters = null, array $data = null)
	{
		$opts = [
			'http' => [
				'method'  => $method,
				'header'  => 'Content-type: application/json',
				'content' => $data ? json_encode($data) : null
			]
		];

		$url .= ($parameters ? '?' . http_build_query($parameters) : '');
		return file_get_contents($url, false, stream_context_create($opts));
	}


	public static function get(string $url, array $parameters = null)
	{
		// check first in the redis server
		// if there's the last used dataA
		// if redis has it, return it
		// else, query the database for it
		// update the redis server
		// and return the result
		if (self::keyExists($url)) {
			$val = self::getValueFromKey($url);
		} else {
			$val = json_decoce(self::makeRequest('GET', $url, $parameters));

			// updates the value in the redis server
			global $redis;
			$redis->setex($url, self::TIME_EXPIRATION, $val);
		}

		return $val;
		//return json_decode(self::makeRequest('GET', $url, $parameters));
	}

	public static function post(string $url, array $parameters = null, array $data)
	{
		// updates the data values in the redis server
		global $redis;
		$redis->setex($url, self::TIME_EXPIRATION, $data);

		// and update the database
		return json_decode(self::makeRequest('POST', $url, $parameters, $data));
	}

	public static function put(string $url, array $parameters = null, array $data)
	{
		// PUT maybe can follow an analogous idea to post

		// updates the data values in the redis server
		global $redis;
		$redis->setex($url, 3600, $data);

		return json_decode(self::makeRequest('PUT', $url, $parameters, $data));
	}

	public static function patch(string $url, array $parameters = null, array $data)
	{
		return json_decode(self::makeRequest('PATCH', $url, $parameters, $data));
	}

	public static function delete(string $url, array $parameters = null, array $data = null)
	{
		// keep a copy of the deleted file in the redis server
		// it will be susbtituted after a while without being used - as a backup maybe
		// or new data will update that data later
		// the above can be an interesting idea
		if (self::keyExists($url)) {
			global $redis;
			$redis->del($url);
		}

		return json_decode(self::makeRequest('DELETE', $url, $parameters, $data));
	}


	private static function getValueFromKey($key)
	{
		try {
			global $redis;
			$redis->get($key);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}

	private static function keyExists($key)
	{

		try {
			global $redis;
			return $redis->exists($key);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
}
