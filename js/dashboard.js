document.addEventListener("DOMContentLoaded", () => {
    const content = document.querySelector(".d-content");
    const sidebar = document.querySelector(".sidebar");
    const additionalMargin = 20; // Additional margin in pixels

    function updateContentMargin() {
        const sidebarWidth = sidebar.offsetWidth;
        content.style.marginLeft = `${sidebarWidth + additionalMargin}px`;
    }

    // Initial update
    updateContentMargin();

    // Add event listeners to update margin on resize
    window.addEventListener("resize", updateContentMargin);

    // Handle menu item clicks
    const menuItems = document.querySelectorAll(".menu-item");
    menuItems.forEach(item => {
        item.addEventListener("click", () => {
            item.classList.toggle("active");
            // Use requestAnimationFrame to ensure smooth margin updates
            requestAnimationFrame(() => {
                updateContentMargin();
            });
        });
    });

    // Observe changes in sidebar width and update margin accordingly
    const observer = new MutationObserver(() => {
        // Use requestAnimationFrame for smoother updates
        requestAnimationFrame(() => {
            updateContentMargin();
        });
    });
    observer.observe(sidebar, { attributes: true, attributeFilter: ['style'] });

    // Handle sidebar transition
    sidebar.addEventListener("transitionrun", updateContentMargin);
    sidebar.addEventListener("transitionend", updateContentMargin);
});
