<?php

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_users";


// Initialize variables for form fields
$first_name = "";
$last_name = "";
$course = "";
$department = "";
$username = "";
$user_password = "";
$role = "";
$email = "";
$yr_lvl = "";

// Initialize variables for error and success messages
$errorMessage = "";
$edit_successMessage = "";

// Check if the request method is GET (usually when loading the form)
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // If the 'id' parameter is not set, redirect to the user list page
    if (!isset($_GET["id"])) {
        header("Location: http://localhost/Capstone_Project/03_admin/user.php");
        exit;
    }

    // Get the user ID from the URL
    $id = $_GET["id"];

    // Retrieve the user's data from the database based on the ID
    $sql = "SELECT * FROM tbl_users WHERE user_id = $id";
    $result = $connection->query($sql);
    $row = $result->fetch_assoc();

    // If the user is not found, redirect to the user list page
    if (!$row) {
        header("Location: http://localhost/Capstone_Project/03_admin/user.php");
        exit;
    }

    // Populate form fields with the user's existing data
    $first_name = $row["first_name"];
    $last_name = $row["last_name"];
    $course = $row["course"];
    $current_department = $row["department"]; 
    $username = $row["username"];
    $user_password = $row["password"];
    $role = $row["role"];
    $email = $row["email"];
    $yr_lvl = $row["yr_lvl"];
} else { // If the request method is POST (when submitting the form)
    // Retrieve form data
    $id = $_POST["id"];
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $course = $_POST["course"];
    $department = $_POST["department"];
    $username = $_POST["username"];
    $new_password = $_POST["password"];
    $role = $_POST["role"];
    $email = $_POST["email"];
    $yr_lvl = $_POST["yr_lvl"];

    do {
        // Check if any of the required fields are empty
        if (empty($first_name) || empty($last_name) || empty($course) || empty($department) || empty($username) || 
            empty($role) || empty($email) || empty($yr_lvl))  {
            $errorMessage = "All fields except password are required";
            break;
        }

        // Prepare the SQL query to update the user's data
        $sql = "UPDATE tbl_users 
        SET first_name = '$first_name', last_name = '$last_name', course = '$course', department = '$department', 
            username = '$username', role = '$role', email = '$email', yr_lvl = '$yr_lvl'";

        // If a new password is provided, hash it and include it in the update
        if (!empty($new_password)) {
            $user_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql .= ", password = '$user_password'";
        }

        // Append the condition to update the correct user by ID
        $sql .= " WHERE user_id = $id";

        // Execute the SQL query
        $result = $connection->query($sql);

        // If the query fails, set an error message and exit the loop
        if (!$result) {
            $errorMessage = "Invalid query: " . $connection->error;
            break;
        }

        // Start a session to store a success message
        session_start();
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'User successfully updated!'
        ];

        // Redirect to the user list page with a success flag
        header("Location: ../03_admin/user.php?edit_success=true");
        exit;

    } while (true); // End of do-while loop
} 

// Fetch the list of departments from `tbl_department`
$sqlDept = "SELECT department FROM tbl_department";
$resultDept = $connection->query($sqlDept);

$organization = [];
if ($resultDept->num_rows > 0) {
    while ($row = $resultDept->fetch_assoc()) {
        $organization[] = $row['department'];
    }
}

include('../resources/utilities/functions/department_operation.php');  
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="../resources/css/create.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
    <style>
        .create_user-page {
            max-width: 800px;
            min-width: 450px;
            width: 95%;
            padding: 30px; /*9px*/
            margin: 15px 10px 20px 20px;
            background: #ffffff;
            box-shadow: 1px 1px 5px 1px rgba(0, 0, 0, 0.2);
            border-radius: 8px;        
        }

        .user-page .d-title {
            font-size: 18px;
        }

        .lbl {
            font-size: 14px;
        }
    </style>
    <div class="wrapper">
        <!------ Navigation Bar --------->
        <?php include('C:/xampp/htdocs/Capstone_Project/resources/utilities/sidebar/admin_sidebar.php'); ?>
        <?php include('../resources/utilities/modal/department_operation_modal.php'); ?>
        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">EDIT USER</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div> 
            </div>
            <div class="user-wrapper">
                <div class="create_user-page"> 
                    <?php
                        if (!empty($errorMessage)) {
                            echo "
                            <div class=\"alert alert-warning d-flex align-items-center\" role=\"alert\">
                                <span class='material-symbols-outlined me-2'>
                                warning
                                </span>
                                <div>
                                    $errorMessage
                                </div>
                                <button type=\"button\" class=\"btn-close ms-auto\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
                            </div>
                            ";
                        }
                    ?>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control form-control-sm" id="first_name" value="<?php echo $first_name; ?>"
                                autocomplete="on">
                            </div>
                            <div class="col">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control form-control-sm" id="last_name" value="<?php echo $last_name; ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="role" class="form-label ">Course</label>
                                <select name="course" class="form-select form-select-sm" id="course">
                                    <option value="" selected>-Select Course-</option>
                                    <option value="BS Nursing" <?php echo htmlspecialchars($course) == 'BS Nursing' ? 'selected' : ''; ?>>BS Nursing</option>
                                    <option value="BS Medical Technology" <?php echo htmlspecialchars($course) == 'BS Medical Technology' ? 'selected' : ''; ?>>BS Medical Technology</option>
                                    <option value="BS Radiologic Technology" <?php echo htmlspecialchars($course) == 'BS Radiologic Technology' ? 'selected' : ''; ?>>BS Radiologic Technology</option>
                                    <option value="BS Physical Therapy" <?php echo htmlspecialchars($course) == 'BS Physical Therapy' ? 'selected' : ''; ?>>BS Physical Therapy</option>
                                    <option value="AB Mass Communication" <?php echo htmlspecialchars($course) == 'AB Mass Communication' ? 'selected' : ''; ?>>AB Mass Communication</option>
                                    <option value="AB Economics" <?php echo htmlspecialchars($course) == 'AB Economics' ? 'selected' : ''; ?>>AB Economics</option>
                                    <option value="BS in Hospitality Management" <?php echo htmlspecialchars($course) == 'BS in Hospitality Management' ? 'selected' : ''; ?>>BS in Hospitality Management</option>
                                    <option value="BS Tourism Management" <?php echo htmlspecialchars($course) == 'BS Tourism Management' ? 'selected' : ''; ?>>BS Tourism Management</option>
                                    <option value="BS Accountancy" <?php echo htmlspecialchars($course) == 'BS Accountancy' ? 'selected' : ''; ?>>BS Accountancy</option>
                                    <option value="BS Management Accounting" <?php echo htmlspecialchars($course) == 'BS Management Accounting' ? 'selected' : ''; ?>>BS Management Accounting</option>
                                    <option value="BS Business Administration Major in Financial Management" <?php echo htmlspecialchars($course) == 'BS Business Administration Major in Financial Management' ? 'selected' : ''; ?>>BS Business Administration Major in Financial Management</option>
                                    <option value="BS Business Administration Major in Marketing Management" <?php echo htmlspecialchars($course) == 'BS Business Administration Major in Marketing Management' ? 'selected' : ''; ?>>BS Business Administration Major in Marketing Management</option>
                                    <option value="BS Business Administration Major in Human Resource Management" <?php echo htmlspecialchars($course) == 'BS Business Administration Major in Human Resource Management' ? 'selected' : ''; ?>>BS Business Administration Major in Human Resource Management</option>
                                    <option value="BS Criminology" <?php echo htmlspecialchars($course) == 'BS Criminology' ? 'selected' : ''; ?>>BS Criminology</option>
                                    <option value="BS Information Systems" <?php echo htmlspecialchars($course) == 'BS Information Systems' ? 'selected' : ''; ?>>BS Information Systems</option>
                                    <option value="BS Psychology" <?php echo htmlspecialchars($course) == 'BS Psychology' ? 'selected' : ''; ?>>BS Psychology</option>
                                    <option value="Bachelor in Secondary Education Major in Mathematics" <?php echo htmlspecialchars($course) == 'Bachelor in Secondary Education Major in Mathematics' ? 'selected' : ''; ?>>Bachelor in Secondary Education Major in Mathematics</option>
                                    <option value="Bachelor in Secondary Education Major in Science" <?php echo htmlspecialchars($course) == 'Bachelor in Secondary Education Major in Science' ? 'selected' : ''; ?>>Bachelor in Secondary Education Major in Science</option>
                                    <option value="Bachelor in Secondary Education Major in English" <?php echo htmlspecialchars($course) == 'Bachelor in Secondary Education Major in English' ? 'selected' : ''; ?>>Bachelor in Secondary Education Major in English</option>
                                    <option value="Bachelor of Culture and Arts Education" <?php echo htmlspecialchars($course) == 'Bachelor of Culture and Arts Education' ? 'selected' : ''; ?>>Bachelor of Culture and Arts Education</option>
                                    <option value="Bachelor of Elementary Education" <?php echo htmlspecialchars($course) == 'Bachelor of Elementary Education' ? 'selected' : ''; ?>>Bachelor of Elementary Education</option>
                                    <option value="Midwifery" <?php echo htmlspecialchars($course) == 'Midwifery' ? 'selected' : ''; ?>>Midwifery</option>
                                    <option value="N/A" <?php echo htmlspecialchars($course) == 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                </select>
                            </div>
                            <div class="col">
                                <label for="department" class="form-label">Department/Organization</label>
                                <div class="input-group">
                                    <select id="department" name="department" class="form-select form-select-sm">
                                        <option value="">-Select a Dept/Org-</option>
                                        
                                        <?php
                                        // Populate the dropdown with the departments from `tbl_department`
                                        foreach ($organization as $dept) {
                                            // If the current department matches, mark it as selected
                                            $selected = ($dept === $current_department) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($dept) . '" ' . $selected . '>' . htmlspecialchars($dept) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" class="form-control form-control-sm" id="username" value="<?php echo $username; ?>"
                                autocomplete="on">
                            </div>
                            <div class="col">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" class="form-control form-control-sm" id="password" value="">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label for="role" class="form-label">Role</label>
                                <select name="role" class="form-select form-select-sm" id="role">
                                    <option value="" selected>-Select Role-</option>
                                    <option value="Admin" <?php echo htmlspecialchars($role) == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="Dean/Department Head" <?php echo htmlspecialchars($role) == 'Dean/Department Head' ? 'selected' : ''; ?>>Dean/Department Head</option>
                                    <option value="Adviser/Moderator" <?php echo htmlspecialchars($role) == 'Adviser/Moderator' ? 'selected' : ''; ?>>Adviser/Moderator</option>
                                    <option value="SCSC President" <?php echo htmlspecialchars($role) == 'SCSC President' ? 'selected' : ''; ?>>SCSC President</option>
                                    <option value="Student-Proposer" <?php echo htmlspecialchars($role) == 'Student-Proposer' ? 'selected' : ''; ?>>Student-Proposer</option>
                                    <option value="Student-Regular" <?php echo htmlspecialchars($role) == 'Student-Regular' ? 'selected' : ''; ?>>Student-Regular</option>>
                                </select>
                            </div>
                            <div class="col">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" class="form-control form-control-sm" id="email" value="<?php echo $email; ?>"
                                autocomplete="on">
                            </div>
                        </div>
                        <div class="row mb-5">
                            <div class="col">
                                <label for="yr_lvl" class="form-label">School Year</label>
                                <select name="yr_lvl" class="form-select form-select-sm" id="yr_lvl">
                                    <option value="1st year" <?php echo htmlspecialchars($yr_lvl) == '1st year' ? 'selected' : ''; ?>>1st year</option>
                                    <option value="2nd year" <?php echo htmlspecialchars($yr_lvl) == '2nd year' ? 'selected' : ''; ?>>2nd year</option>
                                    <option value="3rd year" <?php echo htmlspecialchars($yr_lvl) == '3rd year' ? 'selected' : ''; ?>>3rd year</option>
                                    <option value="4th year" <?php echo htmlspecialchars($yr_lvl) == '4th year' ? 'selected' : ''; ?>>4th year</option>
                                    <option value="1st year irregular" <?php echo htmlspecialchars($yr_lvl) == '1st year irregular' ? 'selected' : ''; ?>>1st year irregular</option>
                                    <option value="2nd year irregular" <?php echo htmlspecialchars($yr_lvl) == '2nd year irregular' ? 'selected' : ''; ?>>2nd year irregular</option>
                                    <option value="3rd year irregular" <?php echo htmlspecialchars($yr_lvl) == '3rd year irregular' ? 'selected' : ''; ?>>3rd year irregular</option>
                                    <option value="4th year irregular" <?php echo htmlspecialchars($yr_lvl) == '4th year irregular' ? 'selected' : ''; ?>>4th year irregular</option>
                                    <option value="N/A" <?php echo htmlspecialchars($yr_lvl) == 'N/A' ? 'selected' : ''; ?>>N/A</option>
                                </select>
                            </div>
                            <div class="col"></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col text-end" style="margin-top: 25px;">
                                <a class="btn btn-secondary btn-m" href="../03_admin/user.php" role="button">Cancel</a>
                                <button type="submit" class="btn btn-success btn-m">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../resources/js/universal.js"></script>
</body>
</html>
