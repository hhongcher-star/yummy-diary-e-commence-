
document.addEventListener("DOMContentLoaded", () => {
  const searchBox = document.getElementById("searchBox");
  const suggestionsBox = document.getElementById("suggestions");

  searchBox.addEventListener("keyup", () => {
    const query = searchBox.value.trim();
    if (query.length < 1) {
      suggestionsBox.style.display = "none";
      return;
    }

    fetch(<?= json_encode(appUrl('api/search-suggest')) ?> + "?q=" + encodeURIComponent(query))
      .then(res => res.json())
      .then(data => {
        suggestionsBox.innerHTML = "";
        if (data.length > 0) {
          data.forEach(item => {
            const div = document.createElement("div");
            div.textContent = `[${item.sku}] ${item.name}`;
            div.addEventListener("click", () => {
              searchBox.value = item.name;
              window.location.href = <?= json_encode(appUrl('search')) ?> + "?q=" + encodeURIComponent(item.name);
            });
            suggestionsBox.appendChild(div);
          });
          suggestionsBox.style.display = "block";
        } else {
          suggestionsBox.style.display = "none";
        }
      });
  });

  // ç‚¹å‡»å¤–é¢æ—¶å…³é—­æç¤ºæ¡†
  document.addEventListener("click", (e) => {
    if (!searchBox.contains(e.target) && !suggestionsBox.contains(e.target)) {
      suggestionsBox.style.display = "none";
    }
  });
});

