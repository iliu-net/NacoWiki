<?php
require(__DIR__.'/../nacowiki.php');

$wiki = new NacoWikiApp([
  'umask' => 0,
  //~ 'file_store' => dirname(__DIR__).'/files',
  'file_store' => '/data/nanowiki',
  'static_path' => __DIR__.'/assets',
  'static_url' => dirname($_SERVER['SCRIPT_NAME'] ?? ($argv[0] ?? __FILE__)).'/assets',
  'theme' => 'dark',
  'theme-highlight-js' => 'base16/atlas.min',
  'theme-codemirror' => 'ayu-dark',
  'proxy-ips' => '192.168.101.252',
  'copyright' => 'Alejandro Liu',
]);
$wiki->run($argv ?? NULL);
