$(document).ready(function() {
    // Destroy the previous instance if it exists
    if ($.fn.DataTable.isDataTable('#proposalTable')) {
        $('#proposalTable').DataTable().destroy();
    }

    // Reinitialize the DataTable
    $('#proposalTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        lengthChange: true,
        pageLength: 5,
        scrollY: '400px', // Set the height of the scrollable area (adjust as needed)
        scrollCollapse: true, // Collapse scroll if fewer entries
        responsive: true, // Enable responsive extension
        columnDefs: [
            { "orderable": false, "targets": [ 0, 1, 7  ] } // Disable sorting on these columns
        ],
        order: [],

        language: {
            paginate: {  
                previous: '<span aria-hidden="true">&laquo;</span>', // Bootstrap left arrow
                next: '<span aria-hidden="true">&raquo;</span>'      // Bootstrap right arrow
            },
            lengthMenu: "_MENU_ entries per page", // Customize length menu display
        },

        initComplete: function() {
            let searchContainer = $('#proposalTable_filter');
            searchContainer.empty(); // Clear existing filter container

            // Create a flex container for horizontal alignment
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

            let searchInput = searchContainer.find('input');
            searchInput.on('keyup change', function() {
                $('#proposalTable').DataTable().search($(this).val()).draw();
            });

            // Apply Bootstrap small button classes to pagination
            $('.dataTables_paginate').addClass('pagination-lg mt-2');
            $('.dataTables_filter').addClass('mb-2');
            $('.dataTables_length').addClass('mb-2');
        }
    });
});