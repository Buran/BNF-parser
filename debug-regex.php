<?php
$re_one_alternative = '
(\^\s*)?
(
	(
		\<(?<link>[a-zA-Z_\d]+)\>
		|
		"
			(?<string>
				(?:
					[^"]*?		# any non-" characters
					(?:\\\.)*	# possible escaped symbols
				)*
			)
		"
		|
		\[
			(?<collection>(?:
				[^\]]*?			# any non-"]" characters
				(?:\\\[.\]])*	# possible escaped symbols
			)*)					# and this chain may be repeated
		\]
	)
	|
	\(
		(?<group>
			(?R)*
		)
	\)
)
(
	\s*
	(?:
		(?<quantifier>[?*+])
		|
		\{\s*
		(?<range>
			\d+\s*(,\s*\d*)?
			|
			,\s*\d+
		)
		\s*\}
	)
)?

(?<condition>
	(\s*\|\|?|\s&&)(?!\s*\)|\s*$)
)?

\s*
';

$re_alternatives = '/(' . $re_one_alternative . ')+/x';
$matches = array();
$string = '"dsfsadf" "sfas" ("inset" ("a" | "b") <space>+)? <length> (<space>+ <length>){1,4} (<space>+ <color>)?';

$string = '"sfda" <webkitColorStop>* (<comma> "from(" <color> ")" <webkitColorStop>* <comma> "to(" <color> ")" || <comma> "to(" <color> ")" <webkitColorStop>* <comma> "from(" <color> ")")? && <webkitColorStop>*';
echo'<pre>';ob_start();print_r($string);echo htmlspecialchars(ob_get_clean());echo'</pre>';

preg_match($re_alternatives, $string, $matches);
echo'<pre>';ob_start();print_r($matches);echo htmlspecialchars(ob_get_clean());echo'</pre>';

preg_match_all('/' . $re_one_alternative . '/x', $string, $matches);
echo'<pre>';ob_start();print_r($matches);echo htmlspecialchars(ob_get_clean());echo'</pre>';
