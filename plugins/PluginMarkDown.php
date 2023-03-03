<?php
/**
 * NacoWiki MarkDown
 *
 * This plugin converts Markdown format to HTML
 *
 * @package Plugins\PluginMarkDown
 */

use NWiki\PluginCollection as Plugins;
use NWiki\Core as Core;

class PluginMarkdown {
  const VERSION = '2.0.0';

  static function readStruct(string $source,array &$meta) : string {
    if (!preg_match('/^\s*---\s*\n/',$source,$mv)) return $source;

    $start = $offset = strlen($mv[0]);

    if (!preg_match('/\n\s*---\s*\n/',$source,$mv,PREG_OFFSET_CAPTURE,$offset)) return $source;
    $end = $mv[0][1];
    $offset = $mv[0][1] + strlen($mv[0][0]);

    $yaml = yaml_parse(substr($source,$start,$end-$start));
    if ($yaml === false) return $source;

    $meta = array_merge($meta,$yaml);

    return substr($source,$offset);
  }
  static function makeSource(array $meta,string $body) : string {
    if (count($meta)) {
      $yaml = substr(yaml_emit($meta),0,-4).'---'.PHP_EOL;
    } else {
      $yaml = '';
    }
    return $yaml.$body;
  }
  static function render(\NacoWikiApp $wiki, array &$ev) : ?bool {
    require_once Plugins::path('lib/Parsedown-1.7.4.php');
    require_once Plugins::path('lib/ParsedownExtra-0.8.1.php');
    require_once Plugins::path('lib/TOC-1.1.2.php');
    require_once Plugins::path('lib/Extension.php');

    $Parsedown = new ParsedownExtension();
    $Parsedown->headown = 1;
    $ev['html'] = $Parsedown->text($ev['html']);
    return Plugins::OK;
  }

  static function edit(\NacoWikiApp $wiki, array &$data) : ?bool {
    $meta = $wiki->meta;
    $payload =  self::readStruct($wiki->source, $meta);
    $wiki->source = self::makeSource($meta,$payload);

    Core::codeMirror($wiki,[
      'js' => [
        'addon/edit/continuelist.min.js',
	'mode/xml/xml.min.js',
	'mode/javascript/javascript.min.js',
	'mode/markdown/markdown.min.js',
	'mode/php/php.min.js',
      ],
      'mode' => 'markdown',
    ]);
    exit();
  }

  static function preSave(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $meta = [];
    $body = self::readStruct($ev['text'], $meta);
    $ev['text'] = self::makeSource($meta,$body);
    return Plugins::OK;
  }

  static function load(array $cfg) : void {
    Plugins::registerMedia(['md','markdown','mkd','mdwn','mdown','mdtxt','mdtext'], self::class);
  }

  static function read(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['payload'] = self::readStruct($ev['source'], $ev['meta']);
    return Plugins::OK;
  }

}
