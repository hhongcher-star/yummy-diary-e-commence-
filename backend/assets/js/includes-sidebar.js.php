
const mobileMoreButton = document.getElementById("mobileMoreBtn");
const mobileMoreMenu = document.getElementById("mobileMoreMenu");

mobileMoreButton?.addEventListener("click", function () {
    const isOpen = mobileMoreMenu.classList.toggle("show");
    mobileMoreButton.setAttribute("aria-expanded", isOpen ? "true" : "false");
});

document.addEventListener("click", function (event) {
    if (!mobileMoreMenu?.classList.contains("show")) return;
    if (mobileMoreMenu.contains(event.target) || mobileMoreButton.contains(event.target)) return;
    mobileMoreMenu.classList.remove("show");
    mobileMoreButton.setAttribute("aria-expanded", "false");
});

