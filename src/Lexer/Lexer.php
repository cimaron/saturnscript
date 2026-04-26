<?php

namespace SaturnScript\Lexer;


class Lexer {

	public $input = "";
	public $curline = 1;
	public $curchar = 1;
	public $curlinei = 0;
	public $i = 0;
	public $_buffer = false;

	public $single = ":;[](){}=,<>&-";

	public $rules =  [

		'NAMESPACE' => 'namespace',

		'CONST' => 'const',
		'CLASS' => 'class',
		'LET' => 'let',

		'TYPE' => '((u|s)(8|16|32))|(fx(8|16|24))',

		'IF' => 'if',
		'FOR' => 'for',
		'WHILE' => 'while',
		'ELSE' => 'else',

		'IDENTIFIER' => "[a-zA-Z_][a-zA-Z_0-9]*",
		'NUMBER' => '\d+',
		
		//'STRING' => '"[^\"]*"',

		//Comparison
		'==' => '==',
		'!=' => '!=',
		'<=' => '<=',
		'>=' => '>=',

		//Assignment
		'+=' => '\+=',
		'-=' => '\-=',
		'^=' => '\^=',

		'++' => '\+\+',
		'--' => '\-\-',
	];

	public function __construct($text) {
		$this->input = $text;
	}

	/**
	 *
	 */
	public function next() {

		while (!$this->eof()) {

			if ($this->is(" ") || $this->is("\t") || $this->is("\n")) {
				$this->adv();
				continue; 
			}

			if ($token = $this->readSingleComment()) {
				return $token;
			}

			if ($token = $this->readMultiComment()) {
				return $token;
			}

			if ($token = $this->readPreprocessor()) {
				return $token;
			}

			/*
			if ($this->readKeyword()) {
				continue;
			}
			*/

			if ($token = $this->readRules()) {
				return $token;
			}

			if ($token = $this->readSingle()) {
				return $token;
			}

			$eol = strpos($this->input, "\n", $this->curlinei);
			$line = substr($this->input, $this->curlinei, $eol - $this->curlinei);

			global $filename;
			echo sprintf("%s:%d:%d Invalid character(s)\n\n", $filename, $this->curline, $this->curchar);

			echo $line . "\n";
			echo str_repeat(" ", $this->curchar - 1) . "^";
			die();
		}
	}

	/**
	 *
	 */
	public function log($str) {
		echo sprintf("[% 3s, % 3s] %s\n", $this->curline, $this->curchar, $str);
	}

	/**
	 *
	 */
	public function get($offset = 0) {

		$i = $this->i + $offset;

		if ($i >= strlen($this->input)) {
			return false;
		}

		return $this->input[$i];
	}

	/**
	 * 
	 */
	public function la($offset = 0) {
		return $this->get(1 + $offset);
	}

	/**
	 *
	 */
	public function is($chr, $offset = 0) {
		return $this->get($offset) === $chr;
	}

	/**
	 *
	 */
	public function eof($offset = 0) {
		return $this->is(false, $offset);
	}

	/**
	 * 
	 */
	public function adv($offset = 1) {

		for ($i = 0; $i < $offset; $i++) {

			if ($this->_buffer !== false) {
				$this->_buffer .= $this->get();
			}

			if ($this->is("\n")) {
				$this->curline++;
				$this->curchar = 0;
				$this->curlinei = $this->i + 1;
			}

			$this->i++;
			$this->curchar++;
		}
	}

	/**
	 *
	 */
	public function buffer() {

		if ($this->_buffer !== false) {
			$buffer = $this->_buffer;
			$this->_buffer = false;
			return $buffer;
		}

		$this->_buffer = "";
	}

	/**
	 *
	 */
	public function readSingleComment() {

		if (!($this->is("/") && $this->is("/", 1))) {
			return false;
		}

		$comment = new Token("COMMENT", $this->curline, $this->curchar);

		$this->adv(2);
		$this->buffer();

		while (!$this->is("\n") && !$this->eof()) {
			$this->adv();
		}

		$comment->text = $this->buffer();

		$this->adv();

		return true;
	}

	/**
	 *
	 */
	public function readMultiComment() {

		if (!($this->is("/") && $this->is("*", 1))) {
			return false;
		}

		$comment = new Token("COMMENT", $this->curline, $this->curchar);

		$this->adv(2);
		$this->buffer();

		while ((!$this->is("*") || !$this->is("/", 1)) && !$this->eof()) {
			$this->adv();
		}

		if ($this->eof()) {
			die("Error: unterminated comment on line $this->curline");
		}

		$comment->text = $this->buffer();

		$this->adv(2);

		return $comment;
	}

	/**
	 *
	 */
	public function readPreprocessor() {

		if (!$this->is("#")) {
			return false;
		}

		$this->adv(1);

		$this->buffer();

		while (!$this->is("\n") && !$this->eof()) {
			$this->adv();
		}

		$text = $this->buffer();
		$this->adv();

		$tok = new Token("PRE", $this->curline, $this->curchar);
		$tok->text = $text;

		return $tok;
	}

	/**
	 *
	 */
	public function readSingle() {

		$chr = $this->get();
		if (strpos($this->single, $chr) === false) {
			return false;
		}

		$tok = new Token($chr, $this->curline, $this->curchar);
		$tok->text = $chr;

		$this->adv(1);

		return $tok;
	}

	/**
	 *
	 */
	public function readRules() {

		$mtoken = false;
		$mtext = "";

		foreach ($this->rules as $token => $rule) {

			$text = $this->matchRule($rule);

			if (!$text === "") {
				continue;
			}
			
			if (strlen($text) > strlen($mtext)) {
				$mtoken = $token;
				$mtext = $text;
			}
		}

		if (!$mtoken) {
			return false;
		}

		$tok = new Token($mtoken, $this->curline, $this->curchar);
		$tok->text = $mtext;

		$this->adv(strlen($mtext));

		return $tok;
	}

	/**
	 *
	 */
	public function matchRule($rule) {

		$buffer = "";

		$i = 0;
		/*
		while (preg_match("/^" . $rule . "$/", $buffer . $this->get($i))) {
			$buffer .= $this->get($i++);
		}
		*/

		$eol = strpos($this->input, "\n", $this->i);
		$line = substr($this->input, $this->i, $eol - $this->i);

		if (preg_match("/^(" . $rule . ")/", $line, $matches)) {
			$buffer = $matches[0];
		}

		return $buffer;
	}

}