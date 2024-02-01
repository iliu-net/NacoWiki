---
title: Backlog
date: "2023-02-12"
author: alex
tags: development, php
---
[toc]

***

%include-start%

- check if walktree callers filter prop and ver files
  - search in navtree shows .prop and .ver's
- opts.yaml in 0ink.net drafts doesn't take
- tree view doesn't scroll properly
- creating folders not clear

%include-stop%

- Spell checker: https://github.com/sparksuite/codemirror-spell-checker
- Enhance D attachments D to also let you do contents:(folder)
- views/page.html : show props created and last change log
- test and fix adding message to the chagelog entries

# VERSIONS Plugin

 * @todo Limit number of versions
 * @todo modify $event[filemeta] $evet[props] in preRead.
   - i.e. show the right props change log entries and the right date in filemeta.
 * ~~@todo detect if changes happened outside NacoWiki (filemtime != change-log)~~
 * tweak the hook that shows version to check if there are versions before
   adding the option.
 * info box should show the number of versions found.

# AutoTag plugin

- Add CLI command to re-tag Wiki sub-trees.
- Create a tagcloud navigator
- nav
  - tag-cloud [all files|current context]
- tags: GET to add or remove tags from the selection cookie
- Create a page that show tags.

# Tools

- Report for checking for broken links (links to pages that don't exist yet), Orphan pages, etc.
  - For wiki links Plugin as a CLI tool

# Maybe

- sort - alpha,latest file (in views/folder)
  - add it to the context?



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


