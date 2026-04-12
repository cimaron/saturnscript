<?php


class Type {

	public static $builtins = [];

	public $name;

	public $class = false;
	public $function = false;
	
	public $members = [];
	
	public function __construct($name) {
		$this->name = $name;
	}
}

class Types {

	public $list = [];

	public function get($name) {
		return $this->list[$name] ?? Type::$builtins[$name] ?? null;
	}

	public function add($name, $type) {
		$this->list[$name] = $type;
		return $type;
	}
}

Type::$builtins['void'] = new Type('void');


Type::$builtins['u8'] = new Type('u8');
Type::$builtins['i8'] = new Type('i8');

Type::$builtins['u16'] = new Type('u16');
Type::$builtins['i16'] = new Type('i16');

Type::$builtins['u32'] = new Type('u32');
Type::$builtins['i32'] = new Type('i32');

Type::$builtins['fx8'] = new Type('fx8');
Type::$builtins['fx16'] = new Type('fx16');
Type::$builtins['fx24'] = new Type('fx24');

