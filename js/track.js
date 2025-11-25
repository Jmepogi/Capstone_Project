document.getElementById('trackButton').addEventListener('click', function () {
    const trackingNumber = document.getElementById('tracking_number').value;

    // Check if the tracking number is entered
    if (trackingNumber === '') {
        showBootstrapAlert('Please enter a tracking number.', 'warning');
        return;
    }

    // Fetch the tracking details using the tracking.php script
    fetch(`tracking.php?tracking_number=${trackingNumber}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showBootstrapAlert(data.error, 'danger');
                updateProgressBar('0%', 'Pending', '#c9681e');
            } else {
                const name = data.name || 'N/A';
                const progressPercentage = data.progress || 0;
                const status = data.status || 'Pending';
        
                // Update the progress bar
                updateProgressBar(`${progressPercentage}%`, status, getStatusColor(status));
        
                // Calculate estimated completion
                let estimatedCompletion = data.estimated_completion || '2-3 business days'; // Default completion time
                
                // Update estimated completion if average processing time is provided
                if (data.average_processing_time) {
                    const avgTime = parseInt(data.average_processing_time, 10);
                    const estimatedDays = Math.ceil(avgTime / (24 * 60 * 60 * 1000)); // Convert milliseconds to days
                    estimatedCompletion = `Approximately ${estimatedDays} business days from now`;
                }
                
                if (status === 'Processed') {
                    estimatedCompletion = `Completed on ${data.processed_full_date}`;
                }
        
                // Update the request details
                document.getElementById('requestDetails').innerHTML = `
                    <table class="table table-bordered">
                        <tbody>
                            <tr>
                                <th>Name</th>
                                <td>${name}</td>
                            </tr>
                            <tr>
                                <th>Request Status</th>
                                <td>${status}</td>
                            </tr>
                            <tr>
                                <th>Request Submitted</th>
                                <td>${data.request_full_date || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>Under Review</th>
                                <td>${data.progress_full_date || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>Ready for Pickup</th>
                                <td>${data.processed_full_date || 'N/A'}</td>
                            </tr>
                            <tr>
                                <th>Estimated Completion</th>
                                <td>${estimatedCompletion}</td>
                            </tr>
                            <tr>
                                <th>Email Notification</th>
                                <td> 'N/A'</td>
                            </tr>
                        </tbody>
                    </table>
                    <p>
                        For any additional inquiries or concerns, please reach out via email at 
                        <a href="mailto:cefimis01@gmail.com">cefimis01@gmail.com</a><br><br>
                    </p>
                `;
            }
        })
        .catch(error => {
            console.error('Error tracking request:', error);
            showBootstrapAlert('An error occurred while tracking your request. Please try again later.', 'danger');
        });
});

// Utility function to update the progress bar
function updateProgressBar(width, textContent, backgroundColor) {
    const progressBar = document.getElementById('progressBar');
    progressBar.style.width = width;
    progressBar.setAttribute('aria-valuenow', width);
    progressBar.textContent = textContent;
    progressBar.style.backgroundColor = backgroundColor;
}

// Utility function to get the color based on status
function getStatusColor(status) {
    switch (status) {
        case 'Pending':
            return '#c9681e';
        case 'In Progress':
            return '#3273a8';
        case 'Processed':
            return '#0d610d';
        default:
            return '';
    }
}

// Add an event listener for when the modal is closed
document.getElementById('trackingModal').addEventListener('hidden.bs.modal', function () {
    // Reset the tracking number input
    document.getElementById('tracking_number').value = '';
    
    // Reset the progress bar
    updateProgressBar('0%', 'Pending', '');
    
    // Clear the request details
    document.getElementById('requestDetails').innerHTML = '';
    
    // Clear any alerts
    document.getElementById('alertPlaceholder').innerHTML = '';
});

// Handle form submission with SweetAlert
document.getElementById('requestForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent the default form submission

    Swal.fire({
        title: 'Processing...',
        text: 'Please wait while we process your request.',
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
