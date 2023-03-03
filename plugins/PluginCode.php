<?php
/**
 * Source code renderer
 *
 * @package Plugins\PluginCode
 */
##---
## title: one-shot
## tags: a,b,c
## my-darling: 3
##---
#
# C/C++, sh/bash, batch, tcl/tk, perl, make, javascript, css
# go, rust, jinja2, lua, properties, sql, yaml
# highlight: https://github.com/highlightjs/highlight.js/blob/main/SUPPORTED_LANGUAGES.md
# CodeMirror: https://github.com/codemirror/codemirror5/tree/master/mode


use NWiki\PluginCollection as Plugins;
use NWiki\Core as Core;

class PluginCode {
  const VERSION = '0.0.0';
  const TYPES = [
    'py' => [
      'meta-re-start' => '/^\s*##---\s*\n/m',
      'meta-re-end' => '/^\s*##---\s*\n/m',
      'meta-re-line' => '/^\s*##\s*([^:]+):\s*(.*)$/',
      'hl-js-class' => 'language-python',
      'cm-mode' => 'python',
      'cm-deps' => [
		"addon/edit/matchbrackets.js",
		"mode/python/python.js",
      ],
      'template' =>
		'#!/usr/bin/env python3'.PHP_EOL.
		'##---'.PHP_EOL.
		'## title: {title}'. PHP_EOL.
		'## date: {date}'. PHP_EOL.
		'##---'.PHP_EOL.
		''.PHP_EOL,
    ],
    'php' => [
      'exts' => ['php','phps'],
      'hl-js-class' => 'language-php',
      'cm-mode' => 'application/x-httpd-php',
      'cm-deps' => [
		"addon/edit/matchbrackets.js",
		"mode/htmlmixed/htmlmixed.js",
		"mode/xml/xml.js",
		"mode/javascript/javascript.js",
		"mode/css/css.js",
		"mode/clike/clike.js",
		"mode/php/php.js",
      ],
      'template' =>
		'<?php'.PHP_EOL.
		'##---'.PHP_EOL.
		'## title: {title}'. PHP_EOL.
		'## date: {date}'. PHP_EOL.
		'##---'.PHP_EOL.
		'phpinfo();'.PHP_EOL,
      'skip-re' => '/<?php\s+/',
      'meta-re-start' => '/^\s*##---\s*\n/m',
      'meta-re-end' => '/^\s*##---\s*\n/m',
      'meta-re-line' => '/^\s*##\s*([^:]+):\s*(.*)\s*$/',
    ],
  ];

  static function readStruct(string $source,array &$meta, string $ext) : string {
    $offset = 0;
    if (!empty(self::TYPES[$ext]['skip-re'])) {
      if (!preg_match(self::TYPES[$ext]['skip-re'],$source,$mv,PREG_OFFSET_CAPTURE,$offset)) return $source;
      $offset = $mv[0][1] + strlen($mv[0][0]);
    }
    if (!preg_match(self::TYPES[$ext]['meta-re-start'],$source,$mv,PREG_OFFSET_CAPTURE,$offset)) return $source;
    $start = $offset = $mv[0][1] + strlen($mv[0][0]);
    if (!preg_match(self::TYPES[$ext]['meta-re-end'],$source,$mv,PREG_OFFSET_CAPTURE,$offset)) return $source;
    $end = $mv[0][1];

    foreach (explode("\n",substr($source,$start,$end-$start)) as $ln) {
      if (! preg_match(self::TYPES[$ext]['meta-re-line'],$ln,$mv)) continue;
      $meta[$mv[1]] = $mv[2];
    }
    return $source;
  }
  //~ static function makeSource(array $meta,string $body) : string {
    //~ if (count($meta)) {
      //~ $yaml = substr(yaml_emit($meta),0,-4).'---'.PHP_EOL;
    //~ } else {
      //~ $yaml = '';
    //~ }
    //~ return $yaml.$body;
  //~ }


  //~ static function preSave(\NacoWikiApp $wiki, array &$ev) : ?bool {
    //~ $meta = [];
    //~ $body = self::readStruct($ev['text'], $meta);
    //~ $ev['text'] = self::makeSource($meta,$body);
    //~ return Plugins::OK;
  //~ }
  static function edit(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ext = $ev['ext'];
    $meta = $wiki->meta;

    if (!file_exists($wiki->filePath())) {
      $vars = [];
      foreach ($meta as $k=>$v) {
	$vars['{'.$k.'}'] = $v;
      }
      $wiki->source = strtr(self::TYPES[$ext]['template'] ?? '',$vars);
    }

    Core::codeMirror($wiki,[
      'js' => self::TYPES[$ext]['cm-deps'],
      'mode' => self::TYPES[$ext]['cm-mode'],
    ]);
    exit();
  }
  static function render(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['html'] = '<pre><code class="'.
		self::TYPES[$ev['ext']]['hl-js-class'].'">'.
		htmlspecialchars($ev['html']) .
		'</code></pre>';
    return Plugins::OK;
  }
  static function read(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['payload'] = self::readStruct($ev['source'], $ev['meta'], $ev['ext']);
    return Plugins::OK;
  }

  static function load(array $cfg) : void {
    foreach (self::TYPES as $lang=>$ldef) {
      $exts = $ldef['exts'] ?? $lang;
      Plugins::registerMedia($exts, self::class);
    }
  }
}

//~ $text = file_get_contents(__FILE__);
//~ $meta = [];
//~ $x = PluginCode::readStruct($text, $meta, 'php');
//~ var_dump([$meta,$x]);

//~ array_shift($argv);
//~ foreach ($argv as $i) {
  //~ $ext = strtolower(pathinfo($i)['extension'] ?? '');
  //~ $meta = [];
  //~ $x = PluginCode::readStruct(file_get_contents($i),$meta,$ext);
  //~ var_dump($meta);
//~ }


