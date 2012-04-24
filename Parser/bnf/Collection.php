<?php
class Collection {

	private $string;
	private $parsed = null;

	public function __construct($string) {
		$this->string = $string;
	}

	/**
	 * Разбор строки коллекции символов. Не понимает escaped-символы. Для указания символа в шестнадцатеричном формате
	 * используются записи #0x1, #0x2f, #0x34f, #0x45f6, #1, #23, #4957435838. Для указания диапазонов используются
	 * записи 1-9, #0x1-d, #0x34f-#23.
	 * TODO: make correct parsing of [-]
	 * @param  $collection
	 * @return array
	 */
	private function parse() {
		$this->parsed = array();
		if (preg_match_all('/
		(
			\#0?(x)([\da-f]{1,4}) |
			(\#)([\d]+) |
			.
		)
		(
			\-
			(
				\#0?(x)([\da-f]{1,4}) |
				(\#)([\d]+) |
				.
			)
		)?
		/ix', trim($this->string), $matches)) {
			foreach ($matches[1] as $k => $v) {
				if (strtolower($matches[2][$k]) == 'x') {
					$start = hexdec($matches[3][$k]);
				} elseif ($matches[4][$k] == '#') {
					$start = intval($matches[5][$k]);
				} else {
					$start = utf8ToUnicode($matches[1][$k]);
					$start = count($start) ? $start[0] : false;
				}
				if (strtolower($matches[8][$k]) == 'x') {
					$finish = hexdec($matches[9][$k]);
				} elseif ($matches[10][$k] == '#') {
					$finish = intval($matches[11][$k]);
				} else {
					$finish = utf8ToUnicode($matches[7][$k]);
					$finish = count($finish) ? $finish[0] : false;
				}
				$this->parsed[] = array($start, $finish ? $finish : $start);
			}
		}
	}

	public function __get($name) {
		if ($name === 'parsedData') {
			if ($this->parsed === null) {
				$this->parse();
			}
			return $this->parsed;
		}
		trigger_error('Undefined property ' . $name . ' via __get()');
	}

	public function __toString() {
		if ($this->parsed === null) {
			$this->parse();
		}
		$accumulator = '';
		reset($this->parsed);
		while (list(, $v) = each($this->parsed)) {
			$accumulator .= '#0x' . dechex($v[0]);
			if ($v[0] !== $v[0]) {
				$accumulator .= '-#0x' . dechex($v[0]);
			}
		}
		return $accumulator;
	}
}