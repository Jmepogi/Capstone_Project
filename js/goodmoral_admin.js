$(document).ready(function() {
    // Handle "Select All" checkbox
    $('#selectAll').click(function() {
        $('input[name="user_ids[]"]').prop('checked', this.checked);
    });

    // Handle "Delete Selected" action
    $('#delete-selected').click(function() {
        if (confirm('Are you sure you want to delete the selected users?')) {
            $('#gmform').append('<input type="hidden" name="action" value="delete">').submit();
        }
    });

    // Handle "Update Selected" action
    $('#update-selected').click(function() {
        if (confirm('Are you sure you want to update the selected users?')) {
            $('#gmform').append('<input type="hidden" name="action" value="update">').submit();
        }
    });

    // Populate email and user ID in the modal
    $('a[data-bs-toggle="modal"]').click(function() {
        var email = $(this).data('email');
        var userId = $(this).closest('tr').find('input[name="user_ids[]"]').val();
        
        $('#emailModal').find('input[name="email"]').val(email);
        $('#emailModal').find('input[name="user_id"]').val(userId);

        // Populate the subject with "Good Moral Request"
        $('#subject').val('Good Moral Request');
    });
  
    // Handle form submission inside the modal
    $('#emailModal form').on('submit', function(event) {
        event.preventDefault(); // Prevent the default form submission

        Swal.fire({
            title: 'Sending...',
            text: 'Please wait, sending your email.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading(); // Show loading spinner
            }
        });

        // Simulate a delay (replace this with actual form submission)
        setTimeout(() => {
            // Submit the form after showing the loading spinner
            event.target.submit();
        }, 1000); // 1-second delay
    });

});
