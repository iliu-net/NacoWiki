---
title: PluginHTML
date: "2023-03-02"
---
This plugin is used to handle HTML files.  Implements a media handler
interface.

To maintain the HTML syntax, HTML documents must follow this template:

```html
<html>
  <head>
    <!-- texts in meta tags are assumed to be url encoded -->
    <!--    Use "%22" to insert a quote (") -->
    <!--    Use "%25" to insert a "%" -->
    <title>Test HTML document</title>
    <meta name="sample" content="meta-data">
    <!--meta name="example-key" content="example-value"-->
  </head>
  <body>
    HTML content
  </body>
</html>
```

Note, only the HTML between `<body>` and `</body>` will be rendered.
Also, the meta data is read from the `<head>` section.  However,
only the lines with `<title>` and `<meta>` tags are recognized.

The `<title>` contents uses `htmlspecialchars` for escaping.  On the
other hand, the content of the `<meta>` is URL encoded at least for the
`%` (`%25`) and `"` (`%22`) characters.
