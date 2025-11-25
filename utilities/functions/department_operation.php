<?php
session_start();

require '../config/system_db.php'; // or include '../config/system_db.php';

// Fetch departments for dropdown
$department_query = "SELECT id, department FROM tbl_department";
$department_result = $conn->query($department_query);

$departments = [];
if ($department_result->num_rows > 0) {
    while ($row = $department_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];

    switch ($action) {
        case 'add':
            $department_name = $_POST['department_name'];
            if (!empty($department_name)) {
                $stmt = $conn->prepare("INSERT INTO tbl_department (department) VALUES (?)");
                $stmt->bind_param("s", $department_name);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Department added successfully.";
                    $_SESSION['msg_type'] = "success";
                } else {
                    $_SESSION['message'] = "Failed to add department.";
                    $_SESSION['msg_type'] = "danger";
                }
                $stmt->close();
            }
            break;

        case 'edit':
            $department_id = $_POST['department_id'];
            $new_department_name = $_POST['new_department_name'];
            if (!empty($department_id) && !empty($new_department_name)) {
                $stmt = $conn->prepare("UPDATE tbl_department SET department = ? WHERE id = ?");
                $stmt->bind_param("si", $new_department_name, $department_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Department updated successfully.";
                    $_SESSION['msg_type'] = "success";
                } else {
                    $_SESSION['message'] = "Failed to update department.";
                    $_SESSION['msg_type'] = "danger";
                }
                $stmt->close();
            }
            break;

        case 'delete':
            $department_id = $_POST['department_id'];
            if (!empty($department_id)) {
                $stmt = $conn->prepare("DELETE FROM tbl_department WHERE id = ?");
                $stmt->bind_param("i", $department_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Department deleted successfully.";
                    $_SESSION['msg_type'] = "success";
                } else {
                    $_SESSION['message'] = "Failed to delete department.";
                    $_SESSION['msg_type'] = "danger";
                }
                $stmt->close();
            }
            break;
    }

    // Redirect to the previous page to show the alert message
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}
?>
