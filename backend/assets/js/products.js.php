
  const alertMsg = <?= json_encode($alert, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const alertDialog = document.getElementById('siteAlertDialog');
  const confirmDialog = document.getElementById('siteConfirmDialog');
  let pendingConfirmForm = null;

  function openSiteDialog(dialog) {
    dialog.classList.add('show');
    document.body.classList.add('dialog-open');
  }

  function closeSiteDialog(dialog) {
    dialog.classList.remove('show');
    if (!document.querySelector('.site-dialog.show')) {
      document.body.classList.remove('dialog-open');
    }
  }

  if (alertMsg) {
    document.getElementById('siteAlertMessage').textContent = alertMsg;
    openSiteDialog(alertDialog);
  }

  document.getElementById('siteAlertOk').addEventListener('click', function () {
    closeSiteDialog(alertDialog);
  });

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmed === '1') {
        return;
      }
      event.preventDefault();
      pendingConfirmForm = form;
      document.getElementById('siteConfirmMessage').textContent = form.dataset.confirm;
      openSiteDialog(confirmDialog);
    });
  });

  document.getElementById('siteConfirmCancel').addEventListener('click', function () {
    pendingConfirmForm = null;
    closeSiteDialog(confirmDialog);
  });

  document.getElementById('siteConfirmOk').addEventListener('click', function () {
    if (!pendingConfirmForm) return;
    pendingConfirmForm.dataset.confirmed = '1';
    pendingConfirmForm.requestSubmit();
  });

  [alertDialog, confirmDialog].forEach(function (dialog) {
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        pendingConfirmForm = null;
        closeSiteDialog(dialog);
      }
    });
  });

  window.addEventListener('DOMContentLoaded', function () {
    if (<?= $openCategory === '1' ? 'true' : 'false' ?>) {
      openCategoryModal();
    }
    if (<?= $openSort === '1' ? 'true' : 'false' ?>) {
      openSortModal();
    }
  });

  function openCategoryModal(){
    document.getElementById('categoryModal').classList.add('show');
  }

  function closeCategoryModal(){
    document.getElementById('categoryModal').classList.remove('show');
  }

  function openSortModal(){
    document.getElementById('sortModal').classList.add('show');
  }

  function closeSortModal(){
    document.getElementById('sortModal').classList.remove('show');
  }

  (function () {
    const groupSelect = document.getElementById('groupSelect');
    const catSelect = document.getElementById('catSelect');

    function syncCategoryOptions() {
      const group = groupSelect.value;
      const options = Array.from(catSelect.querySelectorAll('option'));
      const hasGroup = !!group;

      options.forEach(function (option) {
        const match = !hasGroup || option.getAttribute('data-group') === group || option.value === '';
        option.hidden = !match;
        option.disabled = !match;
      });

      if (group) {
        catSelect.value = '';
      }
    }

    if (groupSelect && catSelect) {
      groupSelect.addEventListener('change', function () {
        syncCategoryOptions();
      });
      syncCategoryOptions();
    }
  })();


