<?php
class Flitter {
	private $fields;
	private $ip;
	private $url;
	private $secret;
	private $algo;
	
	public function __construct($clientIp, $url, $secret, $fieldNames, $algo='md5') {
		$this->fields = $fieldNames;
		$this->ip = strval($clientIp);
		$this->url = $url;
		$this->secret = $secret;
		$this->algo = $algo;
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
		return hash($this->algo, $timestamp. $this->ip . $this->url . $this->secret);
	}
	
	private function fakeFields($fieldCount) {
		$seed = microtime() + rand(1,999);
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
		$timestamp = microtime();
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
		$spinner = $postedData[$spinnerField];
		$decodedFields = $this->decodeFieldNames($spinner);
		
		$parts = array(
			'valid'=>array(),
			'invalid'=>array()
		);
		foreach ($postedData as $field=>$value) {
			if ($field !== $spinnerField && !empty($value)) {
				if (isset($decodedFields[$field])) {
					$parts['valid'][$decodedFields[$field]] = $value;
				} else {
					$parts['invalid'][$field] = $value;
				}
			}
		}
		
		if (isset($parts['valid'][$timestampField])) {
			$spinnerTest = $this->makeSpinner($parts['valid'][$timestampField]);
			if ($spinnerTest !== $spinner) {
				$parts['invalid'][$spinnerField] = '1';
			}
		} else {
			$parts['invalid'][$timestampField] = '1';
		}
		
		return $parts;
	}
}
