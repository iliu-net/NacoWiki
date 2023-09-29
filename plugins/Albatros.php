<?php
/** Albatros
 *
 * Blog generation functionality.
 *
 * *albatros* is written to be similar
 * to [pelican](https://getpelican.com/) static site generator,
 * so that its themes could be used without much change.
 *
 * @package Plugins
 * @phpcod Plugins##Albatros
 * @todo This is a work in progress
 */

use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

require_once(APP_DIR . 'vendor/autoload.php');

/** Blogging site generation
 *
 * Class that actually implements Albatros
 *
 * *albatros* is a static Blogging site generator intended to be
 * similar to [pelican][p] static site generator.
 *
 * I wrote it to migrate my private [home site](https://0ink.net/) to
 * [NacoWiki][nw] static site generation.  I had been using [NacoWiki][nw]
 * as a _front-end_ to edit my [pelican][p] content.  However, eventhough
 * they are both using [Markdown][md] as its format, there is always
 * implementation differences.  By doing this I could use the same renderer
 * for my wiki and my Blog.
 *
 * *albatros* is able to use [pelican v3.x][p] themes after some tweaking.
 * Also, content follows a simmilar structure as [pelican][p], so
 * migration from [pelican][p] to *albatros* should not be too
 * complicated.
 *
 *   [p]: https://getpelican.com/
 *   [nw]: https://github.com/iliu-net/NacoWiki/
 *   [md]: https://daringfireball.net/projects/markdown/
 *
 * ## Configuration
 *
 * *albatros* settings are stored in the `wiki` under an `opts.yaml` file
 * at the root of the `wiki` data store.  There must be a `albatros`
 * key if `opts.yaml` storing all the relevant *albatros* settings.
 *
 * @phpcod Albatros
 */
class Albatros {
  /** var string */
  const VERSION = '0.0.0';

  /** var array Albatros config settings from opts.yaml */
  static $opts = [];
  /** var string[] */
  static $exts = NULL;

  /** var string[] list of articles.  Only contains paths pointing
   * to entries in self::$files.  This is due to this array not being
   * used directly but used to assemble articles_page arrays.
   * */
  static $articles = [];
  /** var string[] list of pages */
  static $pages = [];
  /** var array containing files meta data */
  static $files = [];
  /** array list of articles per author. */
  static $authors = [];
  /** array list of articles per category. */
  static $categories = [];
  /** array list of articles per tag. */
  static $tags = [];
  /** array list of draft articles. */
  static $drafts = [];
  /** cache of HTML generated pages */
  static $htmlcache = [];

  /** Twig template globals
   */
  static $twig = [];

  /** Return this plugin's path
   * @param string $f optional item
   * @return path to filesystem for $f
   */
  static function path(string $f = '') : string {
    return Plugins::path($f);
  }
  /** Index file
   *
   * Examine a file and add it to the self::$files array.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $f : file being indexed
   */
  static function indexFile(\NacoWikiApp $wiki, string $f) : void {
    // Scan files and collect meta data...
    $f = '/'.trim($f,'/');
    $wiki->page = $f;
    $ext = Plugins::mediaExt($f);
    if (!is_null($ext)) {
      ob_start();
      if (Plugins::dispatchEvent($wiki, 'view:'.$ext, Plugins::devt(['ext'=>$ext]))) {
	# Oh, it used 'view'
	ob_end_clean();
	die($f.': Uses view event.  This is NOT implemented'.PHP_EOL);
      }
      ob_end_clean();
      $event = Core::preparePayload($wiki, $wiki->page, $ext);
      $meta = array_merge($event['filemeta'] ?? [],
			  $event['meta'] ?? []);

      $meta['ext'] = $ext;
      $meta['path'] = $f;
      $meta['url'] = trim(substr($f,0,-strlen($ext)),'/').'html';
      if (str_starts_with($f,self::$opts['ARTICLES'])) {
	$meta['type'] = 'article';
	$c = dirname(substr($f,strlen(self::$opts['ARTICLES'])));
	if ($c != '.' || $c != '/') {
	  $meta['x-category'] = $c;
	}
      } elseif (str_starts_with($f,self::$opts['PAGES'])) {
	$meta['type'] = 'page';
      } else {
	$meta['type'] = 'generic';
      }
      self::$files[$f] = $meta;
      if (empty($meta['summary'])) {
	/* Auto generate a summary... */
	$sum = preg_split('/\n/',$event['payload']);
	if (!empty($sum)) {
	  $meta['x_auto_summary'] = strip_tags(implode(PHP_EOL,
					array_slice($sum, 0, self::$opts['SUMMARY_LINES']
					))).PHP_EOL.'...';
	}
      }
      if (empty($meta['author']) || $meta['author'] == '?') {
	# TODO get author from props
	$meta['author'] = self::$opts['AUTHOR'];
	# TODO author maps
      }
      # Modify date base on filename
      if (preg_match('/^(\d\d\d\d-\d\d-\d\d)-/',basename($f),$mv)) {
	$meta['date'] = $mv[1];
      }
      if (empty($meta['modified'])) {
	if (isset(self::$opts['use-git']) && self::$opts['use-git']) {
	  # Use git for calculating modified date...
	  $cwd = getcwd();
	  $output=null; $rc =null;
	  chdir(dirname($wiki->filePath($f)));
	  $out = exec('git --no-pager log -1 --date=short --format=%cd '.
		      escapeshellarg($wiki->filePath($f)), $output, $rc);
	  chdir($cwd);
	  if ($out !== false) {
	    $out = trim($out);
	    if (preg_match('/^\d\d\d\d-\d\d-\d\d$/', $out) && $out != $meta['date'])
	      $meta['modified'] = $out;
	  }
	}
	if (empty($meta['modified'])) {
	  $d = filemtime($wiki->filePath($f));
	  if ($d && date('Y-m-d', $d) != $meta['date']) {
	    $meta['modified'] = date('Y-m-d',$d);
	  }
	}
      }
      if (!empty($meta['tags'])) {
	$meta['x-tags'] = [];
	$t = [];
	foreach (preg_split('/\s*,\s*/', $meta['tags']) as $j) {
	  if (empty($j)) continue;
	  $j = strtolower($j);
	  $meta['x-tags'][$j] = $j;
	  $t[] = $j;
	}
	$meta['tags'] = $t;
      }
      self::$files[$f] = $meta;
    } else {
      $meta = array_merge(Util::fileMeta($wiki->filePath($f)) ?? [],
			  Util::defaultMeta($wiki->filePath($f)) ?? []);
      # Modify date base on filename
      if (preg_match('/^(\d\d\d\d-\d\d-\d\d)-/',basename($f),$mv)) {
	$meta['date'] = $mv[1];
      }
      $meta['ext'] = NULL;
      $meta['path'] = $f;
      $meta['url'] = $f;
      $meta['type'] = 'generic';
      self::$files[$f] = $meta;
      //~ print_r($meta);
    }
  }
  /** Link references
   *
   * Expand the meta data and expand link refernces
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $f : file being indexed
   * @param array $meta : file meta data -- this will be modified.
   */
  static function linkFile(\NacoWikiApp $wiki, string $f, array &$meta) : void {
    # Expand tags!
    if (!empty($meta['tags'])) {
      $meta['x_tags'] = [];
      foreach ($meta['tags'] as $t) {
	$meta['x_tags'][$t] = &self::$tags[$t];
      }
    }
    # Expand categories
    if (!empty($meta['x-category']) && isset(self::$categories[$meta['x-category']])) {
      $meta['category'] = $meta['x-category'];
      $meta['category_url'] = self::$categories[$meta['x-category']]['url'];
    }
    if (!empty($meta['author']) && isset(self::$authors[$meta['author']])) {
      $meta['author_url'] = self::$authors[$meta['author']]['url'];
    }

  }
  /** Creates Cross references
   *
   * Examine a file meta data and generate cross references
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $f : file being indexed
   * @param array $meta : file meta data
   */
  static function xrefFile(\NacoWikiApp $wiki, string $f, array $meta) : void {
    $now = date('Y-m-d');
    if (!empty($meta['date']) && $meta['date'] > $now) {
      self::$drafts[$f] = $f;
      return;
    }
    if (!empty($meta['author'])) {
      if (!isset(self::$authors[$meta['author']]))
	self::$authors[$meta['author']] = [
	  'name' => $meta['author'],
	  'url' => 'authors'.Util::sanitize($meta['author']).'.html',
	];
    }
    if ($meta['type'] == 'article') {
      self::$articles[$f] = $f;
      if (isset($meta['x-category'])) {
	$c = $meta['x-category'];
	if (!isset(self::$categories[$c])) {
	  self::$categories[$c] = [
	    'name' => $c,
	    'url' => trim(self::$opts['ARTICLES'],'/').'/'.$c.'/index.html',
	  ];
	}
      }
      if (!empty($meta['tags'])) {
	foreach ($meta['tags'] as $t) {
	  if (!isset(self::$tags[$t])) {
	    self::$tags[$t] = [
	      'name' => $t,
	      'url' => 'tags/'.Util::sanitize($t).'.html',
	    ];
	  }
	}
      }
    } elseif ($meta['type'] == 'page') {
      self::$pages[$f] = [
	'url' => $meta['url'],
	'title' => $meta['title'],
	'date' => $meta['date'],
	'summary' => isset($meta['summary']) ? $meta['summary'] : $meta['x_auto_summary'],
      ];
    }
  }
  /** Generate individual files
   *
   * Generate files in the output direcotry
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   * @param string $f : file being generated
   * @param array $meta : file meta data
   */
  static function genFile(\NacoWikiApp $wiki,string $output, string $f, array $meta) : void {
    if ($meta['type'] == 'article' || $meta['type'] == 'page') {
      $evopts = [
	'view' => Plugins::path('view.php'),
	'no_exit' => true,
      ];
      $wiki->page = $f;
      self::$twig['output'] = '';
      Plugins::dispatchEvent($wiki, 'read_page', $evopts);
      file_put_contents($output.'/'.$meta['url'],self::$twig['output']);
    } else {
      # Generic file... just copy them
      if (copy($wiki->filePath($f),$output.$f) === false) exit(__LINE__);
    }
  }

  /** Check configured defaults
   *
   * Makes sure that certain keys are defined in the `albatros` section
   * with suitable defaults
   *
   * @param array &$data - content of $wiki->opts['albatros']
   */
  static function checkDefaults(array &$data) : void {
    if (isset($data['TIMEZONE'])) date_default_timezone_set($data['TIMEZONE']);
    foreach ([
	      'DEFAULT_LANG' => 'en',
	      'ARTICLES' => 'posts',
	      'PAGES' => 'pages',
	      'SUMMARY_LINES' => 6,
	      'CSS_FILE' => 'main.css',
	      'DEFAULT_PAGINATION' => 30,
	      'THEME_STATIC_DIR' => 'theme',
	      'HIGHLIGHT_JS' => Core::HIGHLIGHT_JS,

	    ] as $k=>$v) {
      if (empty($data[$k])) $data[$k] = $v;
    }
    if (empty($data['HTML_LANG'])) $data['HTML_LANG'] = $data['DEFAULT_LANG'];
    if (empty($data['FEED_DOMAIN'])) $data['FEED_DOMAIN'] = $data['SITEURL'];
    $data['ARTICLES'] = '/'.trim($data['ARTICLES'],'/').'/';
    $data['PAGES'] = '/'.trim($data['PAGES'],'/').'/';
    $data['now'] = (string)time();
    self::$opts = &$data;
  }
  /** Serve pages
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array $argv Command line arguments
   * @phpcod commands##serve
   * @event cli:serve
   */
  static function serve(\NacoWikiApp $wiki, array $argv) {
    while (count($argv) > 0) {
      if (str_starts_with($argv[0],'--output=')) {
	$output = substr($argv[0],strlen('--output='));
      } else {
	break;
      }
      array_shift($argv);
    }
    if (empty($output)) die('Must specify output directory: --output=path'.PHP_EOL);
    if (count($argv) == 0) {
      $listen = 'localhost:9001';
    } else {
      $listen = array_shift($argv);
    }
    passthru('php -S '.escapeshellarg($listen).' -t '.escapeshellarg($output));
    exit();
  }
  /**
   * Generate static blog site
   *
   * ## Usage:
   *
   * **php web/albatros.php bloggen** [_options_]
   *
   * ## Options
   *
   * * **--cfg-**_[setting]_=_value_ : Modifes the setting `setting`
   *   which normally would be configured in **NacoWiki** definition
   *   to _value_.
   * * **--output=**_directory-path_ : Sets the output directory path.
   *
   * Note that most of the configuration is in the `opts.yaml` at the
   * root of the wiki.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array $argv Command line arguments
   * @phpcod commands##bloggen
   * @event cli:bloggen
   */
  static function generate(\NacoWikiApp $wiki, array $argv) {
    $wiki->loadOpts('/');
    if (empty($wiki->opts['albatros'])) {
      die('Missing opts.yaml with albatros key in this web site'.PHP_EOL);
    }
    self::checkDefaults($wiki->opts['albatros']);

    while (count($argv) > 0) {
      if (str_starts_with($argv[0],'--cfg-')) {
	$kv = explode('=',substr($argv[0],strlen('--cfg-')),2);
	if (empty($kv[0])) die('Incomplete cfg argument'.PHP_EOL);
	if (count($kv) == 1) $kv[] = true;
	$wiki->cfg[$kv[0]] = $kv[1];

      } elseif (str_starts_with($argv[0],'--opt-')) {
	$kv = explode('=',substr($argv[0],strlen('--opt-')),2);
	if (empty($kv[0])) die('Incomplete cfg argument'.PHP_EOL);
	if (count($kv) == 1) $kv[] = true;
	self::$opts[$kv[0]] = $kv[1];
      } elseif (str_starts_with($argv[0],'--output=')) {
	$output = substr($argv[0],strlen('--output='));
      } else {
	break;
      }
      array_shift($argv);
    }
    if (empty($output)) die('Must specify output directory: --output=path'.PHP_EOL);


    //~ if (count($argv) != 1) die('Must specify one page to render'.PHP_EOL);
    //~ Plugins::registerEvent('post-render', [self::class, 'postRender']);

    echo 'Scanning files ..';
    $fs =$wiki->cfg['file_store'];
    list($dirs,$files) = Util::walkTree($fs);
    echo '. DONE'.PHP_EOL;

    echo 'Indexing files ..';
    foreach ($files as $f) {
      self::indexFile($wiki,$f);
    }
    echo '. DONE'.PHP_EOL;

    echo 'Cross-referencing files ..';
    foreach (self::$files as $f => $meta) {
      self::xrefFile($wiki, $f, $meta);
    }
    echo '. DONE'.PHP_EOL;

    echo 'Linking files ..';
    foreach (self::$files as $f => &$meta) {
      self::linkFile($wiki, $f, $meta);
    }
    unset($meta);
    echo '. DONE'.PHP_EOL;

    if (!is_dir($output)) {
      if (mkdir($output) === false) exit(__LINE__);
    }
    foreach ($dirs as $d) {
      if (!is_dir($output.'/'.$d)) {
	if (mkdir($output.'/'.$d) === false) exit(__LINE__);
      }
    }

    echo 'Creating site files ..';
    foreach (self::$files as $f=>$meta) {
      self::genFile($wiki,$output,$f,$meta);
    }
    echo '. DONE'.PHP_EOL;

   if (isset(self::$opts['SEARCH_SITE']) && self::$opts['SEARCH_SITE']) {
      echo 'Copying search files ..';
      # Copy theme static files...
      Util::recurse_copy(self::path('search'),$output.'/.search');
      echo '. DONE'.PHP_EOL;
      # Generating search index
      echo 'Generating search index ..';
      $idx = [];
      foreach (self::$files as $f=>$meta) {
	if (!isset(self::$htmlcache[$f])) continue;
	$idx[] = [
	  'url' => $meta['url'],
	  'title' => htmlspecialchars($meta['title']),
	  'text' => explode("\n",
	      'title: '.strip_tags($meta['title']).PHP_EOL .
	      (empty($meta['tags']) ? '' :
		'tags: '.strip_tags(implode(', ',$meta['tags'])) . PHP_EOL ).
	      PHP_EOL.
	      strip_tags(self::$htmlcache[$f])),

	    ];
      }
      file_put_contents($output.'/.search/index.js','pg_index = '.
			  json_encode($idx));

      echo '. DONE'.PHP_EOL;
   }

    if (is_dir(self::path(self::$opts['THEME'].'/static'))) {
      echo 'Copying theme static files ..';
      # Copy theme static files...
      Util::recurse_copy(self::path(self::$opts['THEME'].'/static'),
		      $output.'/theme');
      echo '. DONE'.PHP_EOL;
    }

    echo 'Creating index ..';
    self::makeIndex($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Creating drafts ..';
    self::makeDrafts($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Creating categories ..';
    self::makeCategories($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Creating tags ..';
    if (!is_dir($output.'/tags')) mkdir($output.'/tags');
    self::makeTags($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Creating author indeces ..';
    if (!is_dir($output.'/authors')) mkdir($output.'/authors');
    self::makeAuthors($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Creating generating archive ..';
    self::makeArchive($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Creating generating sitemap ..';
    self::makeSitemap($wiki, $output);
    echo '. DONE'.PHP_EOL;

    echo 'Building feeds ..';
    self::makeFeeds($wiki, $output);
    echo '. DONE'.PHP_EOL;

    exit;
  }
  /** Generate list of articles
   *
   * @param function $cb function to call to select articles
   * @return array containing article paths
   */
  static function selectArticles($cb) : array {
    $alist = [];
    foreach (self::$files as $f=>$meta) {
      if (!$cb($f,$meta)) continue;
      $alist[$f] = $meta['date'];
    }
    krsort($alist);
    return array_keys($alist);
  }
  /**
   * Fix generated links
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $html input html
   * @return string fixed html
   */
  static function fixLinks(\NacoWikiApp $wiki, string $html) : string {
    if (preg_match_all('/<[Aa]\s+[Hh][Rr][Ee][fF]="([^"]+)"/', $html, $mv)) {
      $vars = [];
      foreach ($mv[0] as $m=>$lnk) {
	if (!$mv[1][$m]) continue;
	$p = $mv[1][$m];
	if ($p[0] == '#') continue;
	if (!str_starts_with($p,$wiki->cfg['base_url'])) continue;
	//~ echo 'FIXLINK: '.$p.'|' . $wiki->cfg['base_url']. PHP_EOL;
	if (is_null(self::$exts)) {
	  $ext = Plugins::mediaExt($p);
	  if (is_null($ext)) continue;
	} else {
	  $ext = strtolower(pathinfo($p)['extension'] ?? '');
	  if (!isset(self::$exts[$ext])) continue;
	}
	$i = strrpos($p,'.');
	if ($i === false) continue;
	$p = substr($p,0,$i);
	$p = substr($p,strlen($wiki->cfg['base_url']));

	//~ echo 'lnk: '.$lnk.' - ';
	//~ echo 'FIXLINK: '.$p.'|' . $wiki->cfg['base_url']. PHP_EOL;
	$vars[$lnk] = '<a href="'.$p.'.html"';
	//~ Cli::stderr($p);
      }
      if (count($vars) > 0) $html = strtr($html,$vars);
    }
    return $html;
  }
  /** Paginate
   * Create paginated views...
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   * @param string $twig : name of Twig template
   * @param string $pgname : base page name
   * @param array &$alst : list articles
   * @param array $vvs : additional settings
   */
  static function paginator(\NacoWikiApp $wiki, string $output, string $twig, string $pgname, array &$alst, array $vvs = []) : void {
    if (str_ends_with($pgname, '.html')) $pgname = substr($pgname,0,-strlen('.html'));

    $pglen = self::$opts['DEFAULT_PAGINATION'];
    $template = self::twigTpl($twig);
    $count = count($alst);

    $numpgs = intval($count/$pglen) + 1;
    $i = 0;

    while (($i * $pglen) < $count) {
      $data = self::$opts;
      $data['pages'] = self::$pages;
      $data['categories'] = self::$categories;
      foreach ($vvs as $k => $j) {
	$data[$k] = $j;
      }
      $data['page_name'] = $pgname;
      $data['articles_paginator'] = [ 'num_pages' => $numpgs ];
      $page = [
	'has_other_pages' => $pglen < $count,
	'object_list' => [],
      ];
      if ($i > 0) {
	$page['has_previous'] = true;
	$page['previous_page_number'] = $i;
      }
      $page['number'] = $i+1;
      if (($i+1)*$pglen < $count) {
	$page['has_next'] = true;
	$page['next_page_number'] = $i+2;
      }

      for ($j = $i* $pglen ; $j < ($i+1)*$pglen && $j < $count; ++$j) {
	$page['object_list'][] = self::$files[$alst[$j]];
      }
      $data['articles_page'] = $page;

      $html = $template->render($data);
      file_put_contents($output.'/'.$pgname.($i ? $i+1 : '').'.html', $html);
      $i++;
    }
  }

  /** Generate category pages
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeCategories(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    foreach (self::$categories as $c=>$cdat) {
      $alst = self::selectArticles(function ($f,$meta) use ($c, $now) {
	if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
	if ($meta['x-category'] == $c) return true;
	return false;
      });
      self::paginator($wiki, $output, 'category', $cdat['url'], $alst,['category' => $c]);

    }
  }
  /** Generate Author pages
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeAuthors(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    foreach (self::$authors as $a=>$d) {
      $alst = self::selectArticles(function ($f,$meta) use ($a, $now) {
	if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
	if ($meta['author'] == $a) return true;
	return false;
      });
      self::paginator($wiki,$output,'author',$d['url'],$alst,['author'=>$a]);
    }
  }
  /** Generate tag pages
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeTags(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    $max = 0;
    foreach (self::$tags as $t=>$td) {
      $alst = self::selectArticles(function ($f,$meta) use ($t, $now) {
	if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
	if (isset($meta['x-tags'][$t])) return true;
	return false;
      });
      self::$tags[$t]['articles'] = count($alst);
      if (self::$tags[$t]['articles'] > $max) $max = self::$tags[$t]['articles'];
      self::paginator($wiki, $output, 'tag', $td['url'], $alst,['tag'=>$t]);
    }
    # Calculate relative weights!
    foreach (self::$tags as $t=>$td) {
      self::$tags[$t]['fsz'] = intval(100 + $td['articles'] * 100 / $max);
    }

    # Generate index page...
    $data = self::$opts;
    $data['pages'] = self::$pages;
    $data['categories'] = self::$categories;
    $tc = [];
    foreach (self::$tags as $t=>$td) {
      $tc[$t] = $td;
    }
    $data['tags'] = $tc;
    $template = self::twigTpl('tags');
    $html = $template->render($data);
    file_put_contents($output.'/tags/index.html', $html);
  }

  /** Generate index pages
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeIndex(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    $alist = self::selectArticles(function ($f,$meta) use ($now) {
      if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
      return true;
    });
    self::paginator($wiki, $output, 'index', 'index', $alist);

  }
  /** Generate Archive page
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeArchive(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    $alst = self::selectArticles(function ($f,$meta) use ($now) {
      if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
      return true;
    });
    if (!count($alst)) return;

    $template = self::twigTpl('archives');

    $data = self::$opts;
    $data['pages'] = self::$pages;
    $data['categories'] = self::$categories;
    $dates = [];
    foreach ($alst as $d) {
      $dates[] = self::$files[$d];
    }
    $data['dates'] = $dates;

    $html = $template->render($data);
    file_put_contents($output.'/archives.html', $html);
  }
  /** Generate Sitemap
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeSitemap(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    $alst = self::selectArticles(function ($f,$meta) use ($now) {
      if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
      return true;
    });
    if (!count($alst)) return;


    $data = self::$opts;
    $data['pages'] = self::$pages;
    $data['categories'] = self::$categories;
    $arts = [];
    foreach ($alst as $d) {
      $arts[] = self::$files[$d];
    }
    $data['articles'] = $arts;

    $template = self::twigTpl('sitemap');
    $html = $template->render($data);
    file_put_contents($output.'/sitemap.html', $html);

    $template = self::twigTpl('sitemap.xml');
    $html = $template->render($data);
    file_put_contents($output.'/sitemap.xml', $html);
  }
  /** Generate drafts
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeDrafts(\NacoWikiApp $wiki, string $output) : void {
    $now = date('Y-m-d');
    $alist = self::selectArticles(function ($f,$meta) use ($now) {
      if ($meta['type'] != 'article' || $meta['date'] <= $now) return false;
      return true;
    });
    if (count($alist))
      self::paginator($wiki, $output, 'index', 'drafts', $alist);

  }
  /** Generate feeds
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $output : output directory path
   */
  static function makeFeeds(\NacoWikiApp $wiki, string $output) : void {
    if (empty(self::$opts['FEED_ALL_ATOM']) && empty(self::$opts['FEED_ALL_ATOM'])) return;

    $now = date('Y-m-d');
    $alst = self::selectArticles(function ($f,$meta) use ($now) {
      if ($meta['type'] != 'article' || $meta['date'] > $now) return false;
      return true;
    });
    if (!count($alst)) return;

    if (!empty(self::$opts['FEED_ALL_ATOM'])) {
      $xml = $output .'/' . self::$opts['FEED_ALL_ATOM'];
      if (!is_dir(dirname($xml))) {
	if (mkdir(dirname($xml),0777,true) === false) die($xml.': mkdir failed'.PHP_EOL);
      }
      $feed = new \FeedWriter\ATOM();

      $feed->setTitle(self::$opts['SITENAME']);
      $feed->setDescription(self::$opts['SITESUBTITLE']);
      $feed->setLink(self::$opts['XML_SITEURL']);
      $feed->setDate(new DateTime());
      if (!empty(self::$opts['LOGO_IMG']))
	$feed->setImage(self::$opts['XML_SITEURL'].'/'.trim(self::$opts['LOGO_IMG'],'/'));
      if (!empty(self::$opts['AUTHOR']))
	$feed->setChannelElement('author', ['name'=> self::$opts['AUTHOR']]);
      $feed->setSelfLink(self::$opts['XML_SITEURL'].'/'. self::$opts['FEED_ALL_ATOM']);

      foreach ($alst as $f) {
	$newItem = $feed->createNewItem();
	$m = self::$files[$f];

	//Add elements to the feed item
	//Use wrapper functions to add common feed elements
	$newItem->setTitle($m['title']);
	$newItem->setLink(self::$opts['XML_SITEURL'].'/'.$m['url']);
	if (!empty($m['modified'])) {
	  $newItem->setDate(new DateTime($m['modified']));
	} else {
	  $newItem->setDate(new DateTime($m['date']));
	}
	$newItem->setAuthor($m['author']);

	//Internally changed to "summary" tag for ATOM feed
	$newItem->setDescription($m['summary']);
	if (isset(self::$htmlcache[$f]))
	  $newItem->setContent(self::$htmlcache[$f]);

	//Now add the feed item
	$feed->addItem($newItem);
      }
      file_put_contents($xml, $feed->generateFeed());
    }
    if (!empty(self::$opts['FEED_ALL_RSS'])) {
      $xml = $output .'/' . self::$opts['FEED_ALL_RSS'];
      if (!is_dir(dirname($xml))) {
	if (mkdir(dirname($xml),0777,true) === false) die($xml.': mkdir failed'.PHP_EOL);
      }
      $feed = new \FeedWriter\RSS2();

      $feed->setTitle(self::$opts['SITENAME']);
      $feed->setDescription(self::$opts['SITESUBTITLE']);
      $feed->setLink(self::$opts['XML_SITEURL']);
      if (!empty(self::$opts['LOGO_IMG']))
	$feed->setImage(self::$opts['XML_SITEURL'].'/'.trim(self::$opts['LOGO_IMG'],'/'),
			self::$opts['SITENAME'], self::$opts['XML_SITEURL']);

      $feed->setChannelElement('language', self::$opts['DEFAULT_LANG']);
      $feed->setDate(new DateTime());
      $feed->setSelfLink(self::$opts['XML_SITEURL'].'/'. self::$opts['FEED_ALL_ATOM']);
      $feed->addGenerator();

      foreach ($alst as $f) {
	$newItem = $feed->createNewItem();
	$m = self::$files[$f];

	//Add elements to the feed item
	//Three mandatory items
	$newItem->setTitle($m['title']);
	$newItem->setLink(self::$opts['XML_SITEURL'].'/'.$m['url']);
	$newItem->setDescription($m['summary']);

	if (!empty($m['modified'])) {
	  $newItem->setDate(new DateTime($m['modified']));
	} else {
	  $newItem->setDate(new DateTime($m['date']));
	}
	$newItem->setAuthor($m['author']);

	//Now add the feed item
	$feed->addItem($newItem);
      }
      file_put_contents($xml, $feed->generateFeed());
    }

  }

  /** Load twig template
   *
   * @param string $template
   * @return Twig template object
   */
  static function twigTpl(string $template) {
    if (!isset(self::$twig['loader'])) {
      self::$twig['loader'] = new \Twig\Loader\FilesystemLoader(self::path(self::$opts['THEME'].'/templates'));
      self::$twig['loader']->addPath(self::path('templates'));
    }
    if (!isset(self::$twig['env']))
      self::$twig['env'] = new \Twig\Environment(self::$twig['loader'], [
	    //~ 'cache' => 't/compilation_cache',
	    'autoescape' => false,
	]);
    if (!isset(self::$twig[$template])) {
      if (!str_ends_with($template,'.xml')) $template .= '.html';
      self::$twig[$template] = self::$twig['env']->load($template);
    }
    return self::$twig[$template];
  }


  /** Process WIKI files into HTML
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array $ex extra data
   */
  static function render(\NacoWikiApp $wiki, array $ex) : void {

    $data = self::$opts;
    $data['pages'] = self::$pages;
    $data['categories'] = self::$categories;
    $meta = self::$files[$wiki->page];

    $template = self::twigTpl($meta['type']);

    $html = $wiki->html;
    $html = self::fixLinks($wiki,$html);
    $html = strtr($html,[
			'{static}'=>'',
			'${SNIPPETS}' => 'https://github.com/alejandroliu/0ink.net/tree/master/snippets'
		      ]);

    if (empty($meta['summary'])) {
      /* Auto generate a summary... */
      $sum = preg_split('/\n/',$html);
      if (!empty($sum)) {
	$meta['summary'] = strip_tags(implode(PHP_EOL,
				      array_slice($sum, 0, self::$opts['SUMMARY_LINES']
				      ))).PHP_EOL.'...';
	self::$files[$wiki->page]['summary'] = $meta['summary'];
      }
    }

    if (self::$files[$wiki->page]['type'] == 'page') {
      $data['page'] = $meta;
      $data['page']['content'] = $html;
      $data['category'] = '';
    } elseif (self::$files[$wiki->page]['type'] == 'article') {
      $data['article'] = $meta;
      $data['article']['content'] = $html;
      $data['category'] = $data['article']['x-category'];
    }
    self::$twig['output'] = $template->render($data);
    self::$htmlcache[$wiki->page] = $html;
  }

  /**
   * Loading entry point for this class
   *
   * Adds commands implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::autoload(self::class);
  }
}
