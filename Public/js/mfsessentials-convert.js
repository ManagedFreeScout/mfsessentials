// MFSEssentials — "Convert to note" / "Convert back to email" in a thread's
// top-right menu (card freescout-modules #261).
//
// The menu item is rendered server-side by the thread.menu hook in
// MFSEssentialsServiceProvider, with the (translatable) confirmation text,
// button label and endpoint in data attributes. This file shows FreeScout's
// own confirm dialog (showModalConfirm, same as core's update confirm) and
// posts through fsAjax() -- never a bare jQuery.post(), which skips the
// X-CSRF-TOKEN priming and fails with a 419 (see README, v1.0.1).
//
// Delegated on document because FreeScout re-renders conversation content
// without a full page reload (same reason as mfsessentials-reactions.js).
// After a successful conversion the page reloads: the thread changes type,
// author and styling, and an activity line is added, so re-rendering it
// server-side is simpler and safer than patching the DOM.

(function () {
  'use strict';

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest ? e.target.closest('.mfsessentials-convert-trigger') : null;
    if (!trigger) {
      return;
    }
    e.preventDefault();

    var threadContainer = trigger.closest('.thread');
    var threadId = threadContainer ? threadContainer.getAttribute('data-thread_id') : null;
    if (!threadId || typeof showModalConfirm !== 'function' || typeof fsAjax !== 'function') {
      return;
    }

    var okClass = 'mfsessentials-convert-ok';
    showModalConfirm(escapeHtml(trigger.getAttribute('data-confirm')), okClass, {
      on_show: function (modal) {
        modal.children().find('.' + okClass + ':first').click(function () {
          modal.modal('hide');
          fsAjax(
            {
              thread_id: threadId,
              direction: trigger.getAttribute('data-direction')
            },
            trigger.getAttribute('data-url'),
            function (response) {
              if (isAjaxSuccess(response)) {
                window.location.reload();
              } else {
                showAjaxError(response);
              }
            }
          );
        });
      }
    }, escapeHtml(trigger.getAttribute('data-ok')));
  });
})();
