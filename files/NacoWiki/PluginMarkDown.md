---
title: PluginMarkDown
date: "2023-03-01"
---
This plugin is used to provide [Markdown][md] functionality to [NacoWiki][NW].  It provides
the following:

- [Markdown][md] renderer with extended syntax.
- [CodeMirror][cm] for editor.
- Allows the inclusion of a [YAML][yaml] block at the beginning to store meta data.

# Meta data

Meta data is stored in a block at the top of the file of the form:

```yaml
---
title: sample block
date: "2023-03-02"
---
```
Only the `title` attribute is used by [NacoWiki][NW].  But any data can be stored in the
[YAML][yaml] block.


# Markup

In addition to [Parsedown][parsedown] and [ParsedownExtra][pdextra] markup it adds the following
extensions:

- checkboxes in lists [x] and [ ] markup
- table span. [See markup][tspan]
- `~~` ~~strike-through~~ (del)
- `++` ++insert++ (ins)
- `^^` ^^superscript^^ (sup)
- `,,` ,,subscript,, (sub)
- `==` ==keyboard== (kbd)
- `??` ??highlight?? (mark)
- "\\" at the end of the line to generate a line break
- Links ending with `^` will open in a new window.
- headown
  - header html tags in the content start at H2 (since H1 is used
    by the wiki's document title.
  - `#++` and `#--` is used to increment headown level.  (Use this in
    combination with file includes.
- diagrams in fenced code blocks.
  - Adding to a fenced code block a tag such as:
    - graphviz-dot
    - graphviz-neato
    - graphviz-fdp
    - graphviz-sfdp
    - graphviz-twopi
    - graphviz-circo
    - `lineart` or `bob` or `aafigure` : parsed using [svgbob][svgbob]
  - This will render the given code as a SVG.
- Allows the use of fenced code blocks with tags to allow for syntax highlighting.
  - Lines begining with \`\`\`tag
    where `tag` is a language for syntax highlighting.
- Markdown libraries:
  - [Parsedown][parsedown]
  - [PardownExtra][pdextra]
  - `[toc]` tag implemented using the
  [TOC extension](https://github.com/KEINOS/parsedown-extension_table-of-contents/^)
  but tweaked to allow for case insensitive tags.
- Unordered list are tweaked to my personal preferences.


[md]: https://www.markdownguide.org/basic-syntax/
[tspan]: https://github.com/KENNYSOFT/parsedown-tablespan^
[NW]: https://github.com/iliu-net/NacoWiki/
[cm]: https://codemirror.net/
[parsedown]: https://github.com/erusev/parsedown
[pdextra]: https://github.com/erusev/parsedown-extra
[svgbob]: https://github.com/ivanceras/svgbob
[yaml]: https://yaml.org/.
