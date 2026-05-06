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


class Prepass extends Parser {

	protected $parser;

	public function __construct($parser) {
		$this->lexer = clone $parser->lexer;
		$this->namespace = new NamespaceObject($parser->namespace->name);
		$this->state = new \stdClass;
		$this->state->filename = $parser->state->filename;
		$this->state->preprocess = true;
	}

	/**
	 * Prepass namespace for symbols
	 */
	public function processNamespace($namespace) {

		while ($declarations = $this->parseNsDeclaration()) {
			foreach ($declarations as $declaration) {
			}
		}

		$this->expect("}");

		$namespace->types = $this->namespace->types;
	}
}

