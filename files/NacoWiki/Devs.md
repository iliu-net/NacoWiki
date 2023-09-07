---
title: Programming Documentation
date: "2023-03-06"
---
[toc]

[NacoWiki][nw] is supposed to be extensible.  This is done through a Plugin architecture.  Plugins
can hook events that change the behaviour of [NacoWiki][nw] or add new functionality.

Documentation for the PHP Programming interface is generated automatically.

[[php-api]]

To create a plugin you can refer to the already existing plugins in the standard distribution.  At
a high-level you need to create a file containing the class that will encapsulate your plugin.

# Example

For example:

```php
use NWiki\PluginCollection as Plugins;
use NWiki\Util as Util;
use NWiki\Core as Core;
use NWiki\Cli as Cli;

class SamplePlugin {
  const VERSION ='0.0';
  /** sample command
   *
   * @param \NanoWikiApp $wiki running wiki instance
   * @param array &$argv Command line arguments
   * @phpcod commands##sample
   * @event cli:sample
   */
  static function render(\NacoWikiApp $wiki, array &$argv) : ?bool {
  	print_r($argv);
  }
  static function load(array $cfg) : void {
    Plugins::autoload(self::class);
  }
}
```

This implements a simple plugin that registers a new CLI command.  The event registration
is by calling  `Plugins::autoload` method.  This method looks for the PHP documentation
string that contains the tag `@event` followed by the event to hook: `cli:sample` in this
example.

# Event Hooks

The following events can be hooked:

$include: phpcod/events-list.md $


# Debugging

$include: debug.md $

# Customizing errors

When hooking the `error_msg` event, the following errors will be generated:

$include: phpcod/error-catalog.md $

# REST API

A basic REST API is available for use for use with JavaScript code running on the browser.

The following API calls are currently defined:

$include: phpcod/RESTAPI.md $


[nw]: https://github.com/iliu-net/NacoWiki/
