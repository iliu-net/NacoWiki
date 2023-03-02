---
title: Backlog
date: "2023-02-12"
tags: development, php
---
[toc]

# UI

- when starting page, it shoudl focus automatically on Edit window
- sort - alpha,latest file (in views/folder)
- search results page is not very clear
  - highlight search result matches
- tweak `css`.

# Mark-up

- Code snippets to load YouTube videos or Google Maps, etc.
  - https://stackoverflow.com/questions/11804820/how-can-i-embed-a-youtube-video-on-github-wiki-pages
  - [[youtube:91233]] Or [[tryme.md]] 
- WikiLinks if no `/` but a `!` should search the name all
  over the place.
  - https://www.php.net/fnmatch

# Tools

- Report for checking for broken links (links to pages that don't exist yet), Orphan pages, etc.
  - For wiki links Plugin as a CLI tool
- static site generator
  - Search: https://stork-search.net/
  - Sitemap generator

# done 

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

# Maybe

- markdown and html media handler
  - Should be configured globally
  - run PHP code


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

  
