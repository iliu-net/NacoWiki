---
title: PluginWikiLinks
date: "2023-03-02"
---
This plugin is used to create hyperlinks with a __"simpler"__ markup style.  It supports the following:

- Internal hypertext links
  - `[[` : opening
  - __url-path__ : URL to page
    - If it begins with `/` it is an absolute path to the wiki.
    - **TO BE IMPLEMENTED** If it begins with `!` it will do a name search for the file.
    - Otherwise it is relative to the current page
  - ==space== followed by html attribute tags (if any, can be omitted)
  - `|` followed by the link text if not specified, defaults to the
    __url-path__.
  - `]]` : closing
- Internal img links
  - `{{` : opening
  - __url-path__ : URL image
    - If it begins with `/` it is an absolute path to the wiki.
    - **TO BE IMPLEMENTED** If it begins with `!` it will do a name search for the file.
    - Otherwise it is relative to the current page
  - ==space== followed by html attribute tags (if any, can be omitted)
  - `|` followed by the `alt` and `title` text.  Defaults to
    __url-path__.
  - `}}` : closing
- **TO BE IMPLEMENTED** Youtube link
  - `[[`
  - youtube: _youtubeID_ | optional text
  - `]]`
    ```html
    <a href="https://www.youtube.com/watch?v=$meta.vid$">
    <img src="https://img.youtube.com/vi/$meta.vid$/0.jpg" width=320 height=240>
    </a>
    ```

