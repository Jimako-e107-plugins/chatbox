# chatbox

A modernized fork of the [e107 CMS](https://e107.org) `chatbox_menu` plugin.

This is a **clean break, not a drop-in replacement.** It is intended for new installs only and shares no markup, class names, or upgrade path with the original `chatbox_menu` plugin.

---

## Status

🚧 **Work in progress.** The original plugin code has been forked verbatim from e107 core. The rewrite is being done file-by-file. See [Goals](#goals) below for the planned scope.

---

## Why fork

The original `chatbox_menu` is a working, useful plugin with over a decade of accumulated HTML output. Over that time it has collected layered Bootstrap 3, 4, and 5 class names; inline styles; inline event handlers; and HTML built via PHP string concatenation. It works, but it is hard to theme, hard to extend, and hard to read.

Rather than continue patching it backwards-compatibly inside e107 core, this fork takes a clean break:

- Bootstrap 5 only — no fallback classes for older versions
- Stable, semantic class names that themes can target reliably
- HTML in template files, not PHP strings
- No inline styles
- No inline event handlers in attributes

The original plugin remains the right choice for existing sites that don't want to break their theme or migrate their data. This fork is for new installs that want clean markup from day one.

---

## Goals

### 1. Bootstrap 5–native markup

Strip all Bootstrap 3/4 fallback classes. The original plugin emits things like:

```html
<input class="btn btn-default btn-secondary button" ...>
<small class="label label-default pull-right float-right">
<div class="control-group form-group">
```

After the rewrite, only Bootstrap 5 classes appear. No `.btn-default`, `.pull-left`, `.label`, `.well`, `.media`, `.control-group`.

### 2. Stable, semantic class hooks for theming

Every themable element gets a class prefixed with the plugin name, so themes can target chatbox-specific elements without depending on Bootstrap class meanings (which vary by plugin author and Bootstrap version):

```html
<div class="chatbox-message">
  <span class="chatbox-username">…</span>
  <time class="chatbox-timestamp">…</time>
  <div class="chatbox-body">…</div>
</div>
```

Bootstrap utility classes (`.d-flex`, `.mb-2`, `.text-center`) may still be used for layout where appropriate. The rule is: **structure from Bootstrap, identity from the plugin.**

### 3. HTML lives in templates, not PHP strings

The original plugin builds the chatbox form via inline string concatenation in `chatbox_menu.php`:

```php
$texta .= "<div class='control-group form-group' id='chatbox-input-block'>";
// ...80 more lines of string-concatenated HTML...
```

The rewrite moves this into the e107 template system. Templates live in the `templates/` folder, one file per surface (the sidebar menu uses `chatbox_menu_template.php`, the standalone page uses `chatbox_template.php`), so the markup is editable as HTML rather than PHP, and so theme authors can override it without touching plugin code.

### 4. No inline styles, no inline event handlers

`style="..."` and `onclick="..."` move into CSS classes and a separate JavaScript file respectively. This makes the plugin themable, accessible, and CSP-compatible.

### 5. Preserve all functionality

This is a refactor, not a feature change. The fork must keep working:

- AJAX message posting (`cb_layer === 2`)
- Flood protection
- Moderator controls (delete, block, unblock)
- Emote picker
- Anonymous posting (when enabled in prefs)
- Fixed-height layer mode (`cb_layer === 1`)
- Cache integration
- Event triggers (`cboxpost`, `user_chatbox_post_created`)

---

## File structure

```
chatbox/
├── chatbox_menu.php                # sidebar widget renderer (e107 menu file)
├── chat.php                        # standalone page (full chat view + moderation)
├── admin_chatbox.php               # admin settings page
├── chatbox_shortcodes.php          # shortcode resolvers ({CB_USERNAME}, etc.)
├── chatbox_sql.php                 # database schema
├── chatbox.js                      # client-side behavior
├── templates/
│   ├── chatbox_menu_template.php   # sidebar widget markup
│   └── chatbox_template.php        # standalone page markup
├── languages/
│   └── English/
│       ├── English_front.php       # front-end strings
│       ├── English_admin.php       # admin strings
│       └── English_global.php      # globally-loaded strings
├── e_*.php                         # e107 integration hooks (notify, search, etc.)
├── plugin.xml
└── README.md
```

A theme author who wants to change markup edits the relevant file in `templates/`. A developer who wants to change behavior edits `chatbox_menu.php` or `chat.php`. A theme author who wants to change appearance writes CSS targeting `.chatbox-*` classes.

For a deeper walkthrough of the file layout and conventions, see [`DEV_NOTES.md`](DEV_NOTES.md).

---

## Themes targeting this plugin

The plugin is theme-agnostic. Any theme can style it by targeting the `.chatbox-*` classes — see the files in `templates/` for the canonical DOM and class reference. A reference theme integration exists as part of the **efiction** theme, but the plugin itself does not depend on any particular theme being installed.

The class names and DOM structure are stable as of version 1.0.

---

## Relationship to efiction

This plugin is part of a longer-term project to build an **efiction** distribution: a curated bundle of e107 theme + plugins that ships as a coherent whole for new installs.

The plugin itself is not branded as efiction and carries no efiction-specific prefixes, classes, or assumptions. It is meant to be useful to the wider e107 community, including users who do not run the efiction theme.

Other plugins in the efiction distribution will follow the same conventions (plugin-name CSS prefix, template-based HTML, Bootstrap 5 only, etc.) so that themes targeting one plugin in the family will recognize patterns from the others.

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
