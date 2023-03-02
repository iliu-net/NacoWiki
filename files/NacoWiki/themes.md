---
title: themes.md
date: "2023-03-02"
---
You can configure a theme for the overall wiki and for the [CodeMirror][cm] and [Highlight.js][hljs]
add-on libraries.

To configure the themes, you must add to the configuration the following keys:

| Key | Use |
|---|---|
| theme | Configure the overall theme of the wiki |
| theme-highlight-js | [highlight.js][hljs] theme used for syntax highlighting of code blocks |
| theme-codemirror | [CodeMirror][cm] theme used in the editor page |

# NacoWiki themes

These are found in the `assets/themes` folder.  There is a theme per folder. To create a new
theme, create a new folder, and copy the assets in there.  You do not need to create all 
new assets.  You can just create symbolic links to assets that you want to re-use.

# [CodeMirror][cm] themes

You just need to specify the theme to be used.  You can preview the themes here:

* [CodeMirror themes demo](https://codemirror.net/5/demo/theme.html^)

Just use the same name in the configuration key.

# [highlight.js][hljs] themes

Just specify the theme to use.  You may need to append `.min` to the theme name depending
on the CDN.

YOu can preview themes here:

* [highlight.js demo](https://highlightjs.org/static/demo/^)

Once you have selected the theme, look in the source of the page to look-up the CSS
file for that theme.

[cm]: https://codemirror.net/
[hljs]: https://highlightjs.org/


