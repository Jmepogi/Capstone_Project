$(document).ready(function() {
    // Handle "Select All" checkbox
    $('#selectAll').click(function() {
        $('input[name="user_ids[]"]').prop('checked', this.checked);
    });

    // Handle "Edit Selected" action
    $('#edit-selected').click(function() {
        $('#bulkActionsForm').append('<input type="hidden" name="action" value="edit">').submit();
    });

    // Handle "Delete Selected" action
    $('#delete-selected').click(function() {
        if (confirm('Are you sure you want to delete the selected users?')) {
            $('#bulkActionsForm').append('<input type="hidden" name="action" value="delete">').submit();
        }
    });

    // Populate email and user ID in the modal
    $('a[data-bs-toggle="modal"]').click(function() {
        var email = $(this).data('email');
        var userId = $(this).closest('tr').find('input[name="user_ids[]"]').val();
        
        $('#emailModal').find('input[name="email"]').val(email);
        $('#emailModal').find('input[name="user_id"]').val(userId);
    });


    $(document).ready(function() {
        $('#signlModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var name = button.data('name'); // Extract info from data-* attributes
            var course = button.data('course');
            var year = button.data('year');
            var permitType = button.data('permitype');
            var reason = button.data('reason');
            var requestDate = button.data('requestdate');
            var status = button.data('status');
            
            var modal = $(this);
            modal.find('#studentName').val(name);
            modal.find('#studentCourse').val(course);
            modal.find('#studentYear').val(year);
            modal.find('#permitType').val(permitType);
            modal.find('#studentReason').val(reason);
            modal.find('#requestDate').val(requestDate);
            modal.find('#permitStatus').val(status);
        });
    });
    
});
