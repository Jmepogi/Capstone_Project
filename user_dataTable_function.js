$(document).ready(function() {
    // Handle "Select All" checkbox
    $('#selectAll').click(function() {
        var isChecked = this.checked;
        // Ensure the checkboxes within the table body are targeted
        $('#usersTable tbody input[name="user_ids[]"]').prop('checked', isChecked); // Updated to #usersTable
    });

    // Handle individual checkbox changes (uncheck "Select All" if any is unchecked)
    $('#usersTable tbody input[name="user_ids[]"]').click(function() { // Updated to #usersTable
        if (!this.checked) {
            $('#selectAll').prop('checked', false); // Uncheck "Select All" if any checkbox is unchecked
        } else if ($('#usersTable tbody input[name="user_ids[]"]:checked').length === $('#usersTable tbody input[name="user_ids[]"]').length) {
            $('#selectAll').prop('checked', true); // Check "Select All" if all checkboxes are checked
        }
    });

    // Handle "Delete Selected" action
    $('#delete-selected').click(function() {
        if ($('input[name="user_ids[]"]:checked').length === 0) {
            alert('Please select at least one user to delete.');
            return;
        }
        if (confirm('Are you sure you want to delete the selected users?')) {
            $('#action-input').val('delete'); // Set action in hidden input
            $('#bulkActionsForm').submit();
        }
    });

    // Handle "Edit Selected Year Level" action
    $('#edit-selected').click(function() {
        if ($('input[name="user_ids[]"]:checked').length === 0) {
            alert('Please select at least one user to edit.');
            return;
        }
        let newYearLevel = prompt('Enter the new Year Level:');
        if (newYearLevel !== null && newYearLevel !== '') {
            $('#action-input').val('edit'); // Set action in hidden input
            $('<input>').attr({
                type: 'hidden',
                name: 'new_year_level',
                value: newYearLevel
            }).appendTo('#bulkActionsForm');
            $('#bulkActionsForm').submit();
        }
    });

    // Handle "Disable Selected" action
    $('#disable-selected').click(function() {
        if ($('input[name="user_ids[]"]:checked').length === 0) {
            alert('Please select at least one user to disable.');
            return;
        }
        if (confirm('Are you sure you want to disable the selected users?')) {
            $('#action-input').val('disable'); // Set action in hidden input
            $('#bulkActionsForm').submit();
        }
    });

    // Handle "Enable Selected" action
    $('#enable-selected').click(function() {
        if ($('input[name="user_ids[]"]:checked').length === 0) {
            alert('Please select at least one user to enable.');
            return;
        }
        if (confirm('Are you sure you want to enable the selected users?')) {
            $('#action-input').val('enable'); // Set action in hidden input
            $('#bulkActionsForm').submit();
        }
    });
});
