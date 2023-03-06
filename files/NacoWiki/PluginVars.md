---
title: PluginVars
---
NacoWiki Vars Plugin

This plugin is used to render config and meta data on a page

This plugin is used to create text substitutions.  There are two
sets of substitutions.  Substitutions done **before**
and **after** rendering.

- Before rendering:
  - `$ urls$`: Current url
  - `$ cfg$` : current configuration (as a YAML document)
  - `$ vars$` : current config variables defined in `cfg[plugins][PluginVars]` (as a YAML document)
  - `$ cfg.key$`: values in the `cofg` table
  - `$ meta.key$` : values defined in the meta data block of the page.
  - `$ file.key$` : file system metadata (usually just the file time stamp).
  - `$ prop.key$` : File properties (managed by `NacoWiki`.
  - `$ key$` : Additional variables as defined in `cfg[plugins][PluginVars]`
- After rendering:
  - `$ plugins$` an unordered HTML list containing loaded plugins.
  - `$ attachments$` an unordered HTML list containg links to
    the current document's attachments.

# CLI sub-commands

This plugin registers two sub-commands:

- `cfg` : dumps current configuration
- `gvars` : dumps defined global variables ad configured in `[plugins][PluginVars]`.

- @phpcod PluginVars
 

***
* plugins/PluginVars.php,11
***

