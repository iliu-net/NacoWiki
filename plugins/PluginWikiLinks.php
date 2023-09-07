<?php
/** PluginWikiLinks
 *
 * Implements Wiki-style links
 *
 * @package Plugins
 * @phpcod Plugins##PluginWikiLinks
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Cli as Cli;


/** Wiki style links
 *
 * This plugin is used to create links that have a shorter format
 * than Markdown style links.
 *
 * Simplified markup for internal links.  It supports:
 *
 * - hypertext links
 *   - `[[` : opening
 *   - __url-path__.
 *   - ==space== followed by html attribute tags (if any, can be omitted)
 *   - `|` followed by the link text if not specified, defaults to the
 *     __url-path__
 *   - optional modifier character.  Could be one of the following:
 *     - `^` : Open in new window (shortcut to target="_blank")
 *     - `$` : Create an editing link (with ?do=edit)
 *     - `*` : normal link with an attached PENCIL link next to it.
 *   - `]]` : closing
 * - img tags
 *   - `{{` : opening
 *   - __url-path__
 *   - ==space== followed by html attribute tags (if any, can be omitted)
 *   - `|` followed by the `alt` and `title` text.  Defaults to
 *     __url-path__.
 *   - `}}` : closing
 *
 *
 * URL paths rules:
 *
 * - paths beginning with `/` are relative to the root of the wiki
 * - paths beginning with `!/` search for full file paths that end with
 *   that path in the entire wiki.
 * - paths beginnig with `!` (without `/`) match basename in the entire wiki.
 * - paths are relative to the current document.
 *
 * @phpcod PluginWikiLinks
 */
class PluginWikiLinks {
  /** var string */
  const VERSION = '1.2.0';

  /** file list cache
   * var string[] */
   static $tree_cache = NULL;

  /** WikiLinks implementation
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $html Input text
   * @return string text with expanded links
   */
  static function wikiLinks($wiki, $html) {
      $vars = [];
      foreach ([
	  '/\[\[[^\]\n:]+\]\]/' => '<a href="%1$s"%3$s>%2$s</a>',
	  '/\{\{[^\}\n:]+\}\}/' => '<img src="%1$s" alt="%2$s" title="%2$s"%3$s>',
	] as $re=>$fmt) {
	if (preg_match_all($re,$html,$mv)) {
	  foreach ($mv[0] as $k) {
	    $v = substr($k,2,-2);
	    if (false !== ($i = strpos($v,'|'))) {
	      $t = substr($v,$i+1);
	      $v = substr($v,0,$i);
	    } else {
	      $t = null;
	    }
	    # Check if there are flags
	    $flags = [];
	    while (strlen($v) > 1) {
	      $i = substr($v,-1,1);
	      if ($i == '^' || $i == '*' || $i == '$') {
		$flags[$i] = $i;
		$v = substr($v,0,-1);
	      } else break;
	    }
	    if (empty($v)) continue;
	    $v = preg_split('/\s+/',$v,2);
	    if (count($v) == 0) continue;
	    $x = isset($v[1]) ? ' '.$v[1] : '';
	    $v = $v[0];
	    if (empty($v)) continue;

	    if (empty($t)) $t = htmlspecialchars(pathinfo($v)['filename']);


	    if (substr($v,0,1) == '!') {
	      # This is a name search operator
	      if (is_null(self::$tree_cache)) {
		list($dirs,$files) = Util::walkTree($wiki->filePath(''));
		self::$tree_cache = [];
		foreach (array_merge($dirs,$files) as $f) {
		  self::$tree_cache['/'.ltrim($f,'/')] = basename($f);
		}
	      }

	      $v = substr($v,1);
	      if (substr($v,0,1) == '/') {
		# path tail search...
		$found = false;
		foreach (self::$tree_cache as $i => $j) {
		  if (!str_ends_with($i, $v)) continue;
		  $found = true;
		  $v = $i;
		  break;
		}
		if (!$found) continue;

	      } else {
		$i = array_search($v,self::$tree_cache);
		if ($i === false) continue;
		$v = $i;
	      }
	    }
	    $v = Util::sanitize($v,$wiki->page);
	    if (isset($flags['^'])) {
	      $x .= ' target="_blank"';
	    }
	    if (isset($flags['$'])) {
	      $v .= '?do=edit';
	    }

	    //~ Util::log('k: '.Util::vdump($k));
	    //~ Util::log('v: '.Util::vdump($v));
	    //~ Util::log('t: '.Util::vdump($t));
	    //~ Util::log('flags: '.Util::vdump($flags));
	    //~ Util::log('x: '.Util::vdump($x));

	    $vars[$k] = sprintf($fmt, $wiki->mkUrl($v), $t,$x);
	    if (isset($flags['*'])) {
	      $vars[$k] .= sprintf($fmt, $wiki->mkUrl($v,['do'=>'edit']), '&#x270E;', $x);
	    }

	    //~ Util::log('vars '.Util::vdump([$k => $vars[$k]]));

	  }
	}
      }
      if (count($vars) == 0) return $html;
      return strtr($html,$vars);
  }
  /**
   * Loading entry point for this class
   *
   * Hooks post-render event implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::registerEvent('post-render', function(\NacoWikiApp $wiki, array &$ev) {
      $ev['html'] = self::wikiLinks($wiki, $ev['html']);
      return Plugins::OK;
    });
  }
}
