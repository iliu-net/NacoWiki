---
title: CHANGES
date: "2023-02-12"
author: alex
---
[toc]

# 3.4.0-rel

- UI tweaks
- Added `vacuum` option.  If enabled `walkTree` (usually from searching
  files) will delete metadata files that are orphaned.
- Core: properties are now saved as JSON files (the PHP JSON parser is more reliable than the YAML one).
- Util: added `is_program_in_path`
- plugins:
  - Versions
    - Deta files are now saved as JSON files (the PHP JSON parser is more reliable than the YAML one).
    - Only gets enabled if `diff` and `patch` commands are available.
    - Minor UI tweaks
  - Albatros
    - Minor fixes
  - AutoTag
    - tagcloud format tweaks
- debugging git mtime
- minor fixes and YAML loader work arounds.

# 3.3.0-rel

- Add a warning when there are unsaved changes on text editing.
- Fixed leaking dot files
- plugins:
  - PluginVars
	- Tweaked so that it can preview Albatros.
    - Fixed a warning
  - Albatros
    - Fixed an incompatibility in YAML reading
    - Added support for [utterances](https://utteranc.es/)

# 3.2.2-rel

- Albatros: UI tweaks
  - Drafts are ordered forward (instead of reverse)
  - Search CSS tweaks
- Filter . (dot) files form search.  Search defaults to global.

# 3.2.1

- Albatros: ensure categories are sorted
- bug fixes

# 3.2.0

- Added document properties
- Added `opts.yaml`
- API improvements
- Bug fixes and UI improvements
- Additional Plugins:
  - Versions
  - AutoTag
  - Albatros : Blog site generator (similar to [Pelican](https://getpelican.com/)).

# 3.1.0

- tweak footer
- PluginWikiLinks: Added optional modifier characters.
- PluginIncludes: Added markers to control how much of the included
  article is displayed.
- Folders in crumbs point to ".../"

# 3.0.0

- Renamed to `NacoWiki`
- Full rewrite of [NanoWiki](https://github.com/iliu-net/nanowiki).
- Cleaner and more functional UI.
- Initial REST-API support.
- Added a CLI interface.
- Off-tree installation, with the option of co-existing multiple instances.
- Code modularization,
  - `nacowiki` main class that integrates everything together.
  - `Core`: main WIKI functionality
  - `Cli`: CLI interface
  - `PluginCollection`: plugin support
- `CodeMirror` support now in the `Core` (instead of depending of Plugin implementations.
- Short code for Youtube links
- Media handling for source files.
- Added `do=raw` to display source code.
- Documentation
- Re-organized CSS files

# 2.x

This refers to [NanoWiki][nw].

# 1.x

[NanoWiki][nw] originally was based on [PicoWiki][pw].


**_NacoWiki is a is mostly a complete rewrite of_**
**_[NanoWiki][nw]_**
**_which in turn is based on [PicoWiki][pw]_.**

  [nw]: https://github.com/iliu-net/nanowiki
  [pw]: https://github.com/luckyshot/picowiki
