---
title: events-list
---
## action:[name]

This event is called in response of a `$_POST[action]` in the
HTTP request.  It is used to trigger a specific action in response
to a HTTP post request.

There are no special event parameters

***
* nacowiki.php,520
***

## api:[name]

This event is called in response of a `$_GET[api]` in the URL's
query string.  It is used to trigger a specific REST API end point.

Event data:

- `status` (output): pre-loaded with Plugins::API_OK to optimistically
  assume success.  Event handlers should change this to
  Plugins:API_ERROR in the event of an error.
- `msg` (output): event handler **must** fill this if an error
  happens.  The convenience function Plugins::apiError is available
  to help this.
- __additional items__ (output): The API end-point shoudl populate the
  event data with additional items.  The whole event data array
  is then send to the client in JSON format.

***
* nacowiki.php,490
***

## check_readable

This event can be used by plugins that implement access control.
This event is used to check if the given file path can be read
by the current user.

Event data:

- `page` (input): URL being checked
- `fpath` (input): File system path to the URL.
- `access` (output): pre-loaded with the access.  Event handler
   must populate with the right access.  This can be
  one of:
  - `true` : access is allowed
  - `false` : access is denied
  - `NULL` : access is denied due to not being authenticated.

***
* nacowiki.php,297
***

## check_writable

This event can be used by plugins that implement access control.
This event is used to check if the given file path can be written
by the current user.

Event data:

- `page` (input): URL being checked
- `fpath` (input): File system path to the URL.
- `access` (output): pre-loaded with the access.  Event handler
   must populate with the right access.  This can be
  one of:
  - `true` : access is allowed
  - `false` : access is denied
  - `NULL` : access is denied due to not being authenticated.

***
* nacowiki.php,258
***

## cli:[name]

This event is used to handle CLI the `[name]` sub-command.

The event data contains the command line arguments for the
sub-command.

***
* nacowiki.php,411
***

## context_loaded

This event is called right after the context has been loaded.

There are no special event parameters

***
* nacowiki.php,452
***

## do:[name]

This event is called in response of a `$_GET[do]` in the URL's
query string.  It is used to trigger a specific command.

There are no special event parameters

***
* nacowiki.php,478
***

## edit:[file-extension]


This event is used by media handlers to output to the web
browser a possibly custom page to edit media.

Typically this is used to pre-format the editable content
separating metadata and body text.  Also, to display
a codemirror with the right modules loaded and initialized
to the right mode.

Event data:

- `ext` (input) : file extension for the given media

***
* classes/Core.php,690
***

## error_msg

This event is called to implement custom error handlers.

Event data:
- `msg` (input) : error message
- `tag` (input) : error tag
- `opts` (input) : flags `EM_NONE` or `EM_PHPERR`.

***
* nacowiki.php,219
***

## layout:[file-extension]


This event is used by media handlers to output to the web
browser a possibly custom layout for the given media type.

Event data:

- `ext` (input) : file extension for the given media

***
* classes/Core.php,489
***

## missing:[file-extension]


This event is used by media handlers to output to the web
browser a possibly custom page to create missing media.

Event data:

- `ext` (input) : file extension for the given media

***
* classes/Core.php,533
***

## missing_page

This event is used to handle http 404 errors (missing resource)

There are no special event parameters

***
* nacowiki.php,557
***

## post-render


This event is used by plugins to post-process data.  This
specific event triggers for any file regardless of the
file extension.

Plugins using this hook can post-process the generated HTML.

Event data:

- `html` (input|output) : current page contents which will be eventually rendered.
- `ext` (input) : file extension for the given media

***
* classes/Core.php,473
***

## post-render:[file-extension]


This event is used by plugins to post-process data.  This
specific event only triggers for pages matching the given
file extension.

Plugins using this hook can post-process the generated HTML.

Event data:

- `html` (input|output) : current page contents which will be eventually rendered.
- `ext` (input) : file extension for the given media

***
* classes/Core.php,459
***

## postSave


This event is used by plugins to examine data after it
was saved to storage.

Usually used to update additional meta data files, like for
example update a tag cloud index.

Event data:

- `text` (input) : textual data that was saved
- `prev` (input) : previous file contents (or NULL)
- `ext` (input) : file extension for the given media

***
* classes/Core.php,821
***

## postSave:[file-extension]


This event is used by media handlers to examine data after it
was saved to storage.

Event data:

- `text` (input) : textual data that was saved
- `prev` (input) : previous file contents (or NULL)
- `ext` (input) : file extension for the given media

***
* classes/Core.php,836
***

## pre-render


This event is used by plugins to pre-process data.  This
specific event triggers for any file regardless of the
file extension.

Since this is a pre-render event, the `html` element
actually contains text before HTML is generated, but may
be modified by the pre-render hook.

Event data:

- `html` (input|output) : current page contents which will be eventually rendered.
- `ext` (input) : file extension for the given media

***
* classes/Core.php,428
***

## pre-render:[file-extension]


This event is used by plugins to pre-process data.  This
specific event only triggers for pages matching the given
file extension.

Since this is a pre-render event, the `html` element
actually contains text before HTML is generated, but may
be modified by the pre-render hook.

Event data:

- `html` (input|output) : current page contents which will be eventually rendered.
- `ext` (input) : file extension for the given media

***
* classes/Core.php,412
***

## preSave


This event is used by plugins to pre-parse text before
saving to storage.

Hooks can examine the data to be saved in the `text` element
of the event array and modify it if needed.

Event data:

- `text` (input|output) : textual data to save
- `prev` (input) : current file contents (or NULL)
- `ext` (input) : file extension for the given media

***
* classes/Core.php,759
***

## preSave:[file-extension]


This event is used by plugins to pre-parse text before
saving to storage.

Hooks can examine the data to be saved in the `text` element
of the event array and modify it if needed.

Usually is used by media handlers to pre-parse data, separating
header meta data from actual content.  And sanitizing any
problematic input.

Event data:

- `text` (input|output) : textual data to save
- `prev` (input) : current file contents (or NULL)
- `ext` (input) : file extension for the given media

***
* classes/Core.php,774
***

## read:[file-extension]

This event is used for media handlers to parse source text
and extract header meta data, and the actual payload containing
the body of the page content.

Event data:

- `filepath` (input) : file system path to source
- `url` (input) : web url to source
- `source` (input) : text containing the page document verbatim
- `filemeta` (input) : pre-loaded file-system based meta data
- `meta` (output) : to be filed by event handler with meta-data.
   it is pre-loaded with data derived from filemeta.
- `payload` (output) : to be filed by event handler with the body of the page.
- `ext` (input) : file extension for the given media

***
* classes/Core.php,346
***

## read_folder

This event is used to handle navigation to a folder.  i.e.
URL is a folder.

There are no special event parameters

***
* nacowiki.php,537
***

## read_page

This event is used to handle navigation to a page.  i.e.
URL is an actual file.

There are no special event parameters

***
* nacowiki.php,549
***

## render:[file-extension]


This event is used by media handlers to convert the
source to HTML.

The hook must take the `html` element from the event
which contains possibily pre-processed input, and
convert it to HTML.

Event data:

- `html` (input|output) : current page contents which will be eventually rendered.
- `ext` (input) : file extension for the given media

***
* classes/Core.php,444
***

## run_init

This event is called right when the `run` method is called.
Plugins can use this to initialize things.  Specifically
for using the `declareContext`.

There are no special event parameters

***
* nacowiki.php,431
***

## save:[file-extension]


This event is used by plugins to save to storage.

Usually is used by media handlers to save data using custom
file formats.

Event data:

- `saved` (output) : flag to indicates that we saved or not
   the file.  If this is set to `false`, then `postSave` events
   will be skipped.  Pre-set to `true` by default.
- `text` (input) : textual data to save
- `prev` (input) : current file contents (or NULL)
- `ext` (input) : file extension for the given media

***
* classes/Core.php,794
***

