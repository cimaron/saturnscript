<?php
require_once __DIR__ . '/../base.php';


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
		$node = new Node($type ?? $token->type, $token);
		return $node;
	}

	public function __construct($type, $token = null) {
		$this->type = $type;

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

