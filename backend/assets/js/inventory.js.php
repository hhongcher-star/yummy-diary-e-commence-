
(function () {
  const reportModal = document.getElementById('reportModal');
  const setReportOpen = open => { reportModal.classList.toggle('show', open); document.body.style.overflow = open ? 'hidden' : ''; };
  document.getElementById('openReport')?.addEventListener('click', () => setReportOpen(true));
  document.getElementById('closeReport')?.addEventListener('click', () => setReportOpen(false));
  reportModal?.addEventListener('click', event => { if (event.target === reportModal) setReportOpen(false); });
  document.querySelectorAll('.report-tab').forEach(tab => tab.addEventListener('click', () => {
    const selected = tab.dataset.reportView;
    document.querySelectorAll('.report-tab').forEach(item => item.setAttribute('aria-selected', String(item === tab)));
    document.querySelectorAll('.report-view').forEach(view => { view.hidden = view.dataset.view !== selected; });
  }));
  document.querySelectorAll('.order-detail-toggle').forEach(button => button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    button.closest('.order-detail-card').querySelector('.order-detail-items').hidden = !open;
  }));
  document.querySelectorAll('.group-toggle').forEach(button => button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(open));
    button.textContent = open ? 'æ”¶èµ·åˆ†ç±»' : 'æŸ¥çœ‹åˆ†ç±»';
    document.querySelectorAll('.variant-inventory-row[data-parent-id="' + button.dataset.groupId + '"]').forEach(row => row.classList.toggle('is-collapsed', !open));
  }));
  const groupSelect = document.getElementById('groupSelect');
  const catSelect = document.getElementById('catSelect');

  function syncCategoryOptions() {
    const group = groupSelect.value;
    const options = Array.from(catSelect.querySelectorAll('option'));

    options.forEach(function (option) {
      const match = !group || option.getAttribute('data-group') === group || option.value === '';
      option.hidden = !match;
      option.disabled = !match;
    });
  }

  if (groupSelect && catSelect) {
    groupSelect.addEventListener('change', function () {
      catSelect.value = '';
      syncCategoryOptions();
    });

    syncCategoryOptions();
  }
})();

