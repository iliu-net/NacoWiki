<?php
/**  @package NWiki */
namespace NWiki;

/**
 * Utility methods
 *
 * Class providing utility methods that are not strictly tied to
 * NacoWiki
 */
class Util {
  /** Set of valid characters
   *
   * Follows [POSIX portable filename character set](https://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap03.html#tag_03_282).
   *
   * If changing this, try to keep valid chars compatible with
   * Windows. (See: https://stackoverflow.com/questions/1976007/what-characters-are-forbidden-in-windows-and-linux-directory-names)
   *
   * Also one must reserve ';' to use for alternative streams for example:
   *
   * `.prop;page.md`
   *
   * @var string
   */
  const VALID_CHARS = '-A-Za-z0-9_\/\.';
  /** content cache
   *
   * Caches file contents
   *
   * @var string[]
   */
  static $cache = [ 'content' => [] ];
  /** used to keep logged strings
   * @var string[] */
  static $logmsg = [];

  /** resolves a file or URL path
   *
   * It will examine the $path and resolves `.` and `..` directory
   * entries.
   *
   * Optionally, will remove path components that start with `.` (dot).
   *
   * Resolved path will never contain any `.` or `..` path components,
   * so, resulting paths will never go up outside the current directory
   * tree.
   *
   * @param string $path file path to resulve
   * @param bool $nodots defaults to false, if true path componets that
   * 			start with `.` (dot) will be removed.
   * @return string resolved path
   */
  static function runPath(string $path, bool $nodots = false) : string {
    $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
    $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
      if ('.' == $part) continue;
      if ('..' == $part) {
	array_pop($absolutes);
      } else {
	if ($nodots && preg_match('/^\.+$/',$part)) continue;
	$absolutes[] = $part;
      }
    }
    return implode(DIRECTORY_SEPARATOR, $absolutes);
  }
  /** make sure input path is sane
   *
   * Will use `runPath` and `VALID_CHARS` to make sure that the
   * given $url does not contain invalid characters (as defined by
   * `VALID_CHARS` and does not contain `.` or `..` path components.
   *
   * @param string $url URL to sanitize
   * @param string $rdoc optional realtive doc to use when sanitizing relative paths
   * @return string sanitized url path
   */
  static function sanitize(string $url, string $rdoc = '') : string {
    if ($url == '/' || $url == '') return $url; // Trival case!
    $pre = substr($url,0,1) == '/' ? '/' : '';
    $suf = substr($url,-1) == '/' ? '/' : '';

    if ($pre == '/') {
      $rdoc = '';
    } else {
      $rdoc = (substr($rdoc,-1) == '/') ? rtrim($rdoc,'/') : dirname($rdoc);
      if ($rdoc != '') $rdoc .= '/';
      $pre = '/';
    }
    $url = self::runPath($rdoc.$url,true); // Gets rid of . and .., also multiple slashes

    $url = preg_replace('/\s+/','_', $url); // No spaces
    $url = preg_replace('/[^'.self::VALID_CHARS.']/', '', $url); // valid chars

    $url = preg_replace('/^\.\.*([^\.\/])/','$1', $url); // cannot start with .
    $url = preg_replace('/\/\.\.*([^\.\/])/','/$1', $url); // cannot start with .

    if ($url == '') return $url;
    return $pre.$url.$suf;
  }

  /** var_dump to string
   *
   * Returns a string representation of the given value
   *
   * @param mixed $val value to dump
   * @return string dumped value
   */
  static function vdump($val) : string {
    ob_start();
    var_dump($val);
    $res = ob_get_contents();
    ob_end_clean();
    return trim($res);
  }
  /** Return a simplified stack trace
   *
   * @return string dumped strack trace suitable for Util::log()
   */
  static function stackTrace() : string {
    $trace = PHP_EOL;
    foreach (debug_backtrace() as $t) {
      $trace.= '   '.$t['file'].','.$t['line'].':('.$t['class'].'::'.$t['function'].') -- '.$t['type'].PHP_EOL;
    }
    return $trace;
  }
  /** log message
   *
   * Writes the message to stderr and also saves it to the `logmsg`
   * static property.  The log messages can then be retrieved
   * later with `dumpLog`.
   *
   * Log entries are tagged with the file and line location of
   * calling scope.
   *
   * @param string $msg text to log
   */
  static function log(string $msg = '') : void {
    $trace = debug_backtrace();
    $file = $trace[0]['file'];
    if (defined('APP_DIR')) {
      if (substr($file,0,strlen(APP_DIR)) == APP_DIR) {
	$file = substr($file,strlen(APP_DIR));
      }
    }

    $tag = $file.','.$trace[0]['line'].':';
    if (isset($trace[1])) {
      foreach (['class','type','function'] as $k) {
	if (!empty($trace[1][$k])) $tag .= $trace[1][$k];
      }
    }
    $tag .= ': ';
    file_put_contents( "php://stderr",$tag.$msg.PHP_EOL);
    self::$logmsg[] = $tag.$msg;
  }
  /** dump log messages
   *
   * Output HTML with the contents of the message log
   *
   * @param bool $hr if true, a `<hr>` will be shown first.
   */
  static function dumpLog(bool $hr = false) : void {
    if (count(self::$logmsg) == 0) return;
    if ($hr) echo '<hr/>';
    echo '<pre>';
    foreach (self::$logmsg as $l) {
      echo (htmlspecialchars($l).PHP_EOL);
    }
    echo '</pre>';
    if ($hr) echo '<hr/>';
  }
  /** Generat file-system level meta data
   *
   * Will create file level metadata containing:
   *
   * - datetime - YYYY-MM-DD HR:MN:SC
   * - year - 4 digit number
   * - mtime - time stamp in Linux Epoch.
   *
   * @param string $fn name of file to use
   * @param $mtime if provided, it will not get timestamps from the file system.
   * @return ?array contains file-system related metadata, NULL on error.
   */
  static function fileMeta(string $fn, int $mtime = NULL) : array {
    if (is_null($mtime)) {
      if (!file_exists($fn)) return [];
      $mtime = filemtime($fn);
      if ($mtime === false) return NULL;
    }

    return [
      'datetime' => gmdate('Y-m-d H:i:s',$mtime),
      'year' => gmdate('Y',$mtime),
      'date' => gmdate('Y-m-d',$mtime),
      'mtime' => $mtime,
    ];
  }
  /** Generate default meta-data from file system
   *
   * Create default metadata from filesystem meta-data containing:
   *
   * - title - based on the filename
   * - date - YYYY-MM-DD
   *
   * @param string $fn name of file to use
   * @param $mtime if provided, it will not get timestamps from the file system.
   * @return ?array contains default metadata or NULL on error
   */
  static function defaultMeta(string $fn, int $mtime = NULL) : ?array {
    if (is_null($mtime)) {
      if (!file_exists($fn)) return NULL;
      $mtime = filemtime($fn);
      if ($mtime === false) return NULL;
    }
    $pi = pathinfo($fn);
    return [
	'title' => $pi['filename'],
	'date' => gmdate('Y-m-d',$mtime),
      ];
  }
  /** cached file_get_contents
   *
   * It wraps file_get_contents with a simple cache.
   *
   * @param string $fn name of file to read
   * @return ?string file context or NULL on error.
   */
  static function fileContents(string $fn) : ?string {
    $fn = realpath($fn);
    if (!isset(self::$cache['content'][$fn])) {
      $c = file_get_contents($fn);
      if ($c === false) $c = NULL;
      self::$cache['content'][$fn] = $c;
    }
    return self::$cache['content'][$fn];
  }
  /** Send $file_path
   *
   * Sends the given file.  It supports byte ranges for
   * download resume.
   *
   * @param string $file_path path of the file to send
   * @param string $mime content-type header
   */
  static function sendFile(string $file_path,string $mime = NULL) : void {
    header('Accept-Ranges: bytes');
    ### Remove headers that might unnecessarily clutter up the output
    header_remove('Cache-Control');
    header_remove('Pragma');
    if (is_null($mime)) {
      $mime = mime_content_type($file_path);

      if ($mime === false) $mime = 'application/octet-stream';
      header('Content-Type: '.$mime);
      header('Content-Disposition: filename="'
	      . basename($file_path) . '"');
    } else {
      header('Content-Type: '.$mime);
    }

    ### Default to send entire file
    $byteOffset = 0;
    $byteLength = $fileSize = filesize($file_path);
    if ($fileSize == 0) return;

    ### Parse Content-Range header for byte offsets, looks like "bytes=11525-" OR "bytes=11525-12451"
    if( isset($_SERVER['HTTP_RANGE']) && preg_match('%bytes=(\d+)-(\d+)?%i', $_SERVER['HTTP_RANGE'], $match) ) {
      ### Offset signifies where we should begin to read the file
      $byteOffset = (int)$match[1];

      ### Length is for how long we should read the file according to the browser, and can never go beyond the file size
      if( isset($match[2]) ){
	$finishBytes = (int)$match[2];
	$byteLength = $finishBytes + 1;
      } else {
	$finishBytes = $fileSize - 1;
      }
      $cr_header = sprintf('Content-Range: bytes %d-%d/%d', $byteOffset, $finishBytes, $fileSize);

      header('HTTP/1.1 206 Partial content');
      header($cr_header);  ### Decrease by 1 on byte-length since this definition is zero-based index of bytes being sent
    }

    if ($byteOffset >= $byteLength) {
      http_response_code(416);
      die('Range outside resource size: '.$_SERVER['HTTP_RANGE']);
    }

    $byteRange = $byteLength - $byteOffset;

    header('Content-Length: ' . $byteRange);
    header('Expires: '. date('D, d M Y H:i:s', time() + 60*60*24*90) . ' GMT');

    $buffer = ''; 			### Variable containing the buffer
    $bufferSize = 1024 * 32;		### Just a reasonable buffer size
    $bytePool = $byteRange;		### Contains how much is left to read of the byteRange

    if(!($handle = fopen($file_path, 'r'))) die("Error reading: $file_path");
    if(fseek($handle, $byteOffset, SEEK_SET) == -1 ) die("Error seeking file");

    while( $bytePool > 0 ) {
      $chunkSizeRequested = min($bufferSize, $bytePool); ### How many bytes we request on this iteration

      ### Try readin $chunkSizeRequested bytes from $handle and put data in $buffer
      $buffer = fread($handle, $chunkSizeRequested);

      ### Store how many bytes were actually read
      $chunkSizeActual = strlen($buffer);

      ### If we didn't get any bytes that means something unexpected has happened since $bytePool should be zero already
      if( $chunkSizeActual == 0 ) die('Chunksize became 0');

      ### Decrease byte pool with amount of bytes that were read during this iteration
      $bytePool -= $chunkSizeActual;

      ### Write the buffer to output
      print $buffer;

      ### Try to output the data to the client immediately
      flush();
    }
  }
  /** internal: get directory contents
   *
   * This will recursively create a list of directory contents.
   *
   * @internal
   * @param string $basedir base directory for searching
   * @param string $subdir current subdirectory
   * @param array &$dirs array receiving directory entries
   * @param array &$files array receiving file entries
   * @param array &$lnkf array containing directories realpath's for avoiding symlink loops.
   */
  static function _walkTree(string $basedir, string $subdir, array &$dirs, array &$files, array &$lnkf) : void {
    $rp = realpath($basedir.$subdir);
    if (isset($lnkf[$rp])) return;
    $lnkf[$rp] = $rp;

    $dp = @opendir($basedir . $subdir);
    //~ echo ('TR:'.__FILE__.','.__LINE__.'|'.$dp.PHP_EOL);
    if ($dp === false) return;
    //~ echo ('TR:'.__FILE__.','.__LINE__.'|'.$dp.PHP_EOL);

    if ($subdir == '') {
      $fdir = $basedir;
    } else {
      $fdir = $basedir . $subdir .'/';
      $subdir .= '/';
    }
    //~ echo ('TR:'.__FILE__.','.__LINE__.'|subdir '.$subdir.PHP_EOL);
    //~ echo ('TR:'.__FILE__.','.__LINE__.'|fdir '.$fdir.PHP_EOL);

    while (false !== ($fn = readdir($dp))) {
      if ($fn == '.' || $fn == '..') continue;
      if (is_dir($fdir . $fn)) {
	$dirs[] = $subdir . $fn;
	//~ if (is_link($fdir . $fn)) continue;
	self::_walkTree($basedir, $subdir.$fn, $dirs, $files,$lnkf);
      } else {
	$files[] = $subdir . $fn;
      }
    }
    closedir($dp);
  }

  /** get directory contents
   *
   * Create a list of files and folders in the $basedir directory.
   *
   * @param string $basedir directory to read.
   * @return array [ $array-of-dirs, $array-of-files ]
   */

  static function walkTree(string $basedir) : array {
    $slnkf = [];
    $basedir = rtrim($basedir,'/');
    if ($basedir == '') $basedir = '.';
    $basedir .= '/';
    $dirs = [];
    $files = [];
    self::_walkTree($basedir,'', $dirs, $files,$slnkf);
    return [$dirs,$files];
  }
  /** Copy files recursively
   *
   * From [copy doc in php.net](https://www.php.net/manual/de/function.copy.php#91010)
   *
   * @param string $src : source directory
   * @param string $dst : target directory
   */
  static function recurse_copy(string $src,string $dst) : void {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
      if (( $file != '.' ) && ( $file != '..' )) {
	if ( is_dir($src . '/' . $file) ) {
	  self::recurse_copy($src . '/' . $file,$dst . '/' . $file);
	}
	else {
	  copy($src . '/' . $file,$dst . '/' . $file);
	}
      }
    }
    closedir($dir);
  }

}

