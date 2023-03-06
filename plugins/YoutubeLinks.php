<?php
/** YoutubeLinks
 *
 * Shortcode for youtube links
 *
 * @package Plugins
 * @phpcod Plugins##YoutubeLinks
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Cli as Cli;


/** Shortocdes for youtube links
 *
 * This plugin is used to create youtube links from shortcodes.
 *
 * Link format:
 *
 * - `[[`
 * - `youtube:`
 * - __youtube id__
 * - Optionally append `^` to open in a new window
 * - Optionally append `|` and text to show text when hovering
 * - `]]`
 *
 * @phpcod YoutubeLinks
 * @link https://stackoverflow.com/questions/11804820/how-can-i-embed-a-youtube-video-on-github-wiki-pages
 */
class YoutubeLinks {
  /** var string */
  const VERSION = '1.0';


  /** Youtube Links implementation
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $html Input text
   * @return string text with expanded links
   */
  static function makeLinks($wiki, $html) {
      $vars = [];
      if (preg_match_all('/\[\[\s*youtube:([^\]\n]+)\]\]/',$html,$mv)) {

	foreach ($mv[0] as $id=>$k) {
	  $v = $mv[1][$id];
	  $t = '';
	  if (false !== ($i = strpos($v,'|'))) {
	    $t = ' title="'.htmlspecialchars(substr($v,$i+1)).'"';
	    $v = substr($v,0,$i);
	  } else {
	    $t = '';
	  }
	  if (substr($v,-1) == '^') {
	    $w=' target="_blank"';
	    $v = substr($v,0,strlen($v)-1);
	  } else {
	    $w='';
	  }

	  $sc = '';
	  $sc .= '<a';
	  $sc .= ' href="https://www.youtube.com/watch?v='.$v.'"';
	  $sc .= $t;
	  $sc .= $w;
	  $sc .= '>';
	  $sc .= '<img src="https://img.youtube.com/vi/'.$v.'/0.jpg" width=320 height=240>';
	  $sc .= '</a>'.PHP_EOL;
	  $vars[$k] = $sc;
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
      $ev['html'] = self::makeLinks($wiki, $ev['html']);
      return Plugins::OK;
    });
  }
}
