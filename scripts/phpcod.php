<?php
#
# Extract additional documentation strings not covered by
# phpdoc
#
require(__DIR__.'/../compat/main.php');
require(__DIR__.'/../classes/Cli.php');
use \NWiki\Cli as Cli;

abstract class MODE {
  const NONE = 0;
  const CODSTR = 1;
  const DOCSTR = 2;
  const SEARCH = NULL;
}
class CSTR {
  const FILE_LINE = 'file-line';
  const TEXT = 'text';
  const COLL = 'collection';
  const SECT = 'section';
  const LEVEL = 'level';
  const NONE = '';
  const TAG = 'tag';

  const SECTIONS = 'sections';
  const MARKERS = 'markers';
  const DOCSTR = 'doc-strings';

  const LOAD='--load=';
  const YAML='--yaml=';
  const DEFEXT = '--default-ext=';
  const OUTPUT='--output=';
}

class RE {
  const SECT = '/^\s*##--\s*(.*)$/';
  const MARKER = '/^\s*##!!\s*(.*)$/';
  const CONTENT = '/^\s*##\s?(.*)$/';

  const START_DOCSTR = '/\/\*\*\s*(.*)$/';
  const END_DOCSTR = '/^(.*)\s*\*\//';
  const DOCSTR_PREFIX = '/^\s*\*\s?(.*)$/';
  const DOCSTR_TAGGED = '/^\s*\*?\s*@phpcod\s*(\S*)/m';
  const DOCSTR_XFORM = '/^(\s*)@/';
  const DOCSTR_XFORM_T = '$1- @';
}

/** write updated data to a file
 *
 * Checks if filename exists, and if it does, it will verify
 * if the data has been changed. If not, it will return NULL.
 * Otherwise the filename will be updated.
 *
 * @param string $filename Path to the file where to write the data.
 * @param string $data The data to write
 * @return true if data was written, NULL if not data needed to be written, false on error.
 */
function file_update(string $filename, string $data) : ?bool {
  if (file_exists($filename)) {
    $old = file_get_contents($filename);
    if ($old === false) return false;
    if ($old == $data) return NULL;
  }
  if (file_put_contents($filename, $data) === false) return false;
  return true;
}

/**
 * Parse tag
 *
 * Expands tags of the form "collection#item"
 *
 * @param string $tag
 * @return array with CSTR::COLL, CSTR::LEVEL and CSTR::SECT
 */
function parseTag(string $tag,string $file, int $lc) : array {
  $res = [
    CSTR::COLL => CSTR::NONE,
    CSTR::LEVEL => 0,
    CSTR::SECT => CSTR::NONE,
    CSTR::TEXT => CSTR::NONE,
    CSTR::FILE_LINE => fileLine($file,$lc),
  ];
  $i = strpos($tag, '#');
  if ($i === false) {
    $res[CSTR::COLL] = $tag;
  } else {
    $res[CSTR::COLL] = substr($tag,0,$i);
    $s = substr($tag,$i);
    $i = 0;
    while ($s[$i] == '#') ++$i;
    $res[CSTR::LEVEL] = $i;
    $res[CSTR::SECT] = substr($s,$i);
  }
  return $res;
}

/** formatted file,lc
 * @param string $file
 * @param int $line
 * @return string formatted file,line
 */
function fileLine(string $file, int $lc) {
  return $file.','.$lc;
}

/** Force a file extension
 *
 * Given a filename, it will make sure that it has a file extension
 * by adding $fext if none was specified.
 *
 * @param string $fn filename
 * @param string $fext default extension to add
 * @return string file with a forced extension
 */
function addExt(string $fn, string $fext) : string {
  if (empty(pathinfo($fn)['extension'])) return $fn.$fext;
  return $fn;
}


/** Transform docstr
 * @param string $docstr string to transform
 * @return string transformed docstr
 */
function transformDocstr(string $docstr) : string {
  return implode("\n",preg_replace(RE::DOCSTR_XFORM, RE::DOCSTR_XFORM_T,explode("\n",$docstr)));
}

/**
 * Process php source file
 * @param string $src input file name
 * @param array $data array receiving results
 * @return true on success, false on error
 */
function readMyFile(string $src, array &$data) : bool {
  $srctxt = file($src);
  if ($srctxt === false) return false;

  $lc = 0;
  $found = MODE::SEARCH;
  $mode = MODE::NONE;
  foreach ($srctxt as $srcln) {
    ++$lc;

    if (($mode == MODE::NONE || $mode == MODE::CODSTR) && preg_match(RE::SECT, $srcln,$mv)) {
      $mode = MODE::CODSTR;
      if ($found) {
	if (!isset($data[CSTR::SECTIONS][$found[CSTR::COLL]][$found[CSTR::SECT].'#'.$found[CSTR::LEVEL]])) {
	  $data[CSTR::SECTIONS][$found[CSTR::COLL]][$found[CSTR::SECT].'#'.$found[CSTR::LEVEL]] = $found;
	  $data[CSTR::SECTIONS][$found[CSTR::COLL]][$found[CSTR::SECT].'#'.$found[CSTR::LEVEL]][CSTR::TEXT] = [
	    [ $found[CSTR::TEXT], $found[CSTR::FILE_LINE] ],
	  ];
	  unset($data[CSTR::SECTIONS][$found[CSTR::COLL]][$found[CSTR::SECT].'#'.$found[CSTR::LEVEL]][CSTR::FILE_LINE]);
	} else {
	  $data[CSTR::SECTIONS][$found[CSTR::COLL]][$found[CSTR::SECT].'#'.$found[CSTR::LEVEL]][CSTR::TEXT][] =
	      [ $found[CSTR::TEXT], $found[CSTR::FILE_LINE] ];
	}
	//~ $data[CSTR::SECTIONS][$found[CSTR::COLL]][$found[CSTR::SECT].'#'.$found[CSTR::LEVEL]][] = $found;
      }
      if ($mv[1] && $mv[1] != '-') { // Handle weird special cases
	$found = parseTag($mv[1], $src, $lc);
      } else {
	$found = MODE::SEARCH;
	$mode = MODE::NONE;
      }
    } else if (($mode == MODE::NONE || $mode == MODE::CODSTR) && preg_match(RE::MARKER, $srcln,$mv)) {
      if (empty($mv[1])) continue;
      $m = explode('|',$mv[1]);
      if (empty($m[1])) $m[1] = '';
      if (empty($m[2])) $m[2] = '';

      if (!isset($data[CSTR::MARKERS][$m[0]][$m[1]][$m[2]])) {
	$data[CSTR::MARKERS][$m[0]][$m[1]][$m[2]] = [
	  CSTR::COLL => $m[0],
	  CSTR::TAG => $m[1],
	  CSTR::TEXT => $m[2],
	  CSTR::FILE_LINE => [],
	];
      }
      $data[CSTR::MARKERS][$m[0]][$m[1]][$m[2]][CSTR::FILE_LINE][] = fileLine($src, $lc);
    } else if ($found && preg_match(RE::CONTENT,$srcln,$mv)) {
      $found[CSTR::TEXT] .= $mv[1].PHP_EOL;
    } else if ($mode == MODE::NONE && preg_match(RE::START_DOCSTR,$srcln, $mv)) {
      // Check for single line docstr
      if (preg_match(RE::END_DOCSTR,$mv[1],$m2)) continue;
	//~ if ($flags & MODE::ALLDOCS == MODE::ALLDOCS) {
	  //~ $data[CSTR::DOCSTR][$src][] = [
	    //~ CSTR::TEXT => $m2[1].PHP_EOL,
	    //~ CSTR::FILE_LINE => fileLine($src,$lc),
	  //~ ];
	//~ }
      $mode = MODE::DOCSTR;
      $found = [
	CSTR::TEXT => $mv[1].PHP_EOL,
	CSTR::FILE_LINE => fileLine($src,$lc),
      ];
    } else if ($mode == MODE::DOCSTR) {
      if (preg_match(RE::END_DOCSTR,$srcln,$mv)) {
	# Found the end of the docstr
	if (preg_match(RE::DOCSTR_PREFIX,$mv[1],$m2)) {
	  $found[CSTR::TEXT] .= $m2[1].PHP_EOL;
	} else {
	  $found[CSTR::TEXT] .= $mv[1].PHP_EOL;
	}
	//~ echo $found[CSTR::TEXT];
	//~ echo PHP_EOL.'***'.PHP_EOL;
	//~ if ($flags & MODE::ALLDOCS == MODE::ALLDOCS) {
	  //~ $found[CSTR::TEXT] = transformDocstr($found[CSTR::TEXT]);
	  //~ $data[CSTR::DOCSTR][$src][] = $found;
	//~ } else
	if (preg_match(RE::DOCSTR_TAGGED, $found[CSTR::TEXT], $mv)) {
	  $found[CSTR::TEXT] = transformDocstr($found[CSTR::TEXT]);
	  $tt = parseTag($mv[1], $src, $lc);
	  if (!isset($data[CSTR::DOCSTR][$tt[CSTR::COLL]][$tt[CSTR::SECT].'#'.$tt[CSTR::LEVEL]])) {
	    $data[CSTR::DOCSTR][$tt[CSTR::COLL]][$tt[CSTR::SECT].'#'.$tt[CSTR::LEVEL]] = [
	      CSTR::COLL => $tt[CSTR::COLL],
	      CSTR::SECT => $tt[CSTR::SECT],
	      CSTR::LEVEL => $tt[CSTR::LEVEL],
	      CSTR::TEXT => [
	    	[ $found[CSTR::TEXT], $found[CSTR::FILE_LINE] ],
	      ],
	    ];
	  } else {
	    $data[CSTR::DOCSTR][$tt[CSTR::COLL]][$tt[CSTR::SECT].'#'.$tt[CSTR::LEVEL]][CSTR::TEXT][] =
		[ $found[CSTR::TEXT], $found[CSTR::FILE_LINE] ];
	  }
	  //~ $data[CSTR::DOCSTR][$tt[CSTR::COLL]][$tt[CSTR::SECT].'#'.$tt[CSTR::LEVEL]][] = $tt;
	  //~ var_dump($tt);
	}
	$mode = MODE::NONE;
	$found = MODE::SEARCH;
      } else {
	if (preg_match(RE::DOCSTR_PREFIX,$srcln,$mv)) {
	  $found[CSTR::TEXT] .= $mv[1].PHP_EOL;
	} else {
	  $found[CSTR::TEXT] .= $srcln;
	}
      }
    }
  }
  return true;
}

$argv0 = array_shift($argv);
$reg = [];
$save_file = NULL;
$fext = '.md';
$outdir = NULL;

while (count($argv) > 0) {
  if (str_starts_with($argv[0], CSTR::LOAD)) {
    $txt = file_get_contents(substr($argv[0], strlen(CSTR::LOAD)));
    if ($txt === false) die('Error reading YAML file'.PHP_EOL);
    $reg = yaml_parse($txt);
    if ($reg === false) die('Error parsing YAML text'.PHP_EOL);
  } elseif (str_starts_with($argv[0], CSTR::YAML)) {
    $save_file = substr($argv[0], strlen(CSTR::YAML));
  } elseif (str_starts_with($argv[0], CSTR::DEFEXT)) {
    $fext = substr($argv[0], strlen(CSTR::DEFEXT));
  } elseif (str_starts_with($argv[0], CSTR::OUTPUT)) {
    $outdir = substr($argv[0], strlen(CSTR::OUTPUT));
  } else {
    break;
  }
  array_shift($argv);
}

$reg = [];
foreach ($argv as $f) {
  if (!is_file($f)) continue;
  readMyFile($f,$reg) || die($f.': Processing Error.'.PHP_EOL);
}

if ($save_file) {
  if (file_update($save_file, yaml_emit($reg)) === true) Cli::stderr($save_file.': YAML file updated');
}


if ($outdir) {
  $outdir = rtrim($outdir,'/').'/';
  if (isset($reg[CSTR::SECTIONS])) {
    foreach ($reg[CSTR::SECTIONS] as $fn => $dat) {
      $fm = addExt($fn,$fext);

      $out = '';
      $out .= '---'.PHP_EOL;
      $out .= 'title: '.$fn . PHP_EOL;
      $out .= '---'.PHP_EOL;
      //~ if (count($dat) > 1)  $out .= '[toc]'.PHP_EOL.'***'.PHP_EOL;

      ksort($dat);
      foreach ($dat as $id => $e) {
	$out .= str_repeat('#',$e[CSTR::LEVEL]) . ' '. $e[CSTR::SECT].PHP_EOL.PHP_EOL;
	foreach ($e[CSTR::TEXT] as $v) {
	  list($txt,$ref) = $v;
	  $out .= $txt . PHP_EOL;
	  $out .= '***'.PHP_EOL;
	  $out .= '* '.$ref . PHP_EOL;
	  $out .= '***'.PHP_EOL;
	  $out .= PHP_EOL;
	}
      }
      if (file_update($outdir.$fm, $out) === true) Cli::stderr($outdir.$fm. ': file updated');
    }
  }
  if (isset($reg[CSTR::MARKERS])) {
    foreach ($reg[CSTR::MARKERS] as $fn => $dat) {
      $fm =addExt($fn,$fext);
      $out = '';
      $out .= '---'.PHP_EOL;
      $out .= 'title: '.$fn . PHP_EOL;
      $out .= '---'.PHP_EOL.PHP_EOL;

      $out .= '| tag | error | reference |'.PHP_EOL;
      $out .= '|---|---|---|'.PHP_EOL;
      ksort($dat);
      foreach ($dat as $tag => $e) {
	$n = $tag ;
	foreach ($e as $k=>$v) {
	  $out .= '| '.$n.' | '. $v[CSTR::TEXT].' | '. implode(', ', $v[CSTR::FILE_LINE]) . ' |'.PHP_EOL;
	  $n = '^';
	}
      }
      if (file_update($outdir.$fm, $out) === true) Cli::stderr($outdir.$fm. ': file updated');
    }
  }
  if (isset($reg[CSTR::DOCSTR])) {
    foreach ($reg[CSTR::DOCSTR] as $fn => $dat) {
      $fm =addExt($fn,$fext);

      $out = '';
      $out .= '---'.PHP_EOL;
      $out .= 'title: '.$fn . PHP_EOL;
      $out .= '---'.PHP_EOL;
      //~ if (count($dat) > 1)  $out .= '[toc]'.PHP_EOL.'***'.PHP_EOL;

      ksort($dat);
      foreach ($dat as $id => $e) {
	if ($e[CSTR::LEVEL] && !empty($e[CSTR::SECT])) {
	  $out .= str_repeat('#',$e[CSTR::LEVEL]) . ' '. $e[CSTR::SECT].PHP_EOL.PHP_EOL;
	}
	foreach ($e[CSTR::TEXT] as $v) {
	  list($txt,$ref) = $v;
	  $out .= $txt . PHP_EOL;
	  $out .= '***'.PHP_EOL;
	  $out .= '* '.$ref . PHP_EOL;
	  $out .= '***'.PHP_EOL;
	  $out .= PHP_EOL;
	}
      }
      if (file_update($outdir.$fm, $out) === true) Cli::stderr($outdir.$fm. ': file updated');

    }
  }
}




