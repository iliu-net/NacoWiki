<?php
/*
NacoWiki HTML
This plugin processes HTML
*/

#
# Template:
# <html>
#  <head>
#    <title>xxx</title>
#    text is assume url encoded, so use
#	%25 to insert a %
#	and %22 to insert a quote.
#    <meta name="key" content="value">
#  </head>
#  <body>
#    Content
#  </body>
# </html>
#
use NWiki\PluginCollection as Plugins;
use NWiki\Core as Core;

class PluginHTML {
  const VERSION = '2.0.0';

  static function readStruct(string $source,array &$meta) : string {
    if (false === ($i = stripos($source,'<body>'))) return $source;

    $j = stripos($source,'</body>');
    $b = strlen('<body>');
    if ($j === false || $j < ($i+$b)) $j = strlen($source);
    $payload = substr($source, $i+$b, $j - $i -$b);

    if (false === ($i = stripos($source,'<head>'))) return $payoad;
    $start = $i+strlen('<head>');
    if (false === ($i = stripos($source,'</head>',$start))) return $payload;
    $end = $i;

    foreach (explode("\n", substr($source,$start,$end)) as $line) {
      if (preg_match('/<title>(.*)<\/title>/',$line,$mv)) {
	$meta['title'] = htmlspecialchars_decode($mv[1]);
	continue;
      }
      if (preg_match('/<meta\s+name="([^"]*)"\s+content="([^"]*)"\s*>/',$line,$mv)) {
	$meta[urldecode($mv[1])] = urldecode($mv[2]);
      }
    }
    return $payload;
  }
  static function makeSource(array $meta,string $body) : string {
    $hdr = '';
    if (count($meta)) {
      $tr = [ '"' => '%22', '%' => '%25' ];
      $hdr = '  <head>'.PHP_EOL;
      $hdr .= '    <!-- texts in meta tags are assumed to be url encoded -->'.PHP_EOL;
      $hdr .= '    <!--    Use "%22" to insert a quote (") -->'.PHP_EOL;
      $hdr .= '    <!--    Use "%25" to insert a "%" -->'.PHP_EOL;

      foreach ($meta as $k=>$v) {
	if ($k == 'title') {
	  $hdr .= '    <title>'.htmlspecialchars($v,ENT_NOQUOTES|ENT_HTML401|ENT_SUBSTITUTE).'</title>'.PHP_EOL;
	} else {
	  $hdr .= '    <meta name="'.strtr($k,$tr).'" content="'.strtr($v,$tr).'">'.PHP_EOL;
	}
      }
      $hdr .= '    <!--meta name="example-key" content="example-value"-->'.PHP_EOL;
      $hdr .= '  </head>'.PHP_EOL;
    }
    return '<html>'.PHP_EOL.$hdr.'  <body>'.PHP_EOL.trim($body).PHP_EOL.'  </body>'.PHP_EOL.'</html>';
  }

  static function preSave(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $meta = [];
    $body = self::readStruct($ev['text'], $meta);
    $ev['text'] = self::makeSource($meta,$body);
    return Plugins::OK;
  }

  static function read(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['payload'] = self::readStruct($ev['source'], $ev['meta']);
    return Plugins::OK;
  }
  static function edit(\NacoWikiApp $wiki, array &$data) : ?bool {
    $meta = $wiki->meta;
    $payload =  self::readStruct($wiki->source, $meta);
    $wiki->source = self::makeSource($meta,$payload);

    Core::codeMirror($wiki,[
      'js' => [
	'mode/xml/xml.min.js',
	'mode/javascript/javascript.min.js',
	'mode/css/css.min.js',
	'mode/htmlmixed/htmlmixed.min.js',
      ],
      'mode' => 'htmlmixed',
    ]);

    exit();
  }
  static function load(array $cfg) : void {
    Plugins::registerMedia(['html','htm'], self::class);
  }
}
