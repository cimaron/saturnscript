<?php

	public function toSS() {
		$method = "toSS" . $this->type;
		if (method_exists($this, $method)) {
			return $this->$method();
		}
		die("Can't translate $this->type");
	}

	protected function children($depth = 0) {
		$out = "";
		foreach ($this->children as $child) {
			$out .= $child->toSS() . "\n" . str_repeat("\t", $depth);
		}
		return $out;
	}

	public function toSSGlobal() {
		return $this->children();
	}

	public function toSSImport() {
		return sprintf("import \"%s\";", $this->text);
	}

	public function toSSComment() {
		return sprintf(strpos($this->text, "\n") !== false ? "/*%s*/" : "//%s\n", $this->text);
	}

	public function toSSNamespace() {
		return sprintf("namespace %s\n{\t%s\n}\n", $this->text, implode("\n\t", $this->children(1)));
	}