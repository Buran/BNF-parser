<?php
class RuleRepeat {

	public $from;
	public $to ;

	public function __construct() {
		$this->reset();
	}

	public function setQuantifier($quantifier) {
		$map = array(
			'*' => array(0, -1),
			'+' => array(1, -1),
			'?' => array(0, 1)
		);
		list($this->from, $this->to) = $map[$quantifier];
	}

	public function setRange($range) {
		$range = preg_split('/\s*,\s*/', $range);
		if (count($range) > 1) {
			list($this->from, $this->to) = $range;
		} else {
			$this->from = $this->to = $range[0];
		}
	}

	public function reset() {
		$this->from = $this->to = 1;

	}

}
