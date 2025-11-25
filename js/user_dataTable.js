$(document).ready(function() {
    // Destroy the previous instance if it exists
    if ($.fn.DataTable.isDataTable('#usersTable')) {
        $('#usersTable').DataTable().destroy();
    }

    // Reinitialize the DataTable
    $('#usersTable').DataTable({
        paging: true,           // Enable pagination
        searching: true,        // Enable search functionality
        ordering: true,         // Enable column ordering
        info: true,             // Show table info (e.g., showing 1 of 10 entries)
        lengthChange: true,     // Allow changing the number of rows per page
        pageLength: 5,          // Set the default page length
        scrollY: '400px',       // Set a scrollable height for the table body
        scrollCollapse: true,   // Collapse the height if fewer entries exist
        responsive: true,       // Enable responsive extension
        
        columnDefs: [
            { "orderable": false, "targets": [0, 1, 2,  4, 6, 7, 8, 10] } // Disable sorting on these columns
        ],
        order: [], // Prevent initial ordering
        

        language: {
            paginate: {  
                previous: '<span aria-hidden="true">&laquo;</span>', // Bootstrap left arrow
                next: '<span aria-hidden="true">&raquo;</span>'      // Bootstrap right arrow
            },
            lengthMenu: "_MENU_ entries per page", // Customize length menu display
        },

        initComplete: function() {
            let searchContainer = $('#usersTable_filter');
            searchContainer.empty(); // Clear existing filter container

            // Create a flex container for horizontal alignment of the search bar
            searchContainer.append(`
                <div class="d-flex justify-content-end align-items-center" style="width:100%">
                    <div class="input-group" style="width:350px">
                        <input type="text" class="form-control form-control-md" placeholder="Search records" aria-label="Search records">
                        <span class="input-group-text">
                            <span class="material-icons" style="font-size: 22px;">search</span>
                        </span>
                    </div>
                </div>
            `);

            // Add event listener for search input
            let searchInput = searchContainer.find('input');
            searchInput.on('keyup change', function() {
                $('#usersTable').DataTable().search($(this).val()).draw();
            });
            
            // Apply Bootstrap small button classes to pagination controls
            $('.dataTables_paginate').addClass('pagination-lg mt-2');
            $('.dataTables_filter').addClass('mb-2');
            $('.dataTables_length').addClass('mb-2');
            
        }
    });
});