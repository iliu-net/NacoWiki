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
 * The included article can contain barriers of the form:
 *
 * * `%`
 * * `include-start`
 * * `%`
 *
 * Or
 *
 * * `%`
 * * `include-stop`
 * * `%`
 *
 * Whene these are present, only the lines between the barriers will be
 * included.  If the start barrier is missing, it will begin from the
 * start of the article.  If the stop barrier is missing, it will include
 * all the way to the end of the article.
 *
 * Barriers can be disabled using the syntax: `$include: file --all`.
 *
 * You can change the `include` part of the barrier to a custom marker
 * using the syntax: `$include: file --mark=custom $`.  This example
 * would change the barriers from `include-start, include-stop` to
 * `custom-start, custom-stop`.
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

    while (preg_match('/\n[ \t]*\$include:\s*([^\$]+)\$[ \t]*\n/', PHP_EOL.$html.PHP_EOL,$mv,PREG_OFFSET_CAPTURE,$offset)) {
      $newhtml .= substr($html,$offset,$mv[0][1]-1-$offset);

      $incfile = trim($mv[1][0]);
      //~ Cli::stderr($incfile,Cli::TRC);
      //~ Util::log('incfile: '.$incfile);

      if (isset($lcache[$incfile])) {
	# If we include the same file multiple times, this will
	# speed up things...
	//~ Util::log('CACHE HIT: '.$incfile);
	$newhtml .= $lcache[$incfile];
      } else {
	//~ Util::log('CACHE MISS: '.$incfile);
	$v = preg_split('/\s+/',$incfile);
	//~ Util::log('v: '.Util::vdump($v));

	$incurl = Util::sanitize(count($v) ? array_shift($v) : $incfile, $doc);

	$c = realpath($files_dir.$incurl).PHP_EOL.implode(':',$v);
	//~ Util::log('c:'.(isset($limit[$c]) ? '(HIT)' : '(MIS)'). ': '.Util::vdump($c));
	if (isset($limit[$c])) {
	  // Prevent loops
	  $xhtml = PHP_EOL;
	  $xhtml .= '> ERROR: "'.$incfile.'" has already been included ';
	  $xhtml .= 'at '.$limit[$c].PHP_EOL;
	  $xhtml .= PHP_EOL;
	} else {
	  $limit[$c] = $doc.':'.$mv[1][1];
	  //~ Util::log('LIMIT(c): '.$limit[$c]);
	  if (file_exists($files_dir.$incurl)) {
	    $event = Core::preparePayload($wiki, $incurl);
	    //~ Util::log('RECURSE_IN: '.$limit[$c]);
	    $xhtml = PHP_EOL.self::mdx_include($wiki, $incurl, $event['payload'], $limit);
	    //~ Util::log('RECURSE_OUT: '.$limit[$c]);

	    //~ Util::log('vCHECK: '.Util::vdump($v));
	    if (!in_array('--all',$v)) {
	      // Handle markers
	      //~ Util::log('opts: '.Util::vdump($v));

	      $start_marker = '/\n[ \t]*%include-start%[ \t]*\n/';
	      $end_marker = '/\n[ \t]*%include-stop%[ \t]*\n/';
	      foreach($v as $i) {
		if (preg_match('/^--mark=(\S+)$/',$i,$opt)) {
		  $start_marker = '/\n[ \t]*%' . preg_quote($opt[1]). '-start%[ \t]*\n/';
		  $end_marker = '/\n[ \t]*%' . preg_quote($opt[1]). '-stop%[ \t]*\n/';
		}
	      }
	      if (preg_match($start_marker,PHP_EOL.$xhtml.PHP_EOL, $marker, PREG_OFFSET_CAPTURE)) {
		$xhtml = substr($xhtml,$marker[0][1]+strlen($marker[0][0])-1);
	      }
	      if (preg_match($end_marker,PHP_EOL.$xhtml.PHP_EOL, $marker, PREG_OFFSET_CAPTURE)) {
		$xhtml = substr($xhtml,0, $marker[0][1]);
		//~ Util::log('POS xhtml: '.Util::vdump($xhtml));
	      }
	    }
	  } else {
	    $xhtml = PHP_EOL;
	    $xhtml .= '> ERROR: '.$incfile.' does not exists!'.PHP_EOL;
	    $xhtml .= PHP_EOL;
	  }
	}
	$lcache[$incfile] = $xhtml;
	$newhtml .= $xhtml;
      }
      $offset = $mv[0][1]+strlen($mv[0][0])-2;
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
