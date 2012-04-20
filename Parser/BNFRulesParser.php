<?php
/*
TODO: отрефакторить и формализовать генерируемую структуру.
*/
class BNFRulesParser {
	/**
	 * 5 link
	 * 6 qutes
	 * 7 set
	 * 8 brackets
	 */
	private $BNF_data = array();
	private $delimiter = '::=';

	public function __construct($RuleRepeat, $Regexp) {
		$this->RuleRepeat = $RuleRepeat;
		$this->Regexp = $Regexp;
	}

	public function addBNFFile($file_name) {
		if (!file_exists($file_name)) {
			trigger_error('BNF-file "' . $file_name . '" does not exist');
			return false;
		} elseif (!is_readable($file_name)) {
			trigger_error('BNF-file "' . $file_name . '" is not readable');
			return false;
		} else {
			array_push($this->BNF_data, file_get_contents($file_name));
		}
	}

	public function addBNFolder($folder_name) {
	}

	public function parseBNFData() {
		$BNF_data = preg_split("/[\r\n]+/", implode($this->BNF_data, "\n"));
		$BNF_rules = array();
		$current_rule = false;
		foreach ($BNF_data as $v) {
			$rule = explode($this->delimiter, $v);
			if (empty($rule)) {
				continue;
			}
			if (count($rule) == 2) {
				$current_rule = trim($rule[0]);
				$BNF_rules[$current_rule] = trim($rule[1]);
			} elseif ($current_rule) {//multiline rule
				$BNF_rules[$current_rule] .= "\r\n" . trim($rule[0]);
			} elseif (strpos(trim($rule[0]), '#') !== 0) {
				trigger_error('BNF syntax error');
			}
		}
		foreach ($BNF_rules as $k => $rule) {
			$BNF_rules[$k] = $this->parseRule($rule);
		}
		return $BNF_rules;
	}

	/**
	 * TODO: вынести метод splitAlternatives($nodes)
	 * @param $str
	 * @return array|bool
	 */
	public function parseRule($rule) {
		/*if (!$this->checkRuleCorrectness($str)) {
			trigger_error('Rule is invalid (' . $str . ').');
			return false;
		}*/
		$nodes = $this->getNodes($rule);
		if (!$nodes) {
			return false;
		}
		if (!$this->checkConditionCorrectness($nodes)) {
			trigger_error('Condition in alternatives must to be identical (' . htmlspecialchars($rule) . ').');
			return false;
		}

		$return = array('condition' => '', 'alternatives' => array());
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
			if ($node['type'] === 'string' || $node['type'] === 'collection') {
				//$string = preg_replace('/\\\(.)/', "$1", $matches['string'][$k]);
				$info['text'] = $node['type'] === 'collection' ? $this->parseCollection(unescape($node['text'])) : unescape($node['text']);
			} elseif ($node['type'] === 'group') {
				$info['text'] = $this->parseRule($node['text']);
			} else {
				$info['text'] = $node['text'];
			}
			$alternatives[] = $info;
			if (current($nodes) === false && !count($return['alternatives'])) {
				$return = $alternatives;
			} elseif (current($nodes) === false || $node['condition']) {
				if (!count($return['alternatives'])) {
					$return['condition'] = $node['condition'];
				}
				$return['alternatives'][] = $alternatives;
				$alternatives = array();
			}
		}
		return $return;
	}

	public function getNodes($string) {
		$matches = array();
		if (!preg_match_all('/' . $this->Regexp->getAlternativeRegexp() . '/sx', $string, $matches, PREG_OFFSET_CAPTURE)) {
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
		if ($match_pos < strlen($string) && !preg_match('/^\s*#/s', trim(substr($string, $match_pos)))) {
			trigger_error('BNF syntax error on pos ' . $match_pos . ' (' . htmlspecialchars($string) . ').');
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

	/**
	 * TODO: make correct parsing of [-]
	 * @param  $collection
	 * @return array
	 */
	public function parseCollection($collection) {
		$ranges = array();
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
		/ix', trim($collection), $matches)) {
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
				$ranges[] = array($start, $finish ? $finish : $start);
			}
		}
		return $ranges;
	}
}
