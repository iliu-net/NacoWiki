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
