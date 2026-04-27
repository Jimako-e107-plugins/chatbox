# DEV_NOTES — chatbox plugin

> ⚠️ **UNDER CONSTRUCTION — NOT FINAL**
>
> This document is a working snapshot of architectural decisions, kept
> in the public repo so that the same version is available across
> environments. **Treat every section as provisional.** Items can and
> will change as new information surfaces during the rewrite — including
> the high-level goals themselves. Do not cite anything here as a settled
> decision without checking the relevant issue or PR in this repo.
>
> Sections marked **(under revision)** are known to be incomplete or
> likely to change soon.

---

A record of architectural decisions and the reasoning behind them, kept
so that context survives even if chat history is lost.

Repo: https://github.com/Jimako-e107-plugins/chatbox
Fork point: the original e107 core plugin `chatbox_menu` (renamed to
`chatbox`).

Last updated: 2026-04-27 (after JS extraction PR — menu surface).

---

## 1. Rewrite goals (high-level, under revision)

The plugin is being rewritten along three parallel goals that **never
get mixed** into a single PR:

1. **HTML lives in templates, not PHP strings.** The markup the plugin
   emits should be editable as HTML so that themes can override it
   without touching plugin code.
2. **Classes separated by concern.** `chatbox-*` classes as stable theme
   hooks; Bootstrap 5 utility classes for layout inside templates only;
   no dead BS3/BS4 leftovers anywhere.
3. **Behavior separated from markup.** JavaScript lives in `chatbox.js`,
   not in inline `onclick=""` attributes.

Each goal is a separate theme (= separate issue / PR).

> **These goals are not fixed.** A goal may be revised or dropped if a
> different change makes it unnecessary, or if work in progress reveals
> that the original framing was wrong. When that happens, the change
> is recorded here with the reasoning.

---

## 2. Current file layout (under revision)

```
chatbox/
├── chatbox_menu.php                # sidebar widget renderer (e107 menu file)
├── chat.php                        # standalone page (full chat view + moderation)
├── chatbox_shortcodes.php          # shortcode resolvers ({CB_USERNAME}, etc.)
├── chatbox.js                      # extracted from inline event handlers
├── templates/
│   ├── chatbox_menu_template.php   # sidebar widget markup
│   └── chatbox_template.php        # standalone page markup
├── languages/
│   └── English/
│       ├── English_front.php      # constants used in front-end output (chatbox_menu.php, chat.php)
│       ├── English_admin.php      # constants used in admin (admin_chatbox.php)
│       └── English_global.php     # constants autoloaded for every request
├── plugin.xml
└── README.md
```

**Note on `languages/`:** files use the e107 nested layout
(`languages/<Language>/<Language>_<type>.php`) and the v2.4+ array
return format. Three types are used:

- `English_global.php` — autoloaded by core via the `lan_global_list`
  pref (`class2.php`). Holds three categories of constants, all of
  which need to be loaded for every request:

  1. **Plugin descriptors** (`LAN_PLUGIN_CHATBOX_MENU_NAME`,
     `LAN_PLUGIN_CHATBOX_MENU_DESCRIPTION`,
     `LAN_PLUGIN_CHATBOX_MENU_POSTS`). Schema is fixed:
     `LAN_PLUGIN_{FOLDER}_*`. e107 core looks these up via
     `e107::getPlugLan('chatbox', '<type>')`, which builds the
     constant name by concatenating the prefix and uppercasing the
     arguments — so the name has to match exactly.
  2. **Admin log labels** (`LAN_AL_CHBLAN_02`). When admin code calls
     `e107::getLog()->add('CHBLAN_02', …)`, core resolves the event
     code to a constant named `LAN_AL_<event_code>` at log render
     time. Render happens on `admin_log.php`, which is outside the
     plugin's own entry points, so the constant must be available
     without an explicit `plugLan()` call.
  3. **Notify trigger labels** (`NT_LAN_CB_2`, `NT_LAN_CB_3`,
     `NT_LAN_CB_5`, `NT_LAN_CB_6`). Used in the `chatbox_notify`
     class config array inside `e_notify.php`. e107 loads
     `e_notify.php` from the notify subsystem (e.g. when admin opens
     the notify config page, or when an event triggers a
     notification) outside the plugin's front-end entry points, so
     the constants must be available without an explicit
     `plugLan()` call.

  Names in categories 2 and 3 do not follow `LAN_PLUGIN_{FOLDER}_*`,
  but they're held in place by the e107 conventions for those
  subsystems — `LAN_AL_*` for admin log, `NT_LAN_*` for notify.
  Renaming them would break their integration with core.
- `English_front.php` — loaded explicitly by `chatbox_menu.php` and
  `chat.php` via `e107::plugLan('chatbox', 'front', true)`.
- `English_admin.php` — loaded explicitly by `admin_chatbox.php` via
  `e107::plugLan('chatbox', 'admin', true)`.

Some constant names (numeric suffixes like `_L4`, `_100`) are legacy
and a candidate for renaming to descriptive names — that's a separate
theme, see Section 5.

### Key layout decisions

- **`chatbox_menu.php` keeps the `_menu` suffix.** An earlier draft
  considered renaming it to `chatbox.php`, but the `_menu` suffix is
  **required by e107** for the file to register as a menu file. Renaming
  would break the plugin.
- **`chat.php` keeps its name for URL compatibility.** Existing chatbox
  installations may have this URL linked from elsewhere. e107 imposes
  no naming constraint on regular plugin pages, so the name is purely
  historical.
- **Two template files, not one.** The original plan called for a single
  `chatbox_template.php`. We split it into `chatbox_menu_template.php`
  (sidebar) and `chatbox_template.php` (standalone page) because each
  surface has its own layout, and mixing them into one file makes the
  template hard to read.

---

## 3. Architectural decisions by theme (under revision)

### 3.1 Template system — wrapper and layout pattern

**Decision:** Follow the e107 comment plugin pattern —
`$CHATBOX_TEMPLATE['layout']` defines the surface composition
(`{CHATBOXFORM}{CHATBOX_LIST}` etc.), and individual subsections
(`['form']`, `['menu']`, `['list']`) are referenced from there as
shortcodes.

**Why:** The wrapper class belongs **inside the layout template**, as
the first class on the outer element. This is the canonical e107
pattern; the comment plugin actually has a bug where it omits this and
we don't want to repeat that mistake.

**Wrapper placement:** The `<div class="chatbox chatbox-menu">` wrapper
lives inside the template, not in PHP. PHP only renders what the
template tells it to. A theme that wants to remove or restructure the
wrapper edits the template; it doesn't have to fight against PHP-emitted
HTML around the template's output.

**Emote panel:** `r_emote()` is an e107 core function that returns HTML
we can't control from a template. Decision: wrap the call in a new
shortcode `sc_cb_emotes` so the template stays the single source of
truth for markup, even though it pushes one more HTML-returning
shortcode onto the list.

### 3.2 Three sources of HTML markup

The plugin emits HTML from three different places, and each requires a
different cleanup approach:

| Source | Where it lives | Treatment |
|---|---|---|
| **PHP strings** | `chatbox_menu.php` form block (~lines 143–224); `chatbox_shortcodes.php` mod controls / blocked badge / bullet | Worst — markup is interleaved with logic via `.=` concatenation. Extract into templates, or at minimum rewrite as cleaner PHP. |
| **Template files** | `templates/chatbox_menu_template.php`, `templates/chatbox_template.php` | Easiest — markup is already isolated, just rewrite. |
| **Shortcode return values** | `chatbox_shortcodes.php` — methods that return HTML (e.g. `<a href="...">user</a>` from `sc_cb_username`) | Medium — markup is in PHP, but each shortcode has a single responsibility, so it can be addressed per-shortcode. |

**Per-shortcode HTML rule:** It's normal for shortcodes to return HTML,
but the class on the outer element should always be passed as a
parameter (conventionally `class=`). Default class applies if the
parameter isn't supplied; user-supplied class adds to the default rather
than replacing it.

### 3.3 CSS class hooks — the `chatbox-*` convention

**Decision:** Every themable element carries a `chatbox-*` class.
Themes target those, not Bootstrap classes.

**Why:** Bootstrap class meanings vary by plugin author and Bootstrap
version. A theme that styles `.btn-primary` styles every primary button
in the entire site, which is wrong. A theme that styles
`.chatbox-submit` styles exactly the chatbox submit button.

**Standard hooks:**

- Template: `.chatbox-message-list`, `.chatbox-message`,
  `.chatbox-message-avatar`, `.chatbox-message-body`, `.chatbox-timestamp`
- Form: `.chatbox-input-block`, `.chatbox-nick`,
  `.chatbox-message-input`, `.chatbox-submit`, `.chatbox-emotes-toggle`,
  `.chatbox-emotes-panel`
- Auxiliary: `.chatbox-error`, `.chatbox-empty`, `.chatbox-login-hint`,
  `.chatbox-more-link`, `.chatbox-posts-block`, `.chatbox-scroll-layer`

**Class ordering:** `button btn btn-primary chatbox-submit` —
generic → Bootstrap-intent → plugin-specific. The universal `button`
override class follows the upstream e107 issue
[#5597](https://github.com/e107inc/e107/issues/5597) pattern.

### 3.4 Bootstrap 5 — what stays, what goes

**Removed BS3/BS4 leftovers (no effect under BS5):**
`media`, `media-list`, `media-body`, `media-object`, `media-left`,
`mr-3`, `unstyled`, `control-group`, `form-group`, `input-xlarge`,
`btn-default`, `well`.

**Removed e107 legacy classes:**
`tbox`, `mediumtext`, `smalltext`, `muted` (without the `text-` prefix),
and the bare `chatbox` class on inputs (which would have collided with
the plugin's root wrapper class).

**Kept BS5 utility classes — but only inside templates:**
`d-flex`, `flex-shrink-0`, `flex-grow-1`, `mb-2`, `mt-3`, `text-muted`.

The `.media` component was dropped in BS5 because it can be replicated
with utilities. `flex-shrink-0` on the avatar (so it doesn't shrink when
the message body is long) and `flex-grow-1` on the body (so it fills
remaining width) reproduce the BS3/BS4 media object behavior in plain
BS5.

**The convention:** structure/layout from BS5 utilities **in templates
only**; identity from `.chatbox-*` everywhere. Utility classes inside
PHP-emitted HTML are a smell — they belong in the template.

### 3.5 Plugin preferences — core vs. plugin namespace

**Current state (legacy, anti-pattern):** The plugin writes its own
preferences directly to the e107 **core** config namespace via
`e107::getConfig('core')->setPref($temp)->save(false)` (in
`admin_chatbox.php`). It then reads them back through the global `$pref`
variable. This was inherited from the original `chatbox_menu` core
plugin.

**Plugin-owned prefs that pollute the core namespace:**
`chatbox_posts`, `cb_mod`, `cb_layer`, `cb_layer_height`, `cb_emote`.

**Genuinely core prefs that the plugin reads (these stay where they are):**
`anon_post`, `user_reg`, `smiley_activate`.

**Decision:** Migrate plugin-owned prefs to
`e107::getPlugConfig('chatbox')` in a separate, dedicated issue. The
migration needs a one-time upgrade hook that:

1. Reads the legacy keys from core prefs.
2. Writes them to plugin prefs.
3. Removes them from core prefs.
4. Saves both.

**Why a separate issue:** Architectural change touching three files plus
a data migration. Not to be folded into a markup or behavior PR.

**Out of scope for the migration:** Renaming the prefs themselves
(dropping the `cb_` prefix once they're in their own namespace) — would
make the migration code messier. Renames, if desired, come as a
follow-up after the namespace move lands.

### 3.6 Notify event naming

**Decision:** Renamed the `cboxpost` event to
`user_chatbox_post_created` (e107 naming convention:
`<scope>_<plugin>_<action>` with past-tense verb).

**Why the `user_` prefix:** Distinguishes user-triggered events from
admin-triggered ones. Forum thread creation, by analogy, would be
`user_forum_thread_created` (vs. an admin-driven moderation event).

**Migration footnote:** Existing installs that had `cboxpost`
configured in `notify_prefs` will end up with a dead entry pointing at
the renamed event. The dead entry is harmless (nothing triggers
`cboxpost` anymore) but it's no longer configurable through the admin
UI. Users upgrading should either delete the dead `cboxpost` key
manually, or accept that the new event registers cleanly on next visit
to the admin Notify page and ignore the dead entry. Fresh installs are
unaffected.

**Related upstream issue:** e107 core should not fatal when a notify
handler points at a missing class. Filed separately at e107inc/e107.
This plugin's rename does not depend on the core fix.

### 3.7 Legacy `$CHATBOXSTYLE` removal

**Removed:** The legacy `$CHATBOXSTYLE` global branch in
`chatbox_menu.php` that allowed a theme to set a string-template
override with `{USERNAME}`/`{MESSAGE}`/`{TIMEDATE}` placeholders.

**Why:** It was a 2010-era theming mechanism. The modern e107 template
system (`e107::getTemplate('chatbox', 'chatbox_menu', 'menu')`) is the
replacement, and a theme that wants to override the markup now does so
via the standard template override path — not via a global string
variable.

### 3.8 Legacy `global $e_event` removal

**Removed:** `global $e_event;` and the deprecated
`$e_event->trigger('cboxpost', $edata_cb);` line.

**Why:** The modern accessor `e107::getEvent()->trigger(...)` was
already used on the next line, so the older global was redundant. The
deprecated `cboxpost` event was being fired alongside the new
`user_chatbox_post_created` purely as a transitional measure; it has
now been removed.

**Pattern to watch for:** Any other `global $e_event;` declarations
elsewhere in the codebase are legacy and should be replaced by
`e107::getEvent()`. Track separately if found.

### 3.9 JS architecture — behavior layer

**Decision:** Inline JS handlers move out of `chatbox_menu.php` into a
single `chatbox.js`, registered as an `e107.behaviors.chatboxMenu`
attach handler and loaded via `e107::js('chatbox', 'chatbox.js', 'jquery')`.

**Why behaviors and not plain `$(document).ready`:** behaviors give a
defined hook for AJAX-replaced content via `e107.attachBehaviors()`,
they're the e107 idiomatic pattern, and `.once()` makes double-binding
guards trivial.

**Decision: every listener is delegated from `document`.**

In AJAX mode (`cb_layer === 2`), submitting the form makes the legacy
`sendInfo()` core helper replace the entire contents of
`#chatbox_posts` with the server response — and the response includes
a fresh copy of the whole form. Listeners bound directly to the form
or its children would die with the old DOM, and `sendInfo()` does
**not** call `e107.attachBehaviors()` on the new content, so a
re-attach won't run on its own. Document-delegated listeners survive
every AJAX swap with no re-bind.

This shaped two follow-on conventions:

- The `.once('chatbox-menu')` marker is anchored on `document`, not on
  any element inside the form. Registration runs once per page load.
- `attach(context, settings)` doesn't iterate `context` for these
  listeners — `document` is the only target, and it's stable.

**Decision: legacy core helpers stay.**

`storeCaret`, `addtext`, `sendInfo`, `expandit`, and the click handler
injected by `r_emote()` are all e107 core code. The behavior **calls**
them, doesn't replace them. Defensive
`typeof window.X === 'function'` guards everywhere so the behavior
fails gracefully if `e_jslib` is disabled.

**Why not modernize:** option 2 from issue #7 (replace
`storeCaret`/`addtext` with `selectionStart`/`setRangeText`) was
considered. Rejected for this PR because the modernization would also
require replacing the click handler injected by `r_emote()` — which is
a core function emitting both HTML and a jQuery click binding in one
call. Touching that expands scope from "extract local handlers" to
"reimplement the emote panel from scratch." Tracked as a separate
theme; may end up as an upstream e107 PR.

**Decision: `data-*` attributes bridge PHP to JS.**

Where the inline handler used to embed config (URLs, panel IDs) into
`onclick="..."`, the PHP side now writes that config to `data-*`
attributes on the same element, and the behavior reads it back:

| Inline (before) | Data attributes (after) |
|---|---|
| `onclick="javascript:sendInfo(URL, 'chatbox_posts', this.form);"` | `data-chatbox-ajax='1' data-chatbox-ajax-url='URL' data-chatbox-ajax-target='chatbox_posts'` |
| `onclick="expandit('emote')"` | `data-chatbox-emote-toggle='emote'` |

The behavior is not coupled to literal IDs like `'emote'` or
`'chatbox_posts'` — those stay PHP/template concerns. The behavior
just reads what the markup tells it.

**Bug fixes folded into the extraction:**

1. **Issue #7 (emote insert on first interaction).** Pre-existing.
   The emote-toggle handler now calls `textarea.focus()` after
   opening the panel; the focus listener calls `storeCaret`, priming
   the caret position. Document-delegated workaround from the
   earlier fix-#7 PR has been removed — its job is now done by the
   unified handler.
2. **HTML5 `required` bypassed in AJAX mode.** Pre-existing in the
   inline-`onclick` code. `event.preventDefault()` on the submit
   button skipped native validation; behavior now calls
   `form.checkValidity()` / `form.reportValidity()` before
   `sendInfo()`.

**Scope limit:** menu surface only. `chat.php` keeps its inline
handlers for now and is handled in a follow-up PR per DEV_NOTES §4.3.

---

## 4. Working conventions

### 4.1 Issue / PR strategy

- **Issues are grouped by theme**, not by file or by step. One theme =
  one issue. Example themes: "template system split", "menu markup
  cleanup", "shortcode HTML extraction", "JS extraction", "plugin prefs
  migration".
- **Every PR references an issue.** Decisions stay documented for future
  reference. This is especially valuable when applying the same patterns
  to other plugins later.
- **Issues, PRs, and code comments are in English.** Discussion in chat
  may be in Slovak.

### 4.2 Phased work within a theme

For markup-related themes, work in three phases:

1. **Inventory.** Catalog every HTML element in the affected surface —
   tag, current classes, inline styles, inline event handlers, source
   (template / PHP string / shortcode return), whether a theme hook
   exists. No proposed changes yet.
2. **Design.** Write the target HTML by hand — Bootstrap 5,
   `chatbox-*` classes, no inline styles, no `onclick`. Discuss as HTML
   before any PHP is written.
3. **Implementation.** One PR per logical unit.

### 4.3 Surface-by-surface, not file-by-file

Two surfaces: **menu** (sidebar widget) and **page** (standalone view).
Work one surface at a time, end to end, before starting the other.
Menu first because it's smaller and a good sandbox; page inherits the
patterns once menu is settled.

Shortcodes are shared between surfaces — handle them during the menu
pass, so that page work inherits already-clean shortcodes.

### 4.4 Minimal-diff discipline

- Don't reorder lines unless the reorder is the point of the change.
- Preserve indentation, whitespace, `PHP_EOL` conventions of the
  surrounding code.
- Don't touch IDs, JS function names, or behavior in a "markup" PR.
- Inline styles and inline event handlers are **explicitly out of scope**
  for markup PRs — they are tracked separately.

### 4.5 Out-of-scope discipline

When a PR touches one theme, related issues that come to mind during
the work get **filed**, not folded in. The "Out of scope" section at
the bottom of every PR description lists what was deliberately not
touched and links to the tracking issue (or notes "tracked separately"
if not yet filed).

---

## 5. Open / pending themes

These are filed (or to be filed) as separate issues:

- **Plugin prefs migration** — move `chatbox_posts`, `cb_mod`,
  `cb_layer`, `cb_layer_height`, `cb_emote` from core config to
  `e107::getPlugConfig('chatbox')`. Includes one-time upgrade hook.
- **Menu markup cleanup** — apply the `chatbox-*` class hook convention,
  remove BS3/BS4 leftovers, address the form block (currently a PHP
  string).
- **Page markup cleanup** — same, after menu is done.
- **Shortcode HTML extraction** — accept `class=` parameter on every
  shortcode that returns HTML; add default classes; standardize the
  pattern.
- **JS extraction — page surface** — apply the same behavior pattern
  to `chat.php` (inline handlers there have not been audited yet).
  Menu surface landed in [PR ref] / closes the JS-extraction-menu
  issue.
- **Modernize legacy caret/text helpers** — replace `storeCaret` /
  `addtext` calls with native `textarea.selectionStart` /
  `setRangeText`. Not contained to the plugin: the click handler
  injected by `r_emote()` in e107 core also calls `addtext`, so a
  full modernization either replaces the emote panel HTML
  (plugin-side workaround) or fixes `r_emote` upstream (preferred).
  Option 2 from issue #7's diagnosis.
- **Inline-style cleanup** — extract `style="..."` attributes to CSS.
- **Notify message body cleanup** — `NT_LAN_CB_*` constants and the HTML
  inside the notify message body.
- **Legacy globals audit** — find and replace any remaining
  `global $e_event;` (and similar legacy globals) with modern
  `e107::get*()` accessors.
- **Language constant rename** — rename numeric / opaque constants
  (`LAN_CHATBOX_100`, `CHATBOX_L4`, `CHATBOX_L11`–`L14`) to descriptive
  names (`LAN_CHATBOX_PLACEHOLDER`, `LAN_CHATBOX_SUBMIT`, etc.). Touches
  language files and every reference site; deliberately not bundled with
  markup PRs.
- **`e_list.php` LAN dependency bug** — references `CHATBOX_L6` without
  loading `English_front.php`, and references `LIST_CHATBOX_2` which is
  not defined anywhere in the plugin.
- **Dead constants cleanup** — `LAN_AL_CHBLAN_01/03/04/05` and
  `NT_LAN_CB_1` are defined in `English_global.php` but unused. Audit
  and remove. Several `CHATBOX_L*` and `CHBLAN_*` may also be unused —
  audit at the same time.

Order is roughly the order above, but not strict — small cleanup tasks
are good warm-ups between larger themes.

---

## 6. Things to read before resuming

If chat history is lost, read these in order to rebuild context:

1. The repo `README.md` — contains the rewrite goals, file structure,
   and theming guidance.
2. This file (`DEV_NOTES.md`) — architectural decisions and rationale.
3. Closed issues and merged PRs in the repo — the actual decision trail.
4. Upstream e107 issue
   [e107inc/e107#5597](https://github.com/e107inc/e107/issues/5597) —
   context for the universal `button` override class pattern.
