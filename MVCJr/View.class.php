<?php
namespace FlipWays\MVCJr;

abstract class View {
	protected $controller;
	protected $models;
	protected $rootDir;

	public function __construct($controller) {
		$this->controller = $controller;
		$this->models = $controller->models;
		$this->rootDir = $controller->application->rootDir;
	}
	
	// General getter magic method
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
	}
	
	// Child classes add output code here
	abstract public function render();
}

