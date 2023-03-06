<?php
/** PluginEmoji
 *
 * Generate Emojjis
 *
 * @package Plugins
 * @phpcod Plugins##PluginEmoji
 */
use NWiki\PluginCollection as Plugins;

/** Emoji ッ Plugin
 *
 * This plugin auto-detects smiley shortcuts and replaces them with emojis
 *
 * - EMOJI Source www.emoji-cheat-sheet.com
 * - ADD WHAT YOU LIKE: https://gist.github.com/hkan/264423ab0ee720efb55e05a0f5f90887
 * - More Unicode searches: http://xahlee.info/comp/unicode_index.html
 *
 * @author Igor Gaffling
 * @phpcod PluginEmoji
 */
class PluginEmoji {
  /** @var string */
  const VERSION = '3.0.0';
  /**
   * Loading entry point for this class
   *
   * Hooks post-render event implemented by this class
   */

  static function load(array $cfg) : void {
    Plugins::registerEvent('post-render', function(\NacoWikiApp $wiki, array &$ev) {
      // doc meta data can be used to skip emoji plugin.
      if (isset($wiki->meta['no-emoji']) && filter_var($wiki->meta['no-emoji'],FILTER_VALIDATE_BOOLEAN)) return Plugins::OK;
      $search_replace = array(
        '(y)'        => '👍',
        '(n)'        => '👎',
        ':+1:'       => '👍',
        ':-1:'       => '👎',
        ':wink:'     => '👋',
        ':tada:'     => '🎉',
        ':cat:'      => '😺',
        ':sparkles:' => '✨',
        ':camel:'    => '🐫',
        ':rocket:'   => '🚀',
        ':metal:'    => '🤘',
        ':star:'     => '⭐',
	':tent:'     => '⛺',
	':joy:'      => '🤣',
	':check_box:' => '&#x2610;',
	':check_mark:' => '&#x2611;',
	':check_cross:' => '&#x2612;',
        '<3'         => '❤', /* ❤️ 💗 */
        ';-)'        => '😉',
        ':-)'        => '🙂',
        ':-|'        => '😐',
        ':-('        => '🙁',
        ':-D'        => '😀',
        ':-P'        => '😛',
        ':-p'        => '😜',
        ':-*'        => '😘',
        ':-o'        => '😮',
        ':-O'        => '😲',
        ':-0'        => '😲',
        '^_^'        => '😁',
        '>_<'        => '😆',
        '3:-)'       => '😈',
        '}:-)'       => '😈',
        '>:-)'       => '😈',
        ":')"        => '😂',
        ":'-)"       => '😂',
        ":'("        => '😢',
        ":'-("       => '😢',
        '0:-)'       => '😇',
        'O:-)'       => '😇',
      );
      $ev['html'] =
	str_replace(array_keys($search_replace), $search_replace, $ev['html']);
      return Plugins::OK;
    });
  }
}
