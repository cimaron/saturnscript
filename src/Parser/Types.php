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

