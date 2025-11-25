<?php
session_start(); // Start the session

// Retrieve flash messages from session
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_venues"; // Table for venues

// Function to set flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// Fetch venue details if ID is provided
$venue_id = $_GET['id'] ?? null;
$venue = null;

if ($venue_id) {
    $sql = "SELECT * FROM $table WHERE venue_id = $venue_id";
    $result = $connection->query($sql);

    if ($result && $result->num_rows > 0) {
        $venue = $result->fetch_assoc();
    } else {
        setFlashMessage('danger', 'Venue not found');
        header("Location: venue.php");
        exit();
    }
}

// Handle form submission for editing a venue
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_venue'])) {
    $venue_name = $connection->real_escape_string($_POST['venue_name']);
    $venue_location = $connection->real_escape_string($_POST['venue_location']);

    $sql = "UPDATE $table 
            SET venue_name = '$venue_name', location = '$venue_location'
            WHERE venue_id = $venue_id";

    if ($connection->query($sql)) {
        setFlashMessage('success', 'Venue updated successfully!');
    } else {
        setFlashMessage('danger', 'Error updating venue: ' . $connection->error);
    }

    header("Location: venue.php");
    exit();
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">

    <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
</head>
<body>
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
    <div class="wrapper">
        <?php include('../resources/utilities/sidebar/admin_sidebar.php'); ?>
        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">EDIT VENUE</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>
            <div class="user-wrapper">
                <!-- Flash message display -->
                <?php if ($flash_message): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> d-flex align-items-center ms-3" role="alert">
                        <span class='material-symbols-outlined me-2'>
                            <?= $flash_message['type'] === 'success' ? 'check_circle' : 'error' ?>
                        </span>
                        <div><?= htmlspecialchars($flash_message['message']) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit Venue Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Edit Venue</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="edit_venue.php?id=<?= $venue_id ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="venue_name" class="form-label">Venue Name</label>
                                    <input type="text" class="form-control" id="venue_name" name="venue_name" value="<?= htmlspecialchars($venue['venue_name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="venue_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="venue_location" name="venue_location" value="<?= htmlspecialchars($venue['location'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="submit" name="edit_venue" class="btn btn-primary">Update Venue</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../resources/js/universal.js"></script>
</body>
</html>