---
title: RESTAPI
---
## page-list

Returns a page list

This is used by the JavaScript to render a tree of available pages.

The event parameter is filled with a property `output` containing

- array with directories
- array with files
- current page
- base URL from $cfg[base_url]

- @todo Should check permissions when returning files
- @todo Should filter out attachment folders
- @see \NWiki\PluginCollection::dispatchEvent
- @phpcod RESTAPI##page-list
- @event api:page-list
- @param \NacoWikiApp $wiki NacoWiki instance
- @param array $ev Event data.
- @return ?bool Returns \NWiki\PluginCollection::OK to indicate that it was handled.
   

***
* classes/Core.php,896
***

