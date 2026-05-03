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

namespace SaturnScript\Parser\Node;

use SaturnScript\Base\Base;


class Node extends Base {

	public $type;

	public $token;
	public $text = "";
	public $value;

	public $typedef;
	public $symbols;
	public $params;

	public $left;
	public $right;
	public $children = [];

	public static function fromToken($token, $type = null) {
		$node = new Node($token, $type);
		return $node;
	}

	public function __construct($token, $type = null) {

		if (is_string($token)) {
			[$token, $type] = [null, $token];
		}

		$this->type = $type ?: $token->type;

		if ($token !== null) {
			$this->setPosition($token->lineno, $token->charno);
			$this->text = $token->text;
		}
	}

	public function push($node) {
		$this->children[] = $node;
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

