<?php
// v1.3 - XML parsing, namespaces, better exception messages
namespace FlipWays;
class DOMplate extends \DOMDocument {
	public $xpath;
	
	public function __construct($domOrPath, $isXML=false) {
		parent::__construct();
		if ($domOrPath instanceof DOMNode) {
			$imported = $this->importNode($domOrPath, true);
			$this->appendChild($imported);
		} elseif (is_string($domOrPath)) {
			if ($isXML) {
				$this->load($domOrPath);
			} else {
				libxml_use_internal_errors(true);
				$this->loadHTMLFile($domOrPath);
				libxml_clear_errors();
				libxml_use_internal_errors(false);
			}
		}
		// XPath has to be added after main content	
		$this->xpath = new \DOMXPath($this);
	}
	
	public function fetch($queryOrNode) {
		if (is_array($queryOrNode)) {
			$result = $queryOrNode;
		} elseif ($queryOrNode instanceof \DOMNode) {
			$result = array($queryOrNode);
		} else {
			if ($queryOrNode instanceof \DOMNodeList) {
				$list = $queryOrNode;
			} else {
				$list = $this->xpath->query($queryOrNode);
				if ($list === false) {
					throw new \Exception('XPath expression invalid: ' . $queryOrNode);
				}
			}
			$result = array();
			foreach ($list as $node) {
				$result[] = $node;
			}
		}
		return $result;
	}
	public function fetchElement($queryOrNode) {
		if ($queryOrNode instanceof \DOMNode) {
			$element = $queryOrNode;
		} else { 
			$queryResult = $this->xpath->query($queryOrNode);
			if ($queryResult === false) {
				throw new \Exception('XPath expression invalid: ' . $queryOrNode);
			}
			$element = $queryResult->item(0);
		}
		if ($element === null) {
			throw new \Exception('Element not found in DOM');
		}
		return $element;
	}
	
	protected function import($domOrPath, $queryOrNode) {
		$subDom = new DOMplate($domOrPath);
		$subElement = $subDom->fetch($queryOrNode)[0];
		$copiedNode = $this->importNode($subElement, true);
		return $copiedNode;
	}
	public function replace($queryOrNode, $domOrPath, $optSubQuery=null) {
		$mainElement = $this->fetch($queryOrNode)[0];
		$mainElement->removeAttribute('id');
		$copiedNode = $this->import($domOrPath, ($optSubQuery ? $optSubQuery : $queryOrNode));
		$mainElement->parentNode->replaceChild($copiedNode, $mainElement);
	}
	public function append($queryOrNode, $domOrPath, $optSubQuery=null) {
		$mainElement = $this->fetch($queryOrNode)[0];
		$copiedNode = $this->import($domOrPath, ($optSubQuery ? $optSubQuery : $queryOrNode));
		$mainElement->parentNode->appendChild($copiedNode);
	}
	public function insert($queryOrNode, $domOrPath, $optSubQuery=null) {
		$mainElement = $this->fetch($queryOrNode)[0];
		$copiedNode = $this->import($domOrPath, ($optSubQuery ? $optSubQuery : $queryOrNode));
		$mainElement->appendChild($copiedNode);
	}
	
	public function insertLocal($queryOrNode, $newQueryOrNode) {
		$mainElement = $this->fetch($queryOrNode)[0];
		$newElement = $this->fetch($newQueryOrNode)[0];
		$mainElement->appendChild($newElement);
	}
	
	public function repeat($queryOrNode, $times, $seperator=false) {
		$mainElement = $this->fetch($queryOrNode)[0];
		$parentElement = $mainElement->parentNode;
		
		if (is_string($seperator)) {
			$seperator = $this->createTextNode($seperator);
		}
		
		$this->removeChildren($parentElement);
		
		for ($i = 0; $i < $times; $i++) {
			$copiedNode = $mainElement->cloneNode(true);
			$parentElement->appendChild($copiedNode);
			if ($seperator !== false) {
				$copiedSeperator = $seperator->cloneNode(true);
				$parentElement->appendChild($copiedSeperator);
			}
		}
	}
	public function remove($queryOrNode) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $mainElement) {
			$mainElement->parentNode->removeChild($mainElement);
		}
	}
	public function removeChildren($queryOrNode) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $mainElement) {
			while (isset($mainElement->firstChild)) { 
				$mainElement->removeChild($mainElement->firstChild); 
			}
		}
	}
	
	public function singleSibling($queryOrNode, $index, $returnParent=false) {
		$queryResult = $this->xpath->query($queryOrNode);
		$pickedChild = $queryResult->item($index);
		if ($returnParent) {
			$mainElement = $this->fetch($queryOrNode)[0]->parentNode->cloneNode();
			$mainElement->appendChild($pickedChild);
			return $mainElement;
		}
		return $pickedChild;
	}
	public function randomSibling($queryOrNode, $returnParent=false) {
		$queryResult = $this->xpath->query($queryOrNode);
		$length = $queryResult->length;
		$rando = mt_rand(0, $length - 1);
		return $this->singleSibling($queryOrNode, $rando, $returnParent);
	}
	
	public function setText($queryOrNode, $newText) {
		$collection = $this->fetch($queryOrNode);
		$this->removeChildren($collection);
		foreach ($collection as $element) {
			$textNode = $this->createTextNode($newText);
			$element->appendChild($textNode);
		}
	}
	public function setAttr($queryOrNode, $attr, $newValue) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $element) {
			$element->setAttribute($attr, $newValue);
		}
	}
	public function removeAttr($queryOrNode, $attr) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $element) {
			$element->removeAttribute($attr);
		}
	}
	public function formatAttr($queryOrNode, $attr, $fmtArgs) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $element) {
			$attrVal = $element->getAttribute($attr);
			$attrVal = vsprintf($attrVal, $fmtArgs);
			$element->setAttribute($attr, $attrVal);
		}
	}
	
	function addClass($queryOrNode, $singleClass) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $element) {
			if ($element->hasAttribute('class')) {
				$classes = $element->getAttribute('class');
				if (!preg_match("/(^| ){$singleClass}($| )/", $classes)) {
					$classes .= ' ' . $singleClass;
				}
			} else {
				$classes = $singleClass;
			}
			$element->setAttribute('class', $classes);
		}
	}
	function removeClass($queryOrNode, $singleClass) {
		$collection = $this->fetch($queryOrNode);
		foreach ($collection as $element) {$
			$classes = preg_replace("/(^| ){$singleClass}($| )/", " ", $element->getAttribute('class'));
			if ($classes === ' ') {
				$classes = '';
			}
			$element->setAttribute('class', $classes);
		}
	}
}
