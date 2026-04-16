<?php
require_once 'lexer.php';
require_once 'parser/abstract.php';
require_once 'parser/namespace.php';
require_once 'parser/type.php';
require_once 'parser/node.php';


class Parser extends AbstractParser {

	public $namespaces = [];
	public $namespace;

	/**
	 *
	 */
	public function parse() {
		return $this->parseGlobal();
	}

	/**
	 *
	 */
	public function parseGlobal() {

		$global = new Node('GLOBAL');
		$global->setPosition(1, 1);

		$this->ast = $global;
		$this->symbols[] = [];

		while ($token = $this->getToken()) {

			if ($token->type == "PRE") {

				if (!preg_match('/^include ["<](.+)[">]$/', $token->text, $matches)) {
					die("Can't handle that yet");
				}

				$node = Node::fromToken($token, 'IMPORT');
				$node->text = $matches[1];

				/*
				if (!isset($this->map[$node->text])) {
					die(sprintf("Missing map for %s", $node->text));
				}
				*/

				$node->text = $this->map[$node->text]->name;
				$global->push($node);
				continue;
			}

			if ($token->type == "NAMESPACE") {
				$ns = $this->parseNamespace($this->ast);
				$global->push($ns);
				continue;
			}

			throw new \Exception("Parse error");

		}

		return $global;
	}

	/**
	 * @return Node
	 */
	public function parseNamespace() {

		$token = $this->getToken();
		if ($token->type != "IDENTIFIER") {
			$this->error("Expected identifer", $token);
		}

		$this->expect("{");

			$this->namespace = new NamespaceObject($token->text);
			$this->namespaces[$this->namespace->name] = $this->namespace;

			$nsNode = new Node('NAMESPACE', $token);

			//Run a prepass to populate forward declarations
			$prepass = new Prepass($this);
			$prepass->processNamespace($this->namespace);

			while ($declarations = $this->parseNsDeclaration()) {
				foreach ($declarations as $declaration) {
					$nsNode->push($declaration, 'namespace');
				}
			}

		$this->expect("}");

		$nsNode->types = $this->namespace->types;
		$nsNode->symbols = $this->namespace->symbols;

		return $nsNode;
	}

	/**
	 * @return array|false
	 */
	public function parseNsDeclaration() {

		if ($const = $this->parseConstList()) {
			return $const;
		}

		if ($lets = $this->parseLetList()) {
			return $lets;
		}

		/*
		if ($func = $this->parseFunction()) {
			return $func;
		}
		*/

		if ($class = $this->parseClass()) {
			return [$class];
		}

		return false;
	}

	/**
	 *
	 */
	public function parseConstList() {

		if ($this->peek()->type != 'CONST') {
			return false;
		}

		$this->getToken(); //consume CONST

		$list = [];

		do {
			$list[] = $this->parseConst();
			$token = $this->getToken();
		} while ($token->type == ',');

		if ($token->type != ";") {
			$this->error("Expected ';'", $eq);
		}

		return $list;
	}

	/**
	 *
	 */
	public function parseConst() {

		$identifier = $this->expect("IDENTIFIER");

		if ($this->namespace->getSymbol($identifier->text, true)) {
			$this->error("$identifier->text already defined", $identifier);
		}

		$this->expect("=");

		$constNode = Node::fromToken($identifier, 'CONST');
		//$node->text = $identifier->text;
		$constNode->value = $this->parseValue();

		//Add to symbol table
		$this->namespace->addSymbol($constNode->text, $constNode);

		return $constNode;
	}

	/**
	 *
	 */
	public function parseLetList() {

		if ($this->peek()->type != 'LET') {
			return false;
		}

		$this->getToken(); //consume LET

		$list = [];

		do {
			$list[] = $this->parseLet();
			$token = $this->getToken();
		} while ($token->type == ',');

		if ($token->type != ";") {
			$this->error("Expected ';'", $eq);
		}

		return $list;
	}

	/**
	 *
	 */
	public function parseLet() {

		$identifier = $this->expect("IDENTIFIER");

		if ($this->namespace->getSymbol($identifier->text, true)) {
			$this->error("$identifier->text already defined", $identifier);
		}

		$letNode = Node::fromToken($identifier, 'LET');

		$this->expect(":");

		$type = $this->getToken();
		if ($type->type == 'identifier') {
			die("@todo implement types");
		} elseif ($type->type == 'TYPE') {
			$letNode->typedef = $type;
		} else {
			$this->error("Expected type", $type);
		}

		//Add to symbol table
		$this->namespace->addSymbol($letNode->text, $letNode);

		$next = $this->getToken();
		if ($next->type == ";" || $next->type == ",") {
			$this->push($next);
			return $letNode;
		}

		if ($next->type != "=") {
			$this->error("Expected '='", $next);
		}

		$letNode->value = $this->parseValue();

		return $letNode;
	}

	/**
	 *
	 */
	public function parseClass() {

		if ($this->peek()->type != 'CLASS') {
			return false;
		}

		$this->getToken(); //consume CLASS

		$identifier = $this->expect("IDENTIFIER");

		if ($this->namespace->getSymbol($identifier->text, true)) {
			$this->error("$identifier->text already defined", $identifier);
		}

		$this->expect("{");

			$node = Node::fromToken($identifier, 'CLASS');

			//Add to symbol table
			$this->namespace->addSymbol($node->text, $node);
			$type = new Type($node->text);

				while ($member = $this->parseMember()) {
					$node->push($member);
				}
			
			$this->namespace->addType($node->text);

		$this->expect("}");

		return $node;
	}

	/**
	 * 
	 */
	public function parseMember() {

		$ident = $this->getToken();
		if ($ident->type != 'IDENTIFIER') {
			$this->push($ident);
			return;
		}

		$node = Node::fromToken($ident);

		$next = $this->getToken();

		//switch to method
		if ($next->type == "(") {
			$this->push($next);
			return $this->parseMethod($ident);
		}

		if ($next->type != ":") {
			$this->error("Expected ':'", $next);
		}

		$type = $this->getToken();
		if ($type->type != 'TYPE' && $type->type != 'IDENTIFIER') {
			$this->error("Expected type", $type);
		}

		if (!$this->namespace->getType($type->text)) {
			$this->error("Expected type", $type);
		}

		$node->typedef = $type;

		$semi = $this->getToken();
		if ($semi->type != ';') {
			$this->error("Expected ';'", $semi);
		}

		return $node;
	}

	/**
	 * 
	 */
	public function parseMethod($ident) {

		$node = Node::fromToken($ident);

		$this->expect("(");

			$node->params = $this->parseMethodParams();

		$this->expect(")");
		$this->expect(":");

			$type = $this->getToken();
			if ($type->type != 'TYPE' && $type->type != 'IDENTIFIER') {
				$this->error("Expected type", $type);
			}

			if (!$this->namespace->getType($type->text)) {
				$this->error("Expected type", $type);
			}

			$node->typedef = $type;

		$this->expect("{");

			//$this->parseMethodBody($node);

		$this->expect("}");

		return $node;
	}

	/**
	 * 
	 */
	public function parseMethodParams() {

		$params = [];

		if ($this->peek()->type == ")") {
			return $params;
		}

		do {
			$paramNode = $this->parseMethodParam();
			if ($paramNode) {
				$params[] = $paramNode;
			}
			$token = $this->getToken();
		} while ($token->type == ',');

		if ($token->type == ')') {
			$this->push($token);
		}

		return $params;
	}

	/**
	 *
	 */
	public function parseMethodParam() {

		$ident = $this->getToken();
		if ($ident->type != "IDENTIFIER") {
			$this->error("Expected identifier", $ident);
		}

		$next = $this->getToken();
		if ($next->type != ":") {
			$this->error("Expected ':'", $next);
		}

		$type = $this->getToken();
		if ($type->type != 'TYPE' && $type->type != 'IDENTIFIER') {
			$this->error("Expected type", $type);
		}

		if (!$this->namespace->getType($type->text)) {
			$this->error("Expected type", $type);
		}

		$node = Node::fromToken($ident);

		$node->typedef = $type;

		return $node;
	}

	/**
	 * 
	 */
	public function parseValue() {

		$value = $this->getToken();

		if (!in_array($value->type, ['NUMBER', 'STRING'])) {
			$this->error("Expected value", $value);
		}

		return $value;
	}
}


require_once 'parser/prepass.php';

