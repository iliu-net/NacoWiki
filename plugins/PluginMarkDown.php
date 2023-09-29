<?php
/** Markdown media handler
 *
 * Media handler for Markdown files
 *
 * @package Plugins
 * @phpcod Plugins##PluginMarkDown
 */
use NWiki\PluginCollection as Plugins;
use NWiki\Core as Core;

/**
 * NacoWiki MarkDown
 *
 * This plugin is used to provide [Markdown][md] functionality to [NacoWiki][NW].  It provides
 * the following:
 *
 * - [Markdown][md] renderer with extended syntax.
 * - [CodeMirror][cm] for editor.
 * - Allows the inclusion of a [YAML][yaml] block at the beginning to store meta data.
 *
 * # Meta data
 *
 * Meta data is stored in a block at the top of the file of the form:
 *
 * ```yaml
 * ---
 * title: sample block
 * date: "2023-03-02"
 * ---
 * ```
 *
 * Only the `title` attribute is used by [NacoWiki][NW].  But any data can be stored in the
 * [YAML][yaml] block.
 *
 * # Markup
 *
 * In addition to [Parsedown][parsedown] and [ParsedownExtra][pdextra] markup it adds the following
 * extensions:
 *
 * - checkboxes in lists [x] and [ ] markup
 * - table span. [See markup][tspan]
 * - `~~` ~~strike-through~~ (del)
 * - `++` ++insert++ (ins)
 * - `^^` ^^superscript^^ (sup)
 * - `,,` ,,subscript,, (sub)
 * - `==` ==keyboard== (kbd)
 * - `??` ??highlight?? (mark)
 * - "\\" at the end of the line to generate a line break
 * - Links ending with `^` will open in a new window.
 * - headown
 *   - header html tags in the content start at H2 (since H1 is used
 *     by the wiki's document title.
 *   - `#++` and `#--` is used to increment headown level.  (Use this in
 *     combination with file includes.
 * - diagrams in fenced code blocks.
 *   - Adding to a fenced code block a tag such as:
 *     - graphviz-dot
 *     - graphviz-neato
 *     - graphviz-fdp
 *     - graphviz-sfdp
 *     - graphviz-twopi
 *     - graphviz-circo
 *     - `lineart` or `bob` or `aafigure` : parsed using [svgbob][svgbob]
 *   - This will render the given code as a SVG.
 * - Allows the use of fenced code blocks with tags to allow for syntax highlighting.
 *   - Lines begining with \`\`\`tag
 *     where `tag` is a language for syntax highlighting.
 * - Markdown libraries:
 *   - [Parsedown][parsedown]
 *   - [PardownExtra][pdextra]
 *   - `[toc]` tag implemented using the
 *   [TOC extension](https://github.com/KEINOS/parsedown-extension_table-of-contents/^)
 *   but tweaked to allow for case insensitive tags.
 * - Unordered list are tweaked to my personal preferences.
 *
 * [md]: https://www.markdownguide.org/basic-syntax/
 * [tspan]: https://github.com/KENNYSOFT/parsedown-tablespan^
 * [NW]: https://github.com/iliu-net/NacoWiki/
 * [cm]: https://codemirror.net/
 * [parsedown]: https://github.com/erusev/parsedown
 * [pdextra]: https://github.com/erusev/parsedown-extra
 * [svgbob]: https://github.com/ivanceras/svgbob
 * [yaml]: https://yaml.org/.
 *
 * @phpcod PluginMarkDown
 */
class PluginMarkdown {
  /** var string */
  const VERSION = '2.0.0';
  /** Read structured data
   *
   * Extracts meta data from the YAML from matter at the beginning
   * of the file.  And the MarkDown source right after.
   *
   * @param string $source text to process
   * @param array &$meta receives the read meta data
   * @param string $ext media file extension
   * @return string Returns Markdown payload
   */
  static function readStruct(string $source,array &$meta) : string {
    if (!preg_match('/^\s*---\s*\n/',$source,$mv)) return $source;

    $start = $offset = strlen($mv[0]);

    if (!preg_match('/\n\s*---\s*\n/',$source,$mv,PREG_OFFSET_CAPTURE,$offset)) return $source;
    $end = $mv[0][1];
    $offset = $mv[0][1] + strlen($mv[0][0]);

    $yaml = yaml_parse(substr($source,$start,$end-$start));
    if ($yaml === false) return $source;

    $meta = array_merge($meta,$yaml);

    return substr($source,$offset);
  }
  /** Formats metadata and Markdown content
   *
   * Formats the file contents to have a metadata in a YAML block
   * at the top, followed by Markdown content right afterwards.
   *
   * @param array &meta meta data
   * @param string $body Markdown payload
   * @return string full file with YAML block and Markdown content
   */

  static function makeSource(array $meta,string $body) : string {
    if (count($meta)) {
      $yaml = substr(yaml_emit($meta),0,-4).'---'.PHP_EOL;
    } else {
      $yaml = '';
    }
    return $yaml.$body;
  }
  /** Render event handler
   *
   * Handles `render:[ext]` events.
   *
   * Convert Markdown to HTML
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function render(\NacoWikiApp $wiki, array &$ev) : ?bool {
    require_once Plugins::path('lib/Parsedown-1.7.4.php');
    require_once Plugins::path('lib/ParsedownExtra-0.8.1.php');
    require_once Plugins::path('lib/TOC-1.1.2.php');
    require_once Plugins::path('lib/Extension.php');

    $Parsedown = new ParsedownExtension();
    $Parsedown->headown = 1;
    $ev['html'] = $Parsedown->text($ev['html']);
    return Plugins::OK;
  }
  /** Edit event handler
   *
   * Handles `edit:[ext]` events.
   *
   * It creates a page with CodeMirror configured in Markdown mode.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function edit(\NacoWikiApp $wiki, array &$data) : ?bool {
    $meta = $wiki->meta;
    $payload =  self::readStruct($wiki->source, $meta);
    $wiki->source = self::makeSource($meta,$payload);

    Core::codeMirror($wiki,[
      'js' => [
        'addon/edit/continuelist.min.js',
	'mode/xml/xml.min.js',
	'mode/javascript/javascript.min.js',
	'mode/markdown/markdown.min.js',
	'mode/php/php.min.js',
      ],
      'mode' => 'markdown',
    ]);
    exit();
  }
  /** preSave event handler
   *
   * Handles `preSave:[ext]` events.
   *
   * Reads from the submitted text and modifes it to make sure
   * that there is a YAML block followed by the Markdown text.
   *
   * This makes the saved file in a consistent format.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function preSave(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $meta = [];
    $body = self::readStruct($ev['text'], $meta);
    # Modify $log ...
    Core::logProps($wiki, $ev['props'], $meta, $body);

    $ev['text'] = self::makeSource($meta,$body);
    return Plugins::OK;
  }
  /** Read event handler
   *
   * Handles `read:[ext]` events.
   *
   * Reads the file extract meta data tags from the YAML block and
   * separates the Markdown content.
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$ev passed event
   */
  static function read(\NacoWikiApp $wiki, array &$ev) : ?bool {
    $ev['payload'] = self::readStruct($ev['source'], $ev['meta']);
    return Plugins::OK;
  }
  /**
   * Loading entry point for this class
   *
   * Hooks Markdown media implemented by this class
   */
  static function load(array $cfg) : void {
    Plugins::registerMedia(['md','markdown','mkd','mdwn','mdown','mdtxt','mdtext'], self::class);
  }
}
