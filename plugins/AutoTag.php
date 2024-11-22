<?php
/** AutoTag
 *
 * Automatically generate tags on articles based on a tag cloud.
 *
 * @package Plugins
 * @phpcod Plugins##AutoTag
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;

/** NacoWiki AutoTag
 *
 * Automatically generate tags on articles based on a tag cloud.
 *
 * It will update the frontmatter header with tags based on the contents
 * of the document.  For this, the media handler must support the
 * `Core::logProps` functionality.
 *
 * To set-up you first need to create a `tagcloud.md` file.  Either in
 * the current directory or a directory above.
 *
 * The `tagcloud.md` must contain:
 *
 * ```markdown
 * # this is comment
 * tag # Just a tag
 * tag2, synonym1, synonym2 # tag2 is recognized, but also, synonym1 and synonym2 are considred for triggering tag2
 * ~tag3 # tag3 is removed from the tag cloud
 * ~tag3, synonym4 # tag3 and synonym4 are removed from the tag cloud
 * ```
 *
 * If there are multiple `tagcloud.md` files along the path all of them
 * are merged.  But the deeper `tagcloud.md` files can use `~` notation
 * to remove words from the tagcloud.
 *
 * Headers containg `tag:` are used. Should be made of comma separated
 * tag lists.
 *
 * In addition, a `autotag-ignore` header is recognized.  Tags here are also
 * comma separated.  If a word is found in this list, the tag will not be
 * added to the tag line.
 *
 * @phpcod AutoTag
 */
class AutoTag {
  /** var string */
  const VERSION = '0.0.0';
  const IGNORES = 'autotag-ignore';
  const TAGCLOUD = '/tagcloud.md';
  const RE_SPLIT = '/\s*,\s*/';

  /** Load tagcloud
   *
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param string $url path of option files to read
   * @return array containing tag cloud, NULL on error
   */
  static function loadTagCloud(\NacoWikiApp $wiki, string $url) : ?array {
    if ($url != '.' && $url != '/') {
      $tags = self::loadTagCloud($wiki,dirname($url));
    } else {
      $tags = [];
    }
    if (!is_dir($wiki->filePath($url))) return $tags;
    if (!file_exists($wiki->filePath($url.self::TAGCLOUD))) return $tags;

    $txt = file_get_contents($wiki->filePath($url.self::TAGCLOUD));
    if ($txt === false) return $tags;

    $offset = 0;
    if (($i = strpos("\n" . $txt,"\n---\n",$offset)) !== false) {
      if (($i = strpos($txt,"\n---\n",$i)) !== false) {
	$offset = $i + 5;
      }
    }
    foreach (explode("\n",substr($txt,$offset)) as $line) {
      if ($line == '```') continue;
      $i = strpos($line,'#');
      if ($i !== false) $line = substr($line,0,$i);
      $line = trim($line);
      if (empty($line)) continue;
      $words = preg_split(self::RE_SPLIT,$line);
      if ($words === false) continue;
      if ($words[0] == '~') {
	$words[0] = substr($words[0],1);
	if ($words[0] == '') continue;
	foreach ($words as $w) {
	  $w = self::tokenize($w);
	  if (empty($w)) continue;
	  unset($tags[$w]);
	}
      } else {
	$t = $words[0];
	foreach ($words as $w) {
	  $w = self::tokenize($w);
	  if (empty($w)) continue;
	  $tags[$w] = $t;
	}
      }
    }
    return $tags;
  }
  /** tokenize word
   *
   * Make a word more easily recognizable by the auto-tagger
   *
   * @param string $word input word
   * @return string tokenized word
   */
  static function tokenize(string $word) : string {
    $word = strtolower(trim($word));
    $word = preg_replace('/^[^a-z0-9]+/','',$word);
    $word = preg_replace('/[^a-z0-9]+$/','',$word);
    return $word;
  }

  /** Auto tagging function
   *
   * log-props event handler
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$event Event data
   * @event log-props
   */
  static function autoTagger(\NacoWikiApp $wiki, array &$ev) : ?bool {
    if (empty($ev['extra'])) return Plugins::NOOP;
    if (basename($wiki->page) == trim(self::TAGCLOUD,'/')) return Plugins::NOOP;
    $tagcloud = self::loadTagCloud($wiki,$wiki->page);
    if (!is_array($tagcloud) || count($tagcloud) == 0)  return Plugins::NOOP;
    Util::log(Util::vdump($tagcloud));
    Util::log(Util::vdump($ev['extra']));
    $ign = [];
    if (isset($ev['meta'][self::IGNORES])) {
      $i = $ev['meta'][self::IGNORES];
      if (!is_array($i)) $i = preg_split(self::RE_SPLIT,$i);
      foreach ($i as $w) {
	$w = self::tokenize($w);
	if (empty($w)) continue;
	$ign[$w] = $w;
      }
    }
    $t = empty($ev['meta']['tags']) ? [] : $ev['meta']['tags'];
    if (!is_array($t)) $t = preg_split(self::RE_SPLIT,$t);
    $tags = [];
    foreach ($t as $i) {
      $tags[$i] = $i;
    }

    foreach (preg_split('/\s+/',$ev['extra']) as $w) {
      $w = self::tokenize($w);
      if (empty($w) || isset($ign[$w])) continue;
      if (isset($tagcloud[$w])) {
	$tags[$tagcloud[$w]] = $tagcloud[$w];
      }
    }
    if (count($tags) > 0) {
      $ev['meta']['tags'] = implode(', ',$tags);
      Util::log(Util::vdump($ev['meta']));
    }
    return Plugins::OK;
  }

  /**
   * Loading entry point for this class
   *
   * Adds event hooks implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::registerEvent('log-props', [self::class, 'autoTagger']);
  }
}
