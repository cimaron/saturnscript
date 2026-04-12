<?php



class Prepass extends Parser {

	protected $parser;

	public function __construct($parser) {
		$this->lexer = clone $parser->lexer;
		$this->namespace = new NamespaceObject($parser->namespace->name);
	}

	/**
	 * Prepass namespace for symbols
	 */
	public function processNamespace($namespace) {

		while ($declarations = $this->parseNsDeclaration()) {
			foreach ($declarations as $declaration) {
			}
		}

		if ($this->getToken()->type != '}') {
			$this->error("Expected '}'");
		}

		$namespace->types = $this->namespace->types;
	}

	/**
	 * 
	 */
	public function parseMember($ident) {

		$node = Node::fromToken($ident);

		$next = $this->getToken();

		//switch to method
		if ($next->type == "(") {
			return $this->parseMethod($ident);
		}

		if ($next->type != ":") {
			$this->error("Expected ':'", $next);
		}

		$type = $this->getToken();
		if ($type->type != 'TYPE' && $type->type != 'IDENTIFIER') {
			$this->error("Expected type", $type);
		}

		$node->typedef = $type;

		$semi = $this->getToken();
		if ($semi->type != ';') {
			$this->error("Expected ';'", $semi);
		}

		return $node;
	}
}

