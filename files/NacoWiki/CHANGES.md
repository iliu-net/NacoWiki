---
title: CHANGES
date: "2023-02-12"
---
[toc]


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

