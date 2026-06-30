
    AOS.init({ duration: 800, offset: 100, once: true });
    window.addEventListener("load", () => {
      const loader = document.getElementById("loader");
      const content = document.getElementById("content");
      setTimeout(() => {
        loader.classList.add("fade-out");
        setTimeout(() => {
          loader.style.display = "none";
          content.classList.add("show");
        }, 600);
      }, 3000);
    });
  
