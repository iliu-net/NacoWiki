<?php
/** Source code renderer
 *
 * Media handler for source code files.
 *
 * @package Plugins
 * @phpcod Plugins##PluginCode
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Core as Core;

/** Implements media handling for programming source code
 *
 * This media handler handles programing languages source code.  This
 * is useful for storing code snippets in the wiki.
 *
 * Source code meta-data can be stored in the file as comments:
 *
 * For PHP:
 * ```php
 * ##---
 * ## title: my-php-file.php
 * ## date: 2023-03-05
 * ##---
 * ```
 *
 * For python:
 * ```python
 * ##---
 * ## title: sample-python.py
 * ## date: 2023-03-25
 * ##---
 * ```
 *
 * ## Adding additional languages
 *
 * To add an additional language, create anew entry in the TYPES
 * constant.
 *
 * - array-key : should be the main file extension for this file type.
 *
 * Each array entry contains:
 *
 * - `exts` (optional) - array with additional file extensions
 * - `meta-re-start` - regular expression used to match begining of metadata block
 * - `meta-re-end` - regular expression used to match end of metadata block
 * - `meta-re-line` - regular expression used to extract metadata line
 * - `hl-js-class' - syntax highlighting class.  Refer to [Highlight.js](https://github.com/highlightjs/highlight.js/blob/main/SUPPORTED_LANGUAGES.md)
 * - `cm-mode` - CodeMirror mode.  [See CodeMirror](https://github.com/codemirror/codemirror5/tree/master/mode)
 * - `cm-deps` - Additional dependancies for CodeMirror.
 * - `template` - Template to use to create new files.
 *
 * @todo Adding additional languages:
 * 	C/C++, sh/bash,batch, tcl/tk, perl, make, javascript, css
 * 	go, rust, jinja2, lua, properties, sql, yaml
 * @phpcod PluginCode
 * @link https://github.com/highlightjs/highlight.js/blob/main/SUPPORTED_LANGUAGES.md
 * @link https://github.com/codemirror/codemirror5/tree/master/mode
 */
class PluginCode {
  /** @var string */
  const VERSION = '1.0';
  /** @var mixed */
  const TYPES = [
    'yaml' => [
      'exts' => ['yaml','yml'],
      'meta-re-start' => '/^\s*##---\s*\n/m',
      'meta-re-end' => '/^\s*##---\s*\n/m',
      'meta-re-line' => '/^\s*##\s*([^:]+):\s*(.*)$/',
      'hl-js-class' => 'language-yaml',
      'cm-mode' => 'yaml',
      'cm-deps' => [
		"mode/yaml/yaml.js",
      ],
      'template' =>
		'#!/usr/bin/env python3'.PHP_EOL.
		'##---'.PHP_EOL.
		'## title: {title}'. PHP_EOL.
		'## date: {date}'. PHP_EOL.
		'##---'.PHP_EOL.
		''.PHP_EOL,
    ],
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
  /** Read structured data
   *
   * Extracts meta data tags embedded in source code
   *
   * @param string $source text to process
   * @param array &$meta receives the read meta data
   * @param string $ext media file extension
   * @return string Returns the source
   */
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
  /** Edit event handler
   *
   * Handles `edit:[ext]` events.
   *
   * It creates a CodeMirror editor page with the configuration
   * matching the source code language.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
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
  /** Render event handler
   *
   * Handles `render:[ext]` events.
   *
   * Escapes the source code and wraps it in an HTML block for syntax highlighting
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function render(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['html'] = '<pre><code class="'.
		self::TYPES[$ev['ext']]['hl-js-class'].'">'.
		htmlspecialchars($ev['html']) .
		'</code></pre>';
    return Plugins::OK;
  }
  /** Read event handler
   *
   * Handles `read:[ext]` events.
   *
   * Reads the source code and extract meta data tags.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function read(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['payload'] = self::readStruct($ev['source'], $ev['meta'], $ev['ext']);
    return Plugins::OK;
  }
  /**
   * Loading entry point for this class
   *
   * Hooks media implemented by this class
   */
  static function load(array $cfg) : void {
    foreach (self::TYPES as $lang=>$ldef) {
      $exts = $ldef['exts'] ?? $lang;
      Plugins::registerMedia($exts, self::class);
    }
  }
}

