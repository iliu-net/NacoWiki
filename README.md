---
title: README.md
date: "2023-03-01"
---
<!-- This is shown in the Github repo, so only use GFM markup. -->

![syntax check](https://github.com/iliu-net/NacoWiki/actions/workflows/static-checks.yaml/badge.svg)
![docgen](https://github.com/iliu-net/NacoWiki/actions/workflows/gh-page-update.yml/badge.svg)

# NacoWiki

**_NacoWiki is a file-based Wiki system._**

# Features

- **Extensible** through plugins.
- **File-based** Easily editable
- Simple off-tree installation with multiple instances.
- CLI interface.
- Syntax highlight'ing based on [hihglight.js](https://highlightjs.org/)
- Highlighting editor based on [CodeMirror](https://codemirror.net/)
- Support of front-matter Meta data.
- Some features from standard Plugins:
  - Generate [grapviz](https://graphviz.org/) or [svgbob](https://github.com/ivanceras/svgbob) drawings from tagged/fenced code blocks
  - Extended Markdown markup.
  - Support for source code with display and editing with syntax highlighting for supported
    languages.  This is meant to be used to store snippets
  - Wiki style links
  - Emojis
  - Static site generator

I myself use it as a personal wiki.  I also use it to document
[NacoWiki][nw] itself.  It has a simple static site generator
`SiteGen` that along with `phpDocumentor` and some custom scripts
it generates the documentation for the
[gh-pages website](https://iliu-net.github.io/NacoWiki/).  Finally
I am using to replace [pelican][pp] static
site generator for my personal [blog](https://0ink.net/).  This
functionality makes use of `Albatros` which is a different plugin
from `SiteGen` that works similarly to [palican][pp]

Essentially it is flat-file based wiki that can export sections of
it as a static site.

***

# Set-up

- copy dist
- create a folder to store entry php and assets.
  - one instance == one entry php.
  - entry php contains configuration to that specific instance.
  - Assets can be shared with multiple instance as long as they use the same code base.
- php _instance-php_ install

# Plugins

Nearly all the functionality of NacoWiki is implemented using
a plugable event architecture.

This means that additional functionality can be added through
plugins.  In fact, most of the Core functionality is implemented
through the same plugin mechanism.

# Requirements

- PHP 7.4.33 or above
- [svgbob](https://github.com/ivanceras/svgbob) : line-art
- graphviz : code diag

## PHP Extensions

- fileinfo - for determining mime content type
- pecl-yaml
- dom
- json

## License & Contact

[NacoWiki][nw] \
Copyright &copy; 2023 Alejandro Liu. \
Licensed under [MIT](https://opensource.org/licenses/MIT).

[Parsedown](https://github.com/erusev/parsedown) by Emanuil Rusev also licensed under a MIT License.

Some plugins and parsedown extensions copyright by their respective authors.

  [nw]: https://github.com/iliu-net/NacoWiki/
  [pp]: https://getpelican.com/
