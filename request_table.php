<?php
session_start(); // Ensure session is started

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_good_moral"; // Table name



// Retrieve flash messages from the session, if any, and then unset them so they don't persist across requests.
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

// Function to set flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// Handle POST requests for delete/update actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? ''; // Get the action from the form submission
    $user_ids = $_POST['user_ids'] ?? []; // Get the selected user IDs from the form

    if (is_array($user_ids) && !empty($user_ids)) {
        // Convert user IDs into a comma-separated string
        $ids = implode(',', array_map('intval', $user_ids));

        switch ($action) {
            case 'delete':
                $sql = "DELETE FROM $table WHERE id IN ($ids)";
                $message = 'User(s) successfully deleted!';
                break;
            case 'update':
                $sql = "UPDATE $table SET status = 'In Progress', progress_date = NOW() WHERE id IN ($ids)";
                $message = 'User status successfully updated to In Progress!';
                break;
            default:
                $sql = "";
                $message = '';
        }

        // Execute the SQL query and set a flash message based on the result
        if ($sql && $connection->query($sql) === TRUE) {
            setFlashMessage('success', $message);
        } else {
            setFlashMessage('danger', 'Error performing action: ' . $connection->error);
        }
    } else {
        setFlashMessage('danger', 'No users selected');
    }

    // Redirect back to the page
    header("Location: request_table.php");
    exit();
}


// Query for calculating average processing time (in days)
$sqlAvgProcessingTime = "
    SELECT AVG(DATEDIFF(processed_date, request_date) + TIME_TO_SEC(TIMEDIFF(processed_date, request_date)) / 86400) AS avg_processing_time
    FROM $table
    WHERE processed_date IS NOT NULL"; // Only include processed requests

$resultAvgProcessingTime = $connection->query($sqlAvgProcessingTime);

// Fetch the result
$avgProcessingTime = 0;
if ($resultAvgProcessingTime && $row = $resultAvgProcessingTime->fetch_assoc()) {
    $avgProcessingTime = $row['avg_processing_time']; // This will be in decimal days
}

// Function to convert decimal days into days, hours, and minutes
function convertToDaysHoursMinutes($decimalDays) {
    $days = floor($decimalDays);
    $hours = floor(($decimalDays - $days) * 24);
    $minutes = round((($decimalDays - $days) * 24 - $hours) * 60);
    return [$days, $hours, $minutes];
}

list($days, $hours, $minutes) = convertToDaysHoursMinutes($avgProcessingTime);


// Query to calculate the number of pending requests based on the 'status' column
$sqlPendingRequests = "
    SELECT COUNT(*) AS pending_count 
    FROM $table 
    WHERE status = 'Pending'";
$resultPendingRequests = $connection->query($sqlPendingRequests);

// Fetch pending requests count
$pendingRequests = 0;
if ($resultPendingRequests && $row = $resultPendingRequests->fetch_assoc()) {
    $pendingRequests = $row['pending_count'];
}

// Query for last month’s pending requests based on the 'status' column
$sqlLastMonthPendingRequests = "
    SELECT COUNT(*) AS last_month_pending 
    FROM $table 
    WHERE status = 'Pending' 
    AND MONTH(request_date) = MONTH(CURDATE()) - 1";
$resultLastMonthPending = $connection->query($sqlLastMonthPendingRequests);

// Fetch last month’s pending requests count
$lastMonthPendingRequests = 0;
if ($resultLastMonthPending && $row = $resultLastMonthPending->fetch_assoc()) {
    $lastMonthPendingRequests = $row['last_month_pending'];
}

// Calculate the percentage change in pending requests
$pendingChangePercent = 0;
if ($lastMonthPendingRequests > 0) {
    $pendingChangePercent = (($pendingRequests - $lastMonthPendingRequests) / $lastMonthPendingRequests) * 100;
}

$pendingChangePercentFormatted = number_format($pendingChangePercent, 2);



// Query to calculate the number of 'In Progress' requests based on the 'status' column
$sqlInProgressRequests = "
    SELECT COUNT(*) AS in_progress_count 
    FROM $table 
    WHERE status = 'In Progress'";
$resultInProgressRequests = $connection->query($sqlInProgressRequests);

// Fetch 'In Progress' requests count
$inProgressRequests = 0;
if ($resultInProgressRequests && $row = $resultInProgressRequests->fetch_assoc()) {
    $inProgressRequests = $row['in_progress_count'];
}

// Query for last month’s 'In Progress' requests based on the 'status' column
$sqlLastMonthInProgressRequests = "
    SELECT COUNT(*) AS last_month_in_progress 
    FROM $table 
    WHERE status = 'In Progress' 
    AND MONTH(request_date) = MONTH(CURDATE()) - 1";
$resultLastMonthInProgress = $connection->query($sqlLastMonthInProgressRequests);

// Fetch last month’s 'In Progress' requests count
$lastMonthInProgressRequests = 0;
if ($resultLastMonthInProgress && $row = $resultLastMonthInProgress->fetch_assoc()) {
    $lastMonthInProgressRequests = $row['last_month_in_progress'];
}

// Calculate the percentage change in 'In Progress' requests
$inProgressChangePercent = 0;
if ($lastMonthInProgressRequests > 0) {
    $inProgressChangePercent = (($inProgressRequests - $lastMonthInProgressRequests) / $lastMonthInProgressRequests) * 100;
}

$inProgressChangePercentFormatted = number_format($inProgressChangePercent, 2);


// Function to get the slowest processing course
function getSlowestProcessingCourse($connection, $table) {
    // Query to calculate average processing time for each course
    $sqlSlowestProcess = "
        SELECT course, 
               AVG(DATEDIFF(processed_date, request_date)) AS avg_processing_time
        FROM $table
        WHERE processed_date IS NOT NULL
        GROUP BY course
        ORDER BY avg_processing_time DESC
        LIMIT 1"; // Get only the slowest course

    $resultSlowestProcess = $connection->query($sqlSlowestProcess);

    // Initialize variables for slowest process info
    $slowestCourse = "N/A";
    $averageProcessingTime = 0;

    if ($resultSlowestProcess && $row = $resultSlowestProcess->fetch_assoc()) {
        $slowestCourse = $row['course'];
        $averageProcessingTime = $row['avg_processing_time'];
    }

    // Convert average processing time from decimal days to days, hours, and minutes
    list($days, $hours, $minutes) = convertToDaysHoursMinutes($averageProcessingTime);

    // Prepare the output
    return [
        'course' => htmlspecialchars($slowestCourse),
        'average_time' => [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
        ]
    ];
}

// Example usage
$slowestProcessingInfo = getSlowestProcessingCourse($connection, $table);



// Query for Good Moral Requests per month
$sqlGoodMoral = "
    SELECT MONTHNAME(request_date) as month, COUNT(*) as count 
    FROM $table 
    GROUP BY MONTH(request_date)
    ORDER BY MONTH(request_date)";
$resultGoodMoral = $connection->query($sqlGoodMoral);

$months = [];
$goodMoralCounts = [];

// Process Good Moral Requests result
while ($row = $resultGoodMoral->fetch_assoc()) {
    $months[] = date('M', strtotime($row['month'])); // Convert to abbreviation
    $goodMoralCounts[] = $row['count'];
}


$monthsJson = json_encode($months);
$goodMoralCountsJson = json_encode($goodMoralCounts);


// Fetch data from the table to display in the HTML table
$sql = "SELECT * FROM $table";
$result = $connection->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Good Moral Request Management</title>
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
        <?php include('../resources/utilities/modal/email_modal.php'); ?>
        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">REQUEST MANAGEMENT</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>
            <div class="user-wrapper">
                <div class="insight-wrapper row">
                    <!-- Card 1 -->
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Average Processing Time</h6>
                            <h4><?php
                            if ($days > 0) {
                                echo $days . ' Days ';
                            }
                            if ($hours > 0) {
                                echo $hours . ' Hours ';
                            }
                            if ($minutes > 0 || ($days == 0 && $hours == 0)) {
                                echo $minutes . ' Minutes';
                            }
                            ?></h4>
                            <p><span style="color: green;">Based on processed requests</span></p>
                        </div>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Pending Requests</h6>
                            <h4><?php echo $pendingRequests; ?></h4> <!-- Dynamic data for pending requests -->
                            <p>
                                <?php if ($pendingChangePercent >= 0): ?>
                                    <span style="color: green;">+<?php echo $pendingChangePercentFormatted; ?>% vs Last Month</span>
                                <?php else: ?>
                                    <span style="color: red;"><?php echo $pendingChangePercentFormatted; ?>% vs Last Month</span>
                                <?php endif; ?>
                            </p> <!-- Trend info -->
                        </div>
                    </div>
                    
                    <!-- Card 3 -->
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>In Progress Requests</h6>
                            <h4><?php echo $inProgressRequests; ?></h4> <!-- Dynamic data for 'In Progress' requests -->
                            <p>
                                <?php if ($inProgressChangePercent >= 0): ?>
                                    <span style="color: green;">+<?php echo $inProgressChangePercentFormatted; ?>% vs Last Month</span>
                                <?php else: ?>
                                    <span style="color: red;"><?php echo $inProgressChangePercentFormatted; ?>% vs Last Month</span>
                                <?php endif; ?>
                            </p> <!-- Trend info -->
                        </div>
                    </div>

                    <!-- HTML Structure for 'Slowest Process' Card -->
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Slowest Process for the Month</h6>
                            <h4><?php echo $slowestProcessingInfo['course']; ?></h4> <!-- Course name -->
                            <p style="color: #bf5615;">Processing Time: 
                                <?php
                                    if ($slowestProcessingInfo['average_time']['days'] > 0) {
                                        echo $slowestProcessingInfo['average_time']['days'] . ' Days ';
                                    }
                                    if ($slowestProcessingInfo['average_time']['hours'] > 0) {
                                        echo $slowestProcessingInfo['average_time']['hours'] . ' Hours ';
                                    }
                                    if ($slowestProcessingInfo['average_time']['minutes'] > 0 || 
                                    ($slowestProcessingInfo['average_time']['days'] == 0 && 
                                        $slowestProcessingInfo['average_time']['hours'] == 0)) {
                                        echo $slowestProcessingInfo['average_time']['minutes'] . ' Minutes';
                                    }
                                ?>
                            </p> <!-- Average processing time -->
                        </div>
                    </div>

                </div>


                <div class="dashboard-wrapper">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="request-card">
                                <h6>Good Moral Request Graph</h6>
                                <div class="chart-container">
                                    <canvas id="goodMoralChart"></canvas>
                                </div>
                            </div>                               
                        </div>
                        <div class="col-md-4">
                            <form class="filter-options" method="POST" action="../resources/utilities/functions/goodmoral_chart_function.php">
                                <label for="courseFilter">Course:</label>
                                <select id="courseFilter" class="form-select">
                                    <option value="All">All</option>
                                    <option value="BS Nursing">BS Nursing</option>
                                    <option value="BS Medical Technology">BS Medical Technology</option>
                                    <option value="BS Radiologic Technology">BS Radiologic Technology</option>
                                    <option value="BS Physical Therapy">BS Physical Therapy</option>
                                    <option value="AB Mass Communication">AB Mass Communication</option>
                                    <option value="AB Economics">AB Economics</option>
                                    <option value="BS in Hospitality Management">BS in Hospitality Management</option>
                                    <option value="BS Tourism Management">BS Tourism Management</option>
                                    <option value="BS Accountancy">BS Accountancy</option>
                                    <option value="BS Management Accounting">BS Management Accounting</option>
                                    <option value="BS Business Administration Major in Financial Management">BS Business Administration Major in Financial Management</option>
                                    <option value="BS Business Administration Major in Marketing Management">BS Business Administration Major in Marketing Management</option>
                                    <option value="BS Business Administration Major in Human Resource Management">BS Business Administration Major in Human Resource Management</option>
                                    <option value="BS Criminology">BS Criminology</option>
                                    <option value="BS Information Systems">BS Information System</option>
                                    <option value="BS Psychology">BS Psychology</option>
                                    <option value="Bachelor in Secondary Education Major in Mathematics">Bachelor in Secondary Education Major in Mathematics</option>
                                    <option value="Bachelor in Secondary Education Major in Science">Bachelor in Secondary Education Major in Science</option>
                                    <option value="Bachelor in Secondary Education Major in English">Bachelor in Secondary Education Major in English</option>
                                    <option value="Bachelor of Culture and Arts Education">Bachelor of Culture and Arts Education</option>
                                    <option value="Bachelor of Elementary Education">Bachelor of Elementary Education</option>
                                    <option value="Midwifery">Midwifery</option>
                                </select>
                                <div class="row mt-2">
                                    <div class="col">
                                        <label for="monthFilter">Month:</label>
                                        <select id="monthFilter" class="form-select">
                                            <option value="All">All</option>
                                            <option value="Jan">Jan</option>
                                            <option value="Feb">Feb</option>
                                            <option value="Mar">Mar</option>
                                            <option value="Mar">Apr</option>
                                            <option value="Mar">May</option>
                                            <option value="Mar">Jun</option>
                                            <option value="Mar">Jul</option>
                                            <option value="Mar">Aug</option>
                                            <option value="Mar">Sep</option>
                                            <option value="Mar">Oct</option>
                                            <option value="Mar">Nov</option>
                                            <option value="Mar">Dec</option>
                                        </select>
                                    </div>
                                    <div class="col">
                                        <label for="yearFilter">Year:</label>
                                        <select id="yearFilter" class="form-select">
                                            
                                        </select>
                                    </div>

                                </div>

                                <div class="text-end">
                                    <button id="applyFilter" class="btn btn-success btn-sm mt-2">Apply Filter</button>
                                </div>                               
                                </form>
                        </div> 
                    </div>
                </div>
                <div class="user-page">
                    <?php if ($flash_message): ?>
                        <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> d-flex align-items-center ms-3" role="alert">
                            <span class='material-symbols-outlined me-2'>
                                <?= $flash_message['type'] === 'success' ? 'check_circle' : 'error' ?>
                            </span>
                            <div><?= htmlspecialchars($flash_message['message']) ?></div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form id="gmform" method="POST" action="request_table.php">
                        <!-- Hidden field to store selected action -->
                        <input type="hidden" name="action" id="action-input" value="">

                    
                        <div class="table-responsive">
                            <table id="requestTable" class="table table-striped ">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="selectAll"></th>
                                        <th>Name</th>
                                        <th>Course</th>
                                        <th>Year Level</th>
                                        <th>Semester</th>
                                        <th>School Year</th> 
                                        <th>Educational Status</th>                                     
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><input type="checkbox" name="user_ids[]" value="<?= $row['id']; ?>"></td>
                                                <td><?= htmlspecialchars($row['name']); ?></td>
                                                <td><?= htmlspecialchars($row['course']); ?></td>
                                                <td><?= htmlspecialchars($row['year_level']); ?></td>
                                                <td><?= htmlspecialchars($row['semester']); ?></td>
                                                <td><?= htmlspecialchars($row['school_year']); ?></td>
                                                <td><?= htmlspecialchars($row['student_status']); ?></td>
                                                <td><?= htmlspecialchars($row['request_date']); ?></td>
                                                <td>
                                                    <span style="color: <?= ($row['status'] == 'Processed' ? 'green' : ($row['status'] == 'In Progress' ? '#3273a8' : '#bf5615')) ?>;">
                                                        <?= htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>                     
                                                <td>
                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#emailModal"
                                                        style="color: #3273a8; font-size: 20px; text-decoration: none; margin-right: 10px;"
                                                        data-email="<?= htmlspecialchars($row['email']); ?>">
                                                        <span class="material-symbols-outlined">mail</span>
                                                    </a>

                                                    <a type="button" id="update-selected"
                                                        style="color: #baa125; font-size: 20px; text-decoration: none; margin-right: 10px;">
                                                        <span class="material-symbols-outlined">update</span>
                                                    </a>

                                                    <a type="button" id="delete-selected"
                                                        style="color: #8c220f; font-size: 20px; text-decoration: none; margin-right: 10px;">
                                                        <span class="material-symbols-outlined">delete</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="11">No records found.</td></tr>
                                    <?php endif; ?>
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
    <!-- Sweet Alert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!--  JS -->
    <script src="../resources/js/goodmoral_table.js"></script>
    <!--<script src="../resources/js/goodmoral_chart.js"></script>-->
    <script src="../resources/js/goodmoral_admin.js"></script>
    <script src="../resources/js/universal.js"></script>
    <script>
     document.addEventListener('DOMContentLoaded', function() {
    // Get current year
    var currentYear = new Date().getFullYear();
    
    // Number of years to generate in the past and future
    var yearRange = 20; // You can increase or decrease this range

    // Get the year select element
    var yearSelect = document.getElementById('yearFilter');
    
    // Generate academic year options in both directions
    for (var i = -yearRange; i <= yearRange; i++) {
        var startYear = currentYear + i;
        var endYear = startYear + 1;
        var option = document.createElement('option');
        option.value = startYear + '-' + endYear;
        option.textContent = startYear + '-' + endYear;
        yearSelect.appendChild(option);
    }

    // Automatically select the current academic year
    yearSelect.value = (currentYear - 1) + '-' + currentYear;

    // Rest of your chart logic below
    var goodMoralCtx = document.getElementById('goodMoralChart').getContext('2d');
    var goodMoralChart;

    // Chart rendering function
    function renderChart(months, counts) {
        if (goodMoralChart) {
            goodMoralChart.destroy(); // Destroy previous chart instance before re-rendering
        }
        goodMoralChart = new Chart(goodMoralCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Good Moral Requests',
                    data: counts,
                    backgroundColor: '#135626',
                    borderWidth: 1,
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { display: false }},
                    y: { beginAtZero: true }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // Initial chart data
    var months = <?php echo $monthsJson; ?>;
    var goodMoralCounts = <?php echo $goodMoralCountsJson; ?>;
    renderChart(months, goodMoralCounts);

    // Filter button event listener
    document.getElementById('applyFilter').addEventListener('click', function(event) {
        // Prevent default form submission
        event.preventDefault();

        var courseFilter = document.getElementById('courseFilter').value;
        var monthFilter = document.getElementById('monthFilter').value;
        var yearFilter = document.getElementById('yearFilter').value;

        // AJAX request to fetch filtered data
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../resources/utilities/functions/goodmoral_chart_function.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                renderChart(response.months, response.counts); // Re-render chart with filtered data
            }
        };

        // Sending filter data (course, month, academic year) to the backend
        xhr.send('course=' + courseFilter + '&month=' + monthFilter + '&year=' + yearFilter);
    });
});

    </script>
   
</body>
</html>

