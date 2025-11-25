<?php
session_start(); // Start the session

// Retrieve flash messages from session
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);


require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_users";



// Function to set flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// Inside the POST handling section of your PHP code
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $user_ids = $_POST['user_ids'] ?? [];

    if (is_array($user_ids) && !empty($user_ids)) {
        $ids = implode(',', array_map('intval', $user_ids)); // Ensure IDs are integers

        switch ($action) {
            case 'delete':
                $sql = "DELETE FROM tbl_users WHERE user_id IN ($ids)";
                $message = 'User(s) successfully deleted!';
                break;
            case 'edit':
                $newYearLevel = $connection->real_escape_string($_POST['new_year_level']);
                $sql = "UPDATE tbl_users SET yr_lvl = '$newYearLevel' WHERE user_id IN ($ids)";
                $message = 'User account successfully updated!';
                break;
            case 'disable':
                $sql = "UPDATE tbl_users SET status = 'disabled' WHERE user_id IN ($ids)";
                $message = 'User(s) successfully disabled!';
                break;
            case 'enable':
                $sql = "UPDATE tbl_users SET status = 'active' WHERE user_id IN ($ids)";
                $message = 'User(s) successfully enabled!';
                break;
            default:
                $sql = "";
                $message = '';
        }

        if ($sql && $connection->query($sql) === TRUE) {
            setFlashMessage('success', $message);
        } else {
            setFlashMessage('danger', 'Error performing action: ' . $connection->error);
        }
    } else {
        setFlashMessage('danger', 'No users selected');
    }

    header("Location: ../03_admin/user.php");
    exit();
}

// Fetch data from the table to display in the HTML table
$sql = "SELECT * FROM $table";
$result = $connection->query($sql);

// Fetch counts for the bar graph
// Count users by role
$sqlRoles = "
    SELECT role, COUNT(*) as count 
    FROM $table 
    GROUP BY role";
$resultRoles = $connection->query($sqlRoles);

$roles = [];
$roleCounts = [];

if ($resultRoles) {
    while ($row = $resultRoles->fetch_assoc()) {
        $roles[] = $row['role'];
        $roleCounts[] = (int)$row['count'];
    }
}

// Count users by status
$sqlStatus = "
    SELECT status, COUNT(*) as count 
    FROM $table 
    GROUP BY status";
$resultStatus = $connection->query($sqlStatus);

$statuses = [];
$statusCounts = [];

if ($resultStatus) {
    while ($row = $resultStatus->fetch_assoc()) {
        $statuses[] = $row['status'];
        $statusCounts[] = (int)$row['count'];
    }
}

// Count users by department if applicable
$sqlDepartments = "
    SELECT department, COUNT(*) as count 
    FROM $table 
    GROUP BY department";
$resultDepartments = $connection->query($sqlDepartments);

$departments = [];
$departmentCounts = [];

if ($resultDepartments) {
    while ($row = $resultDepartments->fetch_assoc()) {
        $departments[] = $row['department'];
        $departmentCounts[] = (int)$row['count'];
    }
}

$connection->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">

     <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">

    
    <!-- Other CSS -->
    <link rel="stylesheet" href="../resources/css/user_table_chart.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

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
                <h2 class="d-title">USER MANAGEMENT</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>
            <div class="user-wrapper">
                
                <style>
                    .chart-container {
   
                        overflow-y: scroll;  /* Enable vertical scrolling */
                    }
                </style>
                <div class="dashboard-wrapper">
                    <div class="col-md-12">
                        <div class="request-card">
                            <div class="form-group">
                                <select class="form-select w-40 form-select-md mb-3 " id="datasetSelector" onchange="updateChart()">
                                    <option value="roles">User Roles Graph</option>
                                    <option value="status">User Status Graph</option>
                                    <option value="departments">Departments/Organizations Graph</option>
                                </select>
                            </div>
                            <div class="chart-container">
                                <canvas id="userChart"></canvas>
                            </div> 
                        </div>                              
                    </div>
                </div>
                <div class="user-page">                      
                    <!-- Search and action buttons -->
                    <form id="bulkActionsForm" method="POST" action="user.php" >
                         <!-- Hidden field to store selected action -->
                        <input type="hidden" name="action" id="action-input" value="">

                        <div class="d-flex justify-content-start mb-4">
                            <div class="dropdown">
                                <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                style="width: 85px;">
                                    Actions
                                </button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item" type="button" id="edit-selected">Edit Selected Year Level</button></li>
                                    <li><button class="dropdown-item" type="button" id="delete-selected">Delete Selected</button></li>
                                </ul>
                            </div>
                            <div class="dropdown ms-2">
                                <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                style="width: 85px;">
                                    Status
                                </button>
                                <ul class="dropdown-menu">
                                    <li><button class="dropdown-item" type="button" id="enable-selected">Enable Selected</button></li>
                                    <li><button class="dropdown-item" type="button" id="disable-selected">Disable Selected</button></li>
                                </ul>
                            </div>
                            <a href="../03_admin/create_user.php" class="btn btn-success btn-sm custom-button ms-2" role="button"
                                style="width: 85px;">     
                                <span>New User</span>
                            </a>
                        </div>
                        <!-- Flash message display -->
                        <?php if ($flash_message): ?>
                            <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> d-flex align-items-center ms-3" role="alert">
                                <span class='material-symbols-outlined me-2'>
                                    <?= $flash_message['type'] === 'success' ? 'check_circle' : 'error' ?>
                                </span>
                                <div><?= htmlspecialchars($flash_message['message']) ?></div>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
    
                        
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-striped ">
                                <thead>
                                    <tr>
                                        <th scope="col"><input type="checkbox" id="selectAll"></th>
                                        <th scope="col">ID</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Course</th>
                                        <th scope="col">Department/Organization</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Username</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Y.L.</th>
                                        <th scope="col">Status</th>
                                        <th scope="col"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><input type="checkbox" name="user_ids[]" value="<?= $row['user_id'] ?>"></td>
                                            <td><?= $row['user_id'] ?></td>
                                            <td><?= $row['first_name'] . ' ' . $row['last_name'] ?></td>
                                            <td><?= $row['course'] ?></td>
                                            <td><?= $row['department'] ?></td>
                                            <td><?= $row['role'] ?></td>
                                            <td><?= $row['username'] ?></td>
                                            <td><?= $row['email'] ?></td>
                                            <td><?= $row['yr_lvl'] ?></td>   
                                            <td>
                                                <span style="color: <?= $row['status'] == 'Active' ? 'green' : '#b52635' ?>;">
                                                    <?= $row['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href='edit_user.php?id=<?= $row['user_id'] ?>' 
                                                    style='color: #baab1e; font-size: 20px; text-decoration: none; margin-right: 10px;'>
                                                    <span class='material-icons'>edit</span>
                                                </a>
                                                
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    
                                </tbody>
                            </table>
                        </div>              
                    </form>
                </div>           
            </div>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!--  JS -->
    <script src="../resources/js/user_dataTable.js"></script>
    <script src="../resources/js/user_dataTable_function.js"></script>
    <script src="../resources/js/universal.js"></script>
    <script>
// Initialize the chart variable
let userChart;

// Fetch data for the chart (this would typically come from a server-side PHP script)
const roles = <?php echo json_encode($roles); ?>;
const roleCounts = <?php echo json_encode($roleCounts); ?>;

const statuses = <?php echo json_encode($statuses); ?>;
const statusCounts = <?php echo json_encode($statusCounts); ?>;

const departments = <?php echo json_encode($departments); ?>;
const departmentCounts = <?php echo json_encode($departmentCounts); ?>;

// Chart rendering function
function renderChart(labels, counts, title, isHorizontal = false) {
    const ctx = document.getElementById('userChart').getContext('2d');
    if (userChart) {
        userChart.destroy(); // Destroy previous chart instance before re-rendering
    }
    userChart = new Chart(ctx, {
        type: 'bar', // Always 'bar', but we'll manipulate the axis for horizontal
        data: {
            labels: labels,
            datasets: [{
                label: title,
                data: counts,
                backgroundColor: '#135626',
                borderWidth: 1,
                borderRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: isHorizontal ? 'y' : 'x', // Set horizontal or vertical axis
            scales: {
                x: { grid: { display: false }}, // Customize x-axis grid if needed
                y: { beginAtZero: true }
            },
            plugins: { legend: { display: false } }
        }
    });
}

// Function to update the chart based on the selected dataset
function updateChart() {
    const datasetSelector = document.getElementById('datasetSelector');
    const selectedValue = datasetSelector.value;

    switch (selectedValue) {
        case 'roles':
            renderChart(roles, roleCounts, 'User Roles', false); // Vertical bar chart
            break;
        case 'status':
            renderChart(statuses, statusCounts, 'User Status', false); // Vertical bar chart
            break;
        case 'departments':
            renderChart(departments, departmentCounts, 'Departments/Organizations', true); // Horizontal bar chart
            break;
        default:
            break;
    }
}

// Initial chart render
updateChart(); // Call this function to render the default chart on page load
</script>

</body>
</html>

