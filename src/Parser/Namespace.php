<?php

namespace SaturnScript\Parser;


class NamespaceObject {

	public $name = "";
	public $symbols = [];
	public $types;
	public $export = [];


	public function __construct($name) {
		$this->name = $name;
		$this->types = new Types();
	}

	public function pushScope() {
		array_unshift($this->symbols, []);
	}

	public function popScope() {
		array_shift($this->symbols);
	}

	public function addSymbol($name, $symbol) {
		$this->symbols[0][$name] = $symbol;
	}

	public function getSymbol($name, $local = false) {

		if ($local) {
			return $this->symbols[0][$name] ?? null;
		}

		foreach ($this->symbols as $table) {
			if (isset($table[$name])) {
				return $table[$name];
			}
		}
	}

	public function addType($name, $type = null) {
		return $this->types->add($name, $type ?? new Type($name));
	}

	public function getType($name) {
		return $this->types->get($name);
	}
}

