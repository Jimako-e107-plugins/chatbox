# chatbox

A modernized fork of the [e107 CMS](https://e107.org) `chatbox_menu` plugin.

This is a **clean break, not a drop-in replacement.** It is intended for new installs only and shares no markup, class names, or upgrade path with the original `chatbox_menu` plugin.

---

## Status

🚧 **Work in progress.** The original plugin code has been forked verbatim from e107 core. The rewrite is being done file-by-file.    
---

## Why fork

The original `chatbox_menu` is a working, useful plugin with over a decade of accumulated HTML output. Over that time it has collected layered Bootstrap 3, 4, and 5 class names; inline styles; inline event handlers; and HTML built via PHP string concatenation. It works, but it is hard to theme, hard to extend, and hard to read.

Rather than continue patching it backwards-compatibly inside e107 core, this fork takes a clean break:

- Bootstrap 5 only — no fallback classes for older versions
- Stable, semantic class names that themes can target reliably
- Update code to e107 v2.4 standards
- HTML in template files, not PHP strings
- No inline styles
- No inline event handlers in attributes


The original plugin remains the right choice for existing sites that don't want to break their theme or migrate their data. This fork is for new installs that want clean markup from day one.

---
 

## Themes targeting this plugin

The plugin is theme-agnostic. Any theme can style it by targeting the `.chatbox-*` classes — see the files in `templates/` for the canonical DOM and class reference.
The class names and DOM structure are stable as of version 1.0.

---


## Contributing

Discussion and feedback welcome via GitHub issues and pull requests.

The rewrite is being done in stages, tracked in [`DEV_NOTES.md`](DEV_NOTES.md). That file also documents the conventions used (CSS, file layout, language constants, etc.) — worth a read before opening a PR.

Discussion of conventions is welcome before code is written for them.

---

## License

Same as e107 core: GNU General Public License v3.

This fork retains the original copyright notices from e107 Inc. (2008–2013) and adds the fork's own copyright for changes made after the fork point.

---

## Acknowledgements

This plugin is a fork of `chatbox_menu`, originally developed as part of [e107 CMS](https://e107.org) by e107 Inc. Thanks to the e107 team for over two decades of work on the CMS and its plugin ecosystem.
