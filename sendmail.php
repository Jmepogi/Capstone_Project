<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';// Ensure PHPMailer is included

session_start(); // Start the session

require '../config/system_db.php'; // or include '../config/system_db.php';

$mail = new PHPMailer(true);

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
    $mail->isHTML(true);
    $mail->Subject = $_POST['subject'];
    $mail->Body    = $_POST['message'];

    // Send email
    $mail->send();

    // Update status to "Processed" after sending email
    $user_id = intval($_POST['user_id']);
    $sql = "UPDATE tbl_good_moral SET status = 'Processed', processed_date = NOW() WHERE id IN ($user_id)";

    
    if ($connection->query($sql) === TRUE) {
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Email sent successfully.'
        ];
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Email sent, but failed to update status: ' . $connection->error
        ];
    }

} catch (Exception $e) {
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'message' => 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo
    ];
}

$connection->close();

// Redirect back to the user.php page
header("Location: request_table.php");
exit();
