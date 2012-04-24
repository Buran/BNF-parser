<?php
/*
TODO: отрефакторить и формализовать генерируемую структуру, описать в документации (PHPDoc?). Важно, чтобы не забыть.
*/
class BNFRulesParser {

	private $BNF_data = array();
	private $delimiter = '::=';
	private $nl = "\r\n";
	private $Rule;
	private $parsed = null;

	public function __construct($RuleRepeat, $Regexp) {
		$this->Rule = new Rule($RuleRepeat, $Regexp);
	}

	public function reset() {
		$this->BNF_data = array();
		return $this;
	}

	public function addBNFFile($file_name) {
		if (!file_exists($file_name)) {
			trigger_error('BNF-file "' . $file_name . '" does not exist');
			return false;
		} elseif (!is_readable($file_name)) {
			trigger_error('BNF-file "' . $file_name . '" is not readable');
			return false;
		} else {
			$this->parsed = null;
			array_push($this->BNF_data, file_get_contents($file_name));
		}
		return $this;
	}

	public function addBNFolder($folder_name) {
		return $this;
	}

	private function parse() {
		$BNF_data = preg_split("/[\r\n]+/", implode($this->BNF_data, "\n"));
		$this->parsed = array();
		$this->parsed_data = array();
		$current_rule = false;
		$matches = array();
		foreach ($BNF_data as $v) {
			if (!trim($v) || strpos(trim($v), '#') === 0) {
				continue;
			}
			if (preg_match('/^(?:\s*([a-zA-Z_-]+[a-zA-Z_-\d]*)\s*' . preg_quote($this->delimiter) . ')?\s*(.*?)$/', $v, $matches)) {
				if ($matches[1]) {
					$current_rule = trim($matches[1]);
					$this->parsed[$current_rule] = trim($matches[2]);
				} else {//multiline rule
					$this->parsed[$current_rule] .= "\r\n" . $matches[2];
				}
			} else {
				trigger_error('BNF syntax error');
			}
		}
		foreach ($this->parsed as $k => $rule) {
			$Rule = clone $this->Rule;
			$this->parsed[$k] = $Rule->setRule($rule);
		}
	}

	public function __get($name) {
		if ($this->parsed === null) {
			$this->parse();
		}
		if ($name === 'parsedData') {
			$parsedData = array();
			reset($this->parsed);
			while (list($k, $v) = each($this->parsed)) {
				$parsedData[$k] = $v->parsedData;
			}
			return $parsedData;
		}
		trigger_error('Undefined property ' . $name . ' via __get()');
	}

	public function __toString() {
		if ($this->parsed === null) {
			$this->parse();
		}
		reset($this->parsed);
		$dump = array();
		while (list($name, $rule) = each($this->parsed)) {
			$dump[] = $name . ' ' . $this->delimiter . ' ' . (string)$rule;
		}
		return implode($this->nl, $dump);
	}

}
