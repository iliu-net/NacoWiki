<?php
/*
PicoWiki include
This plugin is used to include files
*/
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;

class PluginIncludes {
  const VERSION = '2.0.0';

  static function mdx_include($wiki,$doc, $html) {
    $files_dir = $wiki->cfg['file_store'];

    $offset = 0;
    $newhtml = '';

    while (preg_match('/\n\s*\$include:\s*([^\$]+)\$\s*\n/', '\n'.$html.'\n',$mv,PREG_OFFSET_CAPTURE,$offset)) {
      $newhtml .= substr($html,$offset,$mv[0][1]-1-$offset);


      $incfile = trim($mv[1][0]);
      //~ Util::log(Util::vdump($incfile));
      $incurl = Util::sanitize($incfile, $doc);
      //~ Util::log(Util::vdump($incurl));

      if (file_exists($files_dir.$incurl)) {
	$newhtml .= PHP_EOL;

	$event = Core::preparePayload($wiki, $incurl);
	$newhtml .= $event['payload'];

	$newhtml .= PHP_EOL;
      } else {
	$newhtml .= PHP_EOL;
	$newhtml .= '> ERROR: '.$incfile.' does not exists!'.PHP_EOL;
	$newhtml .= PHP_EOL;
      }

      $offset = $mv[0][1]+strlen($mv[0][0])-3;
      //~ echo 'NEXT: ,'.substr($html,$offset,25).','.PHP_EOL;
      //~ echo '</pre>';
    }

    $newhtml .= substr($html,$offset);
    return $newhtml;
  }

  static function load(array $cfg) : void {
    Plugins::registerEvent('pre-render', function(\NacoWikiApp $wiki, array &$ev) {
      $ev['html'] = self::mdx_include($wiki, $wiki->page, $ev['html']);
      return Plugins::OK;
    });
  }
}
