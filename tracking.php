<?php
session_start();

require '../config/system_db.php'; // or include '../config/system_db.php';
$table = "tbl_good_moral";

// Function to add business days
function add_business_days($start_date, $days) {
    $date = new DateTime($start_date);
    $i = 0;
    while ($i < $days) {
        $date->modify('+1 day');
        // If it's a weekend, skip it (1=Monday, 7=Sunday)
        if ($date->format('N') < 6) {
            $i++;
        }
    }
    return $date;
}

// Calculate average business days for completed requests
function get_average_completion_time($connection, $table) {
    $sql = "SELECT AVG(DATEDIFF(processed_date, request_date)) AS average_days
            FROM $table
            WHERE status = 'Processed'";
    
    $result = $connection->query($sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return ceil($row['average_days']); // Round up to the next whole day
    }
    return 2; // Default to 2 days if no data is available
}

// Handle tracking number request
if (isset($_GET['tracking_number'])) {
    header('Content-Type: application/json');

    $tracking_number = $connection->real_escape_string($_GET['tracking_number']);

    // Get the average completion time from past requests
    $average_days = get_average_completion_time($connection, $table);

    $sql = "SELECT tracking_number, status, name,
            CASE 
                WHEN status = 'Pending' THEN 33
                WHEN status = 'In Progress' THEN 66
                WHEN status = 'Processed' THEN 100
            END AS progress,
            DATE_FORMAT(request_date, '%M %d %Y %H:%i:%s') AS request_full_date,
            DATE_FORMAT(progress_date, '%M %d %Y %H:%i:%s') AS progress_full_date,
            DATE_FORMAT(processed_date, '%M %d %Y %H:%i:%s') AS processed_full_date,
            request_date, progress_date, processed_date
            FROM $table
            WHERE tracking_number = '$tracking_number'";

    $result = $connection->query($sql);
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Extract status and request date
        $status = $data['status'];
        $request_date = $data['request_date'];

        // Set estimated completion days based on status or use average
        if ($status == 'Processed') {
            $days_to_complete = 0;
        } else {
            $days_to_complete = $average_days; // Use the calculated average
        }

        // Calculate the estimated completion date
        $estimated_completion_date = add_business_days($request_date, $days_to_complete);

        // Add the estimated completion date to the response data
        $data['estimated_completion'] = $estimated_completion_date->format('F j, Y');

        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Tracking number not found']);
    }

    $connection->close();
    exit();
}

$connection->close();
?>
