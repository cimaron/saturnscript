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

use SaturnScript\Parser\Node\AssignNode;
use SaturnScript\Parser\Node\ControlNode;
use SaturnScript\Parser\Node\Node;
use SaturnScript\Parser\Node\PrimaryNode;


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

		$nsNode->text = $this->namespace->name;
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
	 * Parse type
	 */
	public function parseType() {

		$token = $this->getToken();

		if ($token->type != 'TYPE' && $token->type != 'IDENTIFIER') {
			$this->error("Expected type", $token);
		}

		$type = $this->namespace->getType($token->text);

		//Do i need to remove preoprocess for other sections?
		if (!$this->state->preprocess && !$type) {
			$this->error("Expected type", $token);
		}

		return $type;
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

		$letNode->typedef = $this->parseType();

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

		$this->namespace->addType($identifier->text);

		$this->state->inClass = $identifier->text;

		$this->expect("{");

			$classNode = new Node($identifier, 'CLASS');

			//Add to symbol table
			$this->namespace->addSymbol($classNode->text, $classNode);
			$type = new Type($classNode->text);

				while ($member = $this->parseMember()) {
					$classNode->push($member);
				}

		$this->expect("}");

		$this->state->inClass = false;

		return $classNode;
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

		$node->typedef = $this->parseType();

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

		if ($ident->text == 'constructor' || !$this->nextIs(':')) {
			$node->typedef = $this->namespace->getType('void');			
		} else {
			$this->expect(":");
			$node->typedef = $this->parseType();
		}

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
		$paramNode = new Node($ident);

		$this->expect(':');

		$paramNode->typedef = $this->parseType();

		return $paramNode;
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

		if ($this->nextIs('(')) {
			$this->expect('(');
			$expression = $this->parseExpression();
			$this->expect(')');
			return $expression;
		}

		$lhs = $this->parsePostfix(false);

		//Assignment expression
		if ($lhs && $this->nextIs('=')) {
			return $this->parseAssignmentExpression($lhs);
		}

		$lhs = $lhs ?: $this->parsePostfix(true);

		if ($this->nextIs(['+', '-', '*', '/'])) {
			return $this->parseBinaryExpression($lhs);
		}

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

		if (!$this->state->inClass && $this->nextIs('THIS')) {
			$this->error("Using 'this' outside of class context");
		}

		if (!$this->nextIs(['IDENTIFIER', 'THIS'])) {
			return false;
		}

		$ident = $this->getToken();

		if ($ident->type == 'THIS') {

			$typename = $this->state->inClass;
			$type = $this->namespace->getSymbol($typename);

		/*
		} else {
			$symbol = $this->namespace->getSymbol($ident->text);
			if (!$symbol) {
				$this->error("'$ident->text' is not defined");
			}
			*/
		}

//		exit;

		//object traversal
		if ($this->nextIs('.')) {
			$objectNode = new Node($ident);
			$accessNode = $this->parseMemberAccess($objectNode);
			return $accessNode;
		}

		//array access

		$exprNode = new PrimaryNode($ident);

		return $exprNode;
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
		$assignNode->value = $expression;

		return $assignNode;
	}

	/**
	 *
	 */
	public function parseMemberAccess($objectNode) {

		//First check if it's an object
		$classSymbol = $this->namespace->getSymbol($this->state->inClass);
		
		//Get symbol to check for members
		if (!$this->state->preprocess) {
		}

		$access = $this->expect('.');
		$accessNode = new Node($access);

		$property = $this->expect('IDENTIFIER');
		$accessNode->property = new Node($property);

		//Check member is part of symbol and type
		if (!$this->state->preprocess) {
		}

		if ($this->nextIs('.')) {
			$accessNode->object = $this->parseMemberAccess($objectNode);
			return $accessNode;
		}

		$accessNode->object = $objectNode;

		return $accessNode;
	}

	/**
	 *
	 */
	public function parseBinaryExpression($lhs) {

		$operator = $this->expect(['+', '-', '*', '/']);

		$rhs = $this->parseExpression();

		$binaryNode = new Node($operator);

		$binaryNode->left = $lhs;
		$binaryNode->right = $rhs;

		return $binaryNode;
	}
}

