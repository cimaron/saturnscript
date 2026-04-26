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

				$node = new Node($token, 'IMPORT');
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

		if (!$this->nextIs('CONST')) {
			return false;
		}

		$this->getToken(); //consume CONST

		$list = [$this->parseConst()];
	
		while ($this->nextIs(',')) {
			$this->getToken(); //Consume ','
			$list[] = $this->parseConst();
		}

		$this->expect(';');

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

		$constNode = new Node($identifier, 'CONST');
		//$node->text = $identifier->text;

		$constNode->value = $this->expect(['NUMBER', 'STRING']);

		//Add to symbol table
		$this->namespace->addSymbol($constNode->text, $constNode);

		return $constNode;
	}

	/**
	 *
	 */
	public function parseLetList() {

		if (!$this->nextIs('LET')) {
			return false;
		}

		$this->getToken(); //consume LET

		$list = [$this->parseLet()];
	
		while ($this->nextIs(',')) {
			$this->getToken(); //Consume ','
			$list[] = $this->parseLet();
		}

		$this->expect(';');

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

		$letNode = new Node($identifier, 'LET');

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

		if ($this->nextIs([';', ','])) {
			return $letNode;
		}

		$this->expect('=');

		$letNode->value = $this->expect(['NUMBER', 'STRING']);

		return $letNode;
	}

	/**
	 *
	 */
	public function parseClass() {

		if (!$this->nextIs('CLASS')) {
			return false;
		}

		$this->getToken(); //consume CLASS

		$identifier = $this->expect("IDENTIFIER");

		if ($this->namespace->getSymbol($identifier->text, true)) {
			$this->error("$identifier->text already defined", $identifier);
		}

		$this->expect("{");

			$node = new Node($identifier, 'CLASS');

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

		if (!$this->nextIs('IDENTIFIER')) {
			return false;
		}

		$ident = $this->getToken();
		$node = new Node($ident);

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

		if (!$this->namespace->getType($type->text)) {
			$this->error("Expected type", $type);
		}

		$node->typedef = $type;

		$this->expect(';');

		return $node;
	}

	/**
	 * 
	 */
	public function parseMethod() {

		$ident = $this->getToken();
		$node = new Node($ident);

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

			$this->parseMethodBody($node);

		$this->expect("}");

		return $node;
	}

	/**
	 * 
	 */
	public function parseMethodParams() {

		$params = [];

		if ($this->nextIs(')')) {
			return $params;
		}

		$params[] = $this->parseMethodParam();
	
		while ($this->nextIs(',')) {
			$this->getToken(); //Consume ','
			$params[] = $this->parseMethodParam();
		}

		return $params;
	}

	/**
	 *
	 */
	public function parseMethodParam() {

		$ident = $this->expect('IDENTIFIER');

		$this->expect(':');

		$type = $this->getToken();
		if ($type->type != 'TYPE' && $type->type != 'IDENTIFIER') {
			$this->error("Expected type", $type);
		}

		if (!$this->namespace->getType($type->text)) {
			$this->error("Expected type", $type);
		}

		$node = new Node($ident);
		$node->typedef = $type;

		return $node;
	}

	/**
	 *
	 */
	public function parseMethodBody($methodNode) {

		foreach ($this->parseStatementList(true) as $statement) {
			$methodNode->push($statement);
		}
	}

	/**
	 *
	 */
	public function parseStatementList($allowScope = false) {

		$list = [];

		while ($statement = $this->parseStatement($allowScope)) {
			$list[] = $statement;
		}

		return $list;
	}

	/**
	 *
	 */
	public function parseStatement($allowScope = false) {

		if ($ctrl = $this->parseControlStatement()) {
			return $ctrl;
		}

		if ($allowScope && ($lets = $this->parseLetList())) {
			$this->expect(';');
			return $lets;
		}

		if ($expr = $this->parseExpression()) {
			$this->expect(';');
			return $expr;
		}
	}

	/**
	 * 
	 */
	public function parseValue() {

		if (!$this->nextIs(['NUMBER', 'STRING'])) {
			return false;
		}

		$value = $this->getToken();

		return new PrimaryNode($value);
	}

	/**
	 * 
	 */
	public function parseControlStatement() {

		if (!$this->nextIs(["IF", "FOR", "WHILE"])) {
			return false;
		}

		$ctrl = $this->getToken();
		$ctrlNode = new ControlNode($ctrl);

		$this->expect('(');

			if ($ctrl->type == 'IF' || $ctrl->type == 'WHILE') {
				$ctrlNode->condition = $this->parseExpression();
			} elseif ($ctrl->type == 'FOR') {
				$ctrlNode->condition = $this->parseForInit();
			}

		$this->expect(')');
		$this->expect('{');
			$ctrlNode->body = $this->parseStatementList(false);
		$this->expect('}');

		if ($ctrl->type == 'IF' && $this->nextIs('ELSE')) {
			$this->expect('{');
				$ctrlNode->else = $this->parseStatementList(false);
			$this->expect('}');
		}

		return $ctrlNode;
	}

	/**
	 * 
	 */
	public function parseExpression() {

		$lhs = $this->parsePostfix(false);

		//Assignment expression
		if ($lhs && $this->nextIs('=')) {
			return $this->parseAssignmentExpression($lhs);
		}

		$lhs = $lhs ?: $this->parsePostfix(true);
		//$lhs = $lhs ?: $this->parseUnary();
		$lhs = $lhs ?: $this->parseValue();

		if (!$lhs) {
			return false;
		}

		return $lhs;

		//$next = $this->getToken();


		//Primary expression
		$primaryNode = new Node($primary);
		//NEED TO ADD SYMBOL CHECKING

		//Postfix

		return $primaryNode;
	}

	/**
	 * Parse postfix expression
	 * $fncall determines whether to allow function calls for a normal expression
	 * omitting them allows for parsing LHS of an assignment expression
	 *
	 * @param bool $fncall
	 *
	 * @return Node
	 */
	public function parsePostfix($fncall = true) {

		if (!$this->nextIs(['IDENTIFIER', 'THIS'])) {
			return false;
		}

		$ident = $this->getToken();
		\xdebug_break();

		//Add array access

		//Add object traversal

		//
		return false;
	}

	/**
	 *
	 */
	public function parseAssignmentExpression($lhs) {

		//Do symbol checking here

		$equal = $this->getToken();
		$assignNode = new AssignNode($equal);

		$expression = $this->parseExpression();

		$assignNode->target = $lhs;
		$assignNode->value = $rhs;

		return $assignNode;
	}
}

