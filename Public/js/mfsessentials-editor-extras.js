/**
 * MFS Essentials 1.4.0 — editor extras for the reply / note editor (Summernote 0.8.9),
 * card freescout-modules #340. Switch: "Editor extras" (Manage → Settings → MFS Essentials).
 *
 * 1. Sub-lists: TAB / Shift+TAB inside a bulleted or numbered list goes one level in / out,
 *    plus Indent / Outdent buttons. The sub-list goes INSIDE the previous item
 *    (<li>One<ul><li>Two</li></ul></li>, like Gmail and Google Docs), not next to it like
 *    Summernote's own indent (<ul><li>One</li><ul>...), which e-mail programs may not like.
 *    Outside a list TAB keeps core's behaviour; the buttons then indent the paragraph.
 * 2. Extra toolbar buttons: Undo / Redo, strikethrough, alignment (left / center / right),
 *    horizontal line, fullscreen. Summernote has them built in; core leaves them out.
 *
 * Toolbar buttons are added by name through core's own filters (conversation.editor_toolbar,
 * editor.options), so modules that insert by position (Extended Editor) still work.
 * This file runs before core initialises the editor (document ready), so the filters are in time.
 */
(function () {
    'use strict';

    if (typeof window.fsAddFilter !== 'function' || !window.jQuery) {
        return;
    }
    var $ = window.jQuery;

    // ---------- Undo history ----------

    function contextOf(el) {
        var note = $(el).closest('.note-editor').prev();
        return note.data('summernote') || null;
    }

    // Our DOM changes as one undo step: a snapshot of the state before (if the last snapshot
    // is older, e.g. just after typing), then core's own afterCommand (snapshot + change event,
    // which also triggers FreeScout's draft saving).
    function withUndo(context, change) {
        var editor = context.modules.editor;
        var history = editor.history;
        var editable = context.layoutInfo.editable[0];
        var last = history.stack[history.stackOffset];
        if (!last || last.contents !== editable.innerHTML) {
            history.recordUndo();
        }
        var changed = change(editable);
        if (changed) {
            editor.afterCommand();
        }
        return changed;
    }

    // ---------- Sub-lists ----------

    function isList(node) {
        return node && (node.nodeName === 'UL' || node.nodeName === 'OL');
    }

    function closestIn(node, name, editable) {
        while (node && node !== editable) {
            if (node.nodeName === name) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    // The list items the selection touches (the innermost ones), in document order.
    function selectedItems(editable) {
        var sel = window.getSelection();
        if (!sel.rangeCount) {
            return [];
        }
        var range = sel.getRangeAt(0);
        if (!editable.contains(range.commonAncestorContainer)) {
            return [];
        }
        var first = closestIn(range.startContainer, 'LI', editable);
        if (range.collapsed) {
            return first ? [first] : [];
        }
        var items = [];
        editable.querySelectorAll('li').forEach(function (li) {
            if (li === first || (range.intersectsNode(li) && !li.querySelector('li'))) {
                items.push(li);
            } else if (range.intersectsNode(li)) {
                // A parent item: only if the selection touches its own text, not just its sub-list.
                var own = document.createRange();
                own.setStart(li, 0);
                var sub = Array.prototype.find.call(li.childNodes, isList);
                if (sub) {
                    own.setEndBefore(sub);
                } else {
                    own.setEnd(li, li.childNodes.length);
                }
                if (range.compareBoundaryPoints(Range.START_TO_END, own) > 0
                    && range.compareBoundaryPoints(Range.END_TO_START, own) < 0) {
                    items.push(li);
                }
            }
        });
        return items;
    }

    function saveSelection() {
        var sel = window.getSelection();
        if (!sel.rangeCount) {
            return null;
        }
        var r = sel.getRangeAt(0);
        return {sc: r.startContainer, so: r.startOffset, ec: r.endContainer, eo: r.endOffset};
    }

    // Moving <li> elements keeps their text nodes, so the caret goes back where it was.
    function restoreSelection(saved) {
        if (!saved) {
            return;
        }
        try {
            var r = document.createRange();
            r.setStart(saved.sc, saved.so);
            r.setEnd(saved.ec, saved.eo);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(r);
        } catch (e) {}
    }

    // One level in: into a sub-list at the end of the previous item (created if needed,
    // same type as the current list). The first item of a list has no previous item: unchanged.
    function indentItem(li) {
        var prev = li.previousElementSibling;
        if (!prev || prev.nodeName !== 'LI') {
            return false;
        }
        var type = li.parentNode.nodeName;
        var sub = prev.lastElementChild;
        if (!sub || sub.nodeName !== type) {
            sub = document.createElement(type);
            prev.appendChild(sub);
        }
        // Its own sub-items (if any) come along and stay one level below it.
        sub.appendChild(li);
        return true;
    }

    // One level out: after the parent item in the parent list. Items that came after it in
    // the sub-list move along as its own sub-list, so the order stays the same. A top-level
    // item stays as it is (the list buttons remove the bullet).
    function outdentItem(li) {
        var list = li.parentNode;
        var parentLi = list.parentNode;
        var target;
        if (parentLi && parentLi.nodeName === 'LI') {
            target = parentLi;
        } else if (isList(parentLi)) {
            // Summernote's own nesting (<ul><li/><ul>..</ul></ul>), e.g. from older drafts.
            target = list;
        } else {
            return false;
        }
        var following = [];
        for (var n = li.nextElementSibling; n; n = n.nextElementSibling) {
            following.push(n);
        }
        if (following.length) {
            var sub = li.lastElementChild;
            if (!sub || sub.nodeName !== list.nodeName) {
                sub = document.createElement(list.nodeName);
                li.appendChild(sub);
            }
            following.forEach(function (n) {
                sub.appendChild(n);
            });
        }
        target.parentNode.insertBefore(li, target.nextSibling);
        if (!list.children.length) {
            list.parentNode.removeChild(list);
        }
        return true;
    }

    // Returns true when the selection was in a list (handled), false when it wasn't.
    function changeLevel(context, outdent) {
        var editable = context.layoutInfo.editable[0];
        var items = selectedItems(editable);
        if (!items.length) {
            return false;
        }
        var saved = saveSelection();
        withUndo(context, function () {
            var done = false;
            // In: top to bottom, so each item finds the previous one still in place.
            // Out: bottom to top, so the items after each one are already handled.
            (outdent ? items.slice().reverse() : items).forEach(function (li) {
                done = (outdent ? outdentItem(li) : indentItem(li)) || done;
            });
            return done;
        });
        restoreSelection(saved);
        return true;
    }

    // TAB / Shift+TAB in a list. Capture phase on the document: runs before Summernote's own
    // key handling, which would insert four spaces (TAB) or do nothing (Shift+TAB).
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab' || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        var editable = e.target.closest ? e.target.closest('.note-editable') : null;
        if (!editable) {
            return;
        }
        var sel = window.getSelection();
        if (!sel.rangeCount || !closestIn(sel.getRangeAt(0).startContainer, 'LI', editable)) {
            return; // Not in a list: core's behaviour (tables, spaces).
        }
        if (closestIn(sel.getRangeAt(0).startContainer, 'TD', editable)) {
            return; // A list inside a table cell: core moves between cells.
        }
        var context = contextOf(editable);
        if (!context) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        changeLevel(context, e.shiftKey);
    }, true);

    // ---------- Fullscreen ----------
    // Our CSS makes fullscreen exactly one screen (toolbar, text, FreeScout's Send bar).
    // Summernote remembers the "original" height only after switching to fullscreen, so it
    // would come back at the fullscreen height. Remember it just before the click, put it
    // back when fullscreen ends.
    document.addEventListener('mousedown', function (e) {
        var btn = e.target.closest ? e.target.closest('.note-toolbar .btn') : null;
        if (!btn || !btn.querySelector('.note-icon-arrows-alt')) {
            return;
        }
        var editor = btn.closest('.note-editor');
        if (!editor || editor.classList.contains('fullscreen')) {
            return;
        }
        var editable = editor.querySelector('.note-editable');
        editable.setAttribute('data-mfsl-height', editable.style.height || '');
        new MutationObserver(function (records, observer) {
            if (!editor.classList.contains('fullscreen')) {
                observer.disconnect();
                editable.style.height = editable.getAttribute('data-mfsl-height');
                editable.removeAttribute('data-mfsl-height');
            }
        }).observe(editor, {attributes: true, attributeFilter: ['class']});
    }, true);

    // ---------- Toolbar buttons ----------

    function iconButton(context, icon, tooltip, click) {
        var ui = $.summernote.ui;
        return ui.button({
            contents: ui.icon(icon),
            tooltip: tooltip,
            click: click
        }).render();
    }

    function lang() {
        return $.summernote.lang[$.summernote.options.lang] || $.summernote.lang['en-US'];
    }

    var buttons = {
        // In a list: one level in / out. Elsewhere: core's paragraph indent (margin).
        mfslindent: function (context) {
            return iconButton(context, 'note-icon-align-indent', lang().paragraph.indent + ' (Tab)', function () {
                context.invoke('editor.restoreRange');
                if (!changeLevel(context, false)) {
                    context.invoke('editor.indent');
                }
            });
        },
        mfsloutdent: function (context) {
            return iconButton(context, 'note-icon-align-outdent', lang().paragraph.outdent + ' (Shift+Tab)', function () {
                context.invoke('editor.restoreRange');
                if (!changeLevel(context, true)) {
                    context.invoke('editor.outdent');
                }
            });
        },
        // Alignment as one dropdown (like core's lists button) instead of three buttons.
        mfslalign: function (context) {
            var ui = $.summernote.ui;
            var p = lang().paragraph;
            var item = function (icon, title, cmd) {
                return ui.button({
                    contents: ui.icon(icon),
                    tooltip: title,
                    click: context.createInvokeHandler('editor.' + cmd)
                });
            };
            return ui.buttonGroup([
                ui.button({
                    className: 'dropdown-toggle',
                    contents: ui.dropdownButtonContents(ui.icon('note-icon-align-left'), {icons: {caret: 'note-icon-caret1'}}),
                    tooltip: p.paragraph,
                    data: {toggle: 'dropdown'}
                }),
                ui.dropdown([
                    item('note-icon-align-left', p.left, 'justifyLeft'),
                    item('note-icon-align-center', p.center, 'justifyCenter'),
                    item('note-icon-align-right', p.right, 'justifyRight')
                ])
            ], {className: 'mfsl-align'}).render();
        }
    };

    window.fsAddFilter('editor.options', function (options) {
        options.buttons = $.extend({}, options.buttons || {}, buttons);
        return options;
    });

    // Insert by name, after the button they belong with. Missing anchors (another module
    // changed the toolbar) just mean the button goes at the end of the first group.
    window.fsAddFilter('conversation.editor_toolbar', function (toolbar) {
        if (!toolbar || !toolbar[0] || !Array.isArray(toolbar[0][1])) {
            return toolbar;
        }
        var group = toolbar[0][1].slice(0);
        var add = function (after, names) {
            if (names.every(function (n) { return group.indexOf(n) !== -1; })) {
                return;
            }
            var at = group.indexOf(after);
            if (at === -1) {
                group.push.apply(group, names);
            } else {
                group.splice.apply(group, [at + 1, 0].concat(names));
            }
        };
        add('underline', ['strikethrough']);
        add('lists', ['mfslindent', 'mfsloutdent', 'mfslalign']);
        add('picture', ['hr']);
        add('codeview', ['fullscreen']);
        // Undo / Redo at the start of the same group: a group of their own takes a whole
        // toolbar row in a narrow conversation column (groups wrap as a whole).
        if (group.indexOf('undo') === -1) {
            group.unshift('undo', 'redo');
        }
        var result = toolbar.slice(0);
        result[0] = [toolbar[0][0], group];
        return result;
    });
})();
