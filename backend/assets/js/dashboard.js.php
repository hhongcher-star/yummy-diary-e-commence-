
// âœ… é”€å”®é¢ filter
function loadSalesSummary() {
    const period = document.getElementById("periodFilter").value;
    const month = document.getElementById("monthFilter").value;
    const year = document.getElementById("yearFilter").value;

    document.getElementById("monthFilter").style.display =
        period === "custom_month" ? "inline-block" : "none";

    fetch(`api/orders_api.php?type=sales_summary&period=${period}&month=${month}&year=${year}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById("salesTitle").innerText = data.title;
            document.getElementById("filteredSales").innerText =
                "RM " + Number(data.sales).toFixed(2);
        })
        .catch(() => {
            document.getElementById("filteredSales").innerText = "âŒ é”™è¯¯";
        });
}

document.getElementById("periodFilter").addEventListener("change", loadSalesSummary);
document.getElementById("monthFilter").addEventListener("change", loadSalesSummary);
document.getElementById("yearFilter").addEventListener("change", loadSalesSummary);

loadSalesSummary();

// âœ… åŠ è½½å•†å“åˆ†ç±»
function loadProductCategories() {
    fetch(`api/product_api.php?type=categories`)
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById("productCategoryFilter");
            select.innerHTML = "";

            if (!data.categories) return;

            data.categories.forEach(cat => {
                const option = document.createElement("option");
                option.value = cat.value;
                option.textContent = cat.label;
                select.appendChild(option);
            });
        });
}

// âœ… å•†å“é”€å”®åˆ†æž
function loadProductAnalysis() {
    const period = document.getElementById("productPeriodFilter").value;
    const month = document.getElementById("productMonthFilter").value;
    const year = document.getElementById("productYearFilter").value;
    const category = document.getElementById("productCategoryFilter").value;
    const sort = document.getElementById("productSortFilter").value;
    const limit = document.getElementById("productLimitFilter").value;

    document.getElementById("productMonthFilter").style.display =
        period === "custom_month" ? "inline-block" : "none";

    Promise.all([
        fetch(`api/orders_api.php?type=product_analysis&period=${period}&month=${month}&year=${year}&sort=${sort}&limit=50`).then(res => res.json()),
        fetch(`api/product_api.php?type=product_map`).then(res => res.json())
    ])
    .then(([orderData, productData]) => {
        const box = document.getElementById("productAnalysisTable");

        if (!orderData.products || orderData.products.length === 0) {
            box.innerHTML = "<p>æš‚æ— å•†å“é”€å”®è®°å½•</p>";
            return;
        }

        const productMap = productData.product_map || {};
        const productNameMap = productData.product_name_map || {};

        let merged = orderData.products.map(item => {
            const product =
                productMap[item.sku] ||
                productNameMap[item.product_name] ||
                null;

            if (!product) return null;

            return {
                ...item,
                product_name: product.name || item.product_name,
                parent_name: product.parent_name || "",
                variant_name: product.variant_name || "",
                category_key: product.category,
                category_label: product.category_label,
                stock: product.stock,
                warning_level: product.warning_level,
                image_url: product.image_url
            };
        }).filter(Boolean);

        if (category !== "all") {
            merged = merged.filter(item => item.category_key === category);
        }

        merged = merged.slice(0, Number(limit));

        if (merged.length === 0) {
            box.innerHTML = "<p>è¿™ä¸ªåˆ†ç±»æš‚æ—¶æ²¡æœ‰é”€å”®è®°å½•</p>";
            return;
        }

        let html = `
            <div class="table-scroll"><table class="dashboard-table">
                <thead>
                    <tr>
                        <th>æŽ’å</th><th>å•†å“åç§°</th><th>åˆ†ç±»</th><th>å”®å‡ºæ•°é‡</th>
                        <th>è®¢å•æ¬¡æ•°</th><th>é”€å”®é¢</th><th>å¹³å‡å•ä»·</th><th>åº“å­˜</th>
                    </tr>
                </thead>
                <tbody>
        `;

        merged.forEach((item, index) => {
            html += `
                <tr>
                    <td class="rank-data" data-label="æŽ’å">${index + 1}</td>
                    <td class="product-data" data-label="å•†å“åç§°"><div class="product-cell"><img class="product-thumb" src="${item.image_url}" alt="" onerror="this.src='<?= htmlspecialchars(productImageUrl(''), ENT_QUOTES) ?>'"><span>${item.parent_name ? `<strong>${item.parent_name}</strong><small class="product-parent">åˆ†ç±»æ¬¾å¼ï¼š${item.variant_name}</small>` : item.product_name}</span></div></td>
                    <td data-label="åˆ†ç±»">${item.category_label}</td>
                    <td data-label="å”®å‡ºæ•°é‡">${item.qty_sold}</td><td data-label="è®¢å•æ¬¡æ•°">${item.order_count}</td>
                    <td data-label="é”€å”®é¢">RM ${Number(item.sales).toFixed(2)}</td><td data-label="å¹³å‡å•ä»·">RM ${Number(item.avg_price).toFixed(2)}</td>
                    <td data-label="åº“å­˜">${item.stock}</td>
                </tr>
            `;
        });

        html += `</tbody></table></div>`;
        box.innerHTML = html;
    })
    .catch(() => {
        document.getElementById("productAnalysisTable").innerHTML = "<p>âŒ åŠ è½½é”™è¯¯</p>";
    });
}

["productPeriodFilter", "productMonthFilter", "productYearFilter", "productCategoryFilter", "productSortFilter", "productLimitFilter"]
.forEach(id => {
    document.getElementById(id).addEventListener("change", loadProductAnalysis);
});

loadProductCategories();
loadProductAnalysis();


// âœ… ä»Šæ—¥è®¢å•
fetch("api/orders_api.php")
  .then(res => res.json())
  .then(data => {
    document.getElementById("todayOrders").innerText = data.today_orders;
  })
  .catch(() => {
    document.getElementById("todayOrders").innerText = "âŒ é”™è¯¯";
  });


// âœ… è®¢å• & é”€å”®é¢è¶‹åŠ¿
fetch("api/orders_api.php?type=trend")
  .then(res => res.json())
  .then(data => {
    const ctx = document.getElementById("salesChart").getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [
          {
            label: "é”€å”®é¢ (RM)",
            data: data.sales,
            borderColor: "#ff8a34",
            backgroundColor: "rgba(255,138,52,.12)",
            fill: true,
            tension: 0.3,
            yAxisID: 'y'
          },
          {
            label: "è®¢å•æ•°",
            data: data.orders,
            borderColor: "#a66a3f",
            backgroundColor: "transparent",
            fill: false,
            tension: 0.3,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        stacked: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            type: 'linear',
            display: false,
            position: 'left',
            title: { display: true, text: 'é”€å”®é¢ (RM)' }
          },
          y1: {
            type: 'linear',
            display: false,
            position: 'right',
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'è®¢å•æ•°' }
          }
        }
      }
    });
  });


// âœ… è®¿å®¢è¶‹åŠ¿
fetch("api/visitors_api.php?type=trend")
  .then(res => res.json())
  .then(data => {
    document.getElementById("totalVisitors").innerText = data.total_visitors;

    const ctx = document.getElementById("visitorsChart").getContext("2d");
    new Chart(ctx, {
      type: "line",
      data: {
        labels: data.labels,
        datasets: [{
          label: "è®¿å®¢æ•°",
          data: data.visitors,
          borderColor: "#7867ff",
          backgroundColor: "rgba(120,103,255,.12)",
          fill: true,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        elements: { point: { radius: 0 } },
        scales: { x: { display: false }, y: { display: false } },
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  });

