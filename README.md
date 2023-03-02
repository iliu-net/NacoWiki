---
title: README.md
date: "2023-03-01"
note: This is shown in the Github repo, so onl use GFM markup.
---
# NacoWiki

**_NacoWiki is a small and simple file-based Wiki system._**
**_It is mostly a complete rewrite of_**
**_[NanoWiki](https://github.com/iliu-net/nanowiki)_**
**_which in turn is based on [PicoWiki](https://github.com/luckyshot/picowiki)_**

# Features

- **Extensible**
- **File-based** Easily editable
- ?easy? to install.

***

# Deployment modes

1. copy dist
2. copy index-alt.php and static
3. symlink web
4. symlink web/* to location

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

- fileinfo
- pecl-yaml
- dom
- json


## License & Contact

[NacoWiki](https://github.com/iliu-net/NacoWiki/) \
Copyright &copy; 2023 Alejandro Liu. \
Licensed under [MIT](https://opensource.org/licenses/MIT).

[NanoWiki](https://github.com/iliu-net/nanowiki) \
Copyright &copy; 2022 Alejandro Liu. \
Licensed under [MIT](https://opensource.org/licenses/MIT).

[PicoWiki](https://github.com/luckyshot/picowiki) \
Copyright &copy; 2018-2019 [Xavi Esteve](https://xaviesteve.com/). \
Licensed under [MIT](https://opensource.org/licenses/MIT).

[Parsedown](https://github.com/erusev/parsedown) by Emanuil Rusev also licensed under a MIT License.

Some plugins copyright by their respective authors.
