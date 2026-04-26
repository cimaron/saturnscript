<?php
/**
 * Project  SaturnScript
 * Author   Cimaron Shanahan
 *
 * MIT License
 * Copyright (c) 2026 Cimaron Shanahan
 *
 * See LICENSE.txt for full license text
 */

namespace SaturnScript\Lexer;

use SaturnScript\Base\Base;


class Token extends Base {

	public $type = "";

	public $text = "";
	public $value = null;

	public function __construct($type, $lineno, $charno) {
		$this->type = $type;

		$this->setPosition($lineno, $charno);
	}

	public function __toString() {
		return json_encode($this->__debugInfo());
	}

	public function __debugInfo() {
		$vars = get_object_vars($this);
		$vars['text'] = substr($vars['text'], 0, 20) . (strlen($vars['text']) > 20 ? "..." : "");
		return $vars;
	}
}
