<?php
/*!
 * @file
 * PBKDF2 Implementation (described in RFC 2898)
 * 
 * @param string $password Password
 * @param string $salt Salt
 * @param int $iterations iteration count (use 1000 or higher)
 * @param int $keyLength derived key length
 * @param string $algo (optional) hash algorithm, defaults to 'sha256'
 * 
 * @return Derived key
*/
function pbkdf2($password, $salt, $iterations, $keyLength, $algo = 'sha256') {
	$hashLength = strlen(hash($algo, null, true));
	$keyBlocks = ceil($keyLength / $hashLength);
	$derivedKey = '';
 
	# Create key
	for ( $block = 1; $block <= $keyBlocks; $block ++ ) {
 
		# Initial hash for this block
		$ib = $b = hash_hmac($algo, $salt . pack('N', $block), $password, true);
 
		# Perform block iterations
		for ( $i = 1; $i < $iterations; $i ++ )
 
			# XOR each iterate
			$ib ^= ($b = hash_hmac($algo, $b, $password, true));
 
		$derivedKey .= $ib; # Append iterated block
	}
 
	# Return derived key of correct length
	return substr($derivedKey, 0, $keyLength);
}
?>
