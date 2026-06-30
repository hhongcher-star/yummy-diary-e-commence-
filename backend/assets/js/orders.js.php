
  function toggleSelectAll(source) {
    let checkboxes = document.querySelectorAll("input[name='order_ids[]']");
    checkboxes.forEach(cb => cb.checked = source.checked);
  }

  function confirmBatchAction(event) {
    const submitter = event.submitter;

    if (submitter && ["toggle_payment_id", "mark_paid_id", "mark_unpaid_id", "archive_order_id", "delete_order_id"].includes(submitter.name)) {
      return true;
    }

    const selected = document.querySelectorAll("input[name='order_ids[]']:checked");

    if (selected.length === 0) {
      alert("è¯·å…ˆé€‰æ‹©è®¢å•");
      event.preventDefault();
      return false;
    }

    if (submitter && submitter.name === "delete_selected") {
      return confirm("ç¡®å®šè¦æ‰¹é‡åˆ é™¤é€‰ä¸­çš„è®¢å•å—ï¼Ÿæ­¤æ“ä½œä¸å¯æ¢å¤ï¼");
    }

    if (submitter && submitter.name === "mark_paid") {
      return confirm("ç¡®å®šå°†é€‰ä¸­çš„è®¢å•æ ‡è®°ä¸ºå·²ä»˜æ¬¾å—ï¼Ÿ");
    }

    if (submitter && submitter.name === "mark_unpaid") {
      return confirm("ç¡®å®šå°†é€‰ä¸­çš„è®¢å•æ ‡è®°ä¸ºæœªä»˜æ¬¾å—ï¼Ÿ");
    }

    return true;
  }

