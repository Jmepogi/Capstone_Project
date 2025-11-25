<?php
session_start();  // Start the session to use flash messages

// Enable error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/system_db.php'; // or include '../config/system_db.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Prepare and execute the query to check if email exists
    $stmt = $conn->prepare("SELECT * FROM tbl_users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Generate a new password using a cryptographically secure pseudo-random number generator
        $new_password = bin2hex(random_bytes(4)); // 8 characters long (hexadecimal)
        
        // Hash the new password with a sufficient work factor
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => 12]);

        // Prepare and execute the query to update the password
        $stmt = $conn->prepare("UPDATE tbl_users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        $stmt->execute();

        // Import PHPMailer classes into the global namespace
        require '../PHPMailer/src/Exception.php';
        require '../PHPMailer/src/PHPMailer.php';
        require '../PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';  // Gmail SMTP server
            $mail->SMTPAuth = true;  // Enable SMTP authentication
            $mail->Username = 'cefimis01@gmail.com';  // Gmail email address
            $mail->Password = 'rrvwqvjdmytzpyvx';  // Gmail App Password (if using 2FA)
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;  // TCP port to connect to

            // Recipients
            $mail->setFrom('cefimis01@gmail.com', 'CEFI Office for Student Affairs');
            $mail->addAddress($email);  // Add recipient

            // Content
            $mail->Subject = 'Password Reset';
            $mail->Body = 'Your new password is: ' . $new_password;

            // Send email
            if ($mail->send()) {
                // Set success message in session and redirect
                $_SESSION['flash_message'] = "Password reset successfully. Please check your email for the new password.";
                $_SESSION['flash_type'] = 'success';
            } else {
                // Set error message in session if email fails
                $_SESSION['flash_message'] = "Error sending email: " . $mail->ErrorInfo;
                $_SESSION['flash_type'] = 'danger';
            }
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            $_SESSION['flash_type'] = 'danger';
        }
    } else {
        // Set error message if no email found
        $_SESSION['flash_message'] = "No account found with that email address.";
        $_SESSION['flash_type'] = 'warning';
    }

    // Redirect to the same page to show flash message
    header("Location: reset_password.php");
    exit;
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tab Logo -->
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="../resources/css/forgot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <title>Reset Password</title>
</head>
<body>
<style>
        .wrapper {
            margin: 0 auto;
            margin-top: 50px;
            max-width: 400px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .resetbtn {
            width: 100%;
        }
        .remember {
            text-align: center;
            margin-top: 15px;
        }
    </style>
    <!---------- Header ------------->
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
   
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="wrapper">
                    <h3 class="text-center">Forgot Password?</h3>
                    <?php
                    // Check if a flash message is set and display it
                    if (isset($_SESSION['flash_message'])) {
                        $flash_message = $_SESSION['flash_message'];
                        $flash_type = $_SESSION['flash_type'];

                        echo '<div class="alert alert-' . $flash_type . ' alert-dismissible fade show" role="alert">';
                        echo $flash_message;
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                        echo '</div>';

                        // Unset the flash message after displaying it
                        unset($_SESSION['flash_message']);
                        unset($_SESSION['flash_type']);
                    }
                    ?>
                    <form action="" method="POST">
                        <div class="form-group mb-3">
                            <input type="email" id="email" name="email" placeholder="Enter your email" required class="form-control">
                        </div>
                        <button type="submit" class="btn btn-success btn-block w-100">Reset Password</button>
                        
                        <div class="remember">
                            <p>Remember your password? <a href="../00_login/login.php">Login here</a></p>    
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

