<?php
namespace FlipWays\MVCJr;

abstract class Controller {
	protected $application;
	protected $view;
	protected $models;

	public function __construct($application) {
		$this->application = $application;
	}
	public function execute() {
		echo $this->view->render();
	}
	
	// General getter magic method
	public function __get($property) {
		if (property_exists($this, $property)) {
			return $this->$property;
		}
	}
}

