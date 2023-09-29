---
title: Backlog
date: "2023-02-12"
author: alex
tags: development, php
---
[toc]

***

%include-start%

- Migrate https://0ink.net/

%include-stop%

- Enhance D attachments D to also let you do contents:(folder)
- views/page.html : show props created and last change log
- Display props in page.html
- test and fix adding message to the chagelog entries
- ~~Render correctly:~~
  - [[/0ink-drafts/2021/2021-12-26-pelican_tests.md]]

# VERSIONS Plugin

 * @todo Limit number of versions
 * @todo modify $event[filemeta] $evet[props] in preRead.
 * ~~@todo detect if changes happened outside NacoWiki (filemtime != change-log)~~

# AutoTag plugin

- Add CLI command to re-tag Wiki sub-trees.
 
# Tools

- Report for checking for broken links (links to pages that don't exist yet), Orphan pages, etc.
  - For wiki links Plugin as a CLI tool

# Maybe

- sort - alpha,latest file (in views/folder)
  - add it to the context?

## Tag Navigation

- nav
  - tag-cloud [all files|current context]
- tags: GET to add or remove tags from the selection cookie
- Create a page that show tags.


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


