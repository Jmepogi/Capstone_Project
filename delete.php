<?php
if (isset($_GET["id"])) {
    $id = intval($_GET["id"]);  // Sanitize the ID

    require '../config/system_db.php'; // or include '../config/system_db.php';

    // SQL query to delete the user
    $sql = "DELETE FROM tbl_users WHERE id=$id";

    // Execute the query
    if ($connection->query($sql) === TRUE) {
        session_start();
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'User deleted successfully'
        ];
    } else {
        session_start();
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Failed to delete user'
        ];
    }

    // Close the connection
    $connection->close();

    // Redirect to the user management page
    header("Location: user.php");
    exit;
}
?>
