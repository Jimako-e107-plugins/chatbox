# DEV_NOTES — chatbox plugin

A record of architectural decisions and the reasoning behind them, kept
so that context survives even if chat history is lost.

Repo: https://github.com/Jimako-e107-plugins/chatbox
Fork point: the original e107 core plugin `chatbox_menu` (renamed to
`chatbox`).

Last updated: 2026-04-28 (rewrite phase closed — all four §1 goals
achieved; plugin is feature-complete and in maintenance mode).

---

## 1. Rewrite goals (high-level)

The plugin was rewritten along four parallel goals that were never
mixed into a single PR:

1. **Update code to e107 v2.4 standards.** The plugin was forked from
   e107 core where it had accumulated a decade of legacy patterns —
   prefs in the core namespace, `global $e_event`, `$CHATBOXSTYLE`
   string templating, language constants without the v2.4+ array
   return format, missing `e107::plugLan()` calls, etc. Bring all of
   this in line with what current e107 core expects from a 2.4-era
   plugin. **(achieved — see §3.5–§3.10.)**
2. **Classes separated by concern.** `chatbox-*` classes as stable
   theme hooks; Bootstrap 5 only (no BS3/BS4 leftovers); BS5 utility
   classes for layout where appropriate. **(achieved — see §3.3, §3.4.)**
   Every themable element has a `chatbox-*` hook, so themes can
   restyle the plugin entirely through CSS without touching plugin
   markup. The handful of inline `style="..."` attributes still in
   `chatbox_menu.php` are plugin defaults that themes override
   through normal CSS specificity; they're not blockers for theming.
3. **Behavior separated from markup.** JavaScript lives in `chatbox.js`,
   not in inline `onclick=""` attributes. **(achieved — see §3.9.)**
4. **HTML lives in templates, not PHP strings.** The post-list
   markup the plugin emits is in templates (both menu and page
   surfaces), so themes can override how individual posts render.
   **(achieved.)** The form block, error div, login hint, scroll-layer
   wrapper, and more-link in `chatbox_menu.php` were left as PHP
   strings: extracting them would have required the form to inherit
   conditional rendering for `cb_layer === 2` (AJAX) vs. plain mode,
   for anonymous-post mode, for the emote panel toggle — i.e. a
   non-trivial template language for what a theme rarely needs to
   restyle, since `chatbox-*` hooks already cover the theming case.
   The cost-benefit didn't justify it.

Each goal was a separate theme (= separate issue / PR).

---

## 2. File layout

```
chatbox/
├── chatbox_menu.php                # sidebar widget renderer (e107 menu file)
├── chat.php                        # standalone page (full chat view + moderation)
├── chatbox_shortcodes.php          # shortcode resolvers ({CB_USERNAME}, etc.)
├── chatbox.js                      # extracted from inline event handlers (see §3.9)
├── admin_chatbox.php               # admin preferences screen
├── chatbox_setup.php               # upgrade hooks (see §3.10)
├── chatbox_sql.php                 # install-time table schema
├── e_dashboard.php                 # admin dashboard widget integration
├── e_header.php                    # request-header hook (single-key pref read for CSS)
├── e_list.php                      # site-wide "list" addon integration
├── e_notify.php                    # notify subsystem integration (see §3.6)
├── e_rss.php                       # RSS feed integration
├── e_search.php                    # site-search integration
├── e_user.php                      # user-profile addon integration
├── images/                         # plugin icons (chatbox_16.png, chatbox_32.png, blocked.png)
├── templates/
│   ├── chatbox_menu_template.php   # sidebar widget markup
│   └── chatbox_template.php        # standalone page markup
├── languages/
│   └── English/
│       ├── English_front.php       # constants used in front-end output (chatbox_menu.php, chat.php)
│       ├── English_admin.php       # constants used in admin (admin_chatbox.php)
│       └── English_global.php      # constants autoloaded for every request
├── plugin.xml
```

**Note on `e_*.php` files:** these are e107 plugin-integration hooks
(addons), each a separate well-known filename that core scans for at
specific points in the request lifecycle. `e_header.php` runs on every
request before output starts; `e_notify.php` is loaded by the notify
subsystem; `e_user.php`, `e_search.php`, `e_rss.php`, `e_list.php`,
and `e_dashboard.php` are scanned by their respective subsystems when
they need to enumerate plugin contributions. None of them are entry
points the user navigates to — they're discovered and called by core.
They share concerns with the front-end and admin entry points (prefs,
language constants, shortcodes) but they're not all in the rewrite's
scope: the markup and behavior themes target only the surfaces that
actually emit the chat UI (`chatbox_menu.php`, `chat.php`, the two
template files, and `chatbox_shortcodes.php`).

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

## 3. Architectural decisions by theme

> **Status note.** Sections in this chapter record decisions, not
> progress. All four §1 goals shipped end-to-end on `main`:
>
> - §3.3 and §3.4 record goal #2 (class-hook convention, BS5-only).
> - §3.5–§3.10 record goal #1 (v2.4 standards modernization). §3.5
>   is the prefs-migration decision; §3.10 is its completion record.
> - §3.9 records goal #3 (behavior layer).
> - §3.1 and §3.2 record goal #4 (HTML in templates) — applied to
>   the post-list markup on both surfaces. The form block and
>   auxiliary divs in `chatbox_menu.php` were intentionally left as
>   PHP strings; see §1 goal #4 for the reasoning.

### 3.1 Template system — wrapper and layout pattern

**Note:**  This was postponed because main problem was solved by correct plugin classes.  Left here just for future plans. 

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

**Scope:** the menu PR was originally framed as the first of two
surface PRs (menu first, page next). Audit of `chat.php`,
`chatbox_shortcodes.php`, and the two template files turned up zero
inline JS handlers, zero `<script>` blocks, and zero direct calls to
the legacy core JS helpers (`storeCaret`, `addtext`, `sendInfo`,
`expandit`, `r_emote`). The page surface uses native form submission
for moderator actions; emote/caret/AJAX behavior only exists on the
menu surface. Goal #3 is therefore complete after the menu PR — no
follow-up surface PR is needed.

### 3.10 Plugin prefs migration — core → plugin namespace

**Issue:** [#11](https://github.com/Jimako-e107-plugins/chatbox/issues/11)

**Decision:** The six plugin-owned prefs (`chatbox_posts`, `cb_mod`,
`cb_layer`, `cb_layer_height`, `cb_emote`, `cb_user_addon`) move from
the e107 core config namespace to `e107::getPlugConfig('chatbox')`.
Three core prefs the plugin only reads (`anon_post`, `user_reg`,
`smiley_activate`, plus `menu_wordwrap` consumed by the message
shortcode) stay where they are.

**Why a `_setup.php` and not a one-off ad-hoc script:** e107 has a
documented upgrade-hook contract — core scans for `<plugin>_setup.php`
during the admin database-update flow and calls `upgrade_required()`
to decide whether to surface a notice and `upgrade_post()` to do the
work. Following the convention means the migration runs through the
same UI path admins already know, instead of a custom screen we'd have
to design and document.

**Why `migrateData()` and not hand-rolled read/write/delete:** the
helper on `e_core_pref` does all four steps of the original plan
(read legacy keys, write to plugin namespace, remove from core, save)
in one call, with the documented `if($newPrefs = ...->migrateData(...))`
idiom guarding against a no-op when none of the legacy keys exist.
Re-running the hook is therefore a no-op — important because
`upgrade_required()` could trigger again if an admin's session gets
weird, and we don't want the second run to clobber their saved
choices.

**Reproduce the docs idiom verbatim — no merging, no second core save.**
The first attempt at `upgrade_post()` did three things beyond the
documented idiom: merged `migrateData()`'s return against the
plugin-namespace prefs already loaded by core, cached the
`getPlugConfig` handle in a local, and called `e107::getConfig('core')->save(...)`
a second time after `migrateData()` had already saved core. Each was
intended as a defensive improvement; together they produced a silent
failure on real installs (#11): core was correctly cleaned of the
legacy keys, but the plugin pref row stayed at the seed defaults.
The merge was the killer — `array_merge($newPrefs, $existing)` lets
`$existing` win on key collision, and `$existing` was the seed values
just written by `<pluginPrefs>` from `plugin.xml`. The admin's actual
saved values from core got overwritten by defaults, after which
`setPref()`'s dirty-tracking saw no change and skipped the database
write. The shipped version follows the dev guide exactly: one chained
`getPlugConfig('chatbox')->setPref($newPrefs)->save(...)`, no merge,
no second save. The general rule worth carrying forward: when
documentation gives a specific idiom, reproduce it before adding
cleverness on top — defensive code that hasn't been tested against the
actual failure modes is just noise that hides real bugs.

**`<mainPrefs>` → `<pluginPrefs>` in `plugin.xml`:** the legacy
`<mainPrefs>` element seeds new installs into the **core** namespace,
which is precisely the bug we're fixing for old installs. After the
migration, fresh installs need to land directly in the plugin
namespace, so the element name must change too. Same set of seed
defaults; different destination.

**`cb_wordwrap` left in place:** declared in `<pluginPrefs>`, never
read anywhere in the plugin (the message shortcode reads core's
`menu_wordwrap`). Removing it would mix concerns; tracked under the
existing dead-prefs cleanup entry in §5.

**Rename deferred:** dropping the `cb_` prefix once the prefs are in
their own namespace remains explicitly out of scope, as originally
planned. Migration first, rename later.

**Read form: `e107::pref('chatbox')` everywhere it fits.** Reading the
whole array uses the static shortcut, with a local `$plugPref`
variable on entry-point files that need several keys
(`admin_chatbox.php`, `chatbox_menu.php`, `chat.php`) and a per-method
local in shortcodes (`chatbox_shortcodes.php`) and addons
(`e_user.php`). The longer `e107::getPlugConfig('chatbox')->...` form
is reserved for two cases where the static shortcut doesn't fit: the
write site in `admin_chatbox.php` (which needs to chain
`setPref(...)->save(...)`), and `e_header.php`'s single-key inline
read (which needs `->get('cb_layer')`).

**Bumped `plugin.xml` to version 1.1:** matches the e107 convention
that any change requiring an upgrade hook also bumps the version
number, so core's plugin-version comparison knows an upgrade
notification belongs on this row.

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

## 5. Known issues

The plugin is feature-complete. These are minor known issues that
were noticed during the rewrite but not fixed; they don't break the
plugin's primary function and are open for bug reports if anyone
hits them in practice:

- **`e_user.php` typo** — `e107::isInstalled('chatbox_menu')` checks
  the wrong folder name (should be `chatbox`). Result: the profile
  addon's chatbox-posts percentage always shows 0%.
- **`e_list.php` LAN dependency bug** — references `CHATBOX_L6`
  without loading `English_front.php`, and references
  `LIST_CHATBOX_2` which is not defined anywhere in the plugin.
- **`cb_wordwrap` dead pref** — declared in `<pluginPrefs>` but never
  read; the message shortcode reads core's `menu_wordwrap` instead.
  Harmless but confusing.

Cosmetic cleanup tasks that were considered and dropped: language
constant renames (`LAN_CHATBOX_100` → `LAN_CHATBOX_PLACEHOLDER` etc.),
plugin pref renames (dropping the `cb_` prefix), dead constants
sweep, inline-style extraction. None justify a diff at this point.

The `storeCaret` / `addtext` modernization noted in §3.9 belongs in
e107 core, not this plugin; if anyone takes it on, it's an upstream
PR.

---

## 6. Things to read

To rebuild context — for someone taking over maintenance, or for
resuming after a break:

1. The repo `README.md` — rewrite goals, file structure, theming
   guidance.
2. This file (`DEV_NOTES.md`) — architectural decisions and rationale.
3. Closed issues and merged PRs in the repo — the actual decision trail.
4. Upstream e107 issue
   [e107inc/e107#5597](https://github.com/e107inc/e107/issues/5597) —
   context for the universal `button` override class pattern.