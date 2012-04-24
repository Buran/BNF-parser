<?php

class BNFRegexp {

	private $re_one_alternative = '
		(?<negate>\^\s*)?
		(
			(
				\<(?<link>(?:[a-zA-Z_-]+)(?:[a-zA-Z\d_-]*))\>
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
				\s*(?:\#.*?[\r\n]+)?
					(?<group>
						(?R)*
					)
				\s*(?:\#.*?[\r\n]+)?
			\)
		)
		(
			\s*(?:\#.*?[\r\n]+)?
			(?:
				(?<quantifier>[?*+])
				|
				\{\s*(?:\#.*?[\r\n]+)?
				(?<range>
					\d+\s*(,\s*\d*)?
					|
					,\s*\d+
				)
				\s*\}
			)
		)?

		(
			\s*(?:\#.*?[\r\n]+)?
			(?<condition>\|\|?|&&)(?!\s*\)|\s*$)
		)?

		\s*(?:\#.*?[\r\n]+)?
	';

	public function getAlternativeRegexp() {
		return $this->re_one_alternative;
	}

}