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

    # Hack to work around long tags lines...
    $try = $yaml->loadString($doc);
    if (!(isset($try['tags']) && is_array($try['tags']))) return $try;

    # OK, we need to fix the problem!
    $fixed = [];
    $found = false;
    foreach (explode("\n", $doc) as $line) {
      if ($found) {
	if (preg_match('/^  [^ ]/', $line)) {
	  # This is a continuation line...
	  //~ echo 'CONT LINE: '.$line. PHP_EOL;
	  $fixed[count($fixed)-1] .= $line;
	  $is_fixed = true;
	} else {
	  $found = false;
	}
      } else {
	$fixed[] = $line;
	if (preg_match('/^tags:\s+/',$line)) {
	  $found = true;
	}
      }
    }
    $try = $yaml->loadString(implode("\n", $fixed));
    print_r($try);
    if (isset($try['tags']) && is_array($try['tags'])) die("Another failure!\n");
    return $try;
    return $yaml->loadString(implode("\n", $fixed));
  }
  function yaml_emit_file($f,$data) {
    $yaml = new Alchemy\Component\Yaml\Yaml();
    return file_put_contents($f,$yaml->dump($data));
  }
  function yaml_parse_file($f) {
    $yaml = new Alchemy\Component\Yaml\Yaml();
    $doc = file_get_contents($f);
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
