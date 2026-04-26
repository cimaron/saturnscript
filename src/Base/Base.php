<?php

namespace SaturnScript\Base;


class Base {

	public $lineno;
	public $charno;

	/**
	 *
	 */
	public function __construct($lineno = 0, $charno = 0) {
		$this->setPosition($lineno, $charno);
	}

	/**
	 *
	 */
	public function setPosition($lineno, $charno) {
		$this->lineno = $lineno;
		$this->charno = $charno;
	}

	public function error($str = "") {
		return sprintf("line %d char %d: %s", $this->lineno, $this->charno, $str);
	}
}

