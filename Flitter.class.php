<?php
class Flitter {
	private $fields;
	private $ip;
	private $url;
	private $secret;
	
	public function __construct($fieldNames, $clientIp, $url, $secret) {
		$this->fields = $fieldNames;
		$this->ip = strval($clientIp);
		$this->url = $url;
		$this->secret = $secret;
	}
	
	private function encodeFieldNames($spinner) {
		$fieldsEncoded = array_flip($this->fields);
		foreach ($this->fields as $field) {
			$fieldsEncoded[$field] = md5($field . $spinner . $this->secret . $this->ip);
		}
		return $fieldsEncoded;
	}
	private function decodeFieldNames($spinner) {
		$fieldsDecoded = array_flip($this->encodeFieldNames($spinner));
		return $fieldsDecoded;
	}
	private function makeSpinner($timestamp) {
		return md5($timestamp. $this->ip . $this->url . $this->secret);
	}
	
	private function fakeFields($fieldCount) {
		$seed = microtime() + rand(1,999);
		$fakeFields = array();
		for ($i = 0; $i < $fieldCount; $i++) {
			$fakeFields[] = md5($seed + $i);
		}
		return $fakeFields;
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
		}
		
		return $parts;
	}
	public function decodeSubmission($postedData, $spinnerField="token", $timestampField="timestamp") {
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
