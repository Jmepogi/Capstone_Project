<?php

session_start();

require '../../../config/system_db.php'; // include '../config/system_db.php';

function check_schedule_conflict($date_start, $date_end, $venue) {
    global $connection;

    $sql = "SELECT status FROM tbl_proposal 
            WHERE venue = ? 
            AND NOT (datetime_end <= ? OR datetime_start >= ?)";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("sss", $venue, $date_start, $date_end);
    $stmt->execute();
    $result = $stmt->get_result();

    $error_message = "";
    $warning_message = "";

    while ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Approved') {
            $error_message = "There is an approved activity with this schedule and venue.";
        } elseif ($row['status'] === 'Pending') {
            $warning_message = "There is a pending activity with this schedule and venue.";
        }
    }

    $stmt->close();
    $connection->close();

    if ($error_message) {
        return ['type' => 'danger', 'message' => $error_message];
    } elseif ($warning_message) {
        return ['type' => 'warning', 'message' => $warning_message];
    } else {
        return ['type' => 'success', 'message' => "No conflicts detected."];
    }
}

// Capture form input and check for conflicts
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date_start = $_POST['datetime_start'];
    $date_end = $_POST['datetime_end'];
    $venue = $_POST['venue'];

    $conflict_flash_message = check_schedule_conflict($date_start, $date_end, $venue);
    $_SESSION['conflict_flash_message'] = $conflict_flash_message;
}



// Redirect to avoid form resubmission
header("Location: ../../../01_student/proposal.php");
exit();


// Close database connection
$connection->close();
?>
