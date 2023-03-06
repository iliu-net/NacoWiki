---
title: PluginCode
---
Implements media handling for programming source code

This media handler handles programing languages source code.  This
is useful for storing code snippets in the wiki.

Source code meta-data can be stored in the file as comments:

For PHP:
```php
##---
## title: my-php-file.php
## date: 2023-03-05
##---
```

For python:
```python
##---
## title: sample-python.py
## date: 2023-03-25
##---
```

## Adding additional languages

To add an additional language, create anew entry in the TYPES
constant.

- array-key : should be the main file extension for this file type.

Each array entry contains:

- `exts` (optional) - array with additional file extensions
- `meta-re-start` - regular expression used to match begining of metadata block
- `meta-re-end` - regular expression used to match end of metadata block
- `meta-re-line` - regular expression used to extract metadata line
- `hl-js-class' - syntax highlighting class.  Refer to [Highlight.js](https://github.com/highlightjs/highlight.js/blob/main/SUPPORTED_LANGUAGES.md)
- `cm-mode` - CodeMirror mode.  [See CodeMirror](https://github.com/codemirror/codemirror5/tree/master/mode)
- `cm-deps` - Additional dependancies for CodeMirror.
- `template` - Template to use to create new files.

- @todo Adding additional languages:
	C/C++, sh/bash,batch, tcl/tk, perl, make, javascript, css
	go, rust, jinja2, lua, properties, sql, yaml
- @phpcod PluginCode
- @link https://github.com/highlightjs/highlight.js/blob/main/SUPPORTED_LANGUAGES.md
- @link https://github.com/codemirror/codemirror5/tree/master/mode
 

***
* plugins/PluginCode.php,12
***

