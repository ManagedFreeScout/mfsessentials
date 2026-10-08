# MFSEssentials — FreeScout Editor Upgrade for Replies and Notes Module

**Module alias:** `mfsessentials`
**Version:** 1.2.0
**GitHub:** https://github.com/ManagedFreeScout/mfsessentials
**Namespace:** `Modules\MFSEssentials`

Standalone module, unrelated to other Managed FreeScout modules, like CfsAssist (AI features) or MSTeamsFS (Teams
SSO). From v1.3.0 it needs a licence (yearly subscription, €9, one FreeScout installation, all agents);
buy it on https://managedfreescout.com/mfsessentials/. On the GitHub auto-update pipeline as of v1.1.1
(see Distribution section below).

---
## Summary - What it does

😊 **Emoji & special character picker**
A toolbar button in both the Reply and Note editors that lets you insert emoji and special characters (currency symbols, arrows, typographic dashes, and more) directly into your text — works the same whether you're writing an internal note or a reply that goes straight to a customer.

👍 **Reactions on internal Notes**
React to a colleague's internal note the way you would in Slack or Teams — 👍 ✅ 👀 🎉. See who reacted by hovering over a reaction. Reactions only ever appear on internal Notes, never on customer-facing replies.

🙈 **Convert an email to an internal note (and back)**
Forwarded internal emails sometimes land in a conversation and would then show up for the client in the quoted history of later replies and in the customer portal. Choose **Convert to note** in that email's menu (top right) and it becomes an internal note: clients no longer see it anywhere, while its text, attachments and place in the conversation stay. A banner shows who converted it and who originally sent it, and **Convert back to email** undoes it. Once converted, you can also delete it like any of your own notes.

🔧 **Fixed: "Code" formatting no longer breaks the layout**
FreeScout's built-in "Code" text style previously forced a horizontal scrollbar on long lines instead of wrapping (a known, yet unresolved FreeScout issue, see below Part 0). Fixed.

**Some screenshots:**

<img width="1338" height="465" alt="image" src="https://github.com/user-attachments/assets/87c78d60-5c1a-47e9-9dcd-6ee29b1bdc2f" />

<img width="1203" height="282" alt="image" src="https://github.com/user-attachments/assets/cbdf58b7-56bd-4a0c-af53-dac6b3d841ba" />

<img width="1160" height="313" alt="image" src="https://github.com/user-attachments/assets/128de134-91a3-44c0-97ca-e995621c21f5" />

## How to Install
1. Download the latest mfsessentials.zip from the Releases page.
2. Using FTP, SFTP, or your hosting control panel's file manager, upload and extract the zip into your FreeScout installation's Modules/ folder, so the files land at Modules/MFSEssentials/ (not nested inside another MFSEssentials/ folder).
3. In FreeScout, go to Manage → Modules, find MFSEssentials, and click Activate.
4. Go to Manage → Settings → MFSEssentials, enter your licence key and click Activate licence. You find the key in your customer account (linked in the email you received after your purchase). All features switch on right away: Convert to note in each email's menu, the reaction bar on internal notes, the emoji/symbol button in the Reply and Note editors, and wrapping code blocks.

**Updates**
Once installed, FreeScout will automatically show an "Update available" notice on the Manage → Modules page whenever a new version is released — just click Update Now. No manual re-upload needed for future versions.

---
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

### Part 3 — Convert an email to a note (v1.2.0, board card freescout-modules #261)
Feature request: https://feedback.userreport.com/25a3cb5f-e4bd-4470-b6f3-79fcfaa8e90f/#idea/452667

Clients only ever see threads of type CUSTOMER and MESSAGE: the quoted history in reply
emails (`app/Jobs/SendReplyToCustomer.php`) and the End User Portal
(`Conversation::getReplies()`) both filter on those two types. So the thread's type is
changed to NOTE **in place**: text, attachments, date, order and message-id stay as they are.

- **Menu:** `thread.menu` hook (same as TicketTranslator's "Translate") adds **Convert to note**
  on published customer emails and agent replies, and **Convert back to email** on converted
  notes. Confirmation via core's `showModalConfirm`, request via `fsAjax()` to
  `POST /mfsessentials/thread/convert` (`Http/Controllers/ConvertController.php`), then reload.
- **Who:** every agent with access to the conversation's mailbox (admins always), same access
  rule as core (`userHasAccessToMailbox` + `ThreadPolicy::checkIsOnlyAssigned`).
- **Warnings in the confirmation:** agent replies were already sent and cannot be recalled;
  converting the first message of a conversation is allowed but flagged.
- **Author:** core renders a note's author from `created_by_user`, so the converting agent
  becomes the author (this also gives them core's Delete on it). Original type, author and
  `source_via` are kept in thread meta `mfse_converted`, shown in a banner
  (`thread.before_body`) and restored by Convert back.
- **Activity lines:** line items with action types 231 (to note) and 232 (back to email),
  texts via the `thread.action_text` filter. (`threads.action_type` is a tinyint; core uses
  1–11, SpamFilter 101, Workflows 201.)
- **Reports** that count customer messages count one fewer for a converted email.
- Code: `Services/ThreadConverter.php`, `Public/js/mfsessentials-convert.js`,
  `Resources/views/partials/convert-banner.blade.php`, banner CSS in `module.css`.

### Part 4 — Licence (v1.3.0, board card freescout-modules #194)
Decisions (Rutger, 8 Oct 2026): €9 per year (invAIse PROD activity 13 "MFS Essentials"); no licence,
an expired subscription, or no successful licence check for 14 days = **every** feature off, also
right after updating from a free version (no grace period).

- **No secrets in the module.** The module never calls invAIse itself: it posts only the licence
  key and this installation's domain (host of `app.url`) to the Managed FreeScout hub,
  `POST {hub_url}/modules/mfsessentials/license/{activate|validate|deactivate}`. The hub calls
  invAIse with its own credentials, limited to the MFSEssentials activity (`activity_ids`), and
  passes the answer through. Same idea as MSTeamsFS's `/teams/license` (msteamsfs cards #268, #285).
  `hub_url` defaults to https://app.managedfreescout.com (`MFSESSENTIALS_HUB_URL` to override).
- **Storage:** the shared `modules_licenses` table (`module_alias = mfsessentials`). This module has its
  own guarded migration for it, so it works without MSTeamsFS or StickyMenu installed.
- **Gate:** `LicenseService::isLicensed()` (local read, cached per request). Checked by the CSS/JS
  registration (so the code-block fix is off too), the reactions bar and the convert menu/banner
  hooks, and server-side in ReactionsController and ConvertController. The activity-line text
  filter stays on, so existing "converted" lines keep their wording.
- **Checks:** activate is followed by a validate (invAIse's activate answer has no expiry date);
  re-validated every 6 hours by the scheduler; an unreachable hub/invAIse leaves the stored state as
  it is, and the 14-day staleness backstop (`MAX_STALE_DAYS`) switches the features off if checks
  keep failing. Every successful check touches `updated_at`, also when nothing changed (MSTeamsFS #258).
- **Settings page:** Manage → Settings → MFSEssentials: status (active / expired / not activated /
  in use elsewhere / not checked for 14 days), paid-until date, last check, buy/renew link, licence key
  with Activate / Deactivate. FreeScout's Manage → Modules page shows the licence too.

## Files

```
MFSEssentials/
├── module.json                                Module manifest — no licensing, no auto-update fields
├── composer.json
├── start.php                                   Loads routes
├── Providers/MFSEssentialsServiceProvider.php  stylesheets/javascripts registration, migrations, thread.meta hook
├── Http/
│   ├── routes.php                              POST /mfsessentials/reactions/toggle, POST /mfsessentials/thread/convert
│   └── Controllers/ReactionsController.php, ConvertController.php
├── Services/ThreadConverter.php                Part 3 — convert to note / back, banner data, action types
├── Services/LicenseService.php                 Part 4 — licence via the hub, isLicensed() gate
├── Models/MFSEssentialsLicense.php             Part 4 — modules_licenses row (module_alias mfsessentials), 14-day backstop
├── Config/config.php                           Part 4 — hub_url, buy_url, terms_url
├── Http/Controllers/MFSEssentialsController.php Part 4 — settings-page licence actions (admin)
├── Resources/views/settings/                   Part 4 — settings section + licence panel
├── Entities/ThreadReaction.php                 ALLOWED_EMOJI, toggle(), summaryFor()
├── Database/Migrations/..._create_mfsessentials_thread_reactions_table.php
├── Public/
│   ├── css/module.css                          Part 0 fix + Part 1 picker + Part 2 bar styling
│   └── js/
│       ├── mfsessentials-editor.js             Part 1 — emoji/symbol picker
│       ├── mfsessentials-reactions.js          Part 2 — reaction bar click handling
│       └── mfsessentials-convert.js            Part 3 — convert menu items
└── Resources/views/partials/reactions-bar.blade.php, convert-banner.blade.php
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
| 2026-08-29 | v1.1.2: added the module icon (256x256 PNG, `Public/img/mfsessentials-icon.png`), matching FreeScout's own default-module.png baseline (confirmed via live investigation: `.module-card img { width:128px; height:128px }` in FreeScout core CSS, so any square image scales to fit — 256x256 is exactly 2x for retina, same convention as StickyMenu/AdvancedPrint, not the 1024x1024 MSTeamsFS/MSTeamsSso ship unnecessarily). Added `"img": "/modules/mfsessentials/img/mfsessentials-icon.png"` to `module.json`, matching MSTeamsSso's exact key/path convention. Version bumped via `release-module.sh MFSEssentials 1.1.2`. | Click Update Now on support.stackpros.io (Manage → Modules), Activate (auto-deactivates on reinstall per known gotcha), hard-refresh, confirm the icon actually renders in the module card. |
| 2026-07-21 | v1.1.1 GitHub pipeline setup: confirmed VPS working copy byte-identical to the live install (full recursive md5sum, not just version string) before proceeding. Added `latestVersionUrl`/`latestVersionZipUrl` to `module.json` (pushed to both the dev copy and the live install) and created `version.txt`. First-time repo setup: `/var/www/modules-dev/github-mfsessentials/` created, `.gitignore` copied from `github-msteamssso`'s (not invented fresh), committed and pushed to `github.com/ManagedFreeScout/mfsessentials` (root commit `716a945`, verified publicly reachable via unauthenticated `git ls-remote`). Release zip built and confirmed `.git`-free (caught and corrected one false-positive check first — `.gitignore` itself matches a naive `grep '\.git'`, had to check for `\.git/` specifically). Published GitHub Release `v1.1.1` and uploaded `mfsessentials.zip` via the REST API (no `gh` CLI on this VPS) — PAT read from `~/.credentials/mfstoken` only to construct the remote/Authorization header inline, never echoed/logged. Both auto-update URLs verified live (`version.txt` returns `1.1.1`; the zip URL 302s to the correct release asset). Confirmed from source exactly when/how often FreeScout reads these fields — see Distribution section above; no live-install reinstall needed, pipeline already active. | None outstanding from this session — genuinely done. Next real "next step" is whenever v1.1.2+ actually ships: bump both `module.json` files, update `version.txt`, commit to `github-mfsessentials`, rebuild the release zip, publish a new GitHub Release. |
