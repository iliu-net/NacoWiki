<?php
/** Versions
 *
 * Implements multiple versions for files.
 *
 * @package Plugins
 * @phpcod Plugins##Versions
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;

/** NacoWiki Versions
 *
 *
 * @phpcod Versions
 */
class Versions {
  /** var string */
  const VERSION = '0.0.0';
  const FPREFIX = '/.ver;';

  /** Return this plugin's path
   * @param string $f optional item
   * @return path to filesystem for $f
   */
  static function path(string $f = '') : string {
    return Plugins::path($f);
  }

  /** Create diff between two strings
   *
   * Will compare string $a and string $b line by line and create
   * a patch string that can be used by patchStr to bring the $a to
   * be like $b.
   *
   * @param string $a source string to compare
   * @param string $b dest string to compare
   * @return string containing patch or NULL in case of error
   */
  static function diffStr(string $a,string $b) : ?string {
    $t = sys_get_temp_dir().'/';
    do {
      $tmp = $t . (string) mt_rand();
    } while(!@mkdir($tmp));
    $res = false;
    do {
      if (false === file_put_contents($tmp.'/a',$a)) break;
      if (false === file_put_contents($tmp.'/b',$b)) break;

      ob_start();
      $p = passthru('diff -u '.$tmp.'/a '.$tmp.'/b');
      $res = ob_get_clean();
      if ($p === false) $res = NULL;
    } while (false);
    array_map('unlink', glob($tmp.'/*'));
    rmdir($tmp);
    return $res;
  }
  /** Read versioning data
   *
   * @param string $pgfile File path to document.  Usually `$wiki->filePath()`.
   * @return ?array NULL on Error, otherwise an array structure.
   *
   * The returned array structure has the following components:
   *
   * - `vfile` - name of the version file.
   * - `vs` - version data (delta's or rewrites)
   * - `root` - root entry.  Delta's would start from this root.
   */
  static function readVerData(string $pgfile) : ?array {
    # Identify the version file
    $pf = pathinfo($pgfile);
    $vfile = $pf['dirname'] . self::FPREFIX . $pf['basename'];

    # Load previous versions
    if (file_exists($vfile)) {
      $vs = yaml_parse_file($vfile);
      if (!is_array($vs)) $vs = [];
    } else {
      $vs = [];
    }
    if (isset($vs['root'])) {
      $root = $vs['root'];
      unset($vs['root']);
    } else {
      $root = NULL;
    }
    return [
      'vfile' => $vfile,
      'vs' => $vs,
      'root' => $root,
    ];
  }
  /** Save versioning data
   *
   * Save versioning data to a file.
   *
   * @param array $vdata versioning data to save (same structure as returned by readVerData)
   * @return bool True on success, False on failure
   */
  static function saveVerData(array $vdata) : bool {
    $vs = $vdata['vs'];
    $vs['root'] = $vdata['root'];
    return yaml_emit_file($vdata['vfile'], $vs) === true ? true : false;
  }

  /** apply patches to string
   *
   * Applies patches to a string so that you can recreate a file.
   *
   * @param string $orig Original string that will be patched
   * @param string $diff string contains patches to be applied
   * @return ?string updated string or NULL in case of error.
   */
  static function patchStr(string $orig,string $diff) : ?string {
    $t = sys_get_temp_dir().'/';
    do {
      $tmp = $t . (string) mt_rand();
    } while(!@mkdir($tmp));
    $res = null;
    do {
      if (false === file_put_contents($tmp.'/orig',$orig)) break;
      if (false === file_put_contents($tmp.'/patch',$diff)) break;
      $e = exec('patch  '.$tmp.'/orig '.$tmp.'/patch', $output, $rc);
      if (false === $e) break;
      if ($rc != 0) {
	# This is only visible through the apache2 error log.
	Util::log('Error running patch:'.Util::vdump($output));
	break;
      }
      $res = file_get_contents($tmp.'/orig');
      if ($res === false) $res = null;
    } while (false);
    array_map('unlink', glob($tmp.'/*'));
    rmdir($tmp);
    return $res;
  }
  /** Diff versions
   *
   * Create a new file version before saving to file.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @event postSave
   */
  static function diffVers(\NacoWikiApp $wiki, array &$ev) : ?bool {
    if (isset($wiki->opts['disable-props']) && $wiki->opts['disable-props']) return Plugins::OK; # Disabled

    $ext = Plugins::mediaExt($wiki->page);
    if (is_null($ext)) return Plugins::OK; # Only do it for handled media.

    if (is_null($ev['prev'])) return Plugins::OK; # trival case... no previous version!
    # Get current version props reference
    if (!isset($wiki->props['change-log']) || count($wiki->props['change-log']) == 0)
      return Plugins::OK; # No change-log entry, we can't attach a version then!

    # Determine the current version and the previous version Id's in
    # the change-log
    $vid = $wiki->props['change-log'][0][0];
    $pid = count($wiki->props['change-log']) > 1 ?
	      $wiki->props['change-log'][1][0] : 0;

    # Load previous versions
    $v = self::readVerData($wiki->filePath());
    if (is_null($v)) {
      ##!! error-catalog|os_error|invalid internal state
      $wiki->errMsg('os_error',$wiki->page.': Error reading versioning file');
    }

    # Find all deltas that are in $vs that are after $pid
    $deltas = [];
    $rw = null;
    foreach ($v['vs'] as $did => $diff) {
      if ($did > $pid) {
	if ($diff[0] == 'delta') {
	  $deltas[] = $did;
	} elseif ($diff[0] == 'rewrite') {
	  if (is_null($rw)) {
	    $rw = $did;
	  } elseif ($did < $rw) {
	    $rw = $did;
	  }
	} else {
	  ##!! error-catalog|internal_error|invalid internal state
	  $wiki->errMsg('internal_error',$diff[0].': invalid internal state');
	}
      }
    }

    # Try to recreate the version as ($pid) using the given deltas.
    $tgt = $ev['text'];
    $ctx = empty($v['root']) ? $ev['prev'] : $v['root']; # We should use v[root] otherwise use ev[prev]
    if (count($deltas)) {
      rsort($deltas,SORT_NUMERIC);
      foreach ($deltas as $did) {
	$diff = $v['vs'][$did];
	unset($v['vs'][$did]);
	if (!is_null($rw) && $did > $rw) {
	  # If there is a $rw, skip newer deltas...
	  continue;
	}
	if ($diff[0] == 'rewrite') {
	  $ctx = $diff[1];
	} elseif ($diff[0] == 'delta') {
	  # Calculate delta
	  $ctx = self::patchStr($ctx, $diff[1]);
	  if (is_null($ctx)) {
	    # OH NO!  We can't recreate previous versions!
	    ##!! error-catalog|internal_error|broken versions
	    $wiki->errMsg('internal_error',$did.': unable to patch');
	  }
	} else {
	  ##!! error-catalog|internal_error|invalid internal state
	  $wiki->errMsg('internal_error',$diff[0].': invalid internal state');
	}
      }
    }

    # Calculate delta and determine if it is better to do rewrite.
    $patch = self::diffStr($tgt,$ctx);
    if (is_null($patch) || strlen($patch) > strlen($ctx) * 2 /3) {
      # Delta large when compared to rewrite
      $v['vs'][$vid] = ['rewrite', $ctx ];
    } else {
      $v['vs'][$vid] = [ 'delta', $patch ];
    }

    $v['root'] = $ev['text'];
    if (false === self::saveVerData($v)) {
      ##!! error-catalog|os_error|error saving version data
      $wiki->errMsg('os_error',$vfile.': error saving version data');
    }

    //~ echo '<a href="'.$wiki->mkUrl($wiki->page).'">Continue</a>';

    //~ echo '<h2>Log Index</h2><pre>';
    //~ $logidx = [];
    //~ foreach ($wiki->props['change-log'] as $k=>$le) { $logidx[$le[0]] = $k; }
    //~ foreach ($logidx as $i=>$j) { echo $i.' =&gt; '.$j.PHP_EOL; }
    //~ echo '</pre>';

    //~ echo '<H1>Merged Deltas</H1>';
    //~ foreach ($vs as $k=>$v) {
      //~ echo '<h2>'.$k.'</h2>';
      //~ echo '<pre>';
      //~ var_dump($wiki->props['change-log'][$logidx[$k]]);
      //~ echo '='.PHP_EOL;
      //~ var_dump($v);
      //~ echo '======================'.PHP_EOL;
      //~ echo '</pre>';
    //~ }

    //~ $txt = $ev['text'];
    //~ echo '<h1>Current version</h1>';
    //~ echo '<pre>';var_dump($txt);echo '</pre>';
    //~ echo '<h1>Previous Versions</h1>';
    //~ krsort($vs,SORT_NUMERIC);
    //~ foreach ($vs as $k => $d) {
      //~ if ($d[0] == 'delta') {
	//~ $txt = self::patchStr($txt,$d[1]);
      //~ } elseif ($d[0] =='rewrite') {
	//~ $txt = $d[1];
      //~ } else {
	//~ echo "<h4>OH SHIT</h4>";
	//~ break;
      //~ }
      //~ echo '<h2>'.$k.'</h2>';
      //~ echo '<pre>';var_dump($txt);echo '</pre>';
      //~ echo '<hr>';
      //~ if (is_null($txt)) break;
    //~ }

    //~ echo '<h1>input</h1>';
    //~ echo '<pre>';
    //~ print_r(['wiki'=>$wiki, 'event'=>$ev ]);
    //~ echo '</pre>';
    //~ echo '<h1>diff</h1>';
    //~ echo '<pre>'; var_dump($dif); echo '</pre>';


    //~ exit();
    return Plugins::OK;
  }
  /** Add navigation tools
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @event navtools
   */
  static function navtools(\NacoWikiApp $wiki, array &$ev) : ?bool {
    //~ Util::log('Event: '.Util::vdump($ev));
    if ($wiki->view == 'page' || $wiki->view == 'edit') {
      $ext = Plugins::mediaExt($wiki->page);
      if (is_null($ext)) return Plugins::OK;
      //~ if ($ev['mode'] == 'infobox-bot') {
	//~ $ev['html'] .= '<div><a href="'.$wiki->mkUrl($wiki->page,['do'=>'versions']).'">'.
		    //~ 'view versions'.
		    //~ '</a></div>';
      //~ }
      if ($ev['mode'] == 'edit-bot') {
	$ev['html'] .= '<a href="'.$wiki->mkUrl($wiki->page,['do'=>'versions']).'">'.
		    'view versions'.
		    '</a>';
      }
    } elseif ($wiki->view == 'versions_list') {
      $ext = Plugins::mediaExt($wiki->page);
      if (is_null($ext)) return Plugins::OK;
      if ($ev['mode'] == 'navtools-right') {
	$ev['html'] .= '<a href="'.$wiki->mkUrl($wiki->page).'">'.
		      '&#x1F441; </a>';
      }
    }
    return Plugins::OK;
  }

  /** get a specific version
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @event preRead
   */
  static function getVersion(\NacoWikiApp $wiki, array &$ev) : ?bool {
    if (empty($_GET['version'])) return Plugins::OK;
    $tvid = $_GET['version'];

    $ext = Plugins::mediaExt($wiki->page);
    if (is_null($ext)) {
      ##!! error-catalog|invalid_param|un-supported file type
      $wiki->errMsg('invalid_param',$wiki->page.': not of a supported type');
    }
    if (!isset($wiki->props['change-log']) || count($wiki->props['change-log']) == 0) {
      ##!! error-catalog|invalid_param|no versions found
      $wiki->errMsg('invalid_param',$wiki->page.': no versions found');
    }

    $txt = self::calcVersion($wiki, $wiki->page, $tvid);
    if (is_null($txt)) {
      ##!! error-catalog|internal_error|Unable to calculate version
      $wiki->errMsg('internal_error',$wiki->page.': error calculating version');
    }

    if (is_null($ev['extras'])) $ev['extras'] = [];
    if (!isset($ev['extras']['annotate']))$ev['extras']['annotate'] = '';
    $ev['extras']['annotate'] .=
        '<strong>NOTE:</strong> This is a previous version dated '.
        '<em>'.date('Y-m-d H:i:s',$tvid).'</em>.';
    $ev['source'] = $txt;
    # TODO: hould re-calculate $ev['filemeta']
    # TODO: pop $ev['props'] older changelogs
    return Plugins::OK;
  }

  /** calculate specific version
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @return string calculated version
   */
  static function calcVersion(\NacoWikiApp $wiki, string $page, int $tvid) : ?string {
    # This disables plugins that may read other pages, from trying to
    # retrieve older versions (that do not exist).  Specifically if
    # we are using PluginIncludes, it would try to use preparePayload
    # on a different file which in turn will call getVersion, but
    # at that point, the requested version would not exist.
    unset($_GET['version']);

    $ext = Plugins::mediaExt($page);
    if (is_null($ext)) return NULL;

    $v = self::readVerData($wiki->filePath($page));
    if (count($v['vs']) < 1) return NULL;

    $txt = empty($v['root']) ? $ev['source'] : $v['root'];
    krsort($v['vs'],SORT_NUMERIC);
    $deltas = [];
    $found = false;
    foreach ($v['vs'] as $k => $d) {
      if ($d[0] == 'delta') {
        $deltas[] = $k;
      } elseif ($d[0] == 'rewrite') {
        $txt = $d[1];
        $deltas = [];
      } else {
	Util::log($d[0].': Unknow delta-type -- '.$page);
	return NULL;
      }
      if ($k == $tvid) {
        $found = true;
        break;
      }
    }

    if (!$found) return NULL;

    foreach ($deltas as $k) {
      $d = $v['vs'][$k];
      if ($d[0] == 'delta') {
        $txt = self::patchStr($txt,$d[1]);
      } elseif ($d[0] =='rewrite') {
        $txt = $d[1];
      } else {
	Util::log($d[0].': Unknow delta-type -- '.$page);
	return NULL;
      }
      if (is_null($txt)) return NULL;
    }
    return $txt;
  }


  //~ /** get a specific version
   //~ *
   //~ * @param \NanoWikiApp $wiki running wiki instance
   //~ * @param array &$event Event data
   //~ * @event preRead
   //~ */
  //~ static function getVersion(\NacoWikiApp $wiki, array &$ev) : ?bool {
    //~ if (empty($_GET['version'])) return Plugins::OK;
    //~ $tvid = $_GET['version'];


    //~ $ext = Plugins::mediaExt($wiki->page);
    //~ if (is_null($ext)) {
      //~ ##!! error-catalog|invalid_param|un-supported file type
      //~ $wiki->errMsg('invalid_param',$wiki->page.': not of a supported type');
    //~ }

    //~ $v = self::readVerData($wiki->filePath());
    //~ if (count($v['vs']) < 1 || !isset($wiki->props['change-log']) || count($wiki->props['change-log']) == 0) {
      //~ ##!! error-catalog|invalid_param|no versions found
      //~ $wiki->errMsg('invalid_param',$v['vfile'].': no versions found');
    //~ }
    //~ if (is_null($ev['extras'])) $ev['extras'] = [];
    //~ if (!isset($ev['extras']['annotate']))$ev['extras']['annotate'] = '';
    //~ $ev['extras']['annotate'] .=
	//~ '<strong>NOTE:</strong> This is a previous version dated '.
	//~ '<em>'.date('Y-m-d H:i:s',$tvid).'</em>.';

    //~ $txt = empty($v['root']) ? $ev['source'] : $v['root'];
    //~ krsort($v['vs'],SORT_NUMERIC);
    //~ $deltas = [];
    //~ $found = false;
    //~ foreach ($v['vs'] as $k => $d) {
      //~ if ($d[0] == 'delta') {
	//~ $deltas[] = $k;
      //~ } elseif ($d[0] == 'rewrite') {
	//~ $txt = $d[1];
	//~ $deltas = [];
      //~ } else {
	//~ ##!! error-catalog|internal_error|invalid internal state
	//~ $wiki->errMsg('invalid_error',$d[0].': type error');
      //~ }
      //~ if ($k == $tvid) {
	//~ $found = true;
	//~ break;
      //~ }
    //~ }

    //~ if (!$found) {
      //~ ##!! error-catalog|invalid_param|version not found
      //~ $wiki->errMsg('invalid_param',$wiki->page.': selected version not found');
    //~ }
    //~ foreach ($deltas as $k) {
      //~ $d = $v['vs'][$k];
      //~ if ($d[0] == 'delta') {
	//~ $txt = self::patchStr($txt,$d[1]);
      //~ } elseif ($d[0] =='rewrite') {
	//~ $txt = $d[1];
      //~ } else {
	//~ ##!! error-catalog|internal_error|invalid internal state
	//~ $wiki->errMsg('invalid_error',$d[0].': type error');
      //~ }
      //~ if (is_null($txt)) break;
    //~ }
    //~ if (is_null($txt)) {
      //~ ##!! error-catalog|internal_error|Error retrieving version
      //~ $wiki->errMsg('invalid_error',$tvid.': unable to calculate version');
    //~ }
    //~ $ev['source'] = $txt;

    //~ # TODO: hould re-calculate $ev['filemeta']
    //~ # TODO: pop $ev['props'] older changelogs
    //~ return Plugins::OK;
  //~ }

  /** Compare versions
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @event do:vcompare
   */
  static function compareVers(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ext = Plugins::mediaExt($wiki->page);
    if (is_null($ext)) {
      ##!! error-catalog|invalid_param|un-supported file type
      $wiki->errMsg('invalid_param',$wiki->page.': not of a supported type');
    }
    $wiki->view = 'vcompare';

    # Collect file data
    $wiki->filemeta = Util::fileMeta($wiki->filePath());
    $wiki->meta = Util::defaultMeta($wiki->filePath());
    $wiki->meta['title'] .= ' versions';
    $wiki->props = Core::readProps($wiki, $wiki->filePath());

    if (empty($_GET['a']) || empty($_GET['b'])) {
      ##!! error-catalog|invalid_param|versions to compare missing
      $wiki->errMsg('invalid_param','no versions to compare provided');
    }
    $txts = [];
    foreach (['a','b'] as $i) {
      $vv =$_GET[$i];
      if ($vv == '~') {
	$txts[$i] = [
	    'tvid' => 'current',
	    'txt' => Util::fileContents($wiki->filePath()),
	    'date' => 'Current',
	    ];
      } else {
	$txts[$i] = [
	  'tvid' => $vv,
	  'txt' => self::calcVersion($wiki, $wiki->page, $vv),
	  'date' => date('Y-m-d H:i:s',$vv),
	  ];
      }
    }
    if ($txts['a']['txt'] == $txts['b']['txt']) {
      $difftxt = '';
    } else {
      $difftxt = self::diffStr($txts['a']['txt'], $txts['b']['txt']);
    }


    include Plugins::path('vcmp.html');
    //~ echo '<a href="'.$wiki->mkUrl($wiki->page).'">Continue</a><hr>';

    return Plugins::OK;
  }

  /** View versions
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @event do:versions
   */
  static function viewVers(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ext = Plugins::mediaExt($wiki->page);
    if (is_null($ext)) {
      ##!! error-catalog|invalid_param|un-supported file type
      $wiki->errMsg('invalid_param',$wiki->page.': not of a supported type');
    }
    $wiki->view = 'versions_list';

    # Collect file data
    $wiki->filemeta = Util::fileMeta($wiki->filePath());
    $wiki->meta = Util::defaultMeta($wiki->filePath());
    $wiki->meta['title'] .= ' versions';
    $wiki->props = Core::readProps($wiki, $wiki->filePath());

    $v = self::readVerData($wiki->filePath());
    //~ Util::log('v:'.Util::vdump($v));

    if (count($v['vs']) < 1 || !isset($wiki->props['change-log']) || count($wiki->props['change-log']) == 0) {
      ##!! error-catalog|invalid_param|no versions found
      $wiki->errMsg('invalid_param',$v['vfile'].': no versions found');
    }

    # Itemize links
    $vlinks = [
	[
	  'name' => 'Current',
	  'opts' => [],
	  'raws' => ['do'=>'raw'],
	  'c_v' => '~',
	],
      ];
    $linked = true;
    $i = 0;

    foreach ($wiki->props['change-log'] as $le) {
      $vid = $le[0];
      if (!is_null($i)) {
	$vlinks[$i]['ts'] = $vid;
	$vlinks[$i]['log'] = $le;
	$i = NULL;
      }
      if (isset($v['vs'][$vid])) {
	# log entry has a associated version entry
	if ($v['vs'][$vid][0] == 'rewrite' || ($linked && $v['vs'][$vid][0] == 'delta')) {
	  $i = count($vlinks);
	  $vlinks[] = [
	    'opts' => [ 'version' => $vid ],
	    'raws' => ['do'=>'rawv', 'version' => $vid ],
	    'c_v' => $vid,
	  ];
	  $linked = true;
	}
      } else {
	$linked = false;
      }
    }
    if (!is_null($i)) {
      if (empty($vlinks[$i]['name'])) $vlinks[$i]['name'] = 'Created';
      $t = empty($wiki->props['created']) ?
		[0, '?', '?'] : $wiki->props['created'];
      $vlinks[$i]['ts'] = $t[0];
      $vlinks[$i]['log'] = $t;
    }

    //~ Util::log('page: '.$wiki->page);
    //~ Util::log('props: '.Util::vdump($wiki->props));
    //~ Util::log('meta: '.Util::vdump($wiki->meta));
    //~ Util::log('versions: '.Util::vdump($v['vs']));
    //~ Util::log('vlinks: '.Util::vdump($vlinks));

    include Plugins::path('vlist.html');
    //~ echo '<a href="'.$wiki->mkUrl($wiki->page).'">Continue</a><hr>';

    return Plugins::OK;
  }
  /**
   * This is the event handler that handles the `do:rawv` event.
   *
   * Will serve version without rendering i.e. raw source code.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event do:raw
   */
  static function rawVersion(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (empty($_GET['version'])) return Plugins::OK;
    $tvid = $_GET['version'];


    $ext = Plugins::mediaExt($wiki->page);
    if (is_null($ext)) {
      ##!! error-catalog|invalid_param|un-supported file type
      $wiki->errMsg('invalid_param',$wiki->page.': not of a supported type');
    }
    $wiki->props = Core::readProps($wiki, $wiki->filePath());
    if (!isset($wiki->props['change-log']) || count($wiki->props['change-log']) == 0) {
      ##!! error-catalog|invalid_param|no versions found
      $wiki->errMsg('invalid_param',$wiki->page.': no versions found');
    }

    $txt = self::calcVersion($wiki, $wiki->page, $tvid);
    if (is_null($txt)) {
      ##!! error-catalog|internal_error|Unable to calculate version
      $wiki->errMsg('internal_error',$wiki->page.': error calculating version');
    }
    header('Content-Type: text/plain');
    echo $txt;
    exit;
  }
  /**
   * Loading entry point for this class
   *
   * Adds event hooks implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::registerEvent('postSave', [self::class, 'diffVers']);
    Plugins::registerEvent('navtools', [self::class, 'navtools']);
    Plugins::registerEvent('do:versions', [self::class, 'viewVers']);
    Plugins::registerEvent('do:vcompare', [self::class, 'compareVers']);
    Plugins::registerEvent('do:rawv', [self::class, 'rawVersion']);
    Plugins::registerEvent('preRead', [self::class, 'getVersion']);
  }
}
