<?php
/** HTML renderer
 *
 * Media handler for HTML
 * @package Plugins
 * @phpcod Plugins##PluginHTML
 */

use NWiki\PluginCollection as Plugins;
use NWiki\Core as Core;

/** HTML media handler
 *
 * NacoWiki HTML render.
 *
 * This plugin is used to handle HTML files.  Implements a media
 * handler interface.
 *
 * To maintain the HTML syntax, HTML documents must follow this template:
 *
 * ```html
 * <html>
 *   <head>
 *     <!-- texts in meta tags are assumed to be url encoded -->
 *     <!--    Use "%22" to insert a quote (") -->
 *     <!--    Use "%25" to insert a "%" -->
 *     <title>Test HTML document</title>
 *     <meta name="sample" content="meta-data">
 *     <!--meta name="example-key" content="example-value"-->
 *   </head>
 *   <body>
 *     HTML content
 *   </body>
 * </html>
 * ```
 *
 * Note, only the HTML between `<body>` and `</body>` will be rendered.
 * Also, the meta data is read from the `<head>` section.  However,
 * only the lines with `<title>` and `<meta>` tags are recognized.
 *
 * The `<title>` contents uses `htmlspecialchars` for escaping.  On the
 * other hand, the content of the `<meta>` is URL encoded at least for the
 * `%` (`%25`) and `"` (`%22`) characters.
 *
 *
 * @phpcod PluginHTML
 */

class PluginHTML {
  /** var string */
  const VERSION = '2.0.0';
  /** Read structured data
   *
   * Extracts meta data tags embedded in source code and separates
   * the <body> </body> from the HTML source.
   *
   * @param string $source text to process
   * @param array &$meta receives the read meta data
   * @param string $ext media file extension
   * @return string Returns payload within <body> ... </body> tags.
   */
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
  /** Wraps metadata and HTML content
   *
   * Wraps the metadata and HTML content in a formatted
   * HTML document that follows the template.
   *
   * @param array &meta meta data
   * @param string $body HTML payload
   * @return string full HTML document (with <head> and <body> sections).
   */
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
  /** preSave event handler
   *
   * Handles `preSave:[ext]` events.
   *
   * Reads from the submitted text the <head></head> and parses
   * metadata.  Also, extracts the <body></body> section.
   * It then rewraps into a templated document.
   *
   * This makes the saved file in a consistent format.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function preSave(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $meta = [];
    $body = self::readStruct($ev['text'], $meta);
    $ev['text'] = self::makeSource($meta,$body);
    return Plugins::OK;
  }
  /** Read event handler
   *
   * Handles `read:[ext]` events.
   *
   * Reads the source code and extract meta data tags from the
   * <head></head> section.
   *
   * Also extracts the <body></body and returns it as the payload.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function read(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['payload'] = self::readStruct($ev['source'], $ev['meta']);
    return Plugins::OK;
  }
  /** Edit event handler
   *
   * Handles `edit:[ext]` events.
   *
   * It creates a CodeMirror editor page with the configuration
   * for HTML editing.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */

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
  /**
   * Loading entry point for this class
   *
   * Hooks HTML media implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::registerMedia(['html','htm'], self::class);
  }
}
