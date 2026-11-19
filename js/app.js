/**
 * Shared frontend behaviour.
 *
 * One script serves every screen: mobile sidebar, confirmation and error
 * modals, AJAX form submission with busy buttons, toasts, the users modal and
 * small niceties (password reveal, autodismiss alerts, back to top).
 * Everything is delegated from document/window so views can be replaced by
 * AJAX without rebinding.
 */
(function () {
  'use strict';

  var token = (window.APP && window.APP.csrf)
    || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /* ------------------------------------------------------- mobile sidebar */
  function setSidebar(open) {
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar) { return; }
    sidebar.classList.toggle('open', open);
    if (backdrop) { backdrop.classList.toggle('show', open); }
  }

  document.addEventListener('click', function (event) {
    var toggle = event.target.closest('#sidebarToggle');
    if (toggle) {
      var sidebar = document.getElementById('appSidebar');
      setSidebar(!(sidebar && sidebar.classList.contains('open')));
      return;
    }

    if (event.target.closest('#sidebarBackdrop')) {
      setSidebar(false);
      return;
    }

    var sideLink = event.target.closest('.app-sidebar .sidebar-link');
    if (sideLink && window.matchMedia('(max-width: 991.98px)').matches) {
      setSidebar(false);
    }

    var passwordToggle = event.target.closest('[data-toggle-password]');
    if (passwordToggle) {
      var target = document.getElementById(passwordToggle.getAttribute('data-toggle-password'));
      if (target) {
        var show = target.type === 'password';
        target.type = show ? 'text' : 'password';
        var icon = passwordToggle.querySelector('i');
        if (icon) { icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye'; }
        passwordToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('title', show ? 'Hide password' : 'Show password');
      }
      return;
    }

    var modalTrigger = event.target.closest('[data-toggle-modal]');
    if (modalTrigger) {
      var modalId = modalTrigger.getAttribute('data-toggle-modal');
      var modalEl = document.getElementById(modalId);
      if (modalEl) {
        var payloadStr = modalTrigger.getAttribute('data-payload')
          || modalTrigger.getAttribute('data-edit')
          || null;
        var payload = payloadStr ? readJson(payloadStr) : null;
        prepareModal(modalEl, payload, modalTrigger.hasAttribute('data-empty'));
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
      return;
    }

    var edit = event.target.closest('[data-edit]');
    if (edit) {
      openUserModal(readJson(edit.getAttribute('data-edit')));
      return;
    }

    if (event.target.closest('[data-new-user]')) {
      openUserModal(null);
      return;
    }
  });

  /* --------------------------------------------- modals: forms & confirms */
  function prepareModal(modalEl, payload, isEmpty) {
    var form = modalEl.querySelector('form');
    if (!form) { return; }

    clearErrors();
    if (!form.dataset.defaultAction) { form.dataset.defaultAction = form.action; }
    form.reset();

    if (isEmpty || !payload) {
      var idField = form.querySelector('input[name="id"]');
      if (idField) { idField.value = ''; }
      form.action = form.dataset.defaultAction;
      var title = modalEl.querySelector('[data-student-title]');
      if (title) { title.textContent = title.getAttribute('data-label-add') || 'Add student'; }
      modalEl.querySelectorAll('[data-student-submit]').forEach(function (btn) {
        btn.textContent = btn.getAttribute('data-label-add') || 'Add student';
      });
      var preview = modalEl.querySelector('[data-photo-preview]');
      if (preview) { preview.src = ''; preview.classList.add('d-none'); }
      var photoInput = form.querySelector('input[type="file"][name="photo"]');
      if (photoInput) { photoInput.value = ''; }
      return;
    }

    Object.keys(payload).forEach(function (key) {
      var field = form.querySelector('[name="' + key + '"]');
      if (!field) { return; }
      var value = payload[key];
      if (field.type === 'checkbox') {
        field.checked = !!Number(value);
      } else {
        field.value = value === null || value === undefined ? '' : value;
      }
    });

    var modalTitle = modalEl.querySelector('[data-student-title]');
    if (modalTitle) {
      modalTitle.textContent = (modalTitle.getAttribute('data-label-edit') || 'Edit student') +
        (payload.admission_number ? ' · ' + payload.admission_number : '');
    }
    modalEl.querySelectorAll('[data-student-submit]').forEach(function (btn) {
      btn.textContent = btn.getAttribute('data-label-edit') || 'Save changes';
    });

    var preview = modalEl.querySelector('[data-photo-preview]');
    if (preview) {
      if (payload.photo_path) {
        var base = (window.APP && window.APP.base) || '';
        preview.src = base.replace(/\/+$/, '') + '/' + String(payload.photo_path).replace(/^\/+/, '');
        preview.classList.remove('d-none');
      } else {
        preview.src = '';
        preview.classList.add('d-none');
      }
    }
    var photoInput = form.querySelector('input[type="file"][name="photo"]');
    if (photoInput) { photoInput.value = ''; }

    var formAction = form.getAttribute('data-update-action');
    if (formAction) { form.action = formAction; }

    populateStreams(form);
  }

  function populateStreams(form) {
    var classSelect = form.querySelector('[name="current_class_id"], [name="class_id"]');
    var streamSelect = form.querySelector('[data-stream-select]');
    if (!classSelect || !streamSelect) { return; }

    var streams = (window.SSMS_STREAMS || {})[Number(classSelect.value)] || [];
    var previous = streamSelect.value;
    streamSelect.innerHTML = '<option value="0">— None —</option>';
    streams.forEach(function (stream) {
      var option = document.createElement('option');
      option.value = stream.id;
      option.textContent = stream.name;
      streamSelect.appendChild(option);
    });
    if (streams.some(function (stream) { return String(stream.id) === String(previous); })) {
      streamSelect.value = previous;
    }
  }

  document.querySelectorAll('[data-stream-select]').forEach(function (streamSelect) {
    var form = streamSelect.form;
    if (!form) { return; }
    var classSelect = form.querySelector('[name="current_class_id"], [name="class_id"]');
    if (!classSelect) { return; }
    classSelect.addEventListener('change', function () { populateStreams(form); });
    populateStreams(form);
  });

  var confirmedSubmit = null;

  /* ------------------------------------- form submits, busy state & AJAX */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.tagName !== 'FORM') { return; }

    // Confirmation can sit on the form or on the submit button that fired it.
    var message = (event.submitter && event.submitter.getAttribute('data-confirm'))
      || form.getAttribute('data-confirm');

    if (message && confirmedSubmit !== form) {
      event.preventDefault();
      friendlyConfirm(message).then(function (confirmed) {
        if (!confirmed) { return; }
        confirmedSubmit = form;
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(event.submitter || undefined);
        } else {
          form.submit();
        }
        window.setTimeout(function () { confirmedSubmit = null; }, 0);
      });
      return;
    }
    confirmedSubmit = null;

    var ajaxForm = form.closest('form[data-ajax]');
    if (ajaxForm) {
      event.preventDefault();
      submitAjax(ajaxForm);
      return;
    }

    // Normal form: prevent double submissions and show that work is happening.
    startBusy(form, event.submitter);
  });

  function startBusy(form, submitter) {
    if (form.hasAttribute('data-no-busy')) { return; }
    var button = submitter || form.querySelector('button[type="submit"], input[type="submit"]');
    if (!button || button.disabled) { return; }
    if (button.tagName === 'INPUT') {
      button.disabled = true;
      return;
    }
    button.dataset.idleHtml = button.innerHTML;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Please wait\u2026';
  }

  function resetBusyButtons() {
    document.querySelectorAll('button[data-idle-html]').forEach(function (button) {
      button.innerHTML = button.dataset.idleHtml;
      button.disabled = false;
      button.removeAttribute('aria-busy');
      delete button.dataset.idleHtml;
    });
  }

  window.addEventListener('pageshow', resetBusyButtons);

  /**
   * Friendly confirmation dialog used by every [data-confirm] control.
   * Falls back to window.confirm when Bootstrap is unavailable.
   */
  function friendlyConfirm(message) {
    if (!window.bootstrap || !bootstrap.Modal) {
      return Promise.resolve(window.confirm(message));
    }

    return new Promise(function (resolve) {
      var destructive = /delete|remove|reject|void|deactivate|withdraw|expire/i.test(message);
      var host = document.createElement('div');
      host.innerHTML =
        '<div class="modal fade" tabindex="-1" aria-hidden="true">'
        + '<div class="modal-dialog modal-dialog-centered">'
        + '<div class="modal-content">'
        + '<div class="modal-header"><h5 class="modal-title">Please confirm</h5>'
        + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
        + '<div class="modal-body"><p class="mb-0"></p></div>'
        + '<div class="modal-footer">'
        + '<button type="button" class="btn btn-light" data-bs-dismiss="modal" autofocus>Go back</button>'
        + '<button type="button" class="btn ' + (destructive ? 'btn-danger' : 'btn-primary') + '" data-confirm-yes>Yes, continue</button>'
        + '</div></div></div></div>';

      var modalEl = host.firstElementChild;
      modalEl.querySelector('.modal-body p').textContent = message;
      document.body.appendChild(host);

      var modal = new bootstrap.Modal(modalEl);
      var confirmed = false;
      modalEl.querySelector('[data-confirm-yes]').addEventListener('click', function () {
        confirmed = true;
        modal.hide();
      });
      modalEl.addEventListener('hidden.bs.modal', function () {
        host.remove();
        resolve(confirmed);
      });
      modal.show();
    });
  }

  document.querySelectorAll('[data-submit-on-change]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.form) { select.form.submit(); }
    });
  });

  function submitAjax(form) {
    clearErrors();

    var button = form.querySelector('button[type="submit"]');
    if (button) {
      button.dataset.idleHtml = button.innerHTML;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving\u2026';
    }

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
      .then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          return { ok: response.ok && data.ok, data: data };
        });
      })
      .then(function (result) {
        var data = result.data || {};
        if (result.ok) {
          showErrors({});
          toast('success', data.message || 'Saved.');
          if (data.redirect) {
            window.setTimeout(function () { window.location.href = data.redirect; }, 500);
          }
        } else {
          showErrors(data.errors || {});
          toast('danger', data.message || 'Please check the form and try again.');
        }
      })
      .catch(function () {
        toast('danger', 'The request failed. Check your connection and try again.');
      })
      .finally(function () {
        if (button) {
          button.innerHTML = button.dataset.idleHtml || button.innerHTML;
          button.disabled = false;
          button.removeAttribute('aria-busy');
          delete button.dataset.idleHtml;
        }
      });
  }

  function showErrors(errors) {
    document.querySelectorAll('.is-invalid').forEach(function (el) {
      el.classList.remove('is-invalid');
    });
    document.querySelectorAll('[data-error-for]').forEach(function (el) {
      el.textContent = '';
    });

    Object.keys(errors || {}).forEach(function (key) {
      var slot = document.querySelector('[data-error-for="' + cssEscape(key) + '"]');
      if (!slot) { return; }
      slot.textContent = errors[key];
      var field = slot.previousElementSibling;
      if (field && field.classList) { field.classList.add('is-invalid'); }
    });
  }

  function clearErrors() { showErrors({}); }

  function toast(type, message) {
    var wrap = document.getElementById('toastWrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'toastWrap';
      wrap.className = 'toast-container';
      document.body.appendChild(wrap);
    }

    var el = document.createElement('div');
    el.className = 'app-toast app-toast-' + type;
    el.setAttribute('role', 'status');
    el.textContent = message;
    wrap.appendChild(el);

    window.requestAnimationFrame(function () { el.classList.add('show'); });
    window.setTimeout(function () {
      el.classList.remove('show');
      window.setTimeout(function () { el.remove(); }, 300);
    }, 4500);
  }

  /* ------------------------------------------------------- users modal */
  function syncUserLinkFields() {
    var form = document.getElementById('userForm');
    if (!form) { return; }
    var typeField = form.querySelector('[name="user_type"]');
    var type = typeField ? typeField.value : 'staff';

    form.querySelectorAll('[data-link-group]').forEach(function (group) {
      var active = group.getAttribute('data-link-group') === type;
      group.classList.toggle('d-none', !active);
      var select = group.querySelector('select');
      if (select) { select.disabled = !active; }
    });
  }

  document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'f_user_type') {
      syncUserLinkFields();
    }
  });

  function openUserModal(user) {
    var form = document.getElementById('userForm');
    if (!form) { return; }

    var title = document.getElementById('userModalTitle');
    var password = form.querySelector('[name="password"]');

    clearErrors();
    form.reset();
    syncUserLinkFields();
    form.action = (window.APP && window.APP.urls && window.APP.urls.store) || form.action;
    if (title) { title.textContent = 'Add user'; }
    if (password) {
      password.required = true;
      password.placeholder = 'At least 8 characters';
    }

    if (user) {
      form.action = (window.APP && window.APP.urls && window.APP.urls.update) || form.action;
      if (title) { title.textContent = 'Edit user · ' + user.username; }

      ['id', 'username', 'full_name', 'email', 'phone', 'user_type', 'role_id', 'staff_id', 'guardian_id', 'student_id'].forEach(function (field) {
        var input = form.querySelector('[name="' + field + '"]');
        if (input) { input.value = user[field] === null || user[field] === undefined ? '' : user[field]; }
      });
      syncUserLinkFields();

      if (password) {
        password.required = false;
        password.value = '';
        password.placeholder = 'Leave blank to keep current password';
      }
    }

    var modalElement = document.getElementById('userModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
  }

  document.querySelectorAll('table[data-table]').forEach(function (table) {
    if (!window.DataTable) { return; }

    // Views render a single "no records" row with colspan. DataTables counts
    // DOM cells rather than spanning columns, so that row makes every header
    // past the first look missing ("Requested unknown parameter '1'"). Let
    // DataTables provide its own empty message instead.
    var body = table.tBodies[0];
    if (body) {
      var rows = body.querySelectorAll('tr');
      if (rows.length === 1) {
        var cells = rows[0].querySelectorAll('td');
        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
          body.removeChild(rows[0]);
        }
      }
    }

    new window.DataTable(table, {
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      order: [[0, 'asc']],
      responsive: true,
      language: {
        search: 'Search:',
        searchPlaceholder: 'Search records\u2026',
        lengthMenu: 'Show _MENU_ per page',
        info: 'Showing _START_\u2013_END_ of _TOTAL_',
        infoEmpty: 'No records',
        infoFiltered: '(filtered from _MAX_ total)',
        zeroRecords: 'No matching records. Try a different search.',
        emptyTable: 'Nothing here yet \u2014 records will appear once they are added.',
        paginate: { previous: 'Previous', next: 'Next' }
      }
    });
  });

  var chartCanvas = document.getElementById('classChart');
  if (chartCanvas && window.Chart) {
    var labels = parseJson(chartCanvas.getAttribute('data-labels'), []);
    var values = parseJson(chartCanvas.getAttribute('data-values'), []);

    new window.Chart(chartCanvas, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values,
          backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2'],
          borderWidth: 3,
          borderColor: '#ffffff',
          hoverOffset: 6
        }]
      },
      options: {
        cutout: '68%',
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 16 }
          },
          tooltip: {
            backgroundColor: '#0f172a',
            padding: 10,
            cornerRadius: 8,
            displayColors: true
          }
        }
      }
    });
  }

  /* ------------------------------------------------------------- helpers */
  function readJson(value) {
    try { return JSON.parse(value || 'null'); } catch (error) { return null; }
  }

  function parseJson(value, fallback) {
    try { return JSON.parse(value || 'null') || fallback; } catch (error) { return fallback; }
  }

  function cssEscape(value) {
    if (window.CSS && window.CSS.escape) { return window.CSS.escape(value); }
    return value.replace(/["\\]/g, '\\$&');
  }

  /* ------------------------------------------------------------ niceties */

  // Success messages clear themselves, but pause while the pointer is over
  // them so nobody loses the message they are reading.
  document.querySelectorAll('[data-autodismiss]').forEach(function (alert) {
    var delay = parseInt(alert.getAttribute('data-autodismiss'), 10) || 8000;
    var timer = null;

    function dismiss() {
      if (window.bootstrap && bootstrap.Alert) {
        bootstrap.Alert.getOrCreateInstance(alert).close();
      } else {
        alert.remove();
      }
    }
    function schedule(ms) {
      window.clearTimeout(timer);
      timer = window.setTimeout(dismiss, ms);
    }

    schedule(delay);
    alert.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
    alert.addEventListener('mouseleave', function () { schedule(2500); });
    alert.addEventListener('focusin', function () { window.clearTimeout(timer); });
  });

  // Back-to-top button, revealed once the page is scrolled.
  var scrollTop = document.getElementById('scrollTop');
  if (scrollTop) {
    var onScroll = function () {
      scrollTop.classList.toggle('show', window.scrollY > 320);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    scrollTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Confirmation fields marked data-match="source-id" must equal the source.
  document.querySelectorAll('[data-match]').forEach(function (input) {
    var source = document.getElementById(input.getAttribute('data-match'));
    if (!source) { return; }

    function check() {
      var mismatch = input.value !== '' && input.value !== source.value;
      input.setCustomValidity(mismatch ? 'The passwords do not match.' : '');
    }
    input.addEventListener('input', check);
    source.addEventListener('input', check);
  });

  // Gentle Caps Lock reminder on password fields.
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    var hint = null;
    function toggle(on) {
      if (on && !hint) {
        hint = document.createElement('div');
        hint.className = 'help-text';
        hint.innerHTML = '<i class="bi bi-exclamation-circle"></i><span>Caps Lock is on</span>';
        (input.closest('.mb-3, .mb-4, .field-group') || input.parentElement).appendChild(hint);
      } else if (!on && hint) {
        hint.remove();
        hint = null;
      }
    }
    ['keydown', 'keyup'].forEach(function (type) {
      input.addEventListener(type, function (event) {
        if (typeof event.getModifierState === 'function') {
          toggle(event.getModifierState('CapsLock'));
        }
      });
    });
    input.addEventListener('blur', function () { toggle(false); });
  });

  // Give compact icon-only controls a readable label for tooltips and
  // screen readers when the surrounding table already explains the action.
  var ICON_LABELS = {
    'bi-trash': 'Delete', 'bi-trash3': 'Delete', 'bi-pencil': 'Edit',
    'bi-pencil-square': 'Edit', 'bi-eye': 'View', 'bi-download': 'Download',
    'bi-printer': 'Print', 'bi-x-lg': 'Close', 'bi-x-circle': 'Remove',
    'bi-check2': 'Confirm', 'bi-arrow-repeat': 'Refresh'
  };
  document.querySelectorAll('a.btn, button.btn').forEach(function (el) {
    if (el.hasAttribute('aria-label') || el.hasAttribute('title')) { return; }
    if (el.textContent.trim() !== '') { return; }
    var icon = el.querySelector('i.bi');
    if (!icon) { return; }
    var cls = Array.prototype.find.call(icon.classList, function (name) {
      return ICON_LABELS[name];
    });
    if (!cls) { return; }
    el.setAttribute('aria-label', ICON_LABELS[cls]);
    el.setAttribute('title', ICON_LABELS[cls]);
  });

  // Top header date and time. The server renders the first value, then this
  // keeps it ticking in the application timezone without a page reload.
  document.querySelectorAll('[data-live-datetime]').forEach(function (clock) {
    var dateEl = clock.querySelector('[data-datetime-date]');
    var timeEl = clock.querySelector('[data-datetime-time]');
    if (!dateEl || !timeEl || typeof Intl === 'undefined') { return; }

    var zone = clock.getAttribute('data-timezone') || undefined;
    var dateFmt;
    var timeFmt;
    try {
      dateFmt = new Intl.DateTimeFormat('en-GB', {
        weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: zone
      });
      timeFmt = new Intl.DateTimeFormat('en-GB', {
        hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true, timeZone: zone
      });
    } catch (error) {
      return;
    }

    function tick() {
      var now = new Date();
      dateEl.textContent = dateFmt.format(now);
      timeEl.textContent = timeFmt.format(now).replace(/\b(am|pm)\b/i, function (part) {
        return part.toUpperCase();
      });
    }
    tick();
    window.setInterval(tick, 1000);
  });

  /* ------------------------------------------------------ form experience */

  // Find the visible label that belongs to a control, whether it is linked by
  // for/id, wrapped around the control, or sits in the same field column.
  function formLabelFor(control) {
    if (control.id) {
      var linked = document.querySelector('label[for="' + cssEscape(control.id) + '"]');
      if (linked) { return linked; }
    }
    var wrapping = control.closest('label');
    if (wrapping) { return wrapping; }
    var parent = control.parentElement;
    for (var depth = 0; parent && depth < 3; depth++) {
      var label = parent.querySelector('label');
      if (label) { return label; }
      parent = parent.parentElement;
    }
    return null;
  }

  // Give every form the same finishing touches without touching each view:
  // required labels marked once, data-entry cards flagged for styling, action
  // rows grouped into a consistent footer and a hint about the asterisk.
  function enhanceForms() {
    document.querySelectorAll('form').forEach(function (form) {
      if (form.hasAttribute('data-no-form-enhance')) { return; }

      var inTable = !!form.closest('td');
      var modal = form.closest('.modal');
      var inline = inTable || form.classList.contains('d-inline');
      var controls = Array.prototype.filter.call(
        form.querySelectorAll('input, select, textarea'),
        function (el) {
          var type = (el.getAttribute('type') || '').toLowerCase();
          return ['hidden', 'submit', 'button', 'reset'].indexOf(type) === -1;
        }
      );
      if (!controls.length) { return; }

      var requiredCount = 0;
      controls.forEach(function (control) {
        if (!control.required) { return; }
        control.setAttribute('aria-required', 'true');
        requiredCount++;
        var type = (control.getAttribute('type') || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') { return; }
        var label = formLabelFor(control);
        if (!label || label.querySelector('.text-danger, .req')) { return; }
        // Skip labels that already print an asterisk as plain text.
        if (/\*\s*$/.test(label.textContent)) { return; }
        label.classList.add('is-required');
      });

      if (inline || modal) { return; }

      var method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method === 'post' && controls.length >= 3) {
        var card = form.closest('.card');
        if (card) { card.classList.add('form-surface'); }
      }
      if (method !== 'post' || controls.length < 4) { return; }

      // The action row is the last block that holds buttons but no fields.
      var last = form.lastElementChild;
      if (!last) { return; }

      var bareSubmit = last.matches('button[type="submit"], input[type="submit"], .btn');
      if (bareSubmit && form.classList.contains('row') === false) {
        var bar = document.createElement('div');
        bar.className = 'form-actions';
        form.insertBefore(bar, last);
        bar.appendChild(last);
        last = bar;
      } else if (!last.matches('.form-actions')) {
        var hasFields = last.querySelector('input:not([type="hidden"]), select, textarea');
        var hasButton = last.querySelector('button[type="submit"], input[type="submit"], .btn');
        if (!last.matches('button, input, select, textarea') && !hasFields && hasButton) {
          last.classList.add('form-actions');
        } else {
          return;
        }
      }

      if (requiredCount >= 3 && !last.querySelector('.form-note')) {
        var note = document.createElement('span');
        note.className = 'form-note';
        note.innerHTML = 'Fields marked <span class="req">*</span> are required.';
        last.insertBefore(note, last.firstChild);
      }
    });
  }

  enhanceForms();

  // Put the cursor in the first sensible field whenever a form modal opens.
  document.querySelectorAll('.modal').forEach(function (modalEl) {
    modalEl.addEventListener('shown.bs.modal', function () {
      var form = modalEl.querySelector('form');
      if (!form || form.hasAttribute('data-no-autofocus')) { return; }

      var target = form.querySelector('[autofocus]');
      if (!target) {
        target = Array.prototype.find.call(
          form.querySelectorAll('input, select, textarea'),
          function (el) {
            var type = (el.getAttribute('type') || '').toLowerCase();
            return type !== 'hidden' && type !== 'file' && !el.disabled
              && !el.readOnly && el.offsetParent !== null;
          }
        );
      }
      if (target) {
        window.setTimeout(function () {
          target.focus({ preventScroll: true });
        }, 60);
      }
    });
  });

})();
