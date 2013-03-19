<?php
class Flitter {
	public static function encodeFieldNames($fieldNames, $clientIp, $secret, $spinner) {
		$fieldsEncoded = array_flip($fieldNames);
		foreach ($fieldNames as $field) {
			$fieldsEncoded[$field] = md5($field.$spinner.$secret.$clientIp);
		}
		return $fieldsEncoded;
	}
	public static function decodeFieldNames($fieldNames, $clientIp, $secret, $spinner) {
		$fieldsDecoded = array_flip(self::encodeFieldNames($fieldNames, $clientIp, $secret, $spinner));
		return $fieldsDecoded;
	}
	
	public static function fakeFields($fieldCount) {
		$seed = microtime() + rand(1,999);
		$fakeFields = array();
		for ($i = 0; $i < $fieldCount; $i++) {
			$fakeFields[] = md5($seed + $i);
		}
		return $fakeFields;
	}
	
	public static function makeFormParts($fieldNames, $clientIp, $url, $secret) {
		$clientIp = strval($clientIp);
		$timestamp = microtime();
		$spinner = md5($clientIp.$timestamp.$secret);
		$realFields = self::encodeFieldNames($fieldNames, $clientIp, $secret, $spinner);
		
		$parts = array(
			'timestamp'=>$timestamp,
			'token'=>$spinner,
			'fields'=>$realFields
		);
		
		return $parts;
	}
	public static function decodeSubmission($fieldNames, $clientIp, $url, $secret, $postedData, $spinnerField="token", $timestampField="timestamp") {
		$clientIp = strval($clientIp);
		$spinner = $postedData[$spinnerField];
		$decodedFields = self::decodeFieldNames($fieldNames, $clientIp, $secret, $spinner);
		
		$parts = array(
			'valid'=>array(),
			'invalid'=>array()
		);
		foreach ($postedData as $field=>$value) {
			if ($field !== $spinnerField) {
				if (isset($decodedFields[$field])) {
					$parts['valid'][$decodedFields[$field]] = $value;
				} else {
					$parts['invalid'][$field] = $value;
				}
			}
		}
		
		if (isset($parts['valid'][$timestampField])) {
			$spinnerTest = md5($clientIp.$parts['valid'][$timestampField].$secret);
			if ($spinnerTest !== $spinner) {
				$parts['invalid'][$spinnerField] = '1';
			}
		} else {
			$parts['invalid'][$timestampField] = '1';
		}
		
		return $parts;
	}
}
