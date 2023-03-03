<?php
/**  @package NWiki */
namespace NWiki;

class Util {
  const VALID_CHARS = '-A-Za-z0-9_\/\.';
  # Follows https://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap03.html#tag_03_282
  # POSIX portable filename character set.
  # Try to keep valid chars compatible with Windows (See: https://stackoverflow.com/questions/1976007/what-characters-are-forbidden-in-windows-and-linux-directory-names)
  #
  # We also reserve ';' to use for alternative streams ".prop;page.md"

  static $cache = [ 'content' => [] ];
  static $logmsg = [];

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

  static function vdump($val) : string {
    ob_start();
    var_dump($val);
    $res = ob_get_contents();
    ob_end_clean();
    return trim($res);
  }
  static function log(string $msg) : void {
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
  static function fileMeta(string $fn, int $mtime = NULL) : ?array {
    //~ self::log('TRC:'.__FILE__.','.__LINE__.':mtime '.self::vdump($mtime));
    if (is_null($mtime)) $mtime = filemtime($fn);
    //~ self::log('TRC:'.__FILE__.','.__LINE__.':mtime '.self::vdump($mtime));
    if ($mtime === false) return NULL;

    return [
      'datetime' => gmdate('Y-m-d H:i:s',$mtime),
      'year' => gmdate('Y',$mtime),
      'date' => gmdate('Y-m-d',$mtime),
      'mtime' => $mtime,
    ];
  }
  static function defaultMeta(string $fn, int $mtime = NULL) : ?array {
    //~ self::log('TRC:'.__FILE__.','.__LINE__.':mtime '.self::vdump($mtime));
    if (is_null($mtime)) $mtime = filemtime($fn);
    if ($mtime === false) return NULL;
    return [
	'title' => basename($fn),
	'date' => gmdate('Y-m-d',$mtime),
      ];
  }
  static function fileContents(string $fn) : ?string {
    $fn = realpath($fn);
    if (!isset(self::$cache['content'][$fn])) {
      $c = file_get_contents($fn);
      if ($c === false) $c = NULL;
      self::$cache['content'][$fn] = $c;
    }
    return self::$cache['content'][$fn];
  }
  static function sendFile(string $file_path) : void {
    header('Accept-Ranges: bytes');
    ### Remove headers that might unnecessarily clutter up the output
    header_remove('Cache-Control');
    header_remove('Pragma');
    $mime = mime_content_type($file_path);

    if ($mime === false) $mime = 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Disposition: filename="'
	      . basename($file_path) . '"');

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
}

//~ $b = '';
//~ foreach ($argv as $tc) {
  //~ echo Util::vdump($tc) .' => '. Util::vdump(Util::sanitize($tc,$b)) .PHP_EOL;
//~ }

