<?php
session_start();

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_proposal";



$proposal_id = $_GET['id'] ?? null;
if ($proposal_id) {
    $stmt = $connection->prepare("SELECT title, type, organization, president, datetime_start, datetime_end, status FROM tbl_proposal WHERE proposal_id = ?");
    $stmt->bind_param("i", $proposal_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        echo "No proposal found.";
        exit;
    }
} else {
    echo "No ID provided.";
    exit;
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Proposal Schedule</title>
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
            padding: 30px;
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
        <!-- Navigation Bar -->
        <?php include('C:/xampp/htdocs/Capstone_Project/resources/utilities/sidebar/admin_sidebar.php'); ?>
        <?php include('../resources/utilities/modal/department_operation_modal.php'); ?>
        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">Edit Proposal Schedule</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div> 
            </div>
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
                <form action="save-changes.php" method="POST">
                    <input type="hidden" name="proposal_id" value="<?= htmlspecialchars($proposal_id) ?>">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" value="<?= htmlspecialchars($row['title']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Organization</label>
                        <input type="text" class="form-control" name="organization" value="<?= htmlspecialchars($row['organization']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Start Date & Time</label>
                        <input type="datetime-local" class="form-control" name="datetime_start" value="<?= date('Y-m-d\TH:i', strtotime($row['datetime_start'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date & Time</label>
                        <input type="datetime-local" class="form-control" name="datetime_end" value="<?= date('Y-m-d\TH:i', strtotime($row['datetime_end'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-control-plaintext">
                        <input type="text" class="form-control"  name="status" value="<?= htmlspecialchars($row['status']) ?>" readonly>
                        </div>
                    </div>
                    <div class="text-end">
                        <a class="btn btn-secondary btn-sm" href="../03_admin/scheduling.php" role="button">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../resources/js/universal.js"></script>
</body>
</html>
