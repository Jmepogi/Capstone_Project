<?php
session_start(); // Ensure session is started


require '../config/system_db.php'; // or include '../config/system_db.php';
$table = "tbl_proposal"; // New table name


// Retrieve flash messages from the session, if any, and then unset them so they don't persist across requests.
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

// Function to set flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// Fetch only approved proposals
$query = "SELECT proposal_id, title, type, organization, president, datetime_start, datetime_end, status 
          FROM tbl_proposal 
          WHERE status = 'Approved'
          ORDER BY datetime_start DESC";

$result = $connection->query($query);
$data = array();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format the date
        $datetime_start = new DateTime($row['datetime_start']);
        $formatted_date_start = $datetime_start->format('M d, Y h:i A');
        $datetime_end = new DateTime($row['datetime_end']);
        $formatted_date_end = $datetime_end->format('M d, Y h:i A');
        
        // Create the actions column
        $actions = '<div class="btn-group" role="group">
                     <button type="button" class="btn btn-primary btn-sm edit-btn" 
                             data-id="'.$row['proposal_id'].'"
                             data-course="'.$row['title'].'"
                             data-permitype="'.$row['organization'].'"
                             data-reason="'.$formatted_date_start.'"
                             data-reason-end="'.$formatted_date_end.'"
                             data-status="'.$row['status'].'">
                         <i class="material-icons">edit</i>
                     </button>
                   </div>';
        
        // Add checkbox column
        $checkbox = '<input type="checkbox" class="proposal-checkbox" value="'.$row['proposal_id'].'">';

        $data[] = array(
            "checkbox" => $checkbox,
            "proposal_id" => $row['proposal_id'],
            "title" => $row['title'],
            "type" => $row['type'],
            "organization" => $row['organization'],
            "president" => $row['president'],
            "proposed_date_start" => $formatted_date_start,
            "proposed_date_end" => $formatted_date_end,
            "status" => $row['status'],
            "actions" => $actions
        );
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACTIVITY PROPOSAL Management</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    
    <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <!-- Other CSS -->
    <link rel="stylesheet" href="../resources/css/gm_table_chart.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">  
</head>
<body>
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
    <div class="wrapper">
        <?php include('../resources/utilities/sidebar/admin_sidebar.php'); ?>
        

        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">SCHEDULE MANAGEMENT</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>

            <div class="dashboard-wrapper">
            <div class="table-responsive">
                <table id="requestTable" class="table table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Title</th>
                            <th>Organization</th>
                            <th>Date & Time Start</th>
                            <th>Date & Time End</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <td><input type="checkbox" name="user_ids[]" value="<?= $row['proposal_id']; ?>"></td>
                                    <td><?= htmlspecialchars($row['title']); ?></td>
                                    <td><?= htmlspecialchars($row['organization']); ?></td>
                                    <td><?= $row['proposed_date_start']; ?></td>
                                    <td><?= $row['proposed_date_end']; ?></td>
                                    <td>
                                        <span style="color: <?= ($row['status'] == 'Approved' ? 'green' : ($row['status'] == 'Rejected' ? '#bf5615' : '#3273a8')) ?>;">
                                            <?= htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>                     
                                    <td>
                                        <a href='edit_sched.php?id=<?= $row['proposal_id'] ?>' 
                                                    style='color: #baab1e; font-size: 20px; text-decoration: none; margin-right: 10px;'>
                                                    <span class='material-icons'>edit</span>
                                                </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No records found.</td></tr> <!-- Updated colspan -->
                        <?php endif; ?>
                    </tbody>
               </table>
            </div>
        </div>
    </div>

    <!-- Modal for viewing and editing proposal -->
    <div class="modal fade" id="signlModal" tabindex="-1" aria-labelledby="signlModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="signlModalLabel">Schedule Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="studentName" class="form-label">Title</label>
                        <input type="text" class="form-control" id="studentName" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="studentCourse" class="form-label">Organization</label>
                        <input type="text" class="form-control" id="studentCourse" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="proposalStart" class="form-label">Start Date & Time</label>
                        <input type="text" class="form-control" id="proposalStart" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="proposalEnd" class="form-label">End Date & Time</label>
                        <input type="text" class="form-control" id="proposalEnd" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="proposalStatus" class="form-label">Status</label>
                        <input type="text" class="form-control" id="proposalStatus" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="save-changes">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../resources/js/universal.js"></script>

<script>
    $(document).ready(function () {
        // Initialize DataTable
        $('#requestTable').DataTable();

        // Open modal and populate fields
        $('.edit-btn').on('click', function () {
            var proposal_id = $(this).data('id');
            var title = $(this).data('course');
            var organization = $(this).data('permitype');
            var startDate = $(this).data('reason');
            var endDate = $(this).data('reason-end');
            var status = $(this).data('status');

            // Set modal data before showing
            $('#studentName').val(title);
            $('#studentCourse').val(organization);
            $('#proposalStart').val(startDate);
            $('#proposalEnd').val(endDate);
            $('#proposalStatus').val(status);
            $('#proposal_id').val(proposal_id);

            $('#signlModal').modal('show');
        });

        // Save changes button click handler
        $('#save-changes').on('click', function () {
            $.ajax({
                type: 'POST',
                url: 'save-changes.php',
                data: {
                    proposal_id: $('#proposal_id').val(),
                    datetime_start: $('#proposalStart').val(),
                    datetime_end: $('#proposalEnd').val()
                },
                dataType: 'json',
                success: function (response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        $('#signlModal').modal('hide');
                        location.reload(); // Optionally reload the page
                    } else {
                        alert(response.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    alert('An error occurred while saving changes.');
                }
            });
        });

        // Select all checkboxes logic
        $('#selectAll').on('click', function () {
            var isChecked = $(this).prop('checked');
            $('input[name="user_ids[]"]').prop('checked', isChecked);
        });
    });
</script>
</body>
</html>
