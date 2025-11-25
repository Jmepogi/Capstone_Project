<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';// Ensure PHPMailer is included

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_good_moral";

// Check connection
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

$name = $course = $year_level = $semester = $school_year = $student_status = $email = "";
$errorMessage = '';
$success_message = ''; 
$system_error_message = '';
$duplicate_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST["name"]);
    $course = trim($_POST["course"]);
    $year_level = trim($_POST["year_level"]);
    $semester = trim($_POST["semester"]);
    $school_year = trim($_POST["school_year"]);
    $student_status = trim($_POST["student_status"]);
    $email = trim($_POST["email"]);

    // Check if any field is empty
    if (empty($name) || empty($course) || empty($year_level) || empty($semester) || empty($school_year) || empty($student_status) || empty($email)) {
        $errorMessage = 'All fields are required! Please fill in all the fields.';
    } else {
        // Check if a request from the same email already exists
        $checkStmt = $connection->prepare("SELECT * FROM $table WHERE email = ? AND status != 'Processed'");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $duplicate_error_message = 'A request with this email has already been submitted.';
        } else {
            // Proceed with database insertion and generate tracking number
            $tracking_number = 'CEFI-' . substr(uniqid(), -5);
            $stmt = $connection->prepare("INSERT INTO $table (name, course, year_level, semester, school_year, student_status, email, tracking_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("ssisssss", $name, $course, $year_level, $semester, $school_year, $student_status, $email, $tracking_number);

            if ($stmt->execute()) {
                // Prepare email with PHPMailer
                $mail = new PHPMailer(true); // Passing `true` enables exceptions
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';           // SMTP server
                    $mail->SMTPAuth = true;                   // Enable SMTP authentication
                    $mail->Username = 'cefimis01@gmail.com'; // SMTP username
                    $mail->Password = 'rrvwqvjdmytzpyvx';    // SMTP password (App Password if 2FA is enabled)
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Recipients
                    $mail->setFrom('cefimis01@gmail.com', 'CEFI Office for Student Affairs');
                    $mail->addAddress($_POST['email']);       // Recipient's email
               

                    // Content
                    $mail->isHTML(true);                                       // Set email format to HTML
                    $mail->Subject = 'Your Tracking Number';
                    $mail->Body    = "Dear $name,<br><br>Thank you for your submission, your tracking number is: $tracking_number";
                    $mail->AltBody = "Dear $name,\n\nThank you for your submission, your tracking number is: $tracking_number";

                    $mail->send();
                    $_SESSION['success_message'] = "An email with the tracking number has been sent to $email.";
                    
                    // Redirect to avoid resubmission
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } catch (Exception $e) {
                    $system_error_message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }

                $stmt->close();
            } else {
                $system_error_message = 'An error occurred while processing your request. Please try again later or contact support.';
            }
        }
    }
}

// Display success message if set
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$connection->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request of Good Moral</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="../resources/css/goodmoral.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
</head>

<body>  
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
    
    <div class="d-content">                           
        <div class="permit-page">
            <div class="permit-header">
                <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="permit-logo">                       
                <div>
                    <h1 class="permit-osa">OFFICE FOR STUDENT AFFAIRS </h1>
                    <p class="permit-cefi">CALAYAN EDUCATIONAL FOUNDATION, INC.</p>
                </div>
            </div>
            <div class="permit-type">
                <h2>REQUEST OF GOOD MORAL</h2>
            </div><br>          
            <?php 
            // Display messages
            if (!empty($errorMessage)) {
                echo "
                <div class=\"alert alert-warning d-flex align-items-center\" role=\"alert\">
                    <i class='fa-solid fa-triangle-exclamation me-2'></i>
                    <div>
                        " . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . "
                    </div>
                    <button type=\"button\" class=\"btn-close ms-auto\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
                </div>
                ";
            } elseif (!empty($duplicate_error_message)) {
                echo "
                <div class=\"alert alert-warning d-flex align-items-center\" role=\"alert\">
                    <i class='fa-solid fa-triangle-exclamation me-2'></i>
                    <div>
                        " . htmlspecialchars($duplicate_error_message, ENT_QUOTES, 'UTF-8') . "
                    </div>
                    <button type=\"button\" class=\"btn-close ms-auto\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
                </div>
                ";
            } elseif (!empty($success_message)) {
                echo "
                <div class=\"alert alert-success d-flex align-items-center\" role=\"alert\">
                    <i class='fa-solid fa-circle-check me-2'></i>
                    <div>
                        " . htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') . "
                    </div>
                    <button type=\"button\" class=\"btn-close ms-auto\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
                </div>
                ";
            } elseif (!empty($system_error_message)) {
                echo "
                <div class=\"alert alert-danger d-flex align-items-center\" role=\"alert\">
                    <i class='fa-solid fa-triangle-exclamation me-2'></i>
                    <div>
                        " . htmlspecialchars($system_error_message, ENT_QUOTES, 'UTF-8') . "
                    </div>
                    <button type=\"button\" class=\"btn-close ms-auto\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>
                </div>
                ";
            }
            ?>
            <?php include('../resources/utilities/modal/tracking_modal.php'); ?>
            <form id="requestForm" action="" method="POST">
                <div class="row mb-2">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="Enter your full name"
                        autocomplete="on" value="<?php echo htmlspecialchars($name); ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="role" class="form-label ">Course</label>
                        <select name="course" class="form-select" aria-label="Default select example" style="font-size: 13px;" id="course">
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
                </div>
                <div class="row mb-3">
                    <div class="col-12 col-md-6">
                        <label for="year_level" class="form-label">Year Level</label>
                        <select class="form-select" aria-label="Default select example" style="font-size: 13px;" id="year_level" name="year_level" required>
                            <option value="" selected>- select year level -</option>
                            <option value="1st Year" <?php echo htmlspecialchars($year_level) == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd Year" <?php echo htmlspecialchars($year_level) == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd Year" <?php echo htmlspecialchars($year_level) == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th Year" <?php echo htmlspecialchars($year_level) == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="semester" class="form-label">Semester</label>
                        <select class="form-select" aria-label="Default select example" style="font-size: 13px;" id="semester" name="semester" required>
                            <option value="" selected>- select semester -</option>
                            <option value="1st Semester" <?php echo htmlspecialchars($semester) == '1st Semester' ? 'selected' : ''; ?>>1st Semester</option>
                            <option value="2nd Semester" <?php echo htmlspecialchars($semester) == '2nd Semester' ? 'selected' : ''; ?>>2nd Semester</option>
                            <option value="Summer" <?php echo htmlspecialchars($semester) == 'Summer' ? 'selected' : ''; ?>>Summer</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-12 col-md-6">
                        <label for="school_year" class="form-label">School Year</label>
                        <input type="text" class="form-control" id="school_year" name="school_year" required
                        placeholder="Enter school year (e.g., 2024-2025)">
                        <div id="school_year_error" class="invalid-feedback">
                            Please enter a valid school year in the format YYYY-YYYY.
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="student_status" class="form-label">Student Status</label>
                        <select class="form-select" aria-label="Default select example" style="font-size: 13px;" id="student_status" name="student_status" required>
                            <option value="" selected>- select student status -</option>
                            <option value="Current Student" <?php echo htmlspecialchars($student_status) == 'Current Student' ? 'selected' : ''; ?>>Current Student</option>
                            <option value="Graduate Student" <?php echo htmlspecialchars($student_status) == 'Graduate Student' ? 'selected' : ''; ?>>Graduate Student</option>
                            <option value="Undergraduate Student" <?php echo htmlspecialchars($student_status) == 'Undergraduate Student' ? 'selected' : ''; ?>>Undergraduate Student</option>
                        </select>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-12">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email address (e.g., 014-225@cefi.edu.ph)"
                        autocomplete="on" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-sm" style="margin-top: 30px; width:100px;">Submit</button>
                </div>
                <div id="form_error" class="alert alert-danger mt-3" style="display: none;"></div>
            </form>     
            
        </div>
        <div class="note">
            <div>
                <div class="d-flex justify-content-center mb-3">
                <dotlottie-player 
                        src="https://lottie.host/b04ca62a-7259-4a36-be27-d2fc67cb7fb9/1EUJB0YDvQ.json" 
                        background="transparent"
                        speed="1" 
                        style="width: 300px; height: 250px; padding:none;  " 
                        loop autoplay>
                    </dotlottie-player>
                </div>
                
                <h2>NOTE:</h2>
                <p class="permit-text">
                        
                    Upon submitting your request, a confirmation message will be displayed. 
                    Please ensure that you copy the provided tracking number and use it to monitor the status of your request.  <a href="#" data-bs-toggle="modal" data-bs-target="#trackingModal">Track your request</a><br><br>

                    Also, the processing of a Good Moral Certificate may require several business days. 
                    You will receive further updates via email once the request is completed. <br><br>

                    For any additional inquiries or concerns, please reach out via email at <a href="mailto:cefimis01@gmail.com">cefimis01@gmail.com</a>

                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Link  -->
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script> 
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../resources/js/goodmoral.js"></script>
    <script src="../resources/js/track.js"></script>

   
    
</body>
</html>
