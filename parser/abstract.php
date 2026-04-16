<?php


class AbstractParser {

	public $lexer;
	public $stack = [];
	public $ast;
	public $comments = [];


	public function __construct($name, $text) {
		$this->name = $name;
		$this->lexer = new Lexer($text);
		//$this->map = (array)json_decode(file_get_contents("ss_map.json"));
	}

	/**
	 *
	 */
	public function getToken() {

		while (true) {

			if (count($this->stack) > 0) {
				$token = array_pop($this->stack);
			} else {
				$token = $this->lexer->next();
			}

			if ($token && $token->type == "COMMENT") {
				$this->comments[] = Node::fromToken($token);
				continue;
			}

			return $token;
		}
	}

	public function peek($n = 0) {
		$stack = [];
		for ($i = 0; $i <= $n; $i++) {
			$stack[] = $this->getToken();
		}

		$tok = array_pop($stack);
		$this->push($tok);

		while (count($stack) > 0) {
			$t = array_pop($stack);
			$this->push($t);
		}

		return $tok;
	}

	public function nextIs($type) {
		return in_array($this->peek()->type, (array)$type);
	}

	public function expect($type, $peek = false) {

		$token = $this->getToken();
		if ($token->type != $type) {
			$this->error("Expected '$type'", $token);
		}

		if ($peek) {
			$this->push($token);
		}

		return $token;
	}

	public function push($token) {
		$this->stack[] = $token;
	}

	public function error($str, $token = null) {
		throw new \Exception($token ? $token->error($str) : $str);
	}
}

