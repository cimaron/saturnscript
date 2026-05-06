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

namespace SaturnScript\Parser;

use SaturnScript\Lexer\Lexer;
use SaturnScript\Parser\Node\Node;


class AbstractParser {

	public $lexer;
	public $stack = [];
	public $ast;
	public $comments = [];
	public $state;
	public $includeDir = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->state = new \stdClass;
		$this->state->preprocess = false;
		//$this->map = (array)json_decode(file_get_contents("ss_map.json"));
	}

	/**
	 * Open new file
	 *
	 * @param string $relpath
	 */
	public function openFile($relpath) {

		if ($relpath[0] == "/") {
			$fullpath = $relpath;
		} else {

			$includeDir = array_merge([getcwd()], $this->includeDir);

			foreach ($includeDir as $dir) {
				if (file_exists($dir . '/' . $relpath)) {
					$fullpath = $dir . '/' . $relpath;
					break;
				}
			}
		}

		if (!$fullpath || !file_exists($fullpath)) {
			$this->error("File not found: '$relpath'");
		}

		$this->state->filename = basename($fullpath);

		$text = file_get_contents($fullpath);

		$this->lexer = new Lexer($text);
	}

	/**
	 * Get Token
	 *
	 * @return Token
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

	public function expect($type) {

		if (!$this->nextIs($type)) {
			$this->error(sprintf("Expected '%s'", implode("', '", (array)$type)), $this->getToken());
		}

		return $this->getToken();
	}

	public function push($token) {
		$this->stack[] = $token;
	}

	public function error($str, $token = null) {
		throw new \Exception($this->state->filename . ' ' . ($token ? $token->error($str) : $str));
	}
}

