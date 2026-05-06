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

namespace SaturnScript\Target;


class TargetC extends Target {

	public function code($str) {
		$this->output($str, 'c');
	}

	public function header($str) {
		$this->output($str, 'h');
	}

	public function indent($offset = 0, $char = "\t") {
		return str_repeat($char, $this->depth + $offset);
	}

	public function comment($str, $file = 'c') {

		if (strpos($str, "\n") !== false) {
			$str = "/*" . $str . "*/\n";
		} else {
			$str = "//" . $str . "\n";
		}

		$this->output($str, $file);
	}

	/**
	 *
	 */
	public function getType($type) {

		switch ($type) {
			case 'u8':
				return 'unsigned char';
			case 'u16':
				return 'unsigned short';
			case 'u32':
				return 'unsigned int';
			case 'i8':
				return 'char';
			case 'i16':
				return 'short';
			case 'i32':
				return 'int';
			case 'string':
				return 'char*';
			case 'fx16':
				return 'int';
			case 'void':
				return 'void';
		}

		//$typeData = $this->types->get($type);

		$type = sprintf("__%s__%s*", $this->namespace, $type);

		return $type;
	}

	/**
	 *
	 */
	public function generate($ast) {

		foreach ($ast->children as $child) {
			$this->generateNamespace($child);
		}
	}

	/**
	 *
	 */
	public function generateNamespace($nsNode) {

		$this->namespace = $nsNode->text;
		$this->types = $nsNode->types;

		$header = sprintf("__%s_H_", strtoupper($this->namespace));

		$this->header(sprintf("#ifndef %s\n#define %s\n\n", $header, $header));
		$this->code(sprintf("#include \"%s.h\"\n\n", $this->namespace));

		foreach ($nsNode->children as $decl) {
			$this->generateDecl($decl);
		}

		$this->header(sprintf("#endif\n"));
	}

	/**
	 *
	 */
	public function generateDecl($decl) {

		switch ($decl->type) {

			case 'CONST':
				$this->generateConst($decl);
				return;

			case 'LET':
				$this->generateLet($decl);
				return;

			case 'CLASS':
				$this->generateClass($decl);
				return;

			default:
		}

		$this->error("Not implemented", $decl);
	}

	/**
	 *
	 */
	public function generateConst($const) {

		$name = sprintf("__%s__%s", $this->namespace, $const->text);
		$value = $const->value->text;

		$this->code(sprintf("#define %s %s\n\n", $name, $value));
	}

	/**
	 *
	 */
	public function generateLet($let) {

		$name = sprintf("__%s__%s", $this->namespace, $let->text);
		$type = $let->typedef;

		$cType = $this->getType($type->name);

		$this->code($this->indent() . sprintf("%s %s;\n\n", $cType, $name));
	}

	/**
	 *
	 */
	public function generateClass($class) {

		$name = sprintf("__%s__%s", $this->namespace, $class->text);

		$this->code($this->indent() . "typedef struct {\n");

			$this->depth++;
			$this->generateClassMembers($class);
			$this->depth--;

		$this->code($this->indent() . "} $name;\n\n");

		$this->generateClassMethods($class);
	}

	/**
	 * 
	 */
	public function generateClassMembers($class) {

		foreach ($class->children as $member) {

			$type = $this->getType($member->typedef->name);

			if (!$type || $member->params !== null) {
				continue;
			}

			$this->code($this->indent() . sprintf("%s %s;\n", $type, $member->text));
		}
	}

	/**
	 * 
	 */
	public function generateClassMethods($class) {

		foreach ($class->children as $member) {
			$type = $this->getType($member->typedef->name);

			if (!$type || $member->params === null) {
				continue;
			}

			$this->code($this->indent() . sprintf("%s __%s__%s(", $type, $this->namespace, $member->text));

				//arguments
				$params = [];
				foreach ($member->params as $param) {
					$params[] = $this->generateClassMethodArgument($param);
				}

				$this->code(implode(", ", $params));

			$this->code(") {\n");

				$this->depth++;
				$this->generateStatements($member->children);
				$this->depth--;

			$this->code($this->indent() . "}\n\n");
		}
	}

	/**
	 * 
	 */
	public function generateClassMethodArgument($param) {

		$type = $this->getType($param->typedef->name);

		$out = sprintf("%s %s", $type, $param->text);

		return $out;
	}

	/**
	 *
	 */
	public function generateStatements($statements) {

		foreach ($statements as $statement) {
			$this->generateStatement($statement);
		}
	}

	/**
	 *
	 */
	public function generateStatement($statement) {

		switch ($statement->type) {

			case 'IF':
				$this->generateIf($statement);
				break;

			default:
				$this->code($this->indent());
				$this->generateExpression($statement);
				$this->code(";\n");
		}
	}

	/**
	 *
	 */
	public function generateIf($if) {

		$this->code($this->indent() . sprintf("if ("));
			$this->generateExpression($if->condition);
		$this->code(") {\n");

			$this->depth++;
			$this->generateStatements($if->body);
			$this->depth--;

		$this->code($this->indent() . sprintf("}\n"));
	}

	/**
	 *
	 */
	public function generateExpression($expression) {

		$this->code("(");

		switch ($expression->type) {

			case '=':
				$this->generateAssignExpression($expression);
				break;

			case 'IDENTIFIER':
				$this->generatePrimaryExpression($expression);
				break;

			case '.':
				$this->generatePostfixExpression($expression);
				break;

			case '+':
			case '-':
			case '*':
			case '/':
				$this->generateBinaryExpression($expression);
				break;

			default:
				$this->code('[expression]');
		}

		$this->code(")");
	}

	/**
	 *
	 */
	public function generateAssignExpression($expression) {

		$this->generatePostfixExpression($expression->target);
		$this->code(' = ');
		$this->generateExpression($expression->value);
	}

	/**
	 *
	 */
	public function generatePostfixExpression($expression) {

		switch ($expression->type) {

			case '.':
				$this->code(sprintf("%s->%s", $expression->object->text, $expression->property->text));
				return;
		}

		$this->code("[object]");
	}

	/**
	 *
	 */
	public function generatePrimaryExpression($expression) {

		switch ($expression->type) {
			case 'IDENTIFIER':
				$this->code($expression->text);
				break;

			 default:
			 	$this->code('[PRIMARY]');
		}

	}

	/**
	 *
	 */
	public function generateBinaryExpression($expression) {

		$this->code("(");

		$this->generateExpression($expression->left);

		$this->code(sprintf(" %s ", $expression->type));

		$this->generateExpression($expression->right);

		$this->code(")");
	}
}

