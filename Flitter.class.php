<?php
// Based on http://nedbatchelder.com/text/stopbots.html

class Flitter {
	private $fields;
	private $ip;
	private $secret;
	private $algo;
	private $ignore;
	
	public function __construct($clientIp, $secret, $fieldNames, $ignoreFields=array(), $algo='md5') {
		$this->fields = $fieldNames;
		$this->ip = strval($clientIp);
		$this->secret = $secret;
		$this->algo = $algo;
		$this->ignore = $ignoreFields;
	}
	
	private function encodeFieldNames($spinner) {
		$fieldsEncoded = array_flip($this->fields);
		foreach ($this->fields as $field) {
			$fieldsEncoded[$field] = hash($this->algo, $field . $spinner . $this->secret . $this->ip);
		}
		return $fieldsEncoded;
	}
	private function decodeFieldNames($spinner) {
		$fieldsDecoded = array_flip($this->encodeFieldNames($spinner));
		return $fieldsDecoded;
	}
	private function makeSpinner($timestamp) {
		$timestamp = strval($timestamp);
		$spinner = hash($this->algo, $timestamp. $this->ip . $this->secret);
		return $spinner;
	}
	
	private function fakeFields($fieldCount) {
		$seed = strval(microtime(true) + rand(1,999));
		$fakeFields = array();
		for ($i = 0; $i < $fieldCount; $i++) {
			$fakeFields[] = hash($this->algo, $seed + $i);
		}
		return $fakeFields;
	}
	private function fakeCss($fakeFields, $prefix='.css_') {
		return $prefix . implode(", $prefix", $fakeFields) . ' { display: none; }';
	}
	
	public function makeFormParts($fakes=0) {
		$timestamp = strval(microtime(true));
		$spinner = $this->makeSpinner($timestamp);
		$realFields = $this->encodeFieldNames($spinner);
		
		$parts = array(
			'timestamp'=>$timestamp,
			'token'=>$spinner,
			'fields'=>$realFields
		);
		if ($fakes) {
			$parts['fakeFields'] = $this->fakeFields($fakes);
			$parts['fakeStyles'] = $this->fakeCss($parts['fakeFields']);
		}
		
		return $parts;
	}
	
	public function decodeSubmission($postedData, $timestampField='timestamp', $spinnerField='token') {
		$decodedFields = $this->decodeFieldNames($postedData[$spinnerField]);
		
		$parts = array(
			'valid'=>array(),
			'invalid'=>array(),
			'token'=>$postedData[$spinnerField]
		);
		foreach ($postedData as $field=>$value) {
			if ($field !== $spinnerField && !empty($value)) {
				if (isset($decodedFields[$field])) {
					$parts['valid'][$decodedFields[$field]] = $value;
				} elseif (in_array($field, $this->ignore, TRUE)) {
					$parts['valid'][$field] = $value;
				} else {
					$parts['invalid'][$field] = $value;
				}
			}
		}
		
		if (isset($parts['valid'][$timestampField])) {
			$spinnerTest = $this->makeSpinner($parts['valid'][$timestampField]);
			if ($spinnerTest !== $postedData[$spinnerField]) {
				$parts['invalid'][$spinnerField] = $spinnerTest;
			}
		} else {
			$parts['invalid'][$timestampField] = '1';
		}
		
		return $parts;
	}
}
