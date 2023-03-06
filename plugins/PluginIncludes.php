<?php
/** Include file
 *
 * This plugin is used to include files
 *
 * @package Plugins
 * @phpcod Plugins##PluginIncludes
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

/** PluginIncludes
 *
 * This plugin can be used to include files into a document before
 * rendering.
 *
 * In a new line use: `$include: file $` to include a file.
 *
 * Include file paths are to the current directory unless they start
 * with '/'.  In that case they are relative to the root of the
 * wiki.
 *
 * Included files can further include more files if needed.
 *
 * @phpcod PluginIncludes
 */
class PluginIncludes {
  /** @var string */
  const VERSION = '2.1.0';

  /** Include file implementation
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $doc Document URL
   * @param string $html Input text
   * @return string text with included files
   */
  static function mdx_include(\NacoWikiApp $wiki,string $doc, string $html ,array &$limit = NULL) : string {
    //~ Cli::stderr(Util::vdump($limit),Cli::TRC);
    if (is_null($limit)) $limit = [];
    $files_dir = $wiki->cfg['file_store'];
    $lcache = [];

    $offset = 0;
    $newhtml = '';

    while (preg_match('/\n\s*\$include:\s*([^\$]+)\$\s*\n/', '\n'.$html.'\n',$mv,PREG_OFFSET_CAPTURE,$offset)) {
      $newhtml .= substr($html,$offset,$mv[0][1]-1-$offset);


      $incfile = trim($mv[1][0]);
      //~ Cli::stderr($incfile,Cli::TRC);
      if (isset($lcache[$incfile])) {
	# If we include the same file multiple times, this will
	# speed up things...
	$newhtml .= $lcache[$incfile];
      } else {
	//~ Util::log(Util::vdump($incfile));
	$incurl = Util::sanitize($incfile, $doc);
	//~ Util::log(Util::vdump($incurl));

	$c = realpath($files_dir.$incurl);
	if (isset($limit[$c])) {
	  // Prevent loops
	  $xhtml = PHP_EOL;
	  $xhtml .= '> ERROR: "'.$incfile.'" has already been included ';
	  $xhtml .= 'at '.$limit[$c].PHP_EOL;
	  $xhtml .= PHP_EOL;
	} else {
	  $limit[$c] = $doc.':'.$mv[1][1];
	  if (file_exists($files_dir.$incurl)) {
	    $event = Core::preparePayload($wiki, $incurl);
	    $xhtml = PHP_EOL.self::mdx_include($wiki, $incurl, $event['payload'], $limit);
	  } else {
	    $xhtml = PHP_EOL;
	    $xhtml .= '> ERROR: '.$incfile.' does not exists!'.PHP_EOL;
	    $xhtml .= PHP_EOL;
	  }
	}
	$lcache[$incfile] = $xhtml;
	$newhtml .= $xhtml;
      }
      $offset = $mv[0][1]+strlen($mv[0][0])-3;
    }

    $newhtml .= substr($html,$offset);
    return $newhtml;
  }

  /**
   * Loading entry point for this class
   *
   * Hooks pre-render event implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::registerEvent('pre-render', function(\NacoWikiApp $wiki, array &$ev) {
      $ev['html'] = self::mdx_include($wiki, $wiki->page, $ev['html']);
      return Plugins::OK;
    });
  }
}
