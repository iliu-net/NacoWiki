---
title: PluginWikiLinks
---
Wiki style links

This plugin is used to create links that have a shorter format
than Markdown style links.

Simplified markup for internal links.  It supports:

- hypertext links
  - `[[` : opening
  - __url-path__.
  - ==space== followed by html attribute tags (if any, can be omitted)
  - `|` followed by the link text if not specified, defaults to the
    __url-path__
  - `]]` : closing
- img tags
  - `{{` : opening
  - __url-path__
  - ==space== followed by html attribute tags (if any, can be omitted)
  - `|` followed by the `alt` and `title` text.  Defaults to
    __url-path__.
  - `}}` : closing

URL paths rules:

- paths beginning with `/` are relative to the root of the wiki
- paths beginning with `!/` search for full file paths that end with
  that path in the entire wiki.
- paths beginnig with `!` (without `/`) match basename in the entire wiki.
- paths are relative to the current document.

- @phpcod PluginWikiLinks
 

***
* plugins/PluginWikiLinks.php,14
***

