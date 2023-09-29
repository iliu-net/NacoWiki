---
title: Debugging
date: "2023-03-06"
author: alex
---
The `$context` property of the NacoWiki class contains
a 'debug' flag.  To turn on add to the URL:

- `?ctx_debug=1`

And to turn off add:

- `?noctx_debug=1`

You can then use in your code checks such as:

```php
if ($wiki->context['debug']) {
  echo "DEBUG MODE ON!<br>";
}
```

There is also the function:

```
Util::log($msg)
```

This will add `$msg` to a log buffer which will be shown by the footer
if debug is on.

At the time of this writing, `APP_DIR/views/footer.html` makes
use of this flag to show debug info.
