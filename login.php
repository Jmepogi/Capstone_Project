<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/system_db.php'; // or include '../config/system_db.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure that the POST keys exist before using them
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Check if the username and password are not empty
    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = 'Username and password are required.';
        header('Location: ../00_login/login.php');
        exit();
    }

    // Prepare the SQL statement to prevent SQL injection
    $query = "SELECT * FROM tbl_users WHERE username = ?";
    $stmt = $connection->prepare($query);
    if ($stmt === false) {
        die("Prepare failed: " . $connection->error);
    }

    $stmt->bind_param('s', $username); // Bind the username parameter
    if (!$stmt->execute()) {
        die("Execution failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check if the user's status is disabled
        if ($user['status'] === 'Disabled') {
            $_SESSION['error_message'] = 'Your account is disabled.';
            header('Location: ../00_login/login.php');
            exit();
        }

        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Set session variables with user details
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['department'] = $user['department'];

            // Fetch the moderator's name in the same department
            $moderatorQuery = "SELECT first_name, last_name FROM tbl_users WHERE department = ? AND role = 'Adviser/Moderator' LIMIT 1";
            $moderatorStmt = $connection->prepare($moderatorQuery);
            $moderatorStmt->bind_param('s', $user['department']);
            $moderatorStmt->execute();
            $moderatorResult = $moderatorStmt->get_result();

            if ($moderatorResult->num_rows > 0) {
                $moderator = $moderatorResult->fetch_assoc();
                $_SESSION['moderator_name'] = $moderator['first_name'] . ' ' . $moderator['last_name'];
            } else {
                $_SESSION['moderator_name'] = "No moderator found";
            }

            // Redirect based on the role
            switch ($user['role']) {
                case 'Admin':
                    header('Location: ../03_admin/admin_dbs.php');
                    break;
                case 'Dean/Department Head':
                    header('Location: ../02_faculty/faculty_dbs.php');
                    break;
                case 'Adviser/Moderator':
                    header('Location: ../02_faculty/faculty_dbs.php');
                    break;
                case 'Student-Proposer':
                    header('Location: ../01_student/proposer_dbs.php');
                    break;
                default:
                    header('Location: user_dashboard.php');
                    break;
            }
            exit();
        } else {
            $_SESSION['error_message'] = 'Invalid username or password.';
            header('Location: ../00_login/login.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = 'No user found.';
        header('Location: ../00_login/login.php');
        exit();
    }
}

$connection->close();
?>
  



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="../resources/css/loginn.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <title>OSA Login</title>


    <style>
        .password-wrapper {
            position: relative;
        }
        .password-wrapper .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 22px;
        }
    </style>
</head>

<body>
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo img-fluid">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>

    <div class="main-content">
        <div class="combined-wrapper">
            
            <!-- Additional Information on the Left -->
            <div class="additional-wrapper">
                <h4>Office for Student Affairs MIS</h4>
                <p>This system streamlines the submission and approval process for student proposals and request of good moral. 
                </p>
                    
                    <div class="links">
                        <h5>Quick Links</h5>
                        <ul>
                            <li><a href="../01_student/goodmoral_request.php">Good Moral Request</a></li>
                            <li><a href="../00_login/student_manual.php">Student Manual</a></li>
                            <li><a href="../00_login/signatory_manual.php">Signatory Manual</a></li>
                        </ul>
                    </div>
                </div>
                
                <!-- Login Form on the Right -->
                <div class="wrapper">
                    <h3>LOGIN</h3>
                    <?php if (isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['flash_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>
            
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error_message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    <form action="login.php" method="POST">
                        <div class="mb-4">
                            <input type="text" name="username" id="username" placeholder="Username" required class="form-control">
                        </div>
                        <div class="mb-4  password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Password" required class="form-control">
                            <span class="material-icons toggle-password" id="togglePassword" style="color:#c9c9c9;">visibility</span>
                        </div>
                        <div class="mb-4 mt-3">
                            <button type="submit" class="btn btn-success btn-block w-100">Login</button>
                        </div>
                        <div class="row align-items-center">
                            
                            <a href="../00_login/reset_password.php" class="text-decoration-none">Forgot Password?</a>
                            
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#togglePassword').on('click', function() {
                const passwordField = $('#password');
                const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', type);
                $(this).text(type === 'password' ? 'visibility' : 'visibility_off');
            });
        });
    </script>
    
    <!--  <script src="../resources/js/background.js"></script>   -->
</body>



</html>
