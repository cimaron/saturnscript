#!/usr/bin/php
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

require_once 'src/autoload.php';

if ($argc < 2) {
	echo "Usage: ss [file]";
	exit;
}

$options = getopt("o:", [], $rest_index);


$filename = $argv[$rest_index];


try {

	$parser = new SaturnScript\Parser\Parser();
	$parser->openFile($filename);

	$ast = $parser->parse();

} catch (\Exception $e) {
	die("Parse error at " . $e->getMessage());
}

$o = $options['o'] ?? false;

try {

	if (!$o || $o == 'ast') {
		//print_r($ast);
	} elseif ($o == 'c') {
		$code = new \SaturnScript\Target\TargetC();
		$code->generate($ast);
		$outdir = 'out_c';

	} elseif ($o == 'js') {
		require_once 'target/js.php';
		$code = new TargetJS();
		$code->generate($ast);
		$outdir = 'out_js';
	}

} catch (\Exception $e) {
	die("Generation error at " . $e->getMessage());
}

$outdir = getcwd() . "/". $outdir;
if (!is_dir($outdir)) {
	@mkdir($outdir);
}

foreach ($code->output as $type => $files) {
	foreach ($files as $name => $output) {
		$file = sprintf("%s/%s", $outdir, $name);
		file_put_contents($file, $output);
	}
}


