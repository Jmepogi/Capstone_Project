<?php
// generate_qr.php

if (!isset($_GET['proposal_id'])) {
    die('Proposal ID is required.');
}
$proposal_id = intval($_GET['proposal_id']);
$purpose = $_GET['purpose'] ?? 'evaluation';

require '../config/system_db.php'; // or include '../config/system_db.php';

$title = '';
$stmt = $connection->prepare("SELECT title FROM tbl_proposal WHERE proposal_id = ?");
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$stmt->bind_result($title);
$stmt->fetch();
$stmt->close();
$connection->close();

// Improved URL generation - make sure it points to the correct path
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Make sure the path is correct for the evaluation form
$form_url = sprintf(
    '%s://%s/Capstone_Project/01_student/evaluation_form.php?proposal_id=%d',
    $protocol,
    $host,
    $proposal_id
);

// For debugging - show the URL that will be encoded in the QR code
$debug_url = htmlspecialchars($form_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printable QR Code</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        .qr-container { text-align: center; margin-top: 40px; }
        .print-btn { margin-top: 20px; }
        #qrcode { display: inline-block; margin: 0 auto; }
        .debug-info { font-size: 12px; color: #666; margin-top: 20px; }

        
    </style>
</head>
<body>
    <div class="container qr-container">
        <h2>Scan to Evaluate Activity</h2>
        <div id="qrcode"></div>
        <p class="mt-3">Proposal Title: <strong><?= htmlspecialchars($title) ?></strong></p>
        <div class="d-flex justify-content-center mb-2 gap-2">
            <a href="javascript:history.back()" class="btn btn-secondary btn-sm align-self-center">Back</a>
            <button class="btn btn-primary btn-sm print-btn align-self-center" onclick="window.print()">Print QR Code</button>
        </div>




        <p class="mt-4"><small>This QR code links to the evaluation form for this activity.</small></p>
        <p class="debug-info">URL: <?= $debug_url ?></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create QR code using qrcode.js
            var qrcode = new QRCode(document.getElementById("qrcode"), {
                text: "<?= htmlspecialchars($form_url) ?>",
                width: 300,
                height: 300,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        });
    </script>
</body>
</html> 