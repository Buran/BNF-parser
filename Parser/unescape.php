<?php
/**
 * TODO: make parsing escaped characters more stable.
 */
function unescape($str) {
	$accumulator = '';
	$map = array(
		'n' => "\n",
		'r' => "\r",
		'f' => "\f",
		's' => " ",
		'"' => "\"",
		'\'' => "'",
		'\\' => "\\"
	);
	for ($i = 0; $i < strlen($str); $i++) {
		if ($str{$i} === '\\' && $i < strlen($str) && isset($map[$str{$i + 1}])) {
			$accumulator .= $map[$str{$i + 1}];
			$i++;
		} else {
			$accumulator .= $str{$i};
		}
	}
	return $accumulator;
}
