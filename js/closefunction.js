// Global function to fix modal issues
function fixModalBackdrop() {
    // Remove all backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    
    // Reset body classes and styles
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    
    // Optional: Reset any open modals
    const openModals = document.querySelectorAll('.modal.show');
    openModals.forEach(modal => {
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    });
}

// Function to forcefully close the modal and remove all related elements
function forceCloseModal(modalId = 'SignproposalModal') {
    // Hide the modal using Bootstrap
    const modalElement = document.getElementById(modalId);
    const modal = bootstrap.Modal.getInstance(modalElement);
    if (modal) {
        modal.hide();
    }
    
    // Manual cleanup with a slight delay
    setTimeout(function() {
        // Remove modal backdrop
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // Remove modal-open class and inline styles from body
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        
        // Optional: Hide the modal element directly
        if (modalElement) {
            modalElement.style.display = 'none';
        }
    }, 150);
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add keyboard shortcut: Press Escape key to fix modal issues
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            fixModalBackdrop();
        }
    });

    // Ensure modals work correctly
    const signProposalModal = document.getElementById('SignproposalModal');
    
    if (signProposalModal) {
        // Handle modal close events
        signProposalModal.addEventListener('hidden.bs.modal', function() {
            // Remove modal backdrop
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.remove();
            }
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            // Remove inline style from body
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        });
        
        // Handle form submission
        const form = signProposalModal.querySelector('#signatoryForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                // Let the form submit normally, but close the modal after submission
                setTimeout(function() {
                    const modal = bootstrap.Modal.getInstance(signProposalModal);
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Remove modal backdrop and cleanup
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('padding-right');
                    document.body.style.removeProperty('overflow');
                    
                    // Refresh the page after a short delay to ensure the submission completed
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }, 100);
            });
        }
        
        // Ensure close buttons work
        const closeButtons = signProposalModal.querySelectorAll('[data-bs-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = bootstrap.Modal.getInstance(signProposalModal);
                if (modal) {
                    modal.hide();
                }
                
                // Remove modal backdrop and cleanup
                setTimeout(function() {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('padding-right');
                    document.body.style.removeProperty('overflow');
                }, 150);
            });
        });
    }
}); 