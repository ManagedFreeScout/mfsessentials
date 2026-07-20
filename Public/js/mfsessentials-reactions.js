// MFSEssentials — Note reaction bar click handling + tooltips
//
// The bar itself is rendered server-side (real counts already populated, no
// flash-of-empty-state) by the thread.meta hook in
// MFSEssentialsServiceProvider. This file handles the interactive part:
// clicking a reaction posts to the toggle endpoint and updates the DOM from
// the JSON response, no full page reload; and initializing/refreshing the
// Bootstrap tooltip that shows reactor names on hover.
//
// Event delegation on document.body (not per-element binding) because
// FreeScout re-renders conversation content on navigation without a full
// page reload -- same reason CfsAssist's module.js uses a MutationObserver
// for its own button. Delegation sidesteps the need for one here for the
// click handler: a delegated listener keeps working against freshly-rendered
// bars without having to re-attach anything. Tooltip init needs the
// MutationObserver treatment anyway (below) since Bootstrap's plugin isn't
// event-delegatable the way plain click handlers are.
//
// Tooltip mechanism (confirmed against the live main.js and the vendored
// Bootstrap 3.4.5 -- not assumed):
// - Plain title="..." on a `[data-toggle="tooltip"]` element only becomes a
//   real Bootstrap tooltip once `$(el).tooltip({container:'body'})` runs.
//   FreeScout's own global helpers for this are `initTooltip(selector)` /
//   `initTooltips()` (main.js) -- reused here rather than duplicating the
//   options, so this stays consistent with every other tooltip in the app.
// - There is no single global "content changed" event that re-triggers
//   these -- every FreeScout feature that injects tooltipped content (the
//   merge-conversation modal, conversation viewers) calls initTooltip(s)()
//   itself after its own injection point. Same approach needed here.
// - Bootstrap's init() calls fixTitle() automatically, which moves title
//   into data-original-title and clears title -- so shipping a plain
//   title="..." from the Blade view is correct; it must not be pre-emptied.
// - getTitle() re-reads data-original-title fresh on every show (not cached
//   at init), so updating that attribute directly after a click is enough
//   to refresh content -- no re-init call needed for that case.
// - The jQuery plugin bridge reuses an element's existing `bs.tooltip`
//   instance instead of creating a new one if `.tooltip()` is called again
//   on an already-initialized element -- calling initTooltip() repeatedly
//   (e.g. from the MutationObserver below) is safe, not a stacking risk.
// - The Bootstrap 3 method name is `destroy` (not `dispose` -- that's
//   Bootstrap 5), used below when a reaction's last reactor is removed.

(function () {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function initReactionTooltips() {
    if (window.jQuery && typeof initTooltip === 'function') {
      initTooltip('.mfsessentials-reactions-bar [data-toggle="tooltip"]');
    }
  }

  // FreeScout loads a different conversation's threads via AJAX without a
  // full page reload, so reaction bars (and their un-initialized tooltip
  // elements) can appear well after the page's own initTooltips() already
  // ran on document ready -- same class of problem CfsAssist's own
  // MutationObserver already solves for its toolbar button in this project.
  var observer = new MutationObserver(function () {
    if (document.querySelector('.mfsessentials-reactions-bar')) {
      initReactionTooltips();
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });

  initReactionTooltips();

  document.body.addEventListener('click', function (e) {
    var reaction = e.target.closest ? e.target.closest('.mfsessentials-reaction') : null;
    if (!reaction) return;

    var bar = reaction.closest('.mfsessentials-reactions-bar');
    if (!bar) return;

    var threadId = bar.getAttribute('data-thread-id');
    var emoji = reaction.getAttribute('data-emoji');
    if (!threadId || !emoji) return;

    if (window.jQuery && typeof fsAjax === 'function') {
      // main.js's ajaxSetup() (which primes $.ajaxSetup's X-CSRF-TOKEN header
      // from <meta name="csrf-token">) is NOT a one-time page-load call --
      // it's called fresh inside fsAjax() immediately before every request
      // fsAjax() makes. A direct jQuery.post() bypasses fsAjax() entirely, so
      // the header is never (re-)primed for it -- confirmed via live 419s
      // with no X-CSRF-TOKEN on the request at all. fsAjax() is what every
      // native FreeScout AJAX call actually goes through; using it here gets
      // a fresh header for free instead of inventing a module-specific fix.
      // Signature confirmed against main.js:892 --
      // fsAjax(data, url, success_callback, no_loader, error_callback, custom_options).
      // no_loader=true suppresses FreeScout's global spinner (wrong UX for a
      // single reaction click); error_callback/custom_options are omitted so
      // fsAjax's own default error handling (floating alert + loader reset,
      // main.js ajaxFinish()) applies, same as any other native AJAX action.
      fsAjax(
        { thread_id: threadId, emoji: emoji },
        '/mfsessentials/reactions/toggle',
        function (response) { applyResult(bar, response); },
        true
      );
    } else {
      fetch('/mfsessentials/reactions/toggle', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: 'thread_id=' + encodeURIComponent(threadId) + '&emoji=' + encodeURIComponent(emoji),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) { applyResult(bar, data); })
        .catch(function () { /* leave the bar as-is on error */ });
    }
  });

  function applyResult(bar, data) {
    if (!data || !data.counts) return;
    bar.querySelectorAll('.mfsessentials-reaction').forEach(function (el) {
      var emoji = el.getAttribute('data-emoji');
      var countEl = el.querySelector('.mfsessentials-reaction-count');
      if (countEl && Object.prototype.hasOwnProperty.call(data.counts, emoji)) {
        countEl.textContent = data.counts[emoji];
      }
      var isActive = !!(data.active && data.active[emoji]);
      el.classList.toggle('active', isActive);

      var names = (data.reactor_names && data.reactor_names[emoji]) || [];
      var wasTooltipped = el.getAttribute('data-toggle') === 'tooltip';

      if (names.length) {
        el.setAttribute('data-toggle', 'tooltip');
        el.setAttribute('data-placement', 'top');
        if (wasTooltipped) {
          // Already initialized -- Bootstrap's getTitle() re-reads
          // data-original-title fresh on every show, so this is enough to
          // refresh the content; no re-init/fixTitle call needed.
          el.setAttribute('data-original-title', names.join(', '));
        } else {
          // First reactor on a previously-empty reaction -- ship a plain
          // title, same as the initial Blade render, and let
          // initReactionTooltips() below run fixTitle()/init on it.
          el.setAttribute('title', names.join(', '));
        }
      } else {
        if (wasTooltipped && window.jQuery) {
          jQuery(el).tooltip('destroy');
        }
        el.removeAttribute('data-toggle');
        el.removeAttribute('data-placement');
        el.removeAttribute('title');
        el.removeAttribute('data-original-title');
      }
    });

    initReactionTooltips();
  }
})();
