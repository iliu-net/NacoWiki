---
title: Writing Documentation
date: "2023-09-13"
---
[TOC]

# Process Overview

The document generation process at a high level is:

1. Run `scripts/docgen`
   - extract from source code using `phpcod` write to markdown files
   - use SiteGen to convert .md to .html
2. Run `scripts/docrun`
   - Generate PHP documentation using [phpdoc][phpdoc]
   - Link `SiteGen` files with [phpdoc][phpdoc].

# phpDocumentor

Most of the code is documented using [phpdoc][phpdoc].  For writing [phpdoc][phpdoc]
documentation refer to the following articles:

- [What is a DocBlock](https://docs.phpdoc.org/3.0/guide/getting-started/what-is-a-docblock.html)
- [More on DocBlocks](https://docs.phpdoc.org/3.0/guide/guides/docblocks.html#more-on-docblocks)

# phpcod

There is additional markup (beyond [phpdoc][phpdoc] to document `event` hooks and the REST API.
This is handled by the script `phpcod`.

`phpcod` scans the code for special strings
and extracts them.  These strings are treated as [markdown][markdown].

The scanning works as follows:

- search for `'/^\s*##---\s?(.*)$/m'`
- collect in-between text until the next pattern.
- The `$match[1]` is treated as:
  - `file-name`
  - `#` (optional, and can be 1 `#` or more)
  - `section name` (optional, only if `#`'s were present)
- between matches, we collect lines that begin with:
  - `/^\s*##\s?/`
- these is saved to a file `file-name` with the optional header if specified.

When generating documentation, we use `$ include` in `SiteGen` to include the extracted text
in the right structure.

Additionally, for error messages:

`##!! (file-name)|(element)|(optional? description)`

We collect these and we sort description by how often they happen.  And length as tie breaker.








  [phpdoc]: https://www.phpdoc.org/
  [markdown]: https://daringfireball.net/projects/markdown/

