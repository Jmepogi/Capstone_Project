<?php
require '../config/system_db.php'; // or include '../config/system_db.php';

$first_name = $last_name = $course = $department = $username = $user_password = $role = $email = $yr_lvl = "";
$errorMessage = $user_errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve POST data
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $course = $_POST["course"];
    $department = $_POST["department"];
    $username = $_POST["username"];
    $user_password = $_POST["password"];
    $role = $_POST["role"];
    $email = $_POST["email"];
    $yr_lvl = $_POST["yr_lvl"];

    // Check for empty fields
    if (empty($first_name) || empty($last_name) || empty($course) || empty($department) || empty($username) 
        || empty($user_password) || empty($role) || empty($email) || empty($yr_lvl)) {
        
        session_start();
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'message' => 'All fields are required!'
        ];
        
    } else {
        // Check if the username already exists (case-insensitive)
        $stmt = $connection->prepare("SELECT * FROM tbl_users WHERE LOWER(username) = LOWER(?)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $username_result = $stmt->get_result();

        // Check if the email already exists (case-insensitive)
        $stmt = $connection->prepare("SELECT * FROM tbl_users WHERE LOWER(email) = LOWER(?)");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $email_result = $stmt->get_result();

        if ($username_result->num_rows > 0 && $email_result->num_rows > 0) {
            // Both username and email exist
            session_start();
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Both username and email already exist!'
            ];
        } elseif ($username_result->num_rows > 0) {
            // Username already exists
            session_start();
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Username already exists!'
            ];
        } elseif ($email_result->num_rows > 0) {
            // Email already exists
            session_start();
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Email already exists!'
            ];
        } else {
            // Hash the password
            $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);

            // Prepare the SQL statement
            $stmt = $connection->prepare("INSERT INTO tbl_users (first_name, last_name, course, department, username, password, role, email, yr_lvl) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssss", $first_name, $last_name, $course, $department, $username, $hashed_password, $role, $email, $yr_lvl);

            // Execute the statement
            if ($stmt->execute()) {
                session_start();
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => 'User(s) successfully created!'
                ];
                header("Location: ../03_admin/user.php?create_success=true");
                exit;
            } else {
                session_start();
                $_SESSION['flash_message'] = [
                    'type' => 'danger',
                    'message' => 'An error occurred while creating the user. Please try again.'
                ];
            }

            // Close the statement
            $stmt->close();
        }
    }
}

include('../resources/utilities/functions/department_operation.php'); 

$connection->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
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
        <?php include('../resources/utilities/sidebar/admin_sidebar.php'); ?>
      
        <?php include('../resources/utilities/modal/department_operation_modal.php'); ?>


        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">CREATE USER</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div> 
            </div>
            <div class="user-wrapper">
                <div class="create_user-page"> 
                   
                <?php
                    
                
                
                // Display flash messages if they exist
                if (isset($_SESSION['flash_message'])) {
                    $flash = $_SESSION['flash_message'];
                    echo "
                    <div class=\"alert alert-{$flash['type']} d-flex align-items-center\" role=\"alert\">
                        <span class='material-symbols-outlined me-2'>warning</span>
                        <div>{$flash['message']}</div>
                        <button type=\"button\" class=\"btn-close ms-auto\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
                    </div>
                    ";
                    // Unset the flash message so it's not displayed again
                    unset($_SESSION['flash_message']);
                }
                    
                    
                ?>    
                
                    <form method="POST">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label" >First Name</label>
                                <input type="text" name="first_name" class="form-control form-control-sm" id="first_name" value="<?php echo htmlspecialchars($first_name); ?>"
                                autocomplete="on">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control form-control-sm" id="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
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
                                    <option value="BS Information Systems" <?php echo htmlspecialchars($course) == 'BS Information System' ? 'selected' : ''; ?>>BS Information System</option>
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
                            <div class="col-md-6">
                                <label for="department" class="form-label">Department/Organization</label>
                                <div class="input-group">
                                    <select id="department" name="department" class="form-select form-select-sm">
                                        <option value="">-Select a Dept/Org-</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['department']; ?>"><?php echo $dept['department']; ?></option>
                                            <?php endforeach; ?>
                                    </select>

                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addDeptModal">Add Dept/Org</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editDeptModal">Edit Dept/Org</a></li>
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#deleteDeptModal">Delete Dept/Org</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" name="username" class="form-control form-control-sm" id="username" value="<?php echo htmlspecialchars($username); ?>"
                                autocomplete="on">
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" class="form-control form-control-sm" id="password" value="<?php echo htmlspecialchars($user_password); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label ">Role</label>
                                <select name="role" class="form-select form-select-sm" id="role">
                                    <option value="" selected>-Select Role-</option>
                                    <option value="Admin" <?php echo htmlspecialchars($role) == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="Dean/Department Head" <?php echo htmlspecialchars($role) == 'Dean/Department Head' ? 'selected' : ''; ?>>Dean/Department Head</option>
                                    <option value="Adviser/Moderator" <?php echo htmlspecialchars($role) == 'Adviser/Moderator' ? 'selected' : ''; ?>>Adviser/Moderator</option>
                                    <option value="SCSC President" <?php echo htmlspecialchars($role) == 'SCSC President' ? 'selected' : ''; ?>>SCSC President</option>
                                    <option value="Student-Proposer" <?php echo htmlspecialchars($role) == 'Student-Proposer' ? 'selected' : ''; ?>>Student-Proposer</option>
                                    <option value="Student-Regular" <?php echo htmlspecialchars($role) == 'Student-Regular' ? 'selected' : ''; ?>>Student-Regular</option>
                                    
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" class="form-control form-control-sm" id="email" value="<?php echo htmlspecialchars($email); ?>"
                                autocomplete="on">
                            </div>
                        </div>
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label for="yr_lvl" class="form-label">School Year</label>
                                <select name="yr_lvl" class="form-select form-select-sm" id="yr_lvl">
                                    <option value="" selected>-Select Role-</option>
                                    <option value="1st year" <?php echo ($yr_lvl == '1st year') ? 'selected' : ''; ?>>1st year</option>
                                    <option value="2nd year" <?php echo ($yr_lvl == '2nd year') ? 'selected' : ''; ?>>2nd year</option>
                                    <option value="3rd year" <?php echo ($yr_lvl == '3rd year') ? 'selected' : ''; ?>>3rd year</option>
                                    <option value="4th year" <?php echo ($yr_lvl == '4th year') ? 'selected' : ''; ?>>4th year</option>
                                    <option value="1st year irreg" <?php echo ($yr_lvl == '1st year irreg') ? 'selected' : ''; ?>>1st year irreg</option>
                                    <option value="2nd year irreg" <?php echo ($yr_lvl == '2nd year irreg') ? 'selected' : ''; ?>>2nd year irreg</option>
                                    <option value="3rd year irreg" <?php echo ($yr_lvl == '3rd year irreg') ? 'selected' : ''; ?>>3rd year irreg</option>
                                    <option value="4th year irreg" <?php echo ($yr_lvl == '4th year irreg') ? 'selected' : ''; ?>>4th year irreg</option>
                                    <option value="N/A" <?php echo ($yr_lvl == 'N/A') ? 'selected' : ''; ?>>N/A</option>
                                </select>
                            </div>

                            <div class="col"></div>
                        </div>

                       
                        <div class="row mb-3">
                            <div class="col text-end" style="margin-top: 20px;">
                                <a class="btn btn-secondary btn-m" href="../03_admin/user.php" role="button">Cancel</a>
                                <button type="submit" class="btn btn-success btn-m">Create</button>
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
