document.addEventListener("DOMContentLoaded", function () {
  const tabButtons = document.querySelectorAll(".mel-tab-button");
  const tabSections = document.querySelectorAll(".mel-dashboard-tab");

  if (!tabButtons.length || !tabSections.length) return;

  tabButtons.forEach((button) => {
    button.addEventListener("click", (e) => {
      e.preventDefault();

      const target = button.getAttribute("data-tab");

      // Remove active classes
      tabButtons.forEach((btn) => btn.classList.remove("is-active"));
      tabSections.forEach((section) => section.style.display = "none");

      // Add active class to clicked tab
      button.classList.add("is-active");

      // Show selected section
      const targetSection = document.getElementById(target);
      if (targetSection) {
        targetSection.style.display = "block";
      }
    });
  });

  // ✅ Show default tab (first one)
  const defaultTab = document.querySelector(".mel-tab-button.is-active")?.getAttribute("data-tab");
  if (defaultTab) {
    document.getElementById(defaultTab).style.display = "block";
  }
});