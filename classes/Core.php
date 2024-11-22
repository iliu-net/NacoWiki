<?php
/**  @package NWiki */
namespace NWiki;
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;

/**
 * Main NacoWiki functionality
 *
 * This class contains the main functionality included by NacoWiki
 */
class Core {
  /** URL to the supported CODEMIRROR version
   * @var string */
  const CODEMIRROR = 'https://cdn.jsdelivr.net/npm/codemirror@5.65.4/';
  /** URL to the supported HIGHLIGHT_JS version
   * @var string */
  const HIGHLIGHT_JS = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.1/';
  /** property files prefix
   * @var string */
  const PROPS_FILE_PREFIX = '/.props;';
  /** alternative property files prefix (for background compatibility)
   * @var string */
  const PROP_FILE_PREFIX = '/.prop;';

  /** When a file/directory is deleted, make sure no empty dirs remain
   *
   * When a file or directory is deleted, it checks if afterwards the
   * directory is empty.  If it is, it will also delete that directory
   * until we find a directory that is populated.
   *
   * @param \NacoWikiApp $wiki Current wiki instance
   * @param string $dpath file path relative to $wiki->cfg[file_store] to prune
   */
  static function prunePath(\NacoWikiApp $wiki, string $dpath) : string {
    while ($dpath != '/' && $dpath != '' && $dpath != '.' && !file_exists($wiki->filePath($dpath))) {
      $dpath = dirname($dpath);
    }
    while ($dpath != '/' && $dpath != '' && $dpath != '.') {
      $fpath = rtrim($wiki->filePath($dpath),'/');
      $files = glob($fpath.'/*');
      if (count($files)) break;
      if (rmdir($fpath) === false) {
	##!! error-catalog|os_error|rmdir error
	$wiki->errMsg('os_error',$fpath . ': rmdir error',EM_PHPERR);
      }
      $dpath = dirname($dpath);
    }
    return $dpath;
  }
  /** Make sure a directory (and parent directories in between) exist
   *
   * @param \NacoWikiApp $wiki Current wiki instance
   * @param string $dpath file path relative to $wiki->cfg[file_store] to prune
   */
  static function makePath(\NacoWikiApp $wiki, string $dir) : void {
    $dpath = $wiki->filePath($dir);
    if (is_dir($dpath)) return;
    if (false === mkdir($dpath, 0777, true)) {
      ##!! error-catalog|os_error|mkdir error
      $wiki->errMsg('os_error',$dir.': mkdir error');
    }
    return;
  }

  /** Show a CodeMirror editor web view
   *
   * Will create a web page in the style of the wiki containing a
   * CodeMirror editor for use.
   *
   * The $cm_opts is an array which should contain:
   *
   * - array 'js' : list of CodeMirror modules to load.
   * - array 'css' : list of CodeMirror CSS to load.  Usually for themeing
   * - string 'mode' : CodeMirror editor mode
   * - string 'view' : file path to a view template file
   *
   * @param \NacoWikiApp $wiki Current wiki instance
   * @param array $cm_opts configurable options
   */
  static function codeMirror(\NacoWikiApp $wiki, array $cm_opts = []) : void {
    foreach (['js','css'] as $k) {
      if (!isset($cm_opts[$k])) $cm_opts[$k] = [];
    }
    if (!empty($wiki->cfg['theme-codemirror'])) {
      $cm_opts['css'][] = 'theme/'.$wiki->cfg['theme-codemirror'].'.css';
    }
    include($cm_opts['view'] ?? APP_DIR . 'views/edit-cm.html');
  }
  /** Get the contents of a folder
   *
   * This function reads te contents of a folders and returns to
   * caller as a array with the full URL path as the key and the
   * base name as the data element
   *
   * @param string \NacoWikiApp $wiki current running instance
   * @param string $folder folder to read
   * @return array
   */
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
  /** Implements search functionality
   *
   * It will look for grep style regular expression in $q and returns
   * matches in $folder.  If the $scope is given, it can be:
   *
   * - `local` : searches in the current folder,
   * - `recursive` : searches in the current folder and sub-folders
   * - `global` : searches the whole wiki.
   *
   * The $q search term can be either some text that will be converted
   * into '/'.$q.'/i', with any slashes escaped, or a valid regulare
   * expression (with the start and end delimiters).
   *
   * This means that by default it is a case-insensitve search.
   * To override these defaults you must specify a full RE including
   * the start and end delimiters and any optional modifiers.
   *
   * Returns a list of files in a fomrat similar to folderContents.
   *
   * The optional matches array receives the matching lines.  With
   * the key matching the key in the returned files, and the element
   * being an array with line context (index 0) amd matching text (index 1).
   *
   * @link https://www.php.net/manual/en/pcre.pattern.php
   * @param \NacoWikiApp $wiki current wiki instance
   * @param string $folder folder to start the search
   * @param string $scope search context: "local", "recursive", "global"
   * @param string $q search term
   * @param ?array &$matches optional array that will receive the search matches
   * @return array list of matching files.
   */
  static function search(\NacoWikiApp $wiki, string $folder, string $scope, string $q, ?array &$matches) : array {
    //~ Util::log('TRC:'.__FILE__.','.__LINE__.': folder='.$folder);
    //~ Util::log('TRC:'.__FILE__.','.__LINE__.': scope='.$scope);

    switch ($scope) {
    case 'global':
      list(,$f) = Util::walkTree($wiki->filePath('/'), true, $wiki->cfg['vacuum']);
      foreach ($f as $i) {
	$flst['/'.$i] = $i;
      }
      break;
    case 'recursive':
      list(,$f) = Util::walkTree($wiki->filePath($folder), true, $wiki->cfg['vacuum']);
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
      ##!! error-catalog|param|unknown scope
      $wiki->errMsg('param',$scope.': Unknown scope');
    }

    if (!empty($q)) {
      $files = [];

      // Check the validity of RE
      if (@preg_match($q,'') === false) {
	$re = '/'.str_replace('/','\/',$q).'/i';
	if (@preg_match($re,'') === false) {
	  # The given search string is an invalid REGEX
	  return $files;
	}
      }	else {
	// User really knows what he is doing!
	$re = $q;
      }

      $matches = [];
      foreach ($flst as $i=>$j) {
	$ext = Plugins::mediaExt($i);
	if (is_null($ext)) continue;

	$text = Util::fileContents($wiki->filePath($i));
	if (is_null($text)) continue;
	if (empty($text)) continue;

	$fl = preg_grep($re,explode("\n",$text));
	if (count($fl) > 0) {
	  $fl = array_shift($fl);
	  $files[$i] = $j;

	  if (preg_match($re, $fl, $mv)) {
	    $matches[$i] = [ $fl, $mv[0] ];
	  } else {
	    $matches[$i] = [ $fl, '' ];
	  }
	}
      }
    } else {
      $files = $flst;
    }

    ksort($files, SORT_NATURAL);
    return $files;
  }
  /** Link theme assets
   *
   * Outputs the HTML tags to link the `theme` assets to the currently
   * displayed page
   *
   * @param \NacoWikiApp $wiki running wiki instance
   */
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

  /** Show the contents of a folder
   *
   * This is the Core event handler that handles the `read_folder` event.
   *
   * If the URL does **not** end with `/`, it will redirect to
   * the `default_doc` (usually `index.md` ) if it exists, or
   * it will redirect to an URL ending with `/`.
   * HTTP request.  It is used to trigger a specific action in response
   * to a HTTP post request.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event read_folder
   */
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
  /** Search results event handler
   *
   * This is the Core event handler that handles the `do:search` event.
   *
   * Handles search requests from the web browser.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event do:search
   */
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
  /** Raw page display
   *
   * This is the Core event handler that handles the `do:raw` event.
   *
   * Will serve pages without rendering i.e. raw source code.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event do:raw
   */
  static function rawPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    Util::sendFile($wiki->filePath(),'text/plain');
    exit;
  }
  /** Read properties from props file.
   *
   * Read properties from a YAML file.  If not found, it should
   * pre-initalize things accordingly.
   *
   * @param \NacoWikiApp $wiki running wiki instance
   * @param string $file name of file to use
   * @return array Array containing properties
   */
  static function readProps(\NacoWikiApp $wiki, $file) {
    $name = pathinfo($file);

    $props_file = $name['dirname'].self::PROPS_FILE_PREFIX.$name['basename'];
    if (file_exists($props_file)) {
      # Read it as JSON...
      $jsdoc = file_get_contents($props_file);
      $res = json_decode($jsdoc, true);
      if (is_array($res)) return $res;
    }

    // Kept for backwards compatibility...
    $prop_file = $name['dirname'].self::PROP_FILE_PREFIX.$name['basename'];
    if (file_exists($prop_file)) {
      # Read it as YAML...
      $res = yaml_parse_file($prop_file);
      if (is_array($res)) return $res;
    }
    // Missing or Invalid prop file...
    return [
      'created' => [],
      'change-log' => [],
    ];
  }
  /** Save properties to props file.
   *
   * Save properties ta props file in YAML format.
   *
   * @param \NacoWikiApp $wiki running wiki instance
   * @param array $props Array containing properties
   * @param string $fpath File to save, otherwise uses $wiki->filePath()
   * @return bool true on succes, false on error.
   */
  static function saveProps($wiki,$props,$fpath = NULL) : bool {
    ##-- opts-yaml##disable-props
    ## This option disables the creation of `.props;xxxx` files.
    ##
    ## This is for use in `git` repositories, where that kind of information
    ## should be stored in `git` itself.
    ##--
    if (isset($wiki->opts['disable-props']) && $wiki->opts['disable-props']) return true; # Disabled, so we lie and just say success!
    if (is_null($fpath)) $fpath = $wiki->filePath();
    $name = pathinfo($fpath);

    # New storage in JSON format...
    $prop_file = $name['dirname'].self::PROPS_FILE_PREFIX.$name['basename'];
    if (file_put_contents($prop_file, json_encode($props)) === false) return false;
    return true;
    //~ if (yaml_emit_file($prop_file, $props) === true) return true;
    //~ # Deprecating YAML based property files
    //~ $prop_file = $name['dirname'].self::PROP_FILE_PREFIX.$name['basename'];
    //~ if (yaml_emit_file($prop_file, $props) === true) return true;
    //~ return false;
  }

  /** Calculate a string from an Prop change-log entry
   *
   * Used to reduce the number of change log entries.
   * It uses the remote user if available and remote IP address.
   * Also, the time stamp is reduced to date only (time is removed).
   * The reason is that changes by the same user on the same date
   * should only result in a single change-log entry.
   *
   * @param array $entry Entry to convert to a comparable string.
   * @return string
   */
  static function propEntryStr($entry) {
    return date('Ymd',$entry[0]).'|'.
	      ($entry[1] ?? '?').'|'.
	      ($entry[2] ?? '?');
  }
  /** Update props
   *
   * Update props by creating a new entry in the change log
   *
   * @param \NacoWikiApp $wiki running wiki instance
   * @param array &$props Array containing properties
   */
  static function updateProps(\NacoWikiApp $wiki, &$props) {
    $entry = [ time(), $wiki->remote_addr, $wiki->remote_user ];
    if (count($props['change-log'])) {
      $cur = self::propEntryStr($entry);
      $last = self::propEntryStr($props['change-log'][0]);
      if ($last == $cur) {
	if (count($props['change-log'][0]) > 3) {
	  # Preserve any existing log entries.
	  $entry[] = $props['change-log'][0][3];
	}
	$props['change-log'][0] = $entry;
      } else {
	array_unshift($props['change-log'],$entry);
      }
    } else {
      $props['change-log'][] = $entry;
    }
  }
  /** Default props
   *
   * Return default properties due to a file being created.
   *
   * @param \NacoWikiApp $wiki running wiki instance
   * @return array Array containing new file properties.
   */
  static function defaultProps(\NacoWikiApp $wiki) {
    return [
      'created' => [
	  time(),
	  $wiki->remote_addr,
	  $wiki->remote_user,
      ],
      'change-log' => [],
    ];
  }
  /** Add log entires to props
   *
   * Check if a $meta array contains `add-log` entries and adds them.
   *
   * The `add-log` entry from the $meta array is removed.
   *
   * This function should be called from a `preSave` event handler by
   * a plugin as plugins are responsible from parsing and updating
   * meta data.
   *
   * It may also used by other plugins to modify the saved `meta` data
   * and/or `props` arrays.  (One use is to implement auto-tagging).
   *
   * For this to work the media handler plugin must support this.  i.e.
   * The plugin must rewrite the `$event['text']` with the updated
   * meta data.
   *
   * @param \NacoWikiApp $wiki running wiki instance
   * @param array &$props - properties array that will receive log entries
   * @param array &$meta - meta array with log entires
   * @param string $extra - (Optional) additonal text to be send (usually the article body text).
   *
   */
  static function logProps(\NacoWikiApp $wiki, array &$props, array &$meta, string $extra = NULL) : void {
    if (isset($meta['add-log'])) {
      $logtxt = trim($meta['add-log']);
      unset($meta['add-log']);
      Util::log(Util::vdump($logtxt));
      if ($logtxt != '') {
	if (count($props['change-log']) == 0) {
	  $props['created'][] = $logtxt;
	} else {
	  if (count($props['change-log'][0]) > 3) {
	    $props['change-log'][0][3] .= PHP_EOL.$logtxt;
	  } else {
	    $props['change-log'][0][] = $logtxt;
	  }
	}
      }
    }
    ##-- events-list##log-props
    ## This event is used to manipulate properties and meta headers
    ## before a file is saved.
    ##
    ## Event data:
    ##
    ## - `props` (input|output) : properties to be modified.
    ## - `meta` (input|output) : meta data to be modified
    ## - `extra` (input) : extra data send by the calling plugin (usually the article body text)
    ##--
    $event = [
      'props' => $props,
      'meta' => $meta,
      'extra' => $extra,
    ];
    if (Plugins::dispatchEvent($wiki, 'log-props', $event)) {
      $props = $event['props'];
      $meta = $event['meta'];
    }
  }

  /**
   * Utility function to initialize a prepare payload event array
   *
   * If available a read:filextension event is used to parse the
   * page contents to read meta data, and split any header information
   * from the content body.
   *
   * @param \NacoWikiApp $wiki running wiki instance
   * @param string $url URL of the page being prepared
   * @param string $ext media handler file extension
   * @return array Prepared event array
   */
  static function preparePayload(\NacoWikiApp $wiki, string $url,string $ext = NULL) : array {
    if (is_null($ext)) $ext = Plugins::mediaExt($url);
    $event = [
      'filepath' => $wiki->filePath($url),
      'url' => $url,
      'source' => Util::fileContents($wiki->filePath($url)),
      'filemeta' => Util::fileMeta($wiki->filePath($url)),
      'meta' => Util::defaultMeta($wiki->filePath($url)),
      'props' => self::readProps($wiki, $wiki->filePath($url)),
      'payload' => NULL ,
      'extras' => NULL,
      'ext' => $ext,
    ];
    $wiki->props = &$event['props'];

    ##-- events-list##preRead
    ## This event is used to modify data read from disk.  It is intended
    ## for versioning plugins to display different versions of a file.
    ##
    ## Event data:
    ##
    ## - `filepath` (input) : file system path to source
    ## - `url` (input) : web url to source
    ## - `source` (input|output) : text containing the page document verbatim
    ## - `filemeta` (input|output) : pre-loaded file-system based meta data
    ## - `meta` (input|output) : to be filed by event handler with meta-data.
    ##    it is pre-loaded with data derived from filemeta.
    ## - `props` (input|output) : to be filed by event handler with properties.
    ##    it is pre-loaded with data read by Core.
    ## - `payload` (output) : to be used by later event handlers.
    ## - `ext` (input) : file extension for the given media
    ## - `extras` (output) : additional data to display.
    ##    - `annotate` : HTML containing text to be display after page title.
    ##--
    Plugins::dispatchEvent($wiki, 'preRead', $event);

    ##-- events-list##read:[file-extension]
    ## This event is used for media handlers to parse source text
    ## and extract header meta data, and the actual payload containing
    ## the body of the page content.
    ##
    ## Event data:
    ##
    ## - `filepath` (input) : file system path to source
    ## - `url` (input) : web url to source
    ## - `source` (input) : text containing the page document verbatim
    ## - `filemeta` (input) : pre-loaded file-system based meta data
    ## - `meta` (output) : to be filed by event handler with meta-data.
    ##    it is pre-loaded with data derived from filemeta.
    ## - `props` (output) : to be filed by event handler with properties.
    ##    it is pre-loaded with data read by Core.
    ## - `payload` (output) : to be filed by event handler with the body of the page.
    ## - `ext` (input) : file extension for the given media
    ## - `extras` (output) : additional data to display.
    ##    - `annotate` : HTML containing text to be display after page title.
    ##--
    if (!Plugins::dispatchEvent($wiki, 'read:'.$ext, $event)) {
      $event['payload'] = $event['source'];
    }
    return $event;
  }
  /** Read page event handler
   *
   * This is the Core event handler that handles the `read_page` event.
   *
   * Handles read page requests from the web browser.
   *
   * If the page is media that can be handled by a plugin, it will
   * trigger the necessary events to use the plugin hooks.
   *
   * The general sequence is as follows
   *
   * - Check if this file extension is handled by a plugin.
   *   - If `view` event is available, it will let a plugin to handle
   *     the whole process.
   *   - fires pre-render events to pre-process the page.  It will
   *     first try the pre-render:ext for file extension specific
   *     pre-renders.  Following, the generic pre-render event
   *     will be triggered.
   *   - fires render:ext event to convert the page data into html.
   *   - fires post-render render events to post-process the page.  It
   * 	  will first try post-render:ext, followed by post-render.
   *   - fires the layout:ext to output the page on a custom layout,
   *     otherwise the default layout is used.
   * - If no media handler has been registered for this page's file
   *   extension, the file will be send verbatim (without any
   *   processing).  So binary files such as videos, images, audio,
   *   etc, can be loaded into the Wiki and served directly.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event read_page
   */
  static function readPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    $wiki->view = 'page';
    $ext = Plugins::mediaExt($wiki->page);
    if (!is_null($ext)) {
      ##-- events-list##view:[file-extension]
      ##
      ## This event is used by plugins to process data.  If a plugin
      ## handle this event, it by-passes any other Core related
      ## functionality.  The plugin then is responsible for
      ## generating all the output related to this document.
      ##
      ## Event data:
      ##
      ## - `ext` (input) : file extension for the given media
      ##--
      if (Plugins::dispatchEvent($wiki, 'view:'.$ext, Plugins::devt(['ext'=>$ext]))) {
	if (isset($data['no_exit']) && $data['no_exit']) return NULL;
	exit;
      }

      $event = self::preparePayload($wiki, $wiki->page, $ext);
      $wiki->source = $event['source'];
      $wiki->meta = $event['meta'];
      $wiki->filemeta = $event['filemeta'];
      $wiki->payload = $event['payload'];
      $extras = $event['extras'];

      $event = [ 'html' => $wiki->payload, 'ext' => $ext, 'extras' => &$extras ];
      ##-- events-list##pre-render:[file-extension]
      ##
      ## This event is used by plugins to pre-process data.  This
      ## specific event only triggers for pages matching the given
      ## file extension.
      ##
      ## Since this is a pre-render event, the `html` element
      ## actually contains text before HTML is generated, but may
      ## be modified by the pre-render hook.
      ##
      ## Event data:
      ##
      ## - `html` (input|output) : current page contents which will be eventually rendered.
      ## - `ext` (input) : file extension for the given media
      ## - `extras` (input|output) : additional data to display.
      ##    - `annotate` : HTML containing text to be display after page title.
      ##--
      Plugins::dispatchEvent($wiki, 'pre-render:'.$ext, $event);
      ##-- events-list##pre-render
      ##
      ## This event is used by plugins to pre-process data.  This
      ## specific event triggers for any file regardless of the
      ## file extension.
      ##
      ## Since this is a pre-render event, the `html` element
      ## actually contains text before HTML is generated, but may
      ## be modified by the pre-render hook.
      ##
      ## Event data:
      ##
      ## - `html` (input|output) : current page contents which will be eventually rendered.
      ## - `ext` (input) : file extension for the given media
      ## - `extras` (input|output) : additional data to display.
      ##    - `annotate` : HTML containing text to be display after page title.
      ##--
      Plugins::dispatchEvent($wiki, 'pre-render', $event);
      ##-- events-list##render:[file-extension]
      ##
      ## This event is used by media handlers to convert the
      ## source to HTML.
      ##
      ## The hook must take the `html` element from the event
      ## which contains possibily pre-processed input, and
      ## convert it to HTML.
      ##
      ## Event data:
      ##
      ## - `html` (input|output) : current page contents which will be eventually rendered.
      ## - `ext` (input) : file extension for the given media
      ## - `extras` (input|output) : additional data to display.
      ##    - `annotate` : HTML containing text to be display after page title.
      ##--
      Plugins::dispatchEvent($wiki, 'render:'.$ext, $event);
      ##-- events-list##post-render:[file-extension]
      ##
      ## This event is used by plugins to post-process data.  This
      ## specific event only triggers for pages matching the given
      ## file extension.
      ##
      ## Plugins using this hook can post-process the generated HTML.
      ##
      ## Event data:
      ##
      ## - `html` (input|output) : current page contents which will be eventually rendered.
      ## - `ext` (input) : file extension for the given media
      ## - `extras` (input|output) : additional data to display.
      ##    - `annotate` : HTML containing text to be display after page title.
      ##--
      Plugins::dispatchEvent($wiki, 'post-render:'.$ext, $event);
      ##-- events-list##post-render
      ##
      ## This event is used by plugins to post-process data.  This
      ## specific event triggers for any file regardless of the
      ## file extension.
      ##
      ## Plugins using this hook can post-process the generated HTML.
      ##
      ## Event data:
      ##
      ## - `html` (input|output) : current page contents which will be eventually rendered.
      ## - `ext` (input) : file extension for the given media
      ## - `extras` (input|output) : additional data to display.
      ##    - `annotate` : HTML containing text to be display after page title.
      ##--
      Plugins::dispatchEvent($wiki, 'post-render', $event);
      $wiki->html = $event['html'];

      ##-- events-list##layout:[file-extension]
      ##
      ## This event is used by media handlers to output to the web
      ## browser a possibly custom layout for the given media type.
      ##
      ## Event data:
      ##
      ## - `ext` (input) : file extension for the given media
      ## - `extras` (input) : additional data to display.
      ##    - `annotate` : HTML containing text to be display after page title.
      ##--
      if (Plugins::dispatchEvent($wiki, 'layout:'.$ext, Plugins::devt(['ext'=>$ext, 'extras'=>$extras]))) exit;

      $pgview = $data['view'] ?? APP_DIR . 'views/page.html';
      include($pgview);
      if (isset($data['no_exit']) && $data['no_exit']) return NULL;
      exit;
    }
    Util::sendFile($wiki->filePath());
    if (isset($data['no_exit']) && $data['no_exit']) return NULL;
    exit;
  }
  /** Missing page event handler
   *
   * This is the Core event handler that handles the `missing_page` event.
   *
   * The default shows a page that has a link to create the page
   * or to upload a new file.
   *
   * Media handlers can use this event to display a custom page used
   * to create new media.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event missing_page
   */
  static function missingPage(\NacoWikiApp $wiki, array &$data) : ?bool {

    //~ Util::log('TRC:'.__FILE__.','.__LINE__);
    $now = time();
    $wiki->filemeta = Util::fileMeta($wiki->page,time(),$now);
    $wiki->meta = Util::defaultMeta($wiki->page,time(),$now);
    //~ Util::log('TRC:'.__FILE__.','.__LINE__);
    $wiki->meta['title'] = '404: '.htmlspecialchars($wiki->page);

    $ext = Plugins::mediaExt($wiki->page);
    $wiki->view = 'error404';
    http_response_code(404);
    ##-- events-list##missing:[file-extension]
    ##
    ## This event is used by media handlers to output to the web
    ## browser a possibly custom page to create missing media.
    ##
    ## Event data:
    ##
    ## - `ext` (input) : file extension for the given media
    ##--
    if (!is_null($ext)  && Plugins::dispatchEvent($wiki, 'missing:'.$ext, Plugins::devt(['ext'=>$ext]))) exit();
    include(APP_DIR . 'views/404.html');
    exit();
  }
  /** delete page event handler
   *
   * This is the Core event handler that handles the `do:delete` event.
   *
   * Deletes the given page.  If the current page is a folder and it
   * contains files (not just more folders), it will show the folder
   * contents and ask the user for confirmation.
   *
   * If after the page is deleted, there are no more files in the
   * folder containing the page, it will also delete the containing
   * folder (or folders).
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event do:delete
   */
  static function deletePage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (!$wiki->isWritable()) {
      ##!! error-catalog|write_access|Delete access error
      $wiki->errMsg('write_access',$wiki->filePath().': Delete access error');
    }
    if ($wiki->page == '' || $wiki->page == '/') {
      ##!! error-catalog|invalid_target|Can not delete root folder
      $wiki->errMsg('invalid_target','Can not delete root folder');
    }

    $file_path = $wiki->filePath();
    if (is_dir($file_path)) {
      if (is_link($file_path)) {
	// It is a symlink... can be removed
	##!! error-catalog|os_error|unlink error
	if (unlink($file_path) === false) $wiki->errMsg('os_error',$file_path. ': unlink error', EM_PHPERR);
      } else {
	// It is a real directory
	list ($dirs,$files) = Util::walkTree($file_path, false);
	if (count($files) > 0) {
	  //~ echo ('<pre>');
	  //~ print_r($_GET);
	  //~ echo ('</pre>');
	  if (!empty($_GET['confirm']) && filter_var($_GET['confirm'],FILTER_VALIDATE_BOOLEAN)) {
	    while (count($files)) {
	      $cfile = array_pop($files);
	      ##!! error-catalog|os_error|unlink error
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
	##!! error-catalog|os_error|rmdir error
	if (rmdir($file_path.'/'.$cdir) === false) $wiki->errMsg('os_error',$cdir.': rmdir error', EM_PHPERR);
      }
      ##!! error-catalog|os_error|rmdir error
      if (rmdir($file_path) === false) $wiki->errMsg('os_error',$file_path. ': rmdir error', EM_PHPERR);
    } else {
      # First collect extra files...
      $pf = pathinfo($file_path);
      $vics = glob($pf['dirname'].'/.*;'.$pf['basename']);
      if ($vics === false) {
	$vics = [ $file_path ];
      } else {
	$vics[] = $file_path;
      }
      # Delete all victims...
      foreach ($vics as $f) {
	##!! error-catalog|os_error|unlink error
	if (unlink($f) === false) $wiki->errMsg('os_error',$f. ': unlink error', EM_PHPERR);
      }
    }
    // Clean-up directory path
    $dpath = self::prunePath($wiki, $wiki->page);
    header('Location: '.rtrim($wiki->mkUrl($dpath),'/').'/');
    exit;
  }
  /** rename page event handler
   *
   * This is the Core event handler that handles the `do:rename` event.
   *
   * Renames the given page to the http query string `name` field.
   *
   * If after the page is renamed, there are no more files in the
   * folder that was containing the page, it will also delete the containing
   * folder (or folders).
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event do:rename
   */
  static function renamePage(\NacoWikiApp $wiki, array &$data) : ?bool {
    ##!! error-catalog|invalid_target|Cannot rename root directory
    if ($wiki->page == '/' || $wiki->page == '') $wiki->errMsg('invalid_target','Cannot rename root directory');

    ##!! error-catalog|param|no name specified
    if (empty($_GET['name'])) $wiki->errMsg('param','No name specified');
    $newpage = Util::sanitize($_GET['name'],$wiki->page);
    ##!! error-catalog|no-op|no changes made
    if ($newpage == $wiki->page) $wiki->errMsg('no-op','No changes made');

    if (!$wiki->isWritable() || !$wiki->isWritable($newpage) ) {
      ##!! error-catalog|write_access|rename access error
      $wiki->errMsg('write_access',$wiki->filePath().': Rename access error');
    }

    if (is_dir($wiki->filePath($newpage))) {
      // Is an existing directory
      $newpage = rtrim($newpage,'/').'/'.basename($wiki->page);
    } elseif (file_exists($wiki->filePath($newpage))) {
      // Is an existing file
      ##!! error-catalog|duplicate|already exists
      $wiki->errMsg('duplicate',$newpage . ': Already exists!');
    }

    self::makePath($wiki, dirname($newpage));

    # First collect extra files...
    $srcp = pathinfo($wiki->filePath());
    $dstp = pathinfo($wiki->filePath($newpage));
    $vics = glob($srcp['dirname'].'/.*;'.$srcp['basename']);
    if ($vics == false) {
      $vics = [ $wiki->filePath() ];
    } else {
      $vics[] = $wiki->filePath();
    }
    foreach ($vics as $v) {
      $b = basename($v);
      $t = $dstp['basename'];
      if ($b != $srcp['basename']) {
	$i = strpos($b,';');
	if ($i === false) {
	  ##!! error-catalog|internal_error|Internal error.
	  $wiki->errMsg('internal_error',$b.': invalid name');
	}
	$t = substr($b,0,$i+1).$t;
      }
      //~ echo '<pre>Rename: '.$b.' => '.$t.'</pre>';
      if (false === rename($srcp['dirname'].'/'.$b,
			  $dstp['dirname'].'/'.$t)) {
	##!! error-catalog|os_error|rename file error
	$wiki->errMsg('os_error',$wiki->page.'=>'.$newpage.': rename error', EM_PHPERR);
      }
    }

    // Clean-up directory path
    $dpath = self::prunePath($wiki, $wiki->page);
    header('Location: '.$wiki->mkUrl($newpage));
    exit;
  }
  /** edit page event handler
   *
   * This is the Core event handler that handles the `do:edit` event.
   *
   * The default shows a page with a text area to edit the page.
   *
   * Media handlers can use this event to display a customize the
   * page used to edit the media.
   *
   * Usually media handlers would use this to separate content from
   * header data, and also customize a CodeMirror instance to
   * the correct modes for the given media.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event do:edit
   */
  static function editPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    ##!! error-catalog|invalid_target|Folder not editable
    if (substr($wiki->page,-1) == '/') $wiki->errMsg('invalid_target','Folders are not editable');
    if (!$wiki->isWritable()) {
      ##!! error-catalog|write_access|write access error
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
    $wiki->meta['author'] = $wiki->getUser() ? $wiki->getUser() : '?';

    $ext = Plugins::mediaExt($wiki->page);
    $wiki->view = 'edit';

    ##-- events-list##edit:[file-extension]
    ##
    ## This event is used by media handlers to output to the web
    ## browser a possibly custom page to edit media.
    ##
    ## Typically this is used to pre-format the editable content
    ## separating metadata and body text.  Also, to display
    ## a codemirror with the right modules loaded and initialized
    ## to the right mode.
    ##
    ## Event data:
    ##
    ## - `ext` (input) : file extension for the given media
    ##--
    if (!is_null($ext)  && Plugins::dispatchEvent($wiki, 'edit:'.$ext, Plugins::devt(['ext'=>$ext]))) exit();

    include(APP_DIR . 'views/edit.html');
    exit();
  }
  /** save page event handler
   *
   * This is the Core event handler that handles the `do:save` event.
   *
   * Normally it will get the payload from a field name `text` from the POST data.
   *
   * UNIX EOL conversion is handled here.
   *
   * The following events are triggered:
   *
   * - preSave
   * - preSave:ext
   *   - preSave events happen before data is written.  Can be used
   *     to sanitize user input, by for example, pre-parsing headers.
   *   - There are two events, generic and file extension specific. \
   *     If a media handler is available, both events will be triggered.
   *     Otherwise, only the generic one will be triggered.
   * - save:ext
   *   - This actually saves the page.  If no plugin hooked this event,
   *     it will simply save payload using `file_put_contents` and
   *     save properties.
   * - postSave
   * - postSave:ext
   *   - postSave events happen after data is written.  Can be used
   *     to for example update tag cloud information.
   *   - There are two events, generic and file extension specific. \
   *     If a media handler is available, both events will be triggered.
   *     Otherwise, only the generic one will be triggered.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event action:save
   */
  static function savePage(\NacoWikiApp $wiki, array &$data) : ?bool {
    ##!! error-catalog|invalid_target|folders not editable
    if (substr($wiki->page,-1) == '/') $wiki->errMsg('invalid_target','Folders are not editable');
    if (!$wiki->isWritable()) {
      ##!! error-catalog|write_access|write access error
      $wiki->errMsg('write_access',$wiki->filePath().': Write access error');
    }

    $ext = Plugins::mediaExt($wiki->page);
    $ev = [ 'text' => $_POST['text'] ?? '', 'ext'=>$ext];

    if ($wiki->cfg['unix_eol']) $ev['text'] = str_replace("\r", "", $ev['text']);

    # Pre-read the content
    if (file_exists($wiki->filePath())) {
      $ev['prev'] = Util::fileContents($wiki->filePath());
      $ev['props'] = self::readProps($wiki, $wiki->filePath());
      self::updateProps($wiki, $ev['props']);
    } else {
      $ev['prev'] = NULL;
      $ev['props'] = self::defaultProps($wiki);
    }
    $wiki->props = &$ev['props'];


    ##-- events-list##preSave
    ##
    ## This event is used by plugins to pre-parse text before
    ## saving to storage.
    ##
    ## Hooks can examine the data to be saved in the `text` element
    ## of the event array and modify it if needed.
    ##
    ## Event data:
    ##
    ## - `text` (input|output) : textual data to save
    ## - `prev` (input) : current file contents (or NULL)
    ## - `ext` (input) : file extension for the given media
    ## - `props` (output) : to be filed by event handler with properties.
    ##    it is pre-loaded with data read by Core.
    ##--
    Plugins::dispatchEvent($wiki, 'preSave', $ev);
    ##-- events-list##preSave:[file-extension]
    ##
    ## This event is used by plugins to pre-parse text before
    ## saving to storage.
    ##
    ## Hooks can examine the data to be saved in the `text` element
    ## of the event array and modify it if needed.
    ##
    ## Usually is used by media handlers to pre-parse data, separating
    ## header meta data from actual content.  And sanitizing any
    ## problematic input.
    ##
    ## It is recommended to move 'log' keys from the user entered
    ## meta data block to the `props` array using `Core::logProps`
    ## which will add the log text to the change log.
    ##
    ## Event data:
    ##
    ## - `text` (input|output) : textual data to save
    ## - `prev` (input) : current file contents (or NULL)
    ## - `ext` (input) : file extension for the given media
    ## - `props` (output) : to be filed by event handler with properties.
    ##    it is pre-loaded with data read by Core.
    ##--
    if (!is_null($ext)) Plugins::dispatchEvent($wiki, 'preSave:'.$ext, $ev);

    ##-- events-list##save:[file-extension]
    ##
    ## This event is used by plugins to save to storage.
    ##
    ## Usually is used by media handlers to save data using custom
    ## file formats.
    ##
    ## Event data:
    ##
    ## - `saved` (output) : flag to indicates that we saved or not
    ##    the file.  If this is set to `false`, then `postSave` events
    ##    will be skipped.  Pre-set to `true` by default.
    ## - `text` (input) : textual data to save
    ## - `prev` (input) : current file contents (or NULL)
    ## - `ext` (input) : file extension for the given media
    ## - `props` (input) : properties to save
    ##--
    $ev['saved'] = true;
    if (is_null($ext) || !Plugins::dispatchEvent($wiki, 'save:'.$ext, $ev)) {
      if (is_null($ev['prev']) || $ev['text'] != $ev['prev']) {
	self::makePath($wiki, dirname($wiki->page));
	if (false === file_put_contents($wiki->filePath(), $ev['text'])
	      || false === self::saveProps($wiki,$ev['props'])) {
	  ##!! error-catalog|os_error|write error
	  $wiki->errMsg('os_error',$wiki->page.': write error', EM_PHPERR);
	}
      } else {
	$ev['saved'] = false;
      }
    }
    if ($ev['saved']) {
      ##-- events-list##postSave
      ##
      ## This event is used by plugins to examine data after it
      ## was saved to storage.
      ##
      ## Usually used to update additional meta data files, like for
      ## example update a tag cloud index.
      ##
      ## Event data:
      ##
      ## - `text` (input) : textual data that was saved
      ## - `prev` (input) : previous file contents (or NULL)
      ## - `ext` (input) : file extension for the given media
      ## - `props` (input) : saved properties.
      ##--
      Plugins::dispatchEvent($wiki, 'postSave', $ev);
      ##-- events-list##postSave:[file-extension]
      ##
      ## This event is used by media handlers to examine data after it
      ## was saved to storage.
      ##
      ## Event data:
      ##
      ## - `text` (input) : textual data that was saved
      ## - `prev` (input) : previous file contents (or NULL)
      ## - `ext` (input) : file extension for the given media
      ## - `props` (input) : saved properties.
      ##--
      if (!is_null($ext)) Plugins::dispatchEvent($wiki, 'postSave:'.$ext, $ev);
    }
    header('Location: '.$wiki->mkUrl($wiki->page));
    exit;
  }
  /** attach to page event handler
   *
   * This is the Core event handler that handles the `do:attach` event.
   *
   * Handles POST requests to upload files to pages or folders.  The
   * uploaded file is expected to be in the `fileToUpload` POST element.
   *
   * If uploading to a folder, it will simply upload the file to the
   * directory for that folder.
   *
   * If uploading to a page with $cfg[default_doc] name, it will treat
   * it as uploading to the directory for that default doc page.
   *
   * If uploading to a normal page, it will create a folder with the
   * same name as the page without file extension and upload the file
   * there.
   *
   * As it is, attachments are essentially ordinary files
   * that convention that files in a folder the same
   * name as the page (without extension) are its attachments.
   *
   * @param \NanoWikiApp $wiki current wiki instance
   * @param array $data ignored
   * @event action:attach
   */
  static function attachToPage(\NacoWikiApp $wiki, array &$data) : ?bool {
    if (!$wiki->isWritable()) {
      ##!! error-catalog|write_access|no write access
      $wiki->errMsg('write_access',$wiki->filePath().': Write access error');
    }
    if (substr($wiki->page,-1) != '/') {
      if (basename($wiki->page) == $wiki->cfg['default_doc']) {
	$updir = dirname($wiki->page);
      } else {
	$ext = Plugins::mediaExt($wiki->page);
	if (is_null($ext)) {
	  ##!! error-catalog|invalid_target|can not attach to file
	  $wiki->errMsg('invalid_target',$wiki->filePath().': can not attach to file');
	}
	$p = pathinfo($wiki->page);
	if ($p['basename'] == $p['filename']) {
	  ##!! error-catalog|invalid_target|can not attach to file
	  $wiki->errMsg('invalid_target',$wiki->filePath().': can not attach to file');
	}
	$updir = $p['dirname'].'/'.$p['filename'];
      }
    } else {
      $updir = $wiki->page;
    }
    $updir = '/' . trim($updir,'/') . '/';

    ##!! error-catalog|param|invalid form response
    if (!isset($_FILES['fileToUpload'])) $wiki->errMsg('param','Invalid FORM response');
    $fd = $_FILES['fileToUpload'];
    ##!! error-catalog|param|zero file upload
    if (isset($fd['size']) && $fd['size'] == 0) $wiki->errMsg('param','Zero file submitted');
    ##!! error-catalog|param|upload form error
    if (isset($fd['error']) && $fd['error'] != 0) $wiki->errMsg('param','Error: '.$fd['error']);
    ##!! error-catalog|param|missing file upload
    if (empty($fd['name']) || empty($fd['tmp_name'])) $wiki->errMsg('param','No file uploaded');

    $fname = Util::sanitize(basename($fd['name']));
    $fpath = $wiki->filePath($updir . $fname);
    if (file_exists($fpath)) $wiki->errMsg('duplicate',$fname.': File already exists');

    self::makePath($wiki,$updir);
    if (!move_uploaded_file($fd['tmp_name'],$fpath)) {
      ##!! error-catalog|os_error|error saving uploaded file
      $wiki->errMsg('os_error','Error saving uploaded file', EM_PHPERR);
    }

    header('Location: '.$wiki->mkUrl($updir));
    exit;
  }
  /** Returns a page list
   *
   * This is used by the JavaScript to render a tree of available pages.
   *
   * The event parameter is filled with a property `output` containing
   *
   * - array with directories
   * - array with files
   * - current page
   * - base URL from $cfg[base_url]
   *
   * @todo Should check permissions when returning files
   * @todo Should filter out attachment folders
   * @see \NWiki\PluginCollection::dispatchEvent
   * @phpcod RESTAPI##page-list
   * @event api:page-list
   * @param \NacoWikiApp $wiki NacoWiki instance
   * @param array $ev Event data.
   * @return ?bool Returns \NWiki\PluginCollection::OK to indicate that it was handled.
   */
  static function apiPageList(\NacoWikiApp $wiki, array &$ev) : ?bool {
    //~ $res = Util::walkTree($wiki->cfg['file_store']);
    $res = Util::walkTree($wiki->cfg['file_store'], true, $wiki->cfg['vacuum']);
    $res[] = $wiki->page;
    $res[] = $wiki->cfg['base_url'];
    $ev['output'] = $res;
    return Plugins::OK;
  }
  /**
   * Loading entry point for this class
   *
   * Hooks events implemented by this class
   */
  static function load() : void {
    Plugins::autoload(self::class);

    //~ Plugins::registerEvent('action:attach',[self::class,'attachToPage']);
    //~ Plugins::registerEvent('action:save',[self::class,'savePage']);
    //~ Plugins::registerEvent('do:edit',[self::class,'editPage']);
    //~ Plugins::registerEvent('do:rename',[self::class,'renamePage']);
    //~ Plugins::registerEvent('do:delete',[self::class,'deletePage']);
    //~ Plugins::registerEvent('missing_page',[self::class,'missingPage']);
    //~ Plugins::registerEvent('read_page',[self::class,'readPage']);
    //~ Plugins::registerEvent('api:page-list',[self::class,'apiPageList']);
    //~ Plugins::registerEvent('read_folder',[self::class,'readFolder']);
    //~ Plugins::registerEvent('do:search',[self::class,'searchPage']);
  }
}


