<?php
if (!function_exists('yaml_emit')) {
  //
  // For installations where yaml is not compiled in...
  // we use: https://github.com/eriknyk/Yaml
  require(__DIR__.'/Yaml.php');
  function yaml_emit($data) {
    $yaml = new Alchemy\Component\Yaml\Yaml();
    return $yaml->dump($data);
  }
  function yaml_parse($doc) {
    $yaml = new Alchemy\Component\Yaml\Yaml();
    return $yaml->loadString($doc);
  }
}

if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle) : bool {
    return substr($haystack,0,strlen($needle)) == $needle;
  }
}
if (!function_exists('str_ends_with')) {
  function str_ends_with(string $haystack, string $needle) : bool {
    return substr($haystack,-strlen($needle)) == $needle;
  }
}
