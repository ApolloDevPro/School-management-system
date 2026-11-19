(function () {
  'use strict';

  var token = (window.APP && window.APP.csrf)
    || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

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

  document.addEventListener('submit', function (event) {
    var confirmable = event.target.closest('[data-confirm]');
    if (confirmable && !window.confirm(confirmable.getAttribute('data-confirm'))) {
      event.preventDefault();
      return;
    }

    var form = event.target.closest('form[data-ajax]');
    if (!form) { return; }

    event.preventDefault();
    submitAjax(form);
  });

  document.querySelectorAll('[data-submit-on-change]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.form) { select.form.submit(); }
    });
  });

  function submitAjax(form) {
    clearErrors();

    var button = form.querySelector('button[type="submit"]');
    if (button) { button.disabled = true; }

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
        if (button) { button.disabled = false; }
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
    el.textContent = message;
    wrap.appendChild(el);

    window.requestAnimationFrame(function () { el.classList.add('show'); });
    window.setTimeout(function () {
      el.classList.remove('show');
      window.setTimeout(function () { el.remove(); }, 300);
    }, 4500);
  }

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
      language: { search: 'Filter:', emptyTable: 'No records to display.' }
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
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        plugins: { legend: { position: 'bottom' } },
        maintainAspectRatio: false
      }
    });
  }

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
})();
