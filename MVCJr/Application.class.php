<?php
namespace FlipWays\MVCJr;

class Application {
	protected $url;
	protected $route;
	protected $defaultUrl = '/';
	protected $rootDir = '/';
	protected $scheme;
	
	public function __construct() {
		date_default_timezone_set('America/New_York');
		$this->url = $_GET['url'] !== "" ? $_GET['url'] : $this->defaultUrl;
		$this->route = explode('/', $this->url);
		$this->scheme = $_SERVER['HTTPS'] ? 'https://' : 'http://';
	}
	
	// General getter magic method
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
	}
}
