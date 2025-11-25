$(document).ready(function() {
    // Disable all DataTables warnings globally
    $.fn.dataTable.ext.errMode = 'none';
    
    // Define column widths based on content type - optimized for your specific table structure
    const columnWidths = [
        '30%',  // Title - wider for text content
        '30%',  // Type
        '10%',  // Organization - wider for organization names
        '10%',  // President
        '10%',  // Proposed Date
        '10%',  // Status
        '5%'   // Actions
    ];
    
    // Define table configurations
    const tableConfigs = [
        {
            tableId: '#facultyproposalTable',
            searchPlaceholder: 'Search records',
            emptyMessage: 'No records found.'
        },
        {
            tableId: '#archivedProposalTable',
            searchPlaceholder: 'Search archived records',
            emptyMessage: 'No archived proposals found.'
        }
    ];
    
    // Initialize both tables with the same configuration pattern
    tableConfigs.forEach(config => {
        const tableId = config.tableId;
        const tableIdNoHash = tableId.substring(1);
        
        // Destroy the previous instance if it exists
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }
        
        // Initialize DataTable
        $(tableId).DataTable({
            paging: true,
            searching: true,
            ordering: true,
            info: true,
            lengthChange: true,
            pageLength: 5,
            scrollY: '400px',
            scrollCollapse: true,
            responsive: true,
            autoWidth: false, // Disable auto width to use our custom widths
            
            // Create column definitions with specific widths
            columns: [
                { width: columnWidths[0] }, // Title
                { width: columnWidths[1] }, // Type
                { width: columnWidths[2] }, // Organization
                { width: columnWidths[3] }, // President
                { width: columnWidths[4] }, // Proposed Date
                { width: columnWidths[5] }, // Status
                { width: columnWidths[6], orderable: false } // Actions (not sortable)
            ],
            
            columnDefs: [
                { className: "align-middle", targets: "_all" } // Add vertical centering to all cells
            ],
            
            order: [[4, 'desc']], // Default sort by Proposed Date, newest first
            
            // Override DataTables messaging
            language: {
                paginate: {
                    previous: '<span aria-hidden="true">&laquo;</span>',
                    next: '<span aria-hidden="true">&raquo;</span>'
                },
                lengthMenu: "_MENU_ entries per page",
                emptyTable: config.emptyMessage,
                zeroRecords: "No matching records found."
            },
            
            // Custom rendering for empty tables
            drawCallback: function(settings) {
                if (settings.aiDisplay.length === 0) {
                    if ($(tableId + ' tbody tr td[colspan]').length === 0) {
                        $(tableId + ' tbody').html('<tr><td colspan="7">' + config.emptyMessage + '</td></tr>');
                    }
                }
                
                // Ensure pagination is visible
                $(tableId + '_paginate').show();
                $(tableId + '_info').show();
            },
            
            initComplete: function() {
                // Create a custom container for the table search
                let searchContainer = $(tableId + '_filter');
                searchContainer.empty();
                
                const searchInputId = 'customSearch' + tableIdNoHash;
                
                searchContainer.append(`
                    <div class="d-flex justify-content-end align-items-center" style="width:100%">
                        <div class="input-group" style="width:350px">
                            <input type="text" class="form-control form-control-md" id="${searchInputId}" 
                                   placeholder="${config.searchPlaceholder}" aria-label="${config.searchPlaceholder}">
                            <span class="input-group-text">
                                <span class="material-icons" style="font-size: 22px;">search</span>
                            </span>
                        </div>
                    </div>
                `);
                
                let searchInput = searchContainer.find('#' + searchInputId);
                searchInput.on('keyup change', function() {
                    $(tableId).DataTable().search($(this).val()).draw();
                });
                
                // Make sure pagination is visible and properly styled
                $(tableId + '_paginate').show();
                $(tableId + '_info').show();
                $(tableId + '_wrapper .dataTables_paginate').addClass('pagination-lg mt-2');
                $(tableId + '_wrapper .dataTables_info').addClass('mt-2');
                $(tableId + '_wrapper .dataTables_filter').addClass('mb-2');
                $(tableId + '_wrapper .dataTables_length').addClass('mb-2');
                
                // Force the layout to be applied correctly
                setTimeout(() => {
                    $(tableId).DataTable().columns.adjust().responsive.recalc();
                }, 100);
            }
        });
    });
});