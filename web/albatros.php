<?php
require(__DIR__.'/../nacowiki.php');

$wiki = new NacoWikiApp([
  'umask' => 0,
  'file_store' => getenv('HOME').'/ww/0ink.net/src/content',
  //~ 'file_store' => '/data/nanowiki',
  'static_path' => __DIR__.'/assets',
  'static_url' => dirname($_SERVER['SCRIPT_NAME'] ?? ($argv[0] ?? __FILE__)).'/assets',
  //~ 'theme' => 'dark',
  //~ 'theme-highlight-js' => 'base16/atlas.min',
  //~ 'theme-codemirror' => 'ayu-dark',
]);
$wiki->run($argv ?? NULL);
