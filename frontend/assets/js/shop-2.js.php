
    window.sortOrderDirty = false;
    window.originalSortOrder = [];

    window.updateSortSaveState = function (text, className) {
      const state = document.getElementById('sortSaveState');
      if (!state) return;
      state.textContent = text;
      state.className = `sort-save-state ${className || ''}`;
    };

    window.captureSortOrder = function () {
      const container = document.querySelector('.shop-content');
      window.originalSortOrder = Array.from(container.querySelectorAll('.product-card')).map(card => card.dataset.productId);
    };

    window.refreshSortNumbers = function () {
      const cards = Array.from(document.querySelectorAll('.shop-content .product-card'));
      cards.forEach((card, index) => {
        const input = card.querySelector('.sort-position-input');
        if (input) {
          input.value = index + 1;
          input.max = cards.length;
        }
      });
    };

    window.enableAdminSorting = function () {
      const container = document.querySelector('.shop-content');
      if (!container || container.dataset.sortReady === '1') return;
      container.dataset.sortReady = '1';
      let dragged = null;

      container.addEventListener('dragstart', event => {
        const card = event.target.closest('.product-card');
        if (!card) return;
        dragged = card;
        card.classList.add('dragging');
        event.dataTransfer.effectAllowed = 'move';
      });

      container.addEventListener('dragover', event => {
        event.preventDefault();
        const card = event.target.closest('.product-card');
        if (!card || card === dragged) return;
        container.querySelectorAll('.drag-target').forEach(item => item.classList.remove('drag-target'));
        card.classList.add('drag-target');
        const box = card.getBoundingClientRect();
        container.insertBefore(dragged, event.clientY > box.top + box.height / 2 ? card.nextSibling : card);
      });

      container.addEventListener('dragend', () => {
        if (!dragged) return;
        dragged.classList.remove('dragging');
        container.querySelectorAll('.drag-target').forEach(item => item.classList.remove('drag-target'));
        dragged = null;
        window.sortOrderDirty = true;
        window.refreshSortNumbers();
        window.updateSortSaveState('æœ‰æœªä¿å­˜çš„æŽ’åºä¿®æ”¹', 'dirty');
      });

      container.addEventListener('change', event => {
        const input = event.target.closest('.sort-position-input');
        if (!input) return;
        const card = input.closest('.product-card');
        const cards = Array.from(container.querySelectorAll('.product-card'));
        const targetPosition = Math.max(1, Math.min(cards.length, Number(input.value) || 1));
        const remainingCards = cards.filter(item => item !== card);
        const referenceCard = remainingCards[targetPosition - 1] || null;

        if (referenceCard) {
          container.insertBefore(card, referenceCard);
        } else {
          container.appendChild(card);
        }

        window.sortOrderDirty = true;
        window.refreshSortNumbers();
        window.updateSortSaveState('æœ‰æœªä¿å­˜çš„æŽ’åºä¿®æ”¹', 'dirty');
      });
    };

    document.getElementById('sortSaveButton').addEventListener('click', async () => {
      const container = document.querySelector('.shop-content');
      const activeCategory = document.querySelector('.cat-link.active')?.dataset.cat || '';
      const ids = Array.from(container.querySelectorAll('.product-card')).map(card => Number(card.dataset.productId));
      if (!activeCategory) return;

      window.updateSortSaveState('ä¿å­˜ä¸­...', '');
      const data = new FormData();
      data.append('save_order', '1');
      data.append('category', activeCategory);
      data.append('ordered_ids', JSON.stringify(ids));

      try {
        const response = await fetch(<?= json_encode(appUrl('backend/product_sort.php')) ?>, {method:'POST', body:data});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'ä¿å­˜å¤±è´¥');
        window.sortOrderDirty = false;
        window.captureSortOrder();
        window.updateSortSaveState('é¡ºåºå·²ä¿å­˜', 'success');
      } catch (error) {
        window.updateSortSaveState(error.message || 'ä¿å­˜å¤±è´¥', 'dirty');
      }
    });

    document.getElementById('sortResetButton').addEventListener('click', () => {
      const container = document.querySelector('.shop-content');
      window.originalSortOrder.forEach(id => {
        const card = container.querySelector(`.product-card[data-product-id="${id}"]`);
        if (card) container.appendChild(card);
      });
      window.sortOrderDirty = false;
      window.refreshSortNumbers();
      window.updateSortSaveState('å·²è¿˜åŽŸåˆ°ä¸Šæ¬¡ä¿å­˜é¡ºåº', '');
    });

    window.addEventListener('beforeunload', event => {
      if (!window.sortOrderDirty) return;
      event.preventDefault();
      event.returnValue = '';
    });

    window.captureSortOrder();
    window.refreshSortNumbers();
    window.enableAdminSorting();
  
