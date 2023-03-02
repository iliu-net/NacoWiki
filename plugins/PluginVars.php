<?php
/*
PicoWiki vars
This plugin is used to render config and meta data on a page
*/
use NWiki\PluginCollection as Plugins;


class PluginVars {
  const VERSION = '2.0.0';

  static function load(array $cfg) : void {
    Plugins::registerEvent('post-render', function(\NacoWikiApp $wiki, array &$ev) {
      $vars = [];
      # We do it like this because these expansions may be expensive...
      if (strpos($ev['html'],'$plugins$') !== false) {
	$vars['$plugins$'] = '<ul>';
	foreach (Plugins::$plugins as $x=>$y) {
	  $v = eval('return '.$x.'::VERSION;');
	  $vars['$plugins$'] .= '<li>'.$x.' ('.$v.') : '.htmlspecialchars($y).'</li>';
	}
	$vars['$plugins$'] .= '</ul>';
      }
      if (strpos($ev['html'],'$attachments$') !== false) {
	$fpath = $wiki->page;
	$pi = pathinfo($fpath);
	if ($pi['basename'] == $wiki->cfg['default_doc']) {
	  $fpath = $pi['dirname'];
	} else {
	  $fpath = $pi['dirname'].'/'.$pi['filename'];
	}
	$fpath .= '/';
	$lst = [];
	$dp = @opendir($wiki->filePath($fpath));
	if ($dp !== false) {
	  while (false !== ($fn = readdir($dp))) {
	    if ($fn[0] == '.' || $fn == $wiki->cfg['default_doc']) continue;
	    $lst[$fpath.$fn] = $fn;
	  }
	  closedir($dp);
	}
	if (count($lst) == 0) {
	  $vars['$attachmetns$'] = '<p>No attachments</p>';
	} else {
	  natsort($lst);
	  $vars['$attachments$'] = '<ul>';
	  foreach ($lst as $dh => $fn) {
	    $vars['$attachments$'] .= '<li>'.
		  (is_dir($wiki->filePath($dh)) ? '&#x1F4C1;' : '&#x1F5CE;') .
		  ' <a href="'.$wiki->mkUrl($dh).'">'.
		  htmlspecialchars($fn).
		  '</a></li>';
	  }
	  $vars['$attachments$'] .= '</ul>';
	}
      }
      if (count($vars) > 0) $ev['html'] = strtr($ev['html'], $vars);
      return Plugins::OK;
    });

    Plugins::registerEvent('pre-render', function(\NacoWikiApp $wiki, array &$ev) {
      # variable substitutions #
      $vars = [
	'$url$' => $wiki->page,
      ];
      foreach ([
	    '' => $wiki->cfg['plugins'][basename(__FILE__,'.php')] ?? [],
	    'cfg.' =>$wiki->cfg,
	    'meta.'=>$wiki->meta,
	    'file.' =>$wiki
	  ] as $nsp => &$reg) {
	foreach ($reg as $k=>$v) {
	  $vars['$'.$nsp.$k.'$'] = is_array($v) ? yaml_emit($v) : $v;
	}
      }
      $ev['html'] =  strtr($ev['html'],$vars);
      return Plugins::OK;
    });
  }
}
