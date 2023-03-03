<?php
namespace NWiki;
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;

class Core {
  const CODEMIRROR = 'https://cdn.jsdelivr.net/npm/codemirror@5.65.4/';
  const HIGHLIGHT_JS = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/';

  static function prunePath(\NacoWikiApp $wiki, string $dpath) : string {
    while ($dpath != '/' && $dpath != '' && $dpath != '.' && !file_exists($wiki->filePath($dpath))) {
      $dpath = dirname($dpath);
    }
    while ($dpath != '/' && $dpath != '' && $dpath != '.') {
      $fpath = rtrim($wiki->filePath($dpath),'/');
      $files = glob($fpath.'/*');
      if (count($files)) break;
      if (rmdir($fpath) === false) $wiki->errMsg('os_error',$fpath . ': rmdir error',EM_PHPERR);
      $dpath = dirname($dpath);
    }
    return $dpath;
  }
  static function makePath(\NacoWikiApp $wiki, string $dir) : void {
    $dpath = $wiki->filePath($dir);
    if (is_dir($dpath)) return;
    if (false === mkdir($dpath, 0777, true)) $wiki->errMsg('os_error',$dir.': mkdir error');
    return;
  }

  // call-able operations
  static function codeMirror(\NacoWikiApp $wiki, array $cm_opts = []) : void {
    foreach (['js','css'] as $k) {
      if (!isset($cm_opts[$k])) $cm_opts[$k] = [];
    }
    if (!empty($wiki->cfg['theme-codemirror'])) {
      $cm_opts['css'][] = 'theme/'.$wiki->cfg['theme-codemirror'].'.css';
    }
    include($cm_opts['view'] ?? APP_DIR . 'views/edit-cm.html');
  }

  static function folderContents(\NacoWikiApp $wiki, string $folder) : array {
    $files = [];

    $dp = @opendir($wiki->filePath($folder));
    if ($dp === false) return $files;

    while (false !== ($fn = readdir($dp))) {
      if ($fn[0] == '.') continue;
      $files[$folder . $fn] = $fn;
    }
    closedir($dp);
    natsort($files);
    return $files;
  }
  static function search(\NacoWikiApp $wiki, string $folder, string $scope, string $q, ?array &$matches) : array {
    //~ Util::log('TRC:'.__FILE__.','.__LINE__.': folder='.$folder);
    //~ Util::log('TRC:'.__FILE__.','.__LINE__.': scope='.$scope);

    switch ($scope) {
    case 'global':
      list(,$f) = Util::walkTree($wiki->filePath('/'));
      foreach ($f as $i) {
	$flst['/'.$i] = $i;
      }
      break;
    case 'recursive':
      list(,$f) = Util::walkTree($wiki->filePath($folder));
      foreach ($f as $i) {
	$flst[$folder.$i] = $i;
      }
      break;
    case 'local':
      $i = glob($wiki->filePath($folder).'*');
      $l = strlen($wiki->filePath(''));
      if ($i !== false) {
	foreach ($i as $j) {
	  if (is_dir($j)) continue;
	  $flst[substr($j, $l)] = basename($j);
	}
      }
      break;
    default:
      $wiki->errMsg('param',$scope.': Unknown scope');
    }

    if (!empty($q)) {
      $files = [];
      $matches = [];
      $re = '/^.*\b(' . $q . ')\b.*$/m';
      foreach ($flst as $i=>$j) {
	$ext = Plugins::mediaExt($i);
	if (is_null($ext)) continue;

	//~ Util::log('TRC:'.__FILE__.','.__LINE__.': i='.$i);
	$text = Util::fileContents($wiki->filePath($i));
	if (is_null($text)) continue;
	if (preg_match($re,$text,$mv)) {
	  $matches[$i] = $mv;
	  $files[$i] = $j;
	}
      }
    } else {
      $files = $flst;
    }

    ksort($files, SORT_NATURAL);
    return $files;
  }

  static function theme(\NacoWikiApp $wiki) : void {
    if (!isset($wiki->cfg['theme']) || !isset($wiki->cfg['static_path']) || !isset($wiki->cfg['static_url'])) return;

    $theme = $wiki->cfg['theme'];
    $spath = $wiki->cfg['static_path'];
    $tpath = 'themes/'.$theme.'/';

    if (!is_dir($spath.$tpath)) return;
    list(,$files) = Util::walkTree($spath.$tpath);

    foreach ($files as $asset) {
      if (strtolower(substr($asset,-4)) == '.css') {
	echo '<link rel="stylesheet" href="'. $wiki->assetQS($tpath.$asset).'" rel="stylesheet">'.PHP_EOL;
      }
    }
    //~ Util::log(Util::vdump([$files]));
  }

  // Main user entry points

  static function readFolder(\NacoWikiApp $wiki, array &$data) : ?bool {
    //~ echo('TRC:'.__FILE__.','.__LINE__.':page:'. $wiki->page.PHP_EOL);
    if (substr($wiki->page,-1) != '/') {
      # This is a potential document view...
      if (file_exists($wiki->filePath().'/'.$wiki->cfg['default_doc'])) {
	// Re-direct to document view.
	header('Location: '.$wiki->mkUrl($wiki->page,$wiki->cfg['default_doc']));
	exit(0);
      } else {
	header('Location: '.$wiki->mkUrl($wiki->page.'/'));
	exit(0);
      }
    } else {
      $files = self::folderContents($wiki, $wiki->page);
      if (isset($files[ $wiki->page . $wiki->cfg['default_doc']])) {
	unset($files[ $wiki->page . $wiki->cfg['default_doc']]);
	$has_doc_view = true;
      } else {
	$has_doc_view = false;
      }

      $wiki->filemeta = Util::fileMeta($wiki->filePath());
      $wiki->meta = Util::defaultMeta($wiki->filePath());
      if ($wiki->page == '/') $wiki->meta['title'] = '';

      $wiki->view = 'folder';
      include(APP_DIR . 'views/folder.html');
    }
    exit();
  }
  static function searchPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    //~ echo('TRC:'.__FILE__.','.__LINE__.':page:'. $wiki->page.PHP_EOL);
    $scope = 'local';
    if (!empty($_GET['scope']) && in_array($_GET['scope'],['global','local','recursive'])) $scope = $_GET['scope'];

    if (substr($wiki->page,-1) != '/') {
      $folder = dirname($wiki->page).'/';
    } else {
      $folder = $wiki->page;
    }
    $q = $_GET['q'] ?? '';
    $files = self::search($wiki,$folder,$scope, $q,$matches);

    $wiki->filemeta = Util::fileMeta($wiki->filePath());
    $wiki->meta = Util::defaultMeta($wiki->filePath());
    $wiki->meta['title'] = 'Search results';

    $wiki->view = 'search';
    include(APP_DIR . 'views/folder.html');
    exit;
  }
  static function preparePayload(\NacoWikiApp $wiki, string $url,string $ext = NULL) : array {
    if (is_null($ext)) $ext = Plugins::mediaExt($url);
    $event = [
      'source' => Util::fileContents($wiki->filePath($url)),
      'filemeta' => Util::fileMeta($wiki->filePath($url)),
      'meta' => Util::defaultMeta($wiki->filePath($url)),
      'payload' => NULL ,
      'ext' => $ext,
    ];
    if (!Plugins::dispatchEvent($wiki, 'read:'.$ext, $event)) {
      $event['payload'] = $event['source'];
    }
    return $event;
  }

  static function readPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    $wiki->view = 'page';
    $ext = Plugins::mediaExt($wiki->page);
    if (!is_null($ext)) {
      if (Plugins::dispatchEvent($wiki, 'view:'.$ext, Plugins::event(['ext'=>$ext]))) exit;

      $event = self::preparePayload($wiki, $wiki->page, $ext);
      $wiki->source = $event['source'];
      $wiki->meta = $event['meta'];
      $wiki->filemeta = $event['filemeta'];
      $wiki->payload = $event['payload'];

      $event = [ 'html' => $wiki->payload, 'ext' => $ext ];
      Plugins::dispatchEvent($wiki, 'pre-render:'.$ext, $event);
      Plugins::dispatchEvent($wiki, 'pre-render', $event);
      Plugins::dispatchEvent($wiki, 'render:'.$ext, $event);
      Plugins::dispatchEvent($wiki, 'post-render:'.$ext, $event);
      Plugins::dispatchEvent($wiki, 'post-render', $event);
      $wiki->html = $event['html'];

      if (Plugins::dispatchEvent($wiki, 'layout:'.$ext, Plugins::event(['ext'=>$ext]))) exit;

      $pgview = $data['view'] ?? APP_DIR . 'views/page.html';
      include($pgview);
      exit;
    }
    Util::sendFile($wiki->filePath());
    exit;
  }
  static function missingPage(\NacoWikiApp $wiki, array &$data) : ?bool {

    //~ Util::log('TRC:'.__FILE__.','.__LINE__);
    $wiki->filemeta = Util::fileMeta($wiki->page,time());
    $wiki->meta = Util::defaultMeta($wiki->page,time());
    //~ Util::log('TRC:'.__FILE__.','.__LINE__);
    $wiki->meta['title'] = '404: '.htmlspecialchars($wiki->page);

    $ext = Plugins::mediaExt($wiki->page);
    $wiki->view = 'error404';
    if (!is_null($ext)  && Plugins::dispatchEvent($wiki, 'missing:'.$ext, Plugins::event(['ext'=>$ext]))) exit();
    http_response_code(404);
    include(APP_DIR . 'views/404.html');
    exit();
  }
  static function deletePage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (!$wiki->isWritable()) {
      //~ Plugins::dispatchEvent($wiki, 'delete_access_error', Plugins::event());
      $wiki->errMsg('write_access',$wiki->filePath().': Delete access error');
    }
    if ($wiki->page == '' || $wiki->page == '/') {
      $wiki->errMsg('invalid_target','Can not delete root folder');
    }

    $file_path = $wiki->filePath();
    if (is_dir($file_path)) {
      if (is_link($file_path)) {
	// It is a symlink... can be removed
	if (unlink($file_path) === false) $wiki->errMsg('os_error',$file_path. ': unlink error', EM_PHPERR);
      } else {
	// It is a real directory
	list ($dirs,$files) = Util::walkTree($file_path);
	if (count($files) > 0) {
	  //~ echo ('<pre>');
	  //~ print_r($_GET);
	  //~ echo ('</pre>');
	  if (!empty($_GET['confirm']) && filter_var($_GET['confirm'],FILTER_VALIDATE_BOOLEAN)) {
	    while (count($files)) {
	      $cfile = array_pop($files);
	      if (unlink($file_path.'/'.$cfile) === false) $wiki->errMsg('os_error',$cfile.': unlink error', EM_PHPERR);
	    }
	  } else {
	    $wiki->view = 'dialog';
	    $wiki->meta = [ 'title' => 'Confirm delete' ];
	    include(APP_DIR . 'views/confirm_del.html');
	    exit();
	  }
	}
      }
      // Delete $dirs
      //~ echo ('<pre>');
      //~ print_r($dirs);
      //~ echo ('</pre>');
      while (count($dirs)) {
	$cdir = array_pop($dirs);
	if (rmdir($file_path.'/'.$cdir) === false) $wiki->errMsg('os_error',$cdir.': rmdir error', EM_PHPERR);
      }
      if (rmdir($file_path) === false) $wiki->errMsg('os_error',$file_path. ': rmdir error', EM_PHPERR);
    } else {
      if (unlink($file_path) === false) $wiki->errMsg('os_error',$file_path. ': unlink error', EM_PHPERR);
    }
    // Clean-up directory path
    $dpath = self::prunePath($wiki, $wiki->page);
    header('Location: '.rtrim($wiki->mkUrl($dpath),'/').'/');
    exit;
  }

  static function renamePage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if ($wiki->page == '/' || $wiki->page == '') $wiki->errMsg('invalid_target','Cannot rename root directory');

    if (empty($_GET['name'])) $wiki->errMsg('param','No name specified');
    $newpage = Util::sanitize($_GET['name'],$wiki->page);
    if ($newpage == $wiki->page) $wiki->errMsg('no-op','No changes made');

    if (!$wiki->isWritable() || !$wiki->isWritable($newpage) ) {
      //~ Plugins::dispatchEvent($wiki, 'rename_access_error', Plugins::event());
      $wiki->errMsg('write_access',$wiki->filePath().': Rename access error');
    }

    if (is_dir($wiki->filePath($newpage))) {
      // Is an existing directory
      $newpage = rtrim($newpage,'/').'/'.basename($wiki->page);
    } elseif (file_exists($wiki->filePath($newpage))) {
      // Is an existing file
      $wiki->errMsg('duplicate',$newpage . ': Already exists!');
    }

    self::makePath($wiki, dirname($newpage));
    if (false === rename($wiki->filePath(),$wiki->filePath($newpage)))
      $wiki->errMsg('os_error',$wiki->page.'=>'.$newpage.': rename error', EM_PHPERR);

    // Clean-up directory path
    $dpath = self::prunePath($wiki, $wiki->page);
    header('Location: '.$wiki->mkUrl($newpage));
    exit;
  }

  static function editPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (substr($wiki->page,-1) == '/') $wiki->errMsg('invalid_target','Folders are not editable');
    if (!$wiki->isWritable()) {
      //~ Plugins::dispatchEvent($wiki, 'delete_access_error', Plugins::event());
      $wiki->errMsg('write_access',$wiki->filePath().': Write access error');
    }

    if (file_exists($wiki->filePath())) {
      $time = filemtime($wiki->filePath());
      $wiki->source = Util::fileContents($wiki->filePath());
    } else {
      $time = time();
      $wiki->source = '';
    }
    $wiki->filemeta = Util::fileMeta($wiki->filePath(),$time);
    $wiki->meta = Util::defaultMeta($wiki->filePath(),$time);

    $ext = Plugins::mediaExt($wiki->page);
    $wiki->view = 'edit';
    if (!is_null($ext)  && Plugins::dispatchEvent($wiki, 'edit:'.$ext, Plugins::event(['ext'=>$ext]))) exit();

    include(APP_DIR . 'views/edit.html');
    exit();
  }

  static function savePage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (substr($wiki->page,-1) == '/') $wiki->errMsg('invalid_target','Folders are not editable');
    if (!$wiki->isWritable()) {
      //~ Plugins::dispatchEvent($wiki, 'delete_access_error', Plugins::event());
      $wiki->errMsg('write_access',$wiki->filePath().': Write access error');
    }

    $ext = Plugins::mediaExt($wiki->page);
    $ev = [ 'text' => $_POST['text'] ?? '', 'ext'=>$ext];

    if ($wiki->cfg['unix_eol']) $ev['text'] = str_replace("\r", "", $ev['text']);

    if (!is_null($ext)) {
      Plugins::dispatchEvent($wiki, 'preSave', $ev);
      Plugins::dispatchEvent($wiki, 'preSave:'.$ext, $ev);
      if (Plugins::dispatchEvent($wiki, 'save:'.$ext, $ev)) {
	Plugins::dispatchEvent($wiki, 'postSave:'.$ext, $ev);
	Plugins::dispatchEvent($wiki, 'postSave', $ev);
	exit;
      }
      Plugins::dispatchEvent($wiki, 'postSave:'.$ext, $ev);
    }
    Plugins::dispatchEvent($wiki, 'preSave', $ev);
    self::makePath($wiki, dirname($wiki->page));
    if (false === file_put_contents($wiki->filePath(), $ev['text']))
      $wiki->errMsg('os_error',$wiki->page.': write error', EM_PHPERR);
    Plugins::dispatchEvent($wiki, 'postSave', $ev);

    header('Location: '.$wiki->mkUrl($wiki->page));
    exit;
  }
  static function attachToPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (substr($wiki->page,-1) != '/') $wiki->errMsg('invalid_target','Can only upload to folders');
    if (!$wiki->isWritable()) {
      //~ Plugins::dispatchEvent($wiki, 'delete_access_error', Plugins::event());
      $wiki->errMsg('write_access',$wiki->filePath().': Write access error');
    }
    if (!isset($_FILES['fileToUpload'])) $wiki->errMsg('param','Invalid FORM response');
    $fd = $_FILES['fileToUpload'];
    if (isset($fd['size']) && $fd['size'] == 0) $wiki->errMsg('param','Zero file submitted');
    if (isset($fd['error']) && $fd['error'] != 0) $wiki->errMsg('param','Error: '.$fd['error']);
    if (empty($fd['name']) || empty($fd['tmp_name'])) $wiki->errMsg('param','No file uploaded');

    $fname = Util::sanitize(basename($fd['name']));
    $fpath = $wiki->filePath($wiki->page . $fname);
    if (file_exists($fpath)) $wiki->errMsg('duplicate',$fname.': File already exists');

    if (!move_uploaded_file($fd['tmp_name'],$fpath))
      $wiki->errMsg('os_error','Error saving uploaded file', EM_PHPERR);

    header('Location: '.$wiki->mkUrl($wiki->page, $fname));
    exit;
  }
  static function apiPageList(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $res = Util::walkTree($wiki->cfg['file_store']);
    $res[] = $wiki->page;
    $res[] = $wiki->cfg['base_url'];
    $ev['output'] = $res;
    return Plugins::OK;
  }
  static function load() : void {
    Plugins::registerEvent('do:delete',[self::class,'deletePage']);
    Plugins::registerEvent('do:rename',[self::class,'renamePage']);
    Plugins::registerEvent('do:edit',[self::class,'editPage']);
    Plugins::registerEvent('do:search',[self::class,'searchPage']);
    Plugins::registerEvent('api:page-list',[self::class,'apiPageList']);
    Plugins::registerEvent('action:save',[self::class,'savePage']);
    Plugins::registerEvent('action:attach',[self::class,'attachToPage']);
    Plugins::registerEvent('missing_page',[self::class,'missingPage']);
    Plugins::registerEvent('read_page',[self::class,'readPage']);
    Plugins::registerEvent('read_folder',[self::class,'readFolder']);
  }
}


