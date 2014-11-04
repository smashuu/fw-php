<?php
class DOMplate {
	public $dom;
	public $xpath;
	
	public function __construct($domOrPath) {
		if ($domOrPath instanceof DOMDocument) {
			$this->dom = $domOrPath;
		} else {
			$this->dom = new DOMDocument();
			$this->dom->loadHTMLFile($domOrPath);
			
			if (! $this->dom instanceof DOMDocument) {
				throw new Exception('Could not parse DOM');
			}
		}
		$this->xpath = new DOMXPath($this->dom);
	}
	
	public function fetchElement($queryOrNode) {
		if ($queryOrNode instanceof DOMNode) {
			$element = $queryOrNode;
		} else { 
			$queryResult = $this->xpath->query($queryOrNode);
			if ($queryResult === false) {
				throw new Exception('XPath expression invalid');
			}
			$element = $queryResult->item(0);
		}
		if ($element === null) {
			throw new Exception('Element not found in DOM');
		}
		return $element;
	}
	
	public function replace($queryOrNode, $domOrPath) {
		$mainElement = $this->fetchElement($queryOrNode);
		
		$subDom = new DOMplate($domOrPath);
		$subElement = $subDom->fetchElement($queryOrNode);

		$copiedNode = $this->dom->importNode($subElement, true);
		$mainElement->parentNode->replaceChild($copiedNode, $mainElement);
	}
	public function append($queryOrNode, $domOrPath) {
		$mainElement = $this->fetchElement($queryOrNode);
		
		$subDom = new DOMplate($domOrPath);
		$subElement = $subDom->fetchElement($queryOrNode);

		$copiedNode = $this->dom->importNode($subElement, true);
		$mainElement->parentNode->appendChild($copiedNode);
	}
	public function repeat($queryOrNode, $times, $seperator=false) {
		$mainElement = $this->fetchElement($queryOrNode);
		$parentElement = $mainElement->parentNode;
		
		if (is_string($seperator)) {
			$seperator = $this->dom->createTextNode($seperator);
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
		$mainElement = $this->fetchElement($queryOrNode);
		
		return $mainElement->parentNode->removeChild($mainElement);
	}
	public function removeChildren($queryOrNode) {
		$mainElement = $this->fetchElement($queryOrNode);
		
		while (isset($mainElement->firstChild)) { 
			$mainElement->removeChild($mainElement->firstChild); 
		} 
	}
	
	public function randomSibling($queryOrNode, $returnParent=false) {
		if ($returnParent) {
			$mainElement = $this->fetchElement($queryOrNode)->parentNode->cloneNode();
		}
		$queryResult = $this->xpath->query($queryOrNode);
		$length = $queryResult->length;
		$rando = mt_rand(1, $length);
		$pickedChild = $queryResult->item($rando - 1);
		if ($returnParent) {
			$mainElement->appendChild($pickedChild);
			return $mainElement;
		}
		return $pickedChild;
	}
	
	public function setText($queryOrNode, $newText) {
		$element = $this->fetchElement($queryOrNode);
		$this->removeChildren($element);
		$textNode = $this->dom->createTextNode($newText);
		$element->appendChild($textNode);
	}
	public function setAttr($queryOrNode, $attr, $newValue) {
		$element = $this->fetchElement($queryOrNode);
		$element->setAttribute($attr, $newValue);
	}
	public function removeAttr($queryOrNode, $attr) {
		$element = $this->fetchElement($queryOrNode);
		$element->removeAttribute($attr);
	}
	
	public function render() {
		return $this->dom->saveHTML();
	}
}

class DOMplateDatum {
	public $type;
	public $value;
	
	public function  __construct($type, $val) {
		$this->type = $type;
		$this->value = $val;
	}
}
