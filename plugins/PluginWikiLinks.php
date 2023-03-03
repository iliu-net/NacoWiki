<?php
/**
 * Wiki-style links
 *
 * This plugin is used to create links that have a shorter format
 * than Markdown style links.
 *
 * @package Plugins\PluginWikiLinks
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;


class PluginWikiLinks {
  const VERSION = '1.0.0';

  static function wikiLinks($wiki, $html) {
      $vars = [];
      foreach ([
	  '/\[\[[^\]\n]+\]\]/' => '<a href="%1$s"%3$s>%2$s</a>',
	  '/\{\{[^\}\n]+\}\}/' => '<img src="%1$s" alt="%2$s" title="%2$s"%3$s>',
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

	    if (empty($v)) continue;
	    $v = preg_split('/\s+/',$v,2);
	    if (count($v) == 0) continue;
	    $x = isset($v[1]) ? ' '.$v[1] : '';
	    $v = $v[0];
	    if (empty($v)) continue;
	    if (empty($t)) $t = htmlspecialchars(pathinfo($v)['filename']);

	    //~ echo '<pre>';
	    //~ var_dump($k);
	    //~ var_dump($v);
	    //~ var_dump($x);
	    //~ var_dump($t);
	    //~ echo '</pre>';
	    //~ Util::log(Util::vdump($v));
	    $v = Util::sanitize($v,$wiki->page);
	    //~ Util::log(Util::vdump($v));

	    $vars[$k] = sprintf($fmt, $wiki->mkUrl($v), $t,$x);
	  }
	}
      }
      if (count($vars) == 0) return $html;
      return strtr($html,$vars);
  }

  static function load(array $cfg) : void {
    Plugins::registerEvent('post-render', function(\NacoWikiApp $wiki, array &$ev) {
      $ev['html'] = self::wikiLinks($wiki, $ev['html']);
      return Plugins::OK;
    });
  }
}
