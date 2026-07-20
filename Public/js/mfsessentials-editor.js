// MFSEssentials — Emoji / Special Character picker button
//
// Injects a toolbar button + dropdown panel into every Summernote toolbar on
// the page (Reply and Note editors both use the same toolbar markup, so this
// isn't scoped to one form the way CfsAssist's reply-only ReplAI button is).
//
// Registration pattern deliberately mirrors CfsAssist's Public/js/module.js:
// plain DOM injection next to an existing .note-btn-group, guarded against
// double-injection, re-attempted via MutationObserver because FreeScout
// re-renders the toolbar on conversation navigation without a full page
// reload. This is NOT Summernote's real plugin-registration API (confirmed
// by reading module.js — CfsAssist doesn't use $.summernote.plugins either)
// even though FreeScout does bundle a real example of that API
// (public/js/summernote/plugin/specialchars/summernote-ext-specialchars.js) —
// mirroring the actual working pattern here, not inventing a different one.

(function () {
  'use strict';

  var BTN_CLASS = 'mfsessentials-picker-btn';

  // ~170 common emoji across simple categories.
  var EMOJI_CATEGORIES = {
    'Smileys': [
      '\u{1F600}', '\u{1F603}', '\u{1F604}', '\u{1F601}', '\u{1F606}', '\u{1F605}',
      '\u{1F923}', '\u{1F602}', '\u{1F642}', '\u{1F643}', '\u{1F609}', '\u{1F60A}',
      '\u{1F607}', '\u{1F970}', '\u{1F60D}', '\u{1F929}', '\u{1F618}', '\u{1F617}',
      '\u{1F61A}', '\u{1F619}', '\u{1F60B}', '\u{1F61B}', '\u{1F61C}', '\u{1F92A}',
      '\u{1F61D}', '\u{1F911}', '\u{1F917}', '\u{1F92D}', '\u{1F92B}', '\u{1F914}',
      '\u{1F610}', '\u{1F611}', '\u{1F636}', '\u{1F60F}', '\u{1F612}', '\u{1F644}',
      '\u{1F62C}', '\u{1F925}', '\u{1F62E}', '\u{1F614}', '\u{1F615}', '\u{1F641}',
      '\u{1F623}', '\u{1F616}', '\u{1F62B}', '\u{1F629}', '\u{1F97A}', '\u{1F622}',
      '\u{1F62D}', '\u{1F624}', '\u{1F620}', '\u{1F621}', '\u{1F92C}', '\u{1F631}',
      '\u{1F628}', '\u{1F630}', '\u{1F625}', '\u{1F613}', '\u{1F971}',
      '\u{1F62A}', '\u{1F634}', '\u{1F912}', '\u{1F915}', '\u{1F922}', '\u{1F92E}',
      '\u{1F927}', '\u{1F975}', '\u{1F976}', '\u{1F974}', '\u{1F635}', '\u{1F92F}',
      '\u{1F920}', '\u{1F973}', '\u{1F60E}', '\u{1F913}', '\u{1F9D0}'
    ],
    'People & Gestures': [
      '\u{1F44D}', '\u{1F44E}', '\u{1F44F}', '\u{1F64C}', '\u{1F450}', '\u{1F91D}',
      '\u{1F64F}', '\u{270C}\u{FE0F}', '\u{1F91E}', '\u{1F91F}', '\u{1F44C}', '\u{1F90C}',
      '\u{1F90F}', '\u{270B}', '\u{1F91A}', '\u{1F596}', '\u{1F44B}', '\u{1F919}',
      '\u{1F595}', '\u{1F446}', '\u{1F447}', '\u{1F448}', '\u{1F449}', '\u{261D}\u{FE0F}',
      '\u{1F4AA}', '\u{1F933}', '\u{1F9BE}', '\u{1F9B5}', '\u{1F9B6}', '\u{1F442}',
      '\u{1F440}', '\u{1F5E3}\u{FE0F}', '\u{1F464}', '\u{1F465}'
    ],
    'Hearts & Faces': [
      '\u{2764}\u{FE0F}', '\u{1F9E1}', '\u{1F49B}', '\u{1F49A}', '\u{1F499}', '\u{1F49C}',
      '\u{1F5A4}', '\u{1F90D}', '\u{1F90E}', '\u{1F494}', '\u{2763}\u{FE0F}', '\u{1F495}',
      '\u{1F49E}', '\u{1F493}', '\u{1F497}', '\u{1F496}', '\u{1F498}', '\u{1F49D}',
      '\u{1F489}', '\u{1F48B}'
    ],
    'Animals & Nature': [
      '\u{1F436}', '\u{1F431}', '\u{1F42D}', '\u{1F439}', '\u{1F430}', '\u{1F98A}',
      '\u{1F43B}', '\u{1F43C}', '\u{1F428}', '\u{1F42F}', '\u{1F981}', '\u{1F42E}',
      '\u{1F437}', '\u{1F438}', '\u{1F419}', '\u{1F41D}', '\u{1F98B}', '\u{1F40C}',
      '\u{1F41E}', '\u{1F339}', '\u{1F33A}', '\u{1F337}', '\u{1F340}', '\u{1F332}',
      '\u{1F333}', '\u{2600}\u{FE0F}', '\u{1F319}', '\u{2B50}', '\u{1F308}', '\u{2601}\u{FE0F}'
    ],
    'Food & Drink': [
      '\u{1F34E}', '\u{1F34C}', '\u{1F347}', '\u{1F349}', '\u{1F353}', '\u{1F351}',
      '\u{1F352}', '\u{1F35E}', '\u{1F9C0}', '\u{1F354}', '\u{1F355}', '\u{1F32E}',
      '\u{1F363}', '\u{1F371}', '\u{1F35C}', '\u{1F35B}', '\u{1F369}', '\u{1F36A}',
      '\u{1F382}', '\u{2615}', '\u{1F375}', '\u{1F37A}', '\u{1F377}', '\u{1F37E}',
      '\u{1F9C3}'
    ],
    'Activities & Objects': [
      '\u{26BD}', '\u{1F3C0}', '\u{1F3C8}', '\u{26BE}', '\u{1F3BE}', '\u{1F3C6}',
      '\u{1F947}', '\u{1F3AE}', '\u{1F3B2}', '\u{1F3B8}', '\u{1F3A8}', '\u{1F4F7}',
      '\u{1F3AC}', '\u{1F4BB}', '\u{1F4F1}', '\u{2709}\u{FE0F}', '\u{1F4CE}', '\u{1F4CC}',
      '\u{1F4CB}', '\u{1F4C5}', '\u{1F4C8}', '\u{1F4A1}', '\u{1F511}', '\u{1F512}',
      '\u{1F513}', '\u{1F6A9}', '\u{1F3AF}', '\u{1F680}', '\u{2708}\u{FE0F}', '\u{1F695}'
    ],
    'Symbols': [
      '\u{2705}', '\u{274C}', '\u{2757}', '\u{2753}', '\u{26A0}\u{FE0F}', '\u{1F6AB}',
      '\u{1F195}', '\u{1F4A5}', '\u{1F525}', '\u{2728}', '\u{1F389}', '\u{1F38A}',
      '\u{1F440}', '\u{1F3C1}', '\u{1F517}', '\u{1F4CD}', '\u{2B06}\u{FE0F}', '\u{2B07}\u{FE0F}',
      '\u{27A1}\u{FE0F}', '\u{2B05}\u{FE0F}'
    ]
  };

  var SYMBOL_CATEGORIES = {
    'Currency': ['$', '€', '£', '¥', '¢', '₹', '₽', '₩', '₦', '₫'],
    'Arrows': ['→', '←', '↑', '↓', '↔', '⇒', '⇐', '⇔', '↖', '↗', '↘', '↙'],
    'Legal': ['©', '®', '™', '§', '¶'],
    'Typography': ['–', '—', '‘', '’', '“', '”', '…', '«', '»', '¡', '¿'],
    'Bullets': ['•', '◦', '▪', '●', '‣', '⁃'],
    'Math & Misc': ['×', '÷', '±', '≠', '≤', '≥', '∞', '°', '½', '¼', '¾'],
    'Accented Latin': [
      'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç',
      'È', 'É', 'Ê', 'Ë', 'Ñ', 'Ö', 'Ø', 'Ü',
      'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç',
      'è', 'é', 'ê', 'ë', 'ñ', 'ö', 'ø', 'ü',
      'ÿ', 'œ'
    ]
  };

  function buildGrid(categories) {
    var wrap = document.createElement('div');
    Object.keys(categories).forEach(function (label) {
      var catEl = document.createElement('div');
      catEl.className = 'mfsessentials-picker-category';
      catEl.textContent = label;
      wrap.appendChild(catEl);

      var grid = document.createElement('div');
      grid.className = 'mfsessentials-picker-grid';
      categories[label].forEach(function (ch) {
        var item = document.createElement('span');
        item.className = 'mfsessentials-picker-item';
        item.textContent = ch;
        item.setAttribute('data-char', ch);
        item.title = ch;
        grid.appendChild(item);
      });
      wrap.appendChild(grid);
    });
    return wrap;
  }

  // Inserts at the cursor position in whichever .note-editable currently has
  // focus/selection. Relying on document.execCommand('insertText', ...) here
  // (not Summernote's own jQuery `.summernote('insertText', ...)` API) is a
  // deliberate choice: that API must be invoked on the original element
  // Summernote was initialized on, which isn't reliably discoverable from a
  // DOM-injected button generic enough to work in both the Reply and Note
  // forms. execCommand operates directly on the live browser Selection inside
  // any contenteditable, independent of Summernote's own instance tracking —
  // confirmed against the vendored summernote.js that .note-editable is a
  // plain contenteditable div, and that FreeScout's own editor.insertText
  // (main.js's "insert variable" dropdown) only differs by using Summernote's
  // internal range bookkeeping, which isn't reachable from outside a real
  // plugin registration.
  function insertChar(ch) {
    try {
      document.execCommand('insertText', false, ch);
    } catch (e) {
      // Fallback: append to whichever note-editable is currently focused,
      // or the first one on the page if none is.
      var target = (document.activeElement && document.activeElement.classList &&
                    document.activeElement.classList.contains('note-editable'))
        ? document.activeElement
        : document.querySelector('.note-editable[contenteditable="true"]');
      if (target) {
        target.innerHTML += ch;
        target.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
  }

  function injectInto(toolbar) {
    if (toolbar.querySelector('.' + BTN_CLASS)) return;

    var anchorGroups = toolbar.querySelectorAll('.note-btn-group');
    var anchor = anchorGroups.length ? anchorGroups[anchorGroups.length - 1] : null;
    if (!anchor) return;

    var group = document.createElement('div');
    group.className = 'note-btn-group btn-group';
    group.style.position = 'relative';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = BTN_CLASS + ' note-btn btn btn-default btn-sm';
    btn.title = 'Insert emoji / special character';
    btn.innerHTML = '\u{1F60A}';

    var panel = document.createElement('div');
    panel.className = 'mfsessentials-picker-panel';

    var tabs = document.createElement('div');
    tabs.className = 'mfsessentials-picker-tabs';

    var emojiTab = document.createElement('div');
    emojiTab.className = 'mfsessentials-picker-tab active';
    emojiTab.textContent = 'Emoji';

    var symbolTab = document.createElement('div');
    symbolTab.className = 'mfsessentials-picker-tab';
    symbolTab.textContent = 'Symbols';

    tabs.appendChild(emojiTab);
    tabs.appendChild(symbolTab);

    var body = document.createElement('div');
    body.className = 'mfsessentials-picker-body';

    var emojiGrid = buildGrid(EMOJI_CATEGORIES);
    var symbolGrid = buildGrid(SYMBOL_CATEGORIES);
    symbolGrid.style.display = 'none';
    body.appendChild(emojiGrid);
    body.appendChild(symbolGrid);

    panel.appendChild(tabs);
    panel.appendChild(body);

    // Keep whichever note-editable currently has focus/selection from
    // blurring when the user interacts with our button/panel -- this is
    // what makes insertChar() land in the right place. Plain DOM buttons
    // (unlike Summernote's own registered toolbar buttons) get no such
    // protection for free.
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
    panel.addEventListener('mousedown', function (e) { e.preventDefault(); });

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var wasOpen = panel.classList.contains('open');
      document.querySelectorAll('.mfsessentials-picker-panel.open').forEach(function (p) {
        p.classList.remove('open');
      });
      if (!wasOpen) panel.classList.add('open');
    });

    emojiTab.addEventListener('click', function () {
      emojiTab.classList.add('active');
      symbolTab.classList.remove('active');
      emojiGrid.style.display = '';
      symbolGrid.style.display = 'none';
    });

    symbolTab.addEventListener('click', function () {
      symbolTab.classList.add('active');
      emojiTab.classList.remove('active');
      symbolGrid.style.display = '';
      emojiGrid.style.display = 'none';
    });

    body.addEventListener('click', function (e) {
      var item = e.target.closest ? e.target.closest('.mfsessentials-picker-item') : null;
      if (!item) return;
      insertChar(item.getAttribute('data-char'));
      panel.classList.remove('open');
    });

    document.addEventListener('click', function (e) {
      if (!group.contains(e.target)) {
        panel.classList.remove('open');
      }
    });

    group.appendChild(btn);
    group.appendChild(panel);
    anchor.insertAdjacentElement('afterend', group);
  }

  function injectAll() {
    document.querySelectorAll('div.note-toolbar.panel-heading').forEach(injectInto);
  }

  // FreeScout re-renders the toolbar on conversation navigation without a
  // full page reload -- same gotcha CfsAssist's module.js already solved,
  // same fix (MutationObserver + re-attempt, guarded by the class check
  // in injectInto so it never double-injects).
  var observer = new MutationObserver(function () {
    injectAll();
  });
  observer.observe(document.body, { childList: true, subtree: true });

  injectAll();
})();
