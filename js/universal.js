document.addEventListener("DOMContentLoaded", () => {
    const content = document.querySelector(".d-content");
    const sidebar = document.querySelector(".sidebar");
    const menuIcon = document.querySelector(".menu-icon");
    const additionalMargin = 20; // Additional margin in pixels
    let previousSidebarWidth = sidebar.offsetWidth;
    let isExpanded = false; // Track if sidebar is expanded

    function updateContentMargin() {
        if (sidebar && content) {
            const sidebarWidth = sidebar.offsetWidth;
            if (sidebarWidth !== previousSidebarWidth) {
                previousSidebarWidth = sidebarWidth;
                content.style.marginLeft = `${sidebarWidth + additionalMargin}px`;
            }
        } else {
            console.error("Sidebar or content element not found");
        }
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
        });
    });

    // Observe changes in sidebar width and update margin accordingly
    const resizeObserver = new ResizeObserver(entries => {
        for (let entry of entries) {
            if (entry.target === sidebar) {
                updateContentMargin();
            }
        }
    });

    resizeObserver.observe(sidebar);

    // Handle menu icon click to expand/collapse sidebar
    menuIcon.addEventListener("click", () => {
        isExpanded = !isExpanded;
        if (isExpanded) {
            sidebar.classList.add("expanded");
        } else {
            sidebar.classList.remove("expanded");
        }
        updateContentMargin(); // Adjust content margin after sidebar toggle
    });

    // Handle sidebar hover to collapse/expand
    sidebar.addEventListener("mouseenter", () => {
        sidebar.classList.add("expanded");
        isExpanded = true;
    });

    sidebar.addEventListener("mouseleave", () => {
        sidebar.classList.remove("expanded");
        isExpanded = false;
        // Remove active class from all menu items when the sidebar collapses
        menuItems.forEach(item => {
            item.classList.remove("active");
        });
    });  
});

function printProposal() {
    var proposalForm = document.querySelector('.proposal-page');
    proposalForm.classList.add('print-form');
    window.print();
    proposalForm.classList.remove('print-form');
}


