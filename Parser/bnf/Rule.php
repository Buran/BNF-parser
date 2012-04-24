<?php
class Rule {

	public $rule;
	private $Regexp;
	private $RuleRepeat;
	private $parsed = null;

	public function __construct($RuleRepeat, $Regexp, $rule = false) {
		$this->setRule($rule);
		$this->Regexp = $Regexp;
		$this->RuleRepeat = $RuleRepeat;
	}

	public function __clone() {
	}

	public function setRule($rule) {
		$this->parsed = null;
		$this->rule = trim($rule);
		return $this;
	}

	/**
	 * TODO: вынести метод splitAlternatives($nodes)
	 * @param $str
	 * @return array|bool
	 */
	private function parse() {
		/*if (!$this->checkRuleCorrectness($str)) {
			trigger_error('Rule is invalid (' . $str . ').');
			return false;
		}*/
		$nodes = $this->getNodes();
		if (!$nodes) {
			return false;
		}
		if (!$this->checkConditionCorrectness($nodes)) {
			trigger_error('Condition in alternatives must to be identical (' . htmlspecialchars($this->rule) . ').');
			return false;
		}

		$this->parsed = array('condition' => '', 'alternatives' => array());
		$alternatives = array();

		reset($nodes);
		while (list(, $node) = each($nodes)) {
			$this->RuleRepeat->reset();
			if (strlen($node['range'])) {
				$this->RuleRepeat->setRange($node['range']);
			} elseif (strlen($node['quantifier'])) {
				$this->RuleRepeat->setQuantifier($node['quantifier']);
			}
			$info = array(
				'type' => $node['type'],
				'negate' => $node['^'],
				'min' => $this->RuleRepeat->from,
				'max' => $this->RuleRepeat->to,
				'occurrence' => 0,
				'pointer' => 0
			);
			if ($info['type'] === 'string') {
				//$string = preg_replace('/\\\(.)/', "$1", $matches['string'][$k]);
				$info['text'] = unescape($node['text']);
			} elseif ($info['type'] === 'collection') {
				//todo: move Collection class to IoC
				$Collection = new Collection(unescape($node['text']));
				$info['text'] = $Collection;
			} elseif ($info['type'] === 'group') {
				$Rule = clone $this;
				$info['text'] = $Rule->setRule($node['text']);
			} else {
				$info['text'] = $node['text'];
			}
			$alternatives[] = $info;
			if (current($nodes) === false && !count($this->parsed['alternatives'])) {
				$this->parsed = $alternatives;
			} elseif (current($nodes) === false || $node['condition']) {
				if (!count($this->parsed['alternatives'])) {
					$this->parsed['condition'] = $node['condition'];
				}
				$this->parsed['alternatives'][] = $alternatives;
				$alternatives = array();
			}
		}
	}

	public function getNodes() {
		$matches = array();
		if (!preg_match_all('/' . $this->Regexp->getAlternativeRegexp() . '/sx', $this->rule, $matches, PREG_OFFSET_CAPTURE)) {
			trigger_error('BNF syntax error in rule (' . htmlspecialchars($this->rule) . ').');
			return false;
		}
		$nodes = array();
		$match_pos = 0;
		while ((list($k, $match) = each($matches[0])) && ($match_pos === $match[1])) {
			$match_pos += strlen($match[0]);
			$node = array(
				'^' => !!self::getMatch($matches, 'negate', $k),
				'condition' => self::getMatch($matches, 'condition', $k),
				'range' => self::getMatch($matches, 'range', $k),
				'quantifier' => self::getMatch($matches, 'quantifier', $k)
			);
			$elements = array('link', 'string', 'collection', 'group');
			foreach ($elements as $element) {
				if (isset($matches[$element][$k][0]) && strlen($matches[$element][$k][0])) {
					$node['type'] = $element;
					$node['text'] = self::getMatch($matches, $element, $k);
				}
			}
			$nodes[] = $node;
		}
		if ($match_pos < strlen($this->rule) && !preg_match('/^\s*#/s', trim(substr($this->rule, $match_pos)))) {
			trigger_error('BNF syntax error on pos ' . $match_pos . ' (' . htmlspecialchars($this->rule) . ').');
		}
		return $nodes;
	}

	static function getMatch($matches, $name, $position) {
		return !empty($matches[$name][$position]) ? $matches[$name][$position][0] : '';
	}

	private function checkConditionCorrectness(&$nodes) {
		$conditions = array();
		reset($nodes);
		while (list(, $node) = each($nodes)) {
			$conditions[] = $node['condition'];
		}
		return in_array(implode('', array_unique($conditions)), array('|', '||', '&&', ''));
	}

	public function checkRuleCorrectness($string) {
		$matches = array();
		return preg_match('/^(' . $this->Regexp->getAlternativeRegexp() . ')+$/x', $string, $matches);
	}

	private function parseSequence($sequence) {
		reset($sequence);
		while (list($k, $v) = each($sequence)) {
			//only for group and collection types
			if (is_object($v['text'])/* && (get_class($v['text']) === get_class($this))*/) {
				$sequence[$k]['text'] = $v['text']->parsedData;
			}
		}
		return $sequence;
	}

	private function stringifySequence($sequence) {
		$string = array();
		reset($sequence);
		while (list(, $v) = each($sequence)) {
			if ($v['type'] == 'group') {
				$string[] = '(' . (string)$v['text'] . ')';
			} elseif ($v['type'] == 'collection') {
				$string[] = '[' . (string)$v['text'] . ']';
			} elseif ($v['type'] == 'string') {
				$string[] = '"' . escape($v['text']) . '"';
			} elseif ($v['type'] == 'link') {
				$string[] = '<' . $v['text'] . '>';
			}
		}
		return implode(' ', $string);
	}

	public function __get($name) {
		if ($name === 'parsedData') {
			if ($this->parsed === null) {
				$this->parse();
			}
			if (isset($this->parsed['alternatives'])) {
				$parsedData = $this->parsed;
				reset($parsedData['alternatives']);
				while (list($k, $alternative) = each($parsedData['alternatives'])) {
					$parsedData['alternatives'][$k] = $this->parseSequence($alternative);
				}
			} else {
				$parsedData = $this->parseSequence($this->parsed);
			}
			return $parsedData;
		}
		trigger_error('Undefined property ' . $name . ' via __get()');
	}

	public function __toString() {
		if ($this->parsed === null) {
			$this->parse();
		}
		$string = array();
		if (isset($this->parsed['alternatives'])) {
			reset($this->parsed['alternatives']);
			while (list(, $alternative) = each($this->parsed['alternatives'])) {
				$string[] = $this->stringifySequence($alternative);
			}
			return implode(' ' . $this->parsed['condition'] . ' ', $string);
		}
		return $this->stringifySequence($this->parsed);
	}

}