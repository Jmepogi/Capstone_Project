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

// Handle form submission for adding a venue
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_venue'])) {
    $venue_name = $connection->real_escape_string($_POST['venue_name']);
    $venue_location = $connection->real_escape_string($_POST['venue_location']);

    $sql = "INSERT INTO $table (venue_name, location) 
            VALUES ('$venue_name', '$venue_location')";

    if ($connection->query($sql) === TRUE) {
        setFlashMessage('success', 'Venue added successfully!');
    } else {
        setFlashMessage('danger', 'Error adding venue: ' . $connection->error);
    }

    header("Location: venue.php");
    exit();
}

// Handle deletion of venues
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_venues'])) {
    $venue_ids = $_POST['venue_ids'] ?? [];

    if (is_array($venue_ids) && !empty($venue_ids)) {
        $ids = implode(',', array_map('intval', $venue_ids)); // Ensure IDs are integers
        $sql = "DELETE FROM $table WHERE venue_id IN ($ids)";

        if ($connection->query($sql)) {
            setFlashMessage('success', 'Venue(s) deleted successfully!');
        } else {
            setFlashMessage('danger', 'Error deleting venue(s): ' . $connection->error);
        }
    } else {
        setFlashMessage('danger', 'No venues selected');
    }

    header("Location: venue.php");
    exit();
}

// Fetch all venues from the database
$sql = "SELECT * FROM $table";
$result = $connection->query($sql);

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue Management</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">

    <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <style>
        .dataTables_scrollBody {
            max-height: 400px !important;
            overflow-y: auto !important;
        }
        .table thead th {
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 1;
        }
        /* Custom scrollbar */
        .dataTables_scrollBody::-webkit-scrollbar {
            width: 8px;
        }
        .dataTables_scrollBody::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .dataTables_scrollBody::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .dataTables_scrollBody::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
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
                <h2 class="d-title">VENUE MANAGEMENT</h2>
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

                <!-- Add Venue Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Add New Venue</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="venue.php">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="venue_name" class="form-label">Venue Name</label>
                                    <input type="text" class="form-control" id="venue_name" name="venue_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="venue_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="venue_location" name="venue_location" required>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <button type="submit" name="add_venue" class="btn btn-primary">Add Venue</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Venue Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Venue List</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="venue.php">
                            <button type="submit" name="delete_venues" class="btn btn-danger btn-sm">Delete Selected</button>
                            <div class="table-responsive">
                                <table id="venuesTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th scope="col"><input type="checkbox" id="selectAll"></th>
                                            <th scope="col">ID</th>
                                            <th scope="col">Venue Name</th>
                                            <th scope="col">Location</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result && $result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="venue_ids[]" value="<?= $row['venue_id'] ?>"></td>
                                                    <td><?= $row['venue_id'] ?></td>
                                                    <td><?= $row['venue_name'] ?></td>
                                                    <td><?= $row['location'] ?></td>
                                                    <td>
                                                        <a href='edit_venue.php?id=<?= $row['venue_id'] ?>' 
                                                            style='color: #baab1e; font-size: 20px; text-decoration: none; margin-right: 10px;'>
                                                            <span class='material-icons'>edit</span>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No venues found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <!-- Custom JS -->
    <script src="../resources/js/universal.js"></script>
    <script>
        $(document).ready(function() {
            $('#venuesTable').DataTable({
                scrollY: '400px',
                scrollCollapse: true,
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                dom: '<"top"lf>rt<"bottom"ip><"clear">'
            });
        });
    </script>
</body>
</html>