<?php
/**
 * DONE TODO: support in BNF syntax "||" -- for any order "once-occurrences"
 * DONE TODO: make support of {2} -- repeating count REFACTOR
 * DONE TODO: support comments and empty lines in BNF
 * DONE TODO: support multiline BNF rules
 * DONE TODO: shadow	::= ("inset" <space>+)? && (<length> (<space>+ <length>){1,4}) && (<space>+ <color>)? протестировать, много багов.
 * TODO: make unescaping all symbols in strings and collections
 * TODO: checking for existed links
 * TODO: покрыть тестами все ситуации
 * TODO: закончить, протестировать ^ and expand it as on RPA
 * TODO: переименовать rule в более правильное имя так как это не правило, а составная часть правила
 */
class BNFParser {
	public $current_character;
	private $source;
	private $BNF;
	private $current_rule;
	private $current_rules;

	public function __construct($BNF, $source) {
		$this->BNF = $BNF;
		$this->setSource($source);
	}

	public function setSource($source) {
		$this->reset();
		$this->source = $source;
		$this->string_length = strlen($source);
	}

	// TODO: если в setCurrentRules передается несуществующее правило, то генерировать ошибку парсинга
	public function setCurrentRules(&$rules) {
		$this->reset();
		if (isset($rules['alternatives'])) {
			$this->current_rules = array(&$rules);
		} else {
			$this->current_rules =& $rules;
		}
		reset($this->current_rules);
		$this->current_rule = current($this->current_rules);
		return $this;
	}

	private function reset() {
		$this->current_character = 0;
	}

	public function setCurrentTag($tag) {
		if (!isset($this->BNF[$tag])) {
			trigger_error('no BNF with name "' . $tag . '"');
			return;
		}
		$this->setCurrentRules($this->BNF[$tag]);
		return $this;
	}

	var $k = 0;
	/**
	 * TODO: сделать проверку отрицания ^
	 * @return bool
	 */
	public function testBNF() {

		//TODO: если source пустая строка, то проверять правило на обязательность и возврат делать прямо здесь
		/*
		if (!$this->string_length) {
			return $this->isRuleOptional($this->current_rules);
		}
		*/
		$status = true;
		while ($status && $this->current_rule) {
			$string = $this->endStringReached() ? '' : substr($this->source, $this->current_character);
			$Parser = new BNFParser($this->BNF, $string);
			if (isset($this->current_rule['alternatives'])) {
				if ($this->current_rule['condition'] === '|') {
					$status = $this->testAlternatives($Parser);
				} else {
					$status = $this->testMultipleAlternatives($Parser);
				}
			} elseif ($this->current_rule['type'] === 'group') {
				$status = $this->testGroup($Parser);
			} elseif ($this->current_rule['type'] === 'link') {
				$status = $this->testLink($Parser);
			} elseif ($this->current_rule['type'] === 'string') {
				$status = $this->testString();
			} else {// if ($this->current_rule['type'] === 'collection') {
				$status = $this->testCollection();
			}
		}
		return $status;
	}

	private function processStatus($status, $step_forward) {
		if ($status) {
			$this->current_character += $step_forward;
			$this->current_rule['occurrence']++;
			(!$step_forward || !$this->canBeRepeated()) && $this->goNextRule();
		} elseif ($this->occurrenceMatchesRange($this->current_rule['occurrence'])) {
			$this->goNextRule();
			$status = true;
		}
		return $status;
	}

	private function testAlternatives(&$Parser) {
		reset($this->current_rule['alternatives']);
		while (list($k, ) = each($this->current_rule['alternatives'])) {
			if ($Parser->setCurrentRules($this->current_rule['alternatives'][$k])->testBNF()) {
				$this->current_character += $Parser->current_character;
				$this->goNextRule();
				return true;
			}
		}
	}


	private function isRuleOptional($rule) {
		//todo: make this more algoritmic
		$Parser = new BNFParser($this->BNF, '');
		return $Parser->setCurrentRules($rule)->testBNF() && !$Parser->current_character;
	}

	private function testMultipleAlternatives(&$Parser) {
		$found = array();
		$spacer_length = 0;
		for ($k = 0, $l = count($this->current_rule['alternatives']); $k < $l;) {
			if (in_array($k, $found)) {
				$k++;
				continue;
			}
			if ($Parser->setCurrentRules($this->current_rule['alternatives'][$k])->testBNF() && $Parser->current_character) {
				$found[] = $k;
				$spacer_length = 0;
				$this->current_character += $Parser->current_character;
				$string = $this->endStringReached() ? '' : substr($this->source, $this->current_character);
				$Parser->setSource($string);
				// Определяем, следует ли за уже найденной альтернативой разделитель (если найденные альтернативы есть)
				// и прерываем поиск альтернатив, если разделителя нет.
				if ($Parser->setCurrentTag('space')->testBNF()) {
					$spacer_length = $Parser->current_character;
					$this->current_character += $spacer_length;
					$string = $this->endStringReached() ? '' : substr($this->source, $this->current_character);
					$Parser->setSource($string);
					$k = 0;
					continue;
				} else {
					break;
				}
			}
			$k++;
		}
		
		$this->current_character -= $spacer_length;
		if ($this->current_rule['condition'] === '||') {
			($status = !!count($found)) && $this->goNextRule();
		} else {
			while (list($k,) = each($this->current_rule['alternatives'])) {
				if (!in_array($k, $found) && $this->isRuleOptional($this->current_rule['alternatives'][$k])) {
					$found[] = $k;
				}
			}
			$status = count($found) === count($this->current_rule['alternatives']);
			if ($status) {
				$this->goNextRule();
			}
		}
		return $status;
	}

	private function testGroup(&$Parser) {
		$Parser->setCurrentRules($this->current_rule['text']);
		$status = $Parser->testBNF();
		return $this->processStatus($status, $Parser->current_character);
	}


	private function testLink($Parser) {
		$Parser->setCurrentRules($this->BNF[$this->current_rule['text']]);
		$status = $Parser->testBNF();
		if (!empty($this->current_rule['negate'])) {
			$status = !$status;
		}
		return $this->processStatus($status, $Parser->current_character);
	}

	/**
	 * @param  $collection
	 * @return bool
	 */
	private function testCollection() {
		$status = false;
		$char = @$this->source{$this->current_character};
		$current_hex = utf8ToUnicode($char);
		$current_hex = count($current_hex) ? $current_hex[0] : false;
		foreach ($this->current_rule['text'] as $v) {
			if ($current_hex >= $v[0] && $current_hex <= $v[1]) {
				$status = true;
				break;
			}
		}
		if (!empty($this->current_rule['negate'])) {
			$status = !$status;
		}
		return $this->processStatus($status, $this->endStringReached() ? 0 : 1);
	}

	private function testString() {
		if ($this->endStringReached()) {
			//TODO: support and test here ^
			$status = !strlen($this->current_rule['text']);
		} else {
			$status = $this->current_rule['text'] === substr($this->source, $this->current_character, strlen($this->current_rule['text']));
			if (!empty($this->current_rule['negate'])) {
				$status = !$status;
			}
		}
		return $this->processStatus($status, !empty($this->current_rule['negate']) ? 1 : strlen($this->current_rule['text']));
	}

	private function occurrenceMatchesRange($occurrence) {
		$f = $this->current_rule['min'];
		$t = $this->current_rule['max'];
		if ($f === -1) {
			return $occurrence <= $t;
		}
		return ($occurrence >= $f) && ($t === -1 || $occurrence <= $t);
	}

	private function goNextRule() {
		if (!isset($this->current_rule['alternatives'])) {
			$this->current_rule['occurrence'] = 0;
		}
		$this->current_rule = next($this->current_rules);
		return $this;
	}

	private function canBeRepeated() {
		//проверка $this->current_rule['negate'] превентивная, так как при negate не может быть посторения больше одного раза
		return empty($this->current_rule['negate']) && $this->occurrenceMatchesRange($this->current_rule['occurrence'] + 1);
	}

	private function endStringReached() {
		return $this->current_character === $this->string_length;
	}

}