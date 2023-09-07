---
title: Backlog
date: "2023-02-12"
tags: development, php
---
[toc]

***

%include-start%


- Enhance D attachments D to also let you do contents:(folder)
- Raw page link should be hidden for folder views.
- Render correctly:
  - [[/0ink-drafts/2021/2021-12-26-pelican_tests.md]]

%include-stop%

# Tools

- Report for checking for broken links (links to pages that don't exist yet), Orphan pages, etc.
  - For wiki links Plugin as a CLI tool

# SiteGen

- Templates
  - standard
    - page
    - article list
      - paginated
      - summary
  - specials
    - rss/atom feed
    - sitemap.xml
    - sitemap html
  - lists
    - sitemap (un-paginated, all article list)
  - pelican lists
    - archives : just a list of pages without summaries, grouped by date
    - author
    - category => year
    - index
    - tag
- List
  - last updated
- Search: https://stork-search.net/
- Sitemap generator
- RSS
- Compatibility with [Pelican?](https://getpelican.com/).
  - Use [twig](https://twig.symfony.com/) : since is derived from [Jinja2](https://palletsprojects.com/p/jinja/)
- Git data: save it in a `.git;` file.  To track changes.
  - `git rev-list --pretty=raw HEAD  -- file-path`
  - Only hook event if CLI (See: https://civihosting.com/blog/detect-cli-in-php-script/)
  - modifies `fileMeta` and `props`
- Migrate 0ink.net
- switch to github-actions
    - https://github.blog/2022-08-10-github-pages-now-uses-actions-by-default/
    - https://github.com/marketplace/actions/github-pages-action
    - https://docs.github.com/en/pages/getting-started-with-github-pages/configuring-a-publishing-source-for-your-github-pages-site
- SiteGen -- it doesn't seem to difficult to convert templates, so no real need for twig.

# Maybe

- Properties
  - Create files that begin with `.prop;`. followed by the page.  Track:
    - Remote user @ renite addr
    - Creation date
    - Log of modifications.  Keep it so that there is one log entry per day.
      ```yaml
      created:
      - 2023-03-01
      -
        - user
        - 192.168.101.5
      change-log:
      -
        - 2023-03-02
        -
          - user
          - 192.168.101.5
      ```
  - save this as `wiki->props`.
- Hook postSave event to implement backups or track changes
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

***

# done

- [x] UI: when starting page, it shoudl focus automatically on Edit window
- [x] Should prevent saving files where there is no change to source.
- [x] Youtube Links: Code snippets to load YouTube videos
  - https://stackoverflow.com/questions/11804820/how-can-i-embed-a-youtube-video-on-github-wiki-pages
- [x] WikiLinks
  - [x] if no `/` but a `!` should search the name all
    over the place.
    - ~~https://www.php.net/fnmatch~~
  - ~~Confirm if attachments are referenced properly~~
    - ~~Attachments should be linked directly~~
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
- [x]  tweak `css`.
- [x] ghrelease checks and gh-actions
- [x] phpDoc
- ~~more attachment logic~~
  - ~~walktree -- should filter out attachment folders~~
  - ~~when removing page, should remove attachment folders~~
  - ~~when doing makePath, we should check if we are creating folders within attachment folders.~~
  - ~~when creating file, make sure a file with the same name (but different extension)~~
    ~~doesn't exist.~~
  - [x] Document that attachment's are just a convenience logic of storing files in a folder
    of the same name as the page (without extension)
- [x] phpdoc
- [x] add a 'do=raw' link to allow for downloading of source code.

## More docs

We need more markup (beyond phpDoc) to document `event` hooks and API.  So we search
the code for special strings and extract them.  We parse these as Markdown.

- search for `'/^\s*##---\s?(.*)$/m'`
- collect in-between text until the next pattern.
- The `$match[1]` is treated as:
  - `file-name`
  - `#` (optional, and can be 1 `#` or more)
  - `section name` (optional, only if `#`'s were present)
- between matches, we collect lines that begin with:
  - `/^\s*##\s?/`
- these is saved to a file `file-name` with the optional header if specified.

When generating document, we use `$ include` to include the extracted text
in the right structure.

For error messages:

`##!! (file-name)|(element)|(optional? description)`

We collect these and we sort description by how often they happen.  And length as tie breaker.

- extract from source code write to markdown files
- use SiteGen to convert .md to .html
- include in docs directory


