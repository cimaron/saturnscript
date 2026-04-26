<?php

namespace SaturnScript\Target;


class TargetJs extends Target {

	public function code($str) {
		$this->output($str, 'js');
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
		}

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

		foreach ($nsNode->children as $decl) {
			$this->generateDecl($decl);
		}
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

		$name = $const->text;
		$value = $const->value->text;

		$this->code(sprintf("const %s = %s;\n\n", $name, $value));
	}

	/**
	 *
	 */
	public function generateLet($let) {

		$name = $let->text;
		$type = $let->typedef->text;

		$jsType = $this->getType($type);

		$tab = str_repeat("\t", $this->depth);

		$this->code($tab . sprintf("let %s;\n\n", $name));
	}

	/**
	 *
	 */
	public function generateClass($class) {

		$name = $class->text;

		$tab = str_repeat("\t", $this->depth);

		$this->code($tab . "class $name {\n");

		//$this->generateClassMembers($class);

		$this->code($tab . "}\n\n");

		//$this->generateClassMethods($class);
	}
}

