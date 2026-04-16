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

		$this->expect("}");

		$namespace->types = $this->namespace->types;
	}

	/**
	 * 
	 */
	public function parseMember() {

		if (!$this->nextIs('IDENTIFIER')) {
			return false;
		}

		$ident = $this->getToken();
		$node = Node::fromToken($ident);

		//switch to method
		if ($this->nextIs('(')) {
			$this->push($ident);
			return $this->parseMethod();
		}

		$this->expect(':');

		$type = $this->getToken();
		if ($type->type != 'TYPE' && $type->type != 'IDENTIFIER') {
			$this->error("Expected type", $type);
		}

		$node->typedef = $type;

		$this->expect(';');

		return $node;
	}

}

