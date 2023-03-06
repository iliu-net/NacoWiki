---
title: commands
---
## cfg

Dump config

This implements the cli sub-command cfg

This shows current running configuration of the wiki.

- @param \NanoWikiApp $wiki running wiki instance
- @param array $argv Command line arguments
- @event cli:gvars
- @phpcod commands##cfg
   

***
* plugins/PluginVars.php,68
***

## files

List files

 Makes a list of Wiki files

- @phpcod commands##files
- @event cli:files
   

***
* plugins/SiteGen.php,156
***

## gvars

Dump global variables

This implements the cli sub-command gvars

This will dump all the variables defined in the configuration
of the wiki user cfg[plugins][PluginVars]

- @param \NanoWikiApp $wiki running wiki instance
- @param array $argv Command line arguments
- @event cli:gvars
- @phpcod commands##gvars


***
* plugins/PluginVars.php,46
***

## help


Show this help

This implements a CLI sub-command for HELP

It will look into the plugin configuration and display what
are the available sub-commands.  In addition if there is
a docstring for the implementing function, it will display
it.  It also shows what plugin's class is providing this
command.

- @param \NanoWikiApp $wiki running wiki instance
- @param array $argv Command line arguments
- @event cli:help
- @phpcod commands##help
   

***
* classes/Cli.php,70
***

## install


Install assets

This implements a CLI sub-command for plugins.

This will check if the assets directory specified in the
\NanoWikiApp configuration exists, and if it does not
exist, it will create it.

- @param \NanoWikiApp $wiki running wiki instance
- @param array $argv Command line arguments
- @event cli:install
- @phpcod commands##install
   

***
* classes/Cli.php,130
***

## mkassets

Copy assets

 Makes a copy of static assets to the generated site

- @phpcod commands##mkassets
- @event cli:mkassets
   

***
* plugins/SiteGen.php,121
***

## plugins

List available plugins

This implements a CLI sub-command for plugins.

This looks in the plugin configuration and shows what plugins
are currently available, its source file and the plugin version
if any

- @param \NanoWikiApp $wiki running wiki instance
- @param array $argv Command line arguments
- @event cli:plugins
- @phpcod commands##plugins
   

***
* classes/Cli.php,105
***

## render


Render a wiki page as HTML

- @param \NanoWikiApp $wiki running wiki instance
- @param array $argv Command line arguments
- @phpcod commands##render
- @event cli:render
   

***
* plugins/SiteGen.php,68
***

