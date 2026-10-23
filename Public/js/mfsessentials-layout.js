/**
 * MFS Essentials 1.4.0 — layout features (card freescout-modules #340, tested as "MFS Layout (test)").
 * 1. Fixed panels: measures the top menu height for the CSS (--mfsl-nav-h); only toolbar +
 *    title stay fixed on a conversation page.
 * 2. Split view on folder list pages: the list stays left, the clicked conversation
 *    opens on the right in an iframe (?x_embed=1, which core already supports:
 *    no top menu/footer, and core keeps x_embed through its own AJAX redirects).
 * 3. Status filters, 4. sortable Customer / Assigned To + adjustable column widths.
 * Each feature has its own switch (Manage → Settings → MFS Essentials); the agent's own choices
 * (split view, divider, column widths, hidden statuses) are saved per user in FreeScout.
 */
(function () {
    'use strict';

    var body = document.body;
    if (!body || !body.classList.contains('mfsl-on')) {
        return;
    }

    var root = document.documentElement;
    var MIN_SPLIT_WIDTH = 1200;

    // Switches + this agent's preferences, from the JSON block the module puts in <head>.
    var cfg = {features: {}, prefs: {}, prefsUrl: ''};
    try {
        var cfgEl = document.getElementById('mfsessentials-config');
        if (cfgEl) {
            cfg = JSON.parse(cfgEl.textContent);
        }
    } catch (e) {}
    var feature = function (name) {
        return !!(cfg.features && cfg.features[name]);
    };
    var prefs = cfg.prefs || {};

    // Save one preference for this agent; returns a promise (resolved when saved or failed).
    function savePref(key, value) {
        prefs[key] = value;
        if (!cfg.prefsUrl || !window.fetch) {
            return Promise.resolve();
        }
        var token = document.querySelector('meta[name="csrf-token"]');
        var data = new URLSearchParams();
        data.append('key', key);
        data.append('value', JSON.stringify(value));
        return fetch(cfg.prefsUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: data
        }).catch(function () {});
    }

    // Dragging fires many changes: save the last one shortly after the agent stops.
    var saveTimers = {};
    function savePrefSoon(key, value) {
        prefs[key] = value;
        clearTimeout(saveTimers[key]);
        saveTimers[key] = setTimeout(function () {
            savePref(key, value);
        }, 400);
    }

    // ---------- Fixed panels ----------

    function setNavHeight() {
        var nav = document.querySelector('.navbar-static-top');
        root.style.setProperty('--mfsl-nav-h', (nav ? nav.offsetHeight : 0) + 'px');
    }
    setNavHeight();
    window.addEventListener('resize', setNavHeight);

    // ---------- Conversation: only toolbar + title bar fixed (Rutger, 19 Oct) ----------
    // Everything core renders in #conv-subject below the title (other modules' blocks such as
    // Custom Fields, Checklists, Kanban, and the reply / note editor) moves to the top of the
    // message column, so it scrolls with the page while the header stays small. Same order on
    // screen. Done after core's own start-up code (document ready), so handlers it binds via
    // '#conv-subject ...' (e.g. "switch to note") are already attached and move along.
    function slimHeader() {
        var subject = document.getElementById('conv-subject');
        var main = document.getElementById('conv-layout-main');
        var title = subject && subject.querySelector(':scope > .conv-subj-block');
        if (!title || !main || document.getElementById('mfsl-below-header')) {
            return;
        }
        var rest = [];
        for (var n = title.nextSibling; n; n = n.nextSibling) {
            rest.push(n);
        }
        if (!rest.length) {
            return;
        }
        var below = document.createElement('div');
        below.id = 'mfsl-below-header';
        rest.forEach(function (node) {
            below.appendChild(node);
        });
        main.insertBefore(below, main.firstChild);
        // Core toggles .action-visible on #conv-subject for the borders between these blocks
        // and the editor (.action-visible .conv-top-block + .conv-action-wrapper): mirror it.
        var sync = function () {
            below.classList.toggle('action-visible', subject.classList.contains('action-visible'));
        };
        sync();
        new MutationObserver(sync).observe(subject, {attributes: true, attributeFilter: ['class']});
    }
    if (feature('fixed_panels') && body.classList.contains('body-conv') && window.jQuery) {
        window.jQuery(function () {
            setTimeout(slimHeader, 0);
        });
        // "This reply will go to the customer. Switch to a note" (shown when the last thread is
        // a colleague's note): core binds it via '#conv-subject .switch-to-note' in a nested
        // ready handler, which may run before or after the move. Handle it here, once, before
        // core's handler (capture phase), with core's own switchToNote().
        document.addEventListener('click', function (e) {
            var link = e.target.closest ? e.target.closest('#mfsl-below-header .switch-to-note') : null;
            if (link && typeof window.switchToNote === 'function') {
                e.preventDefault();
                e.stopPropagation();
                window.switchToNote();
            }
        }, true);
    }

    // ---------- Are we the conversation inside the split pane? ----------

    var inPane = false;
    try {
        inPane = window.self !== window.top
            && window.parent.document.body.classList.contains('mfsl-split');
    } catch (e) {}
    if (inPane) {
        body.classList.add('mfsl-in-pane');
        // Core 1.8.248's own list column on the conversation page (shown when the conv_col
        // cookie is set, which the pane shares with the main window): our list is already on
        // the left. Marked hidden before core's initConvCol() runs on document ready, so core
        // doesn't load it either. The cookie itself (the agent's choice) is left alone.
        var coreCol = document.getElementById('conv-list-2col');
        if (coreCol) {
            coreCol.classList.add('hidden');
        }
        // The fixed header sits at the very top of the pane, so core's tooltips above its buttons
        // (Assignee, Status, ...) would open outside the frame: only the arrow showed. Show them
        // below instead; Bootstrap reads the placement on every show. Same for the customer
        // panel's cog ("Settings") in the top right corner: there to the left, as its menu
        // opens below it.
        if (window.jQuery) {
            window.jQuery(document).on('show.bs.tooltip', '#conv-layout-header [data-toggle="tooltip"], #conv-layout-customer [data-toggle="tooltip"]', function () {
                var tip = window.jQuery(this).data('bs.tooltip');
                if (tip) {
                    tip.options.placement = window.jQuery(this).closest('#conv-layout-customer').length ? 'left' : 'bottom';
                }
            });
        }
        return;
    }

    // ---------- Split view: folder list pages only ----------

    var content = document.querySelector('.content-2col');
    // No table when the folder is empty (core then renders only a message). The toolbar must
    // still be there, or a folder filtered down to nothing could not be un-filtered.
    var hasTable = !!document.querySelector('.table-conversations');
    var hasFilters = body.classList.contains('mfsl-status-filters');
    if (!content || !body.getAttribute('data-folder_id') || (!hasTable && !hasFilters)
        || !(feature('split_view') || feature('list_columns') || hasFilters)) {
        return;
    }

    var list, divider, pane, iframe, empty, toggle;
    var currentId = null;

    function splitAllowed() {
        return window.innerWidth >= MIN_SPLIT_WIDTH;
    }

    function getParam(name) {
        try {
            return new URL(window.location.href).searchParams.get(name);
        } catch (e) {
            return null;
        }
    }

    function setOpenParam(id, push) {
        try {
            var url = new URL(window.location.href);
            if (id) {
                url.searchParams.set('mfsl_open', id);
            } else {
                url.searchParams.delete('mfsl_open');
            }
            if (url.href === window.location.href) {
                return;
            }
            if (push) {
                window.history.pushState({mfslOpen: id || null}, '', url.href);
            } else {
                window.history.replaceState({mfslOpen: id || null}, '', url.href);
            }
        } catch (e) {}
    }

    function build() {
        if (list) {
            return;
        }
        list = document.createElement('div');
        list.className = 'mfsl-list';
        while (content.firstChild) {
            list.appendChild(content.firstChild);
        }
        content.appendChild(list);

        divider = document.createElement('div');
        divider.className = 'mfsl-divider';
        divider.title = 'Drag to resize';
        content.appendChild(divider);

        pane = document.createElement('div');
        pane.className = 'mfsl-pane';
        empty = document.createElement('div');
        empty.className = 'mfsl-pane-empty';
        empty.textContent = 'Select a conversation to open it here.';
        pane.appendChild(empty);
        content.appendChild(pane);

        initDivider();
        window.addEventListener('resize', syncListBox);

        // The list is re-rendered by core (pagination, refresh): re-mark the open row.
        new MutationObserver(markActive).observe(list, {childList: true, subtree: true});
    }

    // The bulk-actions bar follows the list's size and position (see CSS).
    function syncListBox() {
        if (!list) {
            return;
        }
        var rect = list.getBoundingClientRect();
        root.style.setProperty('--mfsl-list-px', Math.round(rect.width) + 'px');
        root.style.setProperty('--mfsl-list-left', Math.round(rect.left) + 'px');
    }

    function markActive() {
        if (!list) {
            return;
        }
        var rows = list.querySelectorAll('tr.conv-row');
        for (var i = 0; i < rows.length; i++) {
            var isActive = currentId && rows[i].getAttribute('data-conversation_id') === String(currentId);
            if (rows[i].classList.contains('mfsl-active') !== !!isActive) {
                rows[i].classList.toggle('mfsl-active', !!isActive);
            }
        }
    }

    function refreshList() {
        if (typeof window.loadConversations === 'function') {
            window.loadConversations('', '', true);
        }
    }

    function embedUrl(href) {
        var url = new URL(href, window.location.href);
        url.hash = '';
        url.searchParams.set('x_embed', '1');
        return url.href;
    }

    // Prefer the row's own link: it carries folder_id, which core uses for "next conversation".
    function hrefFor(id) {
        var link = list && list.querySelector('tr.conv-row[data-conversation_id="' + id + '"] a[href*="/conversation/"]');
        return link ? link.href : '/conversation/' + encodeURIComponent(id);
    }

    function openConversation(id, href, push) {
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.setAttribute('title', 'Conversation');
            iframe.addEventListener('load', onPaneLoad);
            pane.appendChild(iframe);
        }
        empty.style.display = 'none';
        iframe.style.display = '';
        currentId = String(id);
        markActive();
        iframe.src = embedUrl(href);
        setOpenParam(currentId, push);
    }

    function closePane(push) {
        currentId = null;
        markActive();
        if (iframe) {
            iframe.removeAttribute('src');
            iframe.style.display = 'none';
        }
        if (empty) {
            empty.style.display = '';
        }
        setOpenParam(null, push);
    }

    function onPaneLoad() {
        if (!iframe.getAttribute('src')) {
            return;
        }
        var loc;
        try {
            loc = iframe.contentWindow.location;
            void loc.pathname;
        } catch (e) {
            // Framing blocked (X-Frame-Options/CSP on this server): open normally instead.
            window.location.href = iframe.src.replace(/([?&])x_embed=1&?/, '$1').replace(/[?&]$/, '');
            return;
        }
        if (loc.href === 'about:blank') {
            return;
        }

        var match = loc.pathname.match(/\/conversation\/(\d+)/);
        if (match) {
            // Every load in the pane can follow a change (reply, status, assign, next
            // conversation after sending), so bring the list up to date.
            currentId = match[1];
            markActive();
            setOpenParam(currentId, false);
            refreshList();
            return;
        }

        // Core left the conversation (after close/delete it goes back to the folder).
        if (loc.pathname === window.location.pathname) {
            closePane(false);
            refreshList();
            return;
        }
        // Anything else (customer profile, another mailbox...): open it in the main window.
        var target = new URL(loc.href);
        target.searchParams.delete('x_embed');
        window.location.href = target.href;
    }

    function initDivider() {
        var saved = parseFloat(prefs.listw);
        if (saved >= 20 && saved <= 75) {
            root.style.setProperty('--mfsl-list-w', saved + '%');
        }
        divider.addEventListener('mousedown', function (e) {
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();
            body.classList.add('mfsl-dragging');
            var rect = content.getBoundingClientRect();
            var pct = null;
            function move(ev) {
                // The conversation pane keeps at least 400px.
                var maxPct = Math.min(75, (rect.width - 400) / rect.width * 100);
                pct = Math.min(maxPct, Math.max(20, (ev.clientX - rect.left) / rect.width * 100));
                root.style.setProperty('--mfsl-list-w', pct.toFixed(1) + '%');
                syncListBox();
            }
            function up() {
                body.classList.remove('mfsl-dragging');
                document.removeEventListener('mousemove', move);
                document.removeEventListener('mouseup', up);
                if (pct !== null) {
                    savePref('listw', parseFloat(pct.toFixed(1)));
                }
            }
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', up);
        });
    }

    // restoring = applying the saved choice on page load (nothing to save then).
    function setSplit(on, restoring) {
        if (on) {
            build();
            body.classList.add('mfsl-split');
            syncListBox();
        } else {
            body.classList.remove('mfsl-split');
            if (list) {
                closePane(false);
            }
        }
        if (!restoring) {
            savePref('split', !!on);
        }
        if (toggle) {
            toggle.classList.toggle('active', on);
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
        }
    }

    // Toolbar above the list: filters on the left, split view button on the right
    // (the button is hidden below MIN_SPLIT_WIDTH by the CSS).
    var bar = document.createElement('div');
    bar.className = 'mfsl-split-bar';

    // Status filters: Show active / pending / closed. The server says whether they apply to this
    // folder (body.mfsl-status-filters) and does the filtering in the
    // folder query; here we only save the agent's unticked statuses (preference hide_status).
    // Core's own status names (translated by FreeScout's Lang).
    var statusName = function (key, fallback) {
        try {
            var t = window.Lang && Lang.get('messages.' + key);
            return t && t !== 'messages.' + key ? t : fallback;
        } catch (e) {
            return fallback;
        }
    };
    var STATUSES = [
        {id: 1, label: statusName('active', 'Active')},
        {id: 2, label: statusName('pending', 'Pending')},
        {id: 3, label: statusName('closed', 'Closed')}
    ];

    function readHidden() {
        return Array.isArray(prefs.hide_status) ? prefs.hide_status.map(Number) : [];
    }

    function writeHidden(list) {
        return savePref('hide_status', list);
    }

    if (body.classList.contains('mfsl-status-filters')) {
        var filters = document.createElement('div');
        filters.className = 'mfsl-filters';
        var boxes = [];
        var hidden = readHidden();

        // The last ticked box can't be unticked: an empty list would look like a bug.
        var guardLast = function () {
            var ticked = boxes.filter(function (b) { return b.checked; });
            boxes.forEach(function (b) {
                b.disabled = ticked.length === 1 && b.checked;
                b.parentNode.title = b.disabled ? 'At least one status stays visible' : '';
            });
        };

        STATUSES.forEach(function (st) {
            var label = document.createElement('label');
            label.className = 'mfsl-filter';
            var box = document.createElement('input');
            box.type = 'checkbox';
            box.value = String(st.id);
            box.checked = hidden.indexOf(st.id) === -1;
            box.addEventListener('change', function () {
                // Keep statuses hidden in other folders (e.g. Closed while in Mine) as they are.
                var list = readHidden().filter(function (id) { return id !== st.id; });
                if (!box.checked) {
                    list.push(st.id);
                }
                guardLast();
                // The server filters with the saved preference: reload the list once it's saved.
                writeHidden(list.sort()).then(function () {
                    if (document.querySelector('.table-conversations') && typeof window.loadConversations === 'function') {
                        window.loadConversations(1);
                    } else {
                        window.location.reload();
                    }
                });
            });
            boxes.push(box);
            label.appendChild(box);
            label.appendChild(document.createTextNode(' ' + st.label));
            filters.appendChild(label);
        });
        guardLast();
        bar.appendChild(filters);
    }

    toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'btn btn-default btn-xs mfsl-split-toggle';
    toggle.innerHTML = '<i class="glyphicon glyphicon-pause"></i> Split view';
    toggle.addEventListener('click', function () {
        setSplit(!body.classList.contains('mfsl-split'));
    });
    if (hasTable && feature('split_view')) {
        bar.appendChild(toggle);
    }
    if (bar.children.length) {
        content.insertBefore(bar, content.firstChild);
    }

    // A plain click on a row opens it in the pane. Ctrl/Cmd/Shift/middle click,
    // checkboxes, the star and non-conversation links keep their normal behaviour.
    document.addEventListener('click', function (e) {
        if (!body.classList.contains('mfsl-split') || !splitAllowed()) {
            return;
        }
        if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
            return;
        }
        var row = e.target.closest ? e.target.closest('tr.conv-row') : null;
        if (!row || !list || !list.contains(row)) {
            return;
        }
        if (e.target.closest('input, label, .conv-cb, .conv-star, .dropdown-menu, [data-toggle]')) {
            return;
        }
        var link = e.target.closest('a');
        if (link && link.href && !/\/conversation\/\d+/.test(link.href)) {
            return;
        }
        var convLink = link || row.querySelector('a[href*="/conversation/"]');
        if (!convLink) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        openConversation(row.getAttribute('data-conversation_id'), convLink.href, true);
    }, true);

    window.addEventListener('popstate', function () {
        if (!body.classList.contains('mfsl-split')) {
            return;
        }
        var id = getParam('mfsl_open');
        if (id) {
            openConversation(id, hrefFor(id), false);
        } else {
            closePane(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && currentId && body.classList.contains('mfsl-split')
            && !document.querySelector('.modal.in')) {
            closePane(true);
        }
    });

    // ---------- Extra sortable columns: Customer, Assigned To ----------
    // Core sorts subject/number/date from the table's data-sorting_* attributes, which every
    // core list refresh sends along. We use the same attributes; the server sorts in our
    // folder.conversations_query filter. Core re-renders the table with its own default sort,
    // so after every re-render the attributes and arrows are put back (like core: not kept
    // across a page reload).
    var extraSort = null; // {by: 'customer'|'assignee', order: 'asc'|'desc'}
    var SORT_COLS = {customer: 'th.conv-customer', assignee: 'th.conv-owner'};

    // Core reads these through jQuery's .data(), which prefers its own cache over the
    // attributes once anything was cached. Clear the cache (never fill it), so core always
    // reads the current attributes, also after its own header click sets them.
    function clearSortingCache(table) {
        if (window.jQuery) {
            window.jQuery(table).removeData(['sorting_sort_by', 'sorting_order']);
        }
    }

    function setTableSorting(table, by, order) {
        table.setAttribute('data-sorting_sort_by', by);
        table.setAttribute('data-sorting_order', order);
        clearSortingCache(table);
    }

    // Core's arrows: ↑ = descending, ↓ = ascending (kept the same here).
    function stripArrows(el) {
        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
        var node;
        while ((node = walker.nextNode())) {
            if (/[↑↓]/.test(node.nodeValue)) {
                node.nodeValue = node.nodeValue.replace(/\s*[↑↓]/g, '');
            }
        }
    }

    function decorateSort(table) {
        var thead = table.querySelector('thead');
        if (!thead) {
            return;
        }
        Object.keys(SORT_COLS).forEach(function (by) {
            var th = thead.querySelector(SORT_COLS[by]);
            if (!th) {
                return;
            }
            var trigger = th.querySelector('.mfsl-sort');
            if (!trigger) {
                trigger = document.createElement('span');
                trigger.className = 'mfsl-sort';
                trigger.setAttribute('data-sort-by', by);
                trigger.setAttribute('role', 'button');
                trigger.title = 'Sort';
                // Inside core's label (a block span), so the arrow stays on the same line.
                var label = th.querySelector('span');
                if (by === 'customer' && label) {
                    // The whole "Customer" label sorts, like core's sortable headers.
                    label.classList.add('mfsl-sort-label');
                }
                // Before core's filter icon in "Assigned To": that icon only appears on hover
                // and would otherwise push the arrow away from under the mouse.
                var icon = label && label.querySelector('small');
                if (icon) {
                    label.insertBefore(trigger, icon);
                } else {
                    (label || th).appendChild(trigger);
                }
            }
            var active = extraSort && extraSort.by === by;
            trigger.classList.toggle('mfsl-sort-active', !!active);
            trigger.textContent = active ? (extraSort.order === 'desc' ? '↑' : '↓') : '⇅';
        });
        if (extraSort) {
            setTableSorting(table, extraSort.by, extraSort.order);
            thead.querySelectorAll('.conv-col-sort').forEach(stripArrows);
        }
        // Core's sortable headers (Conversation, Number, Waiting Since) only show an arrow while
        // they are the sort. Give the others the same ⇅ as ours, so every sortable column looks
        // sortable. Inside core's .conv-col-sort span, so a click on it sorts like the label.
        thead.querySelectorAll('.conv-col-sort').forEach(function (span) {
            if (/[↑↓]/.test(span.textContent) || span.querySelector('.mfsl-sort-hint')) {
                return;
            }
            var hint = document.createElement('span');
            hint.className = 'mfsl-sort-hint';
            hint.textContent = '⇅';
            span.appendChild(hint);
        });
    }

    function sortBy(by) {
        var table = document.querySelector('.table-conversations');
        if (!table || typeof window.loadConversations !== 'function') {
            return;
        }
        var order = extraSort && extraSort.by === by && extraSort.order === 'asc' ? 'desc' : 'asc';
        extraSort = {by: by, order: order};
        setTableSorting(table, by, order);
        window.loadConversations('', '', true);
    }

    // Capture phase: runs before core's handlers, so a click on the sort arrow inside
    // "Assigned To" doesn't also open core's assignee filter.
    document.addEventListener('click', function (e) {
        var t = e.target.closest ? e.target : null;
        if (!t) {
            return;
        }
        var trigger = t.closest('.mfsl-sort');
        var label = !trigger && t.closest('.mfsl-sort-label');
        if (label) {
            trigger = label.closest('th').querySelector('.mfsl-sort');
        }
        if (trigger) {
            e.preventDefault();
            e.stopPropagation();
            sortBy(trigger.getAttribute('data-sort-by'));
            return;
        }
        // Core's own sortable headers take over again.
        var coreSort = t.closest('.table-conversations .conv-col-sort');
        if (coreSort) {
            extraSort = null;
            clearSortingCache(coreSort.closest('.table-conversations'));
        }
    }, true);

    // ---------- Resizable columns (normal list, not split view) ----------
    // Each handle sits on the border between two columns and moves that border: the column on
    // the left grows, the one on the right shrinks, so the edge follows the mouse (the table
    // always fills the width). Conversation has no fixed width of its own: it is whatever the
    // other columns leave. Double-click a border to reset the columns on both sides.
    // Saved per agent (preference colw).
    var COL_ORDER = ['customer', 'subject', 'owner', 'number', 'date'];
    var MIN_COL = 50;
    var MIN_SUBJECT = 160;
    var colWidths = {};
    if (prefs.colw && typeof prefs.colw === 'object' && !Array.isArray(prefs.colw)) {
        Object.keys(prefs.colw).forEach(function (name) {
            colWidths[name] = parseInt(prefs.colw[name], 10);
        });
    }
    var suppressClick = false;

    function colEl(table, name) {
        return table.querySelector('colgroup col.conv-' + name);
    }

    function thEl(table, name) {
        return table.querySelector('thead th.conv-' + name);
    }

    function applyColWidths(table) {
        COL_ORDER.forEach(function (name) {
            var col = colEl(table, name);
            if (col && name !== 'subject') {
                col.style.width = colWidths[name] ? colWidths[name] + 'px' : '';
            }
        });
    }

    function saveColWidths() {
        savePrefSoon('colw', Object.assign({}, colWidths));
    }

    function decorateResize(table) {
        applyColWidths(table);
        // The columns this table actually has, in order (Assigned To isn't on every list).
        var cols = COL_ORDER.filter(function (name) {
            return thEl(table, name) && colEl(table, name);
        });
        // A handle on every border between two of them (not on the table's right edge).
        cols.slice(0, -1).forEach(function (left, i) {
            var right = cols[i + 1];
            var th = thEl(table, left);
            if (th.querySelector('.mfsl-col-resize')) {
                return;
            }
            var handle = document.createElement('div');
            handle.className = 'mfsl-col-resize';
            handle.title = 'Drag to resize, double-click to reset';
            handle.addEventListener('mousedown', function (e) {
                if (e.button !== 0) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                startResize(table, left, right, e.clientX);
            });
            // A click on the handle is never a click on the header (sort, assignee filter).
            handle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
            });
            handle.addEventListener('dblclick', function (e) {
                e.preventDefault();
                e.stopPropagation();
                delete colWidths[left];
                delete colWidths[right];
                saveColWidths();
                applyColWidths(table);
            });
            th.appendChild(handle);
        });
    }

    function startResize(table, left, right, startX) {
        var wLeft = thEl(table, left).getBoundingClientRect().width;
        var wRight = thEl(table, right).getBoundingClientRect().width;
        // How far the border may move: no column below its minimum. Conversation (subject)
        // only changes implicitly, so only its minimum counts there.
        var minDx = (left === 'subject' ? MIN_SUBJECT : MIN_COL) - wLeft;
        var maxDx = wRight - (right === 'subject' ? MIN_SUBJECT : MIN_COL);
        var changed = false;
        body.classList.add('mfsl-resizing');
        function move(ev) {
            var dx = Math.max(minDx, Math.min(maxDx, ev.clientX - startX));
            if (left !== 'subject') {
                colWidths[left] = Math.round(wLeft + dx);
            }
            if (right !== 'subject') {
                colWidths[right] = Math.round(wRight - dx);
            }
            applyColWidths(table);
            changed = true;
        }
        function up() {
            body.classList.remove('mfsl-resizing');
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup', up);
            if (changed) {
                saveColWidths();
                // The mouseup can turn into a click on the header (sort, assignee filter).
                suppressClick = true;
                setTimeout(function () { suppressClick = false; }, 0);
            }
        }
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', up);
    }

    document.addEventListener('click', function (e) {
        if (suppressClick) {
            e.preventDefault();
            e.stopPropagation();
            suppressClick = false;
        }
    }, true);

    // Core replaces the whole table on every list refresh: decorate each new one.
    function decorateTable() {
        var table = content.querySelector('.table-conversations');
        if (table && !table.hasAttribute('data-mfsl-decorated')) {
            table.setAttribute('data-mfsl-decorated', '1');
            decorateSort(table);
            decorateResize(table);
        }
    }
    if (feature('list_columns')) {
        decorateTable();
        new MutationObserver(decorateTable).observe(content, {childList: true, subtree: true});
    }

    // Restore the agent's choice (saved per user).
    if (feature('split_view') && prefs.split === true) {
        setSplit(true, true);
        // Only when it is still in the list: core's bulk actions (assign, status, delete) reload
        // the page, and a conversation that left the list would otherwise come back in the pane.
        var openId = getParam('mfsl_open');
        if (openId && /^\d+$/.test(openId)) {
            if (list.querySelector('tr.conv-row[data-conversation_id="' + openId + '"]')) {
                openConversation(openId, hrefFor(openId), false);
            } else {
                setOpenParam(null, false);
            }
        }
    }
})();
