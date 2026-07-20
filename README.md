# MFSEssentials — FreeScout Editor Upgrade for Replies and Notes Module

**Module alias:** `mfsessentials`
**Version:** 1.1.1
**GitHub:** https://github.com/ManagedFreeScout/mfsessentials
**Namespace:** `Modules\MFSEssentials`

Standalone module, unrelated to other Managed FreeScout modules, like CfsAssist (AI features) or MSTeamsFS (Teams
SSO). No licensing, no settings page. On the GitHub auto-update pipeline as of v1.1.1
(see Distribution section below).

---
## Summary - What it does

😊 **Emoji & special character picker**
A toolbar button in both the Reply and Note editors that lets you insert emoji and special characters (currency symbols, arrows, typographic dashes, and more) directly into your text — works the same whether you're writing an internal note or a reply that goes straight to a customer.

👍 **Reactions on internal Notes**
React to a colleague's internal note the way you would in Slack or Teams — 👍 ✅ 👀 🎉. See who reacted by hovering over a reaction. Reactions only ever appear on internal Notes, never on customer-facing replies.

🔧 **Fixed: "Code" formatting no longer breaks the layout**
FreeScout's built-in "Code" text style previously forced a horizontal scrollbar on long lines instead of wrapping (a known, yet unresolved FreeScout issue, see below Part 0). Fixed.

**Some screenshots:**

<img width="1328" height="303" alt="image" src="https://github.com/user-attachments/assets/8125a498-360e-48a0-8cbc-781868f3f66b" />

<img width="1203" height="282" alt="image" src="https://github.com/user-attachments/assets/cbdf58b7-56bd-4a0c-af53-dac6b3d841ba" />

<img width="1160" height="313" alt="image" src="https://github.com/user-attachments/assets/128de134-91a3-44c0-97ca-e995621c21f5" />

## How to Install
Download the latest mfsessentials.zip from the Releases page.
Using FTP, SFTP, or your hosting control panel's file manager, upload and extract the zip into your FreeScout installation's Modules/ folder, so the files land at Modules/MFSEssentials/ (not nested inside another MFSEssentials/ folder).
In FreeScout, go to Manage → Modules, find MFSEssentials, and click Activate.
That's it — no configuration, no license key, no settings page. The emoji/symbol button appears immediately in your Reply and Note editors, and the reaction bar appears on internal Notes.
Updates

Once installed, FreeScout will automatically show an "Update available" notice on the Manage → Modules page whenever a new version is released — just click Update Now. No manual re-upload needed for future versions.


## What's in v1

### Part 0 — Code-block wrap fix
Summernote's "Code" style option wraps content in a plain `<pre>` tag (confirmed against
the exact vendored `summernote.js` in the 1.8.223 install: `styleTags` default array
includes `'pre'`, and `lang.style.pre = 'Code'`). Neither the live editor
(`.note-editable`) nor the rendered conversation (`.thread-body`, confirmed in
`resources/views/conversations/partials/thread.blade.php`) had any wrapping rule for it,
so long unbroken lines force horizontal scroll instead of wrapping
(https://github.com/freescout-help-desk/freescout/issues/5167). Fixed with a CSS rule in
`Public/css/module.css` targeting both selectors.

### Part 1 — Emoji / Special Character button
Toolbar button + dropdown panel (Emoji / Symbols tabs) in both the Reply and Note
Summernote editors. Registered via plain DOM injection next to an existing
`.note-btn-group`, mirroring CfsAssist's `Public/js/module.js` ReplAI button exactly
(same technique: anchor + `insertAdjacentElement`, `MutationObserver` re-attempt because
FreeScout re-renders the toolbar on conversation navigation, class-based
double-injection guard). This is **not** Summernote's real plugin-registration API
(`$.extend($.summernote.plugins, {...})`) even though FreeScout does bundle one working
example of that API
(`public/js/summernote/plugin/specialchars/summernote-ext-specialchars.js`, not wired
into either configured toolbar) — CfsAssist doesn't use it either, so this module doesn't
either, per instruction to mirror the actual established pattern.

Insertion uses `document.execCommand('insertText', false, char)` on the currently
focused `.note-editable`, with `mousedown` `preventDefault()` on the button/panel to keep
that focus/selection from being lost when the user interacts with the picker — chosen
over Summernote's own `.summernote('insertText', ...)` jQuery command because that API
requires a reference to the original element Summernote was initialized on, which isn't
reliably discoverable from a DOM-injected button generic enough to work in both forms.

### Part 2 — Note reactions
Slack/Teams-style reaction bar (👍 ✅ 👀 🎉) under Notes only. New table
`mfsessentials_thread_reactions` (migration mirrors MSTeamsFS's own nWidart migration
pattern). Rendered server-side via the `thread.meta` Eventy hook — real counts already
populated on first page load, no flash-of-empty-state (same hook/signature confirmed in
`NOTES_REACTIONS_DISCOVERY.md`). Toggling posts to `POST /mfsessentials/reactions/toggle`
(`web`+`auth` middleware), which re-validates `isNote()` server-side (403 otherwise —
reactions must never be possible on customer-facing threads, not just hidden
client-side) and validates the emoji against a fixed server-side allow-list
(`ThreadReaction::ALLOWED_EMOJI`) regardless of what the client posts. Hovering a
reaction with count > 0 shows a native browser tooltip listing who reacted.

**Known collation gotcha (fixed in v1.1.0):** the `emoji` column must stay
`utf8mb4_bin`. FreeScout's table-wide default (`utf8mb4_unicode_ci`) has no real
collation weights above U+FFFF and treats all supplementary-plane emoji as equal under
`=` — see the `2026_07_21_...` migration for the full story and hard evidence. Never
"fix" this by changing the column back to the table-wide default collation.

## Files

```
MFSEssentials/
├── module.json                                Module manifest — no licensing, no auto-update fields
├── composer.json
├── start.php                                   Loads routes
├── Providers/MFSEssentialsServiceProvider.php  stylesheets/javascripts registration, migrations, thread.meta hook
├── Http/
│   ├── routes.php                              POST /mfsessentials/reactions/toggle
│   └── Controllers/ReactionsController.php
├── Entities/ThreadReaction.php                 ALLOWED_EMOJI, toggle(), summaryFor()
├── Database/Migrations/..._create_mfsessentials_thread_reactions_table.php
├── Public/
│   ├── css/module.css                          Part 0 fix + Part 1 picker + Part 2 bar styling
│   └── js/
│       ├── mfsessentials-editor.js             Part 1 — emoji/symbol picker
│       └── mfsessentials-reactions.js          Part 2 — reaction bar click handling
└── Resources/views/partials/reactions-bar.blade.php
```

## Distribution

On the GitHub-based auto-update pipeline as of v1.1.1, same mechanism as MSTeamsFS:
- Repo: https://github.com/ManagedFreeScout/mfsessentials (public, one repo per module)
- `module.json` carries `latestVersionUrl` (points to `version.txt` on `main`) and
  `latestVersionZipUrl` (points to the latest GitHub Release's `mfsessentials.zip` asset)
- Working copy stays at `/var/www/modules-dev/MFSEssentials/`; the GitHub-tracked copy is
  a separate folder at `/var/www/modules-dev/github-mfsessentials/` (copied over, version
  bumped, committed, pushed — working copy is never git-tracked directly)
- Release zips are always named exactly `mfsessentials.zip` regardless of version, and
  must never contain a `.git/` directory (FreeScout can't write into it on extract — the
  hosting user can't write to `/Modules/`, causes a permission error)

**Confirmed from source (2026-07-21), not assumed:** FreeScout's Manage → Modules page
(`app/Http/Controllers/ModulesController.php`) calls `\Module::clearCache()` immediately
before reading each installed module's own `version`/`latestVersionUrl`/`latestVersionZipUrl`
— i.e. it re-reads the module's actual `module.json` off disk fresh on every single page
load, then does a live Guzzle GET to `latestVersionUrl` and compares versions right there.
This is not a one-time install-time registration and nothing is cached indefinitely — once
`module.json` on the live install carries these fields, the pipeline is active immediately,
no reinstall/reactivate needed.

## Session log

| Date | What was done | What's next |
|---|---|---|
| 2026-07-20 | v1.0.0 built: all three parts, packaged to /tmp/MFSEssentials_v1.0.0.zip on the VPS. All PHP files linted clean (`php -l`, PHP 8.3.6 on the VPS — the live install runs 8.3.30). Both JS files linted clean (`node --check`). Not yet installed or tested on the live FreeScout install — no SSH/DirectAdmin access to support.stackpros.io exists from this session (same limitation FreeScout_Development_Notes.md and NOTES_REACTIONS_DISCOVERY.md both already flag). | Manual zip-upload-and-test cycle per FreeScout_Development_Notes.md §5, then run through the test steps for all three parts. |
| 2026-07-20 | v1.0.1 fix: reactions toggle was throwing a live 419 (CSRF mismatch) — confirmed via live debugging that `mfsessentials-reactions.js`'s direct `jQuery.post()` call bypassed `fsAjax()`, which is what actually (re-)primes `$.ajaxSetup`'s X-CSRF-TOKEN header on every call (main.js:892) — it's not a passive one-time page-load setup. Fixed by routing the jQuery branch through `fsAjax(data, url, success_callback, no_loader)` instead, confirmed against main.js source (signature, always-POST, JSON auto-parse, `no_loader=true` suppresses the global spinner). fetch() fallback branch untouched. Repackaged as /tmp/MFSEssentials_v1.0.1.zip. | Live click-test — no 419, bar updates, persists on refresh, toggle-off works. Re-confirm Parts 0/1 unaffected (only the reactions JS changed). |
| 2026-07-21 | v1.1.0, with direct SSH access to support.stackpros.io for the first time (key configured at `~/.ssh/stackpros_freescout` on the VPS). **Root cause found for the emoji-collision bug**: `utf8mb4_unicode_ci` (FreeScout's table-wide default collation) has no real collation weights for supplementary-plane codepoints (every emoji here is above U+FFFF) and treats 👍/👀/🎉 as equal under `=` — confirmed with hard evidence (`WEIGHT_STRING()`/direct equality queries against the live DB, before and after). Fixed with a new migration (`2026_07_21_000000_change_mfsessentials_emoji_column_collation`) changing just the `emoji` column to `utf8mb4_bin` (byte-exact — the semantically correct choice for an identity-match column), run live via `php artisan module:migrate MFSEssentials --force`. Verified at both the raw-SQL and application layers (`ThreadReaction::toggle()`/`summaryFor()` exercised directly via a bootstrapped standalone script, since `tinker` doesn't work on this hosting) — all four emoji now count/toggle independently, per-user isolation confirmed with two real users. Removed the leftover debug `\Log::info()` line and `.bak` file from a prior live debugging session. **Also added**: reactor-name tooltips — `summaryFor()` now also returns reactor names per emoji (one extra `whereIn` query, not N+1), the `thread.meta` view renders a `title="Name1, Name2"` attribute (native tooltip, no attribute at all when empty), and `applyResult()` keeps it live after a click without a refresh. Verified via a standalone Blade-render test (real HTML output inspected) and the same app-layer script (two real users' names resolved correctly per emoji). Only the two debugging-session test rows remain in the live table (thread_id 9448/9449) — left alone, not real data, flagged rather than deleted unprompted. | The two things that still need an actual browser — (1) click the reaction bar in the real Teams/FreeScout UI and confirm the hover tooltip shows names, (2) general regression click-through of the bar now that three files changed. |
| 2026-07-21 | v1.1.1 fix: reactor-name tooltips were rendering with a plain `title="..."` attribute, which never showed on hover in the live browser (confirmed: title present in DOM, hover events fired, no tooltip ever appeared). Root cause: FreeScout's own tooltips (confirmed live on e.g. the sidebar "Open Submenu" tooltip) all go through Bootstrap 3's `.tooltip()` plugin, not native browser tooltips — a plain `title` attribute alone does nothing here. Confirmed the exact mechanism directly against the live `public/js/main.js` and vendored `public/js/bootstrap.js` (v3.4.5): `initTooltip(selector)`/`initTooltips()` are FreeScout's own global init helpers (no single global "content changed" event re-triggers them — every feature that injects tooltipped content calls these itself after its own AJAX injection point, confirmed by finding all their call sites); Bootstrap's `init()` calls `fixTitle()` automatically (moves `title` → `data-original-title`, so shipping plain `title` from Blade is correct, must not pre-empty it); `getTitle()` re-reads `data-original-title` fresh on every show (no cache), so a content update after a click just needs that attribute updated directly; the plugin bridge reuses an already-initialized element's `bs.tooltip` instance rather than double-initializing, so calling `initTooltip()` repeatedly is safe. Fixed: view now emits `data-toggle="tooltip" data-placement="top"` alongside `title` (omitted entirely when there are no reactors, unchanged from before); JS now calls FreeScout's own `initTooltip()` on load, via a MutationObserver (bars can appear via FreeScout's own conversation-switching AJAX, same class of problem CfsAssist's button injection already solved), and after every `applyResult()`; `applyResult()` now adds/removes `data-toggle`/`data-placement` and updates `data-original-title` directly (or calls `.tooltip('destroy')`, Bootstrap 3's real method name, when a reaction's last reactor is removed) instead of just setting `title`. Collation fix and `ThreadReaction` logic untouched. Verified the new markup via a standalone Blade-render test (`data-toggle`/`data-placement`/`title` present and correctly formed for reactions with reactors, absent for empty ones) — visual hover behavior itself still needs a real browser. Repackaged as /tmp/MFSEssentials_v1.1.1.zip. | Hover a reaction with reactors in the live UI and confirm the Bootstrap tooltip now actually appears; also re-test after switching between two different conversations (not just page load) to confirm the MutationObserver path also works, not only the initial-render path. |
| 2026-07-21 | v1.1.1 GitHub pipeline setup: confirmed VPS working copy byte-identical to the live install (full recursive md5sum, not just version string) before proceeding. Added `latestVersionUrl`/`latestVersionZipUrl` to `module.json` (pushed to both the dev copy and the live install) and created `version.txt`. First-time repo setup: `/var/www/modules-dev/github-mfsessentials/` created, `.gitignore` copied from `github-msteamssso`'s (not invented fresh), committed and pushed to `github.com/ManagedFreeScout/mfsessentials` (root commit `716a945`, verified publicly reachable via unauthenticated `git ls-remote`). Release zip built and confirmed `.git`-free (caught and corrected one false-positive check first — `.gitignore` itself matches a naive `grep '\.git'`, had to check for `\.git/` specifically). Published GitHub Release `v1.1.1` and uploaded `mfsessentials.zip` via the REST API (no `gh` CLI on this VPS) — PAT read from `~/.credentials/mfstoken` only to construct the remote/Authorization header inline, never echoed/logged. Both auto-update URLs verified live (`version.txt` returns `1.1.1`; the zip URL 302s to the correct release asset). Confirmed from source exactly when/how often FreeScout reads these fields — see Distribution section above; no live-install reinstall needed, pipeline already active. | None outstanding from this session — genuinely done. Next real "next step" is whenever v1.1.2+ actually ships: bump both `module.json` files, update `version.txt`, commit to `github-mfsessentials`, rebuild the release zip, publish a new GitHub Release. |
