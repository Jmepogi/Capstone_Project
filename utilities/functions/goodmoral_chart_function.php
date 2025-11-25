<?php


require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_good_moral"; // Table name


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect POST data
    $course = $_POST['course'] ?? 'All';
    $month = $_POST['month'] ?? 'All';
    $year = $_POST['year'] ?? 'All';

    // Extract the start and end year from the academic year range if provided
    if ($year !== 'All') {
        list($startYear, $endYear) = explode('-', $year);
    }

    // Base query for Good Moral Requests
    $query = "SELECT MONTH(request_date) as month, COUNT(*) as count FROM $table WHERE 1=1";

    // Filter by course
    if ($course !== 'All') {
        $query .= " AND course = '" . $connection->real_escape_string($course) . "'";
    }

    // Filter by month
    if ($month !== 'All') {
        $monthNumber = date('n', strtotime($month)); // Convert month name to number
        $query .= " AND MONTH(request_date) = '$monthNumber'";
    }

    // Filter by academic year range (both start and end year)
    if ($year !== 'All') {
        $query .= " AND (YEAR(request_date) = '$startYear' OR YEAR(request_date) = '$endYear')";
    }

    $query .= " GROUP BY MONTH(request_date) ORDER BY MONTH(request_date)";

    // Execute query
    $result = $connection->query($query);

    // Prepare data for JSON response
    $months = [];
    $counts = [];

    while ($row = $result->fetch_assoc()) {
        $months[] = date('M', mktime(0, 0, 0, $row['month'], 10)); // Convert month number to short month name
        $counts[] = $row['count'];
    }

    // Return the JSON response for the chart
    echo json_encode(['months' => $months, 'counts' => $counts]);
}

$connection->close();
?>
