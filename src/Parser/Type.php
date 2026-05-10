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


class Type {

	public static $builtins = [];

	public $name;
	public $size;

	public $class = false;
	public $function = false;
	
	public $members = [];
	
	/**
	 * @param   string   $name
	 * @param   int      $size
	 */
	public function __construct($name, $size = 1) {
		$this->name = $name;
		$this->size = $size;
	}

	/**
	 * Get memory size of type
	 *
	 * @return  int
	 */
	public function getSize() {

		$size = $this->size;

		foreach ($this->members as $member) {
			$size += $member->getSize();
		}

		return $size;
	}
}


Type::$builtins['void'] = new Type('void', 0);


Type::$builtins['u8'] = new Type('u8', 1);
Type::$builtins['i8'] = new Type('i8', 1);

Type::$builtins['u16'] = new Type('u16', 2);
Type::$builtins['i16'] = new Type('i16', 2);

Type::$builtins['u32'] = new Type('u32', 4);
Type::$builtins['i32'] = new Type('i32', 4);

Type::$builtins['fx8'] = new Type('fx8', 4);
Type::$builtins['fx16'] = new Type('fx16', 4);
Type::$builtins['fx24'] = new Type('fx24', 4);

