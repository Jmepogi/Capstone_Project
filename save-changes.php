<?php
session_start();


require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_proposal";



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the POST data from the form
    $proposal_id = $_POST['proposal_id'];
    $title = $_POST['title']; // Assuming title is read-only and doesn't need to be updated
    $organization = $_POST['organization']; // Assuming organization is read-only and doesn't need to be updated
    $datetime_start = $_POST['datetime_start'];
    $datetime_end = $_POST['datetime_end'];
    $status = $_POST['status']; // Status is displayed but is readonly, update if needed

    // Prepare the update query
    $stmt = $connection->prepare("UPDATE tbl_proposal SET datetime_start = ?, datetime_end = ?, status = ? WHERE proposal_id = ?");
    $stmt->bind_param("sssi", $datetime_start, $datetime_end, $status, $proposal_id);

    // Execute the query
    if ($stmt->execute()) {
        // Redirect to another page or show a success message
        header("Location: scheduling.php?update=success");
        exit;
    } else {
        // Handle error, show an alert or message
        echo "Error updating record: " . $stmt->error;
    }

    $stmt->close();
}

$connection->close();
?>
