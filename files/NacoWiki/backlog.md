---
title: Backlog
date: "2023-02-12"
tags: development, php
---
[toc]

***


# Issues

- Render correctly:
  - [[/0ink-drafts/2021/2021-12-26-pelican_tests.md]]
- more attachment logic
  - walktree -- should filter out attachment folders
  - when removing page, should remove attachment folders
  - when doing makePath, we should check if we are creating folders within attachment folders.
  - when creating file, make sure a file with the same name (but different extension)
    doesn't exist.

# Mark-up

- WikiLinks
  - Code snippets to load YouTube videos or Google Maps, etc.
    - https://stackoverflow.com/questions/11804820/how-can-i-embed-a-youtube-video-on-github-wiki-pages
    - [[youtube:91233]] Or [[tryme.md]] 
  - if no `/` but a `!` should search the name all
    over the place.
    - https://www.php.net/fnmatch
  - Confirm if attachments are referenced properly
    - Attachments should be linked directly

# Tools

- Report for checking for broken links (links to pages that don't exist yet), Orphan pages, etc.
  - For wiki links Plugin as a CLI tool
- static site generator
  - Search: https://stork-search.net/
  - Sitemap generator

# done 

- [x] attachments not done properly at the moment.
  - [x] add file attachments (only for actual media handled pages)
  - [x] ~~handle it in PluginVars~~
- [x] search results page is not very clear
- [x] highlight search result matches
- [x] Add a drop down to show meta data of current page
- [x] dropdown for file list: add link to copy page path to Clipboard &#x29C9;
- [x] UI elements
- [x] static site first step: single file generator
- [x] bind-key to invoke edit link
- [x] split css
  - layout
  - color/types
- [x] implement a dark theme
- [x] generate links that open new windows if URL ends with "^".  ^ is stripped.
- [x] File tree display doesn't handle symlinks.
-[x]  tweak `css`.

# phpDoc

- must run as docker container (since void does not include `phar` in PHP.
  - `docker run --rm -v ${PWD}:/data phpdoc/phpdoc:3 -d . -t docs/API
- using composer didn't work
- For plugins:
  ```php
  /**
  * @package Plugins\PluginName
  */
  ```
  at the top to have plugins show up as a different package. \
  See https://docs.phpdoc.org/3.0/guide/references/phpdoc/tags/package.html
- Docblocks
  - https://docs.phpdoc.org/3.0/guide/getting-started/what-is-a-docblock.html#what-is-a-docblock
  - https://docs.phpdoc.org/3.0/guide/guides/docblocks.html#more-on-docblocks
- Running:
  - https://docs.phpdoc.org/3.0/guide/guides/running-phpdocumentor.html
- configuring
  - https://docs.phpdoc.org/3.0/guide/references/configuration.html

# Maybe

- ghrelease checks and gh-actions
- Properties
  - Create files that begin with `.prop;`. followed by the page.  Track:
    - Remote user
    - Creation date
    - Log of modifications.  Keep it so that there is one log entry per day.
  - save this as `wiki->props`.
- preSave and postSave events to implement backups of track changes
  - backups
    - keep {n} versions.  But only overwrite backup if older than a day.
  - Track changes
    - Create files with `.n;page.md` followed by page.  Where `n` is a number.
    - Store reverse diffs in here.
    - Possible libraries for generating diffs:
      - https://github.com/baraja-core/simple-php-diff
      - https://github.com/jfcherng/php-diff
      - https://www.php.net/manual/en/function.xdiff-file-diff.php
      - or just exec `diff` command.
- markdown and html media handler
  - Should be configured globally
  - run PHP code
- UI: when starting page, it shoudl focus automatically on Edit window
  - ??don't know how to do this.??
- sort - alpha,latest file (in views/folder)
  - add it to the context?

## Tag Navigation

- nav
  - tag-cloud [all files|current context]
- tags: GET to add or remove tags from the selection cookie
- tagging
  - [ ] auto-tagging: based on words and tagcloud
  - tag from git
  - auto-tags: automatically generated
  - tags: manual tags
  - exclude-tags: removed.


## Markdown text diagrams

- blockdiag
  - http://blockdiag.com/en/

## Other diag integrations

- https://github.com/cidrblock/drawthe.net
- https://github.com/jgraph/drawio

## auth

- user authentication
  - https://www.devdungeon.com/content/http-basic-authentication-php
- http daemon authentication
  - https://httpd.apache.org/docs/2.4/howto/auth.html
- add front-matter-yaml support
  - md : when saving, check yaml
  - getRemoteUser
      - http user?
      - remote IP
  - if file does not exist
  - created: <date> <remote-user>
  - updated-by: <remote-user>
  - if (log in meta/yaml) {
    make log empty
    change-log: <date> <remote-user> <log-msg>

  
