<?php

namespace SaturnScript\Target;


class Target {

	public $namespace;
	public $depth = 0;

	public $scope = [];

	public $currentNode;
	public $currentComment;
	public $comment_index = 0;

	public $output = [];

	public function error($str, $node = null) {
		throw new \Exception($node ? $node->error($str) : $str);
	}

	/**
	 *
	 */
	protected function output($str, $type) {

		$ns = $this->namespace;
		$file = $ns . '.' . $type;

		if (!isset($this->output[$type])) {
			$this->output[$type] = [];
		}

		if (!isset($this->output[$type][$file])) {
			$this->output[$type][$file] = "";
		}

		$this->output[$type][$file] .= $str;
	}


}

