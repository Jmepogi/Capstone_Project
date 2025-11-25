<?php
session_start();

// Retrieve user's name from session
$first_name = $_SESSION['first_name'] ?? null;
$last_name = $_SESSION['last_name'] ?? null;
$full_name = $first_name . ' ' . $last_name;

require '../config/system_db.php'; // or include '../config/system_db.php';
$table = "tbl_proposal";

// Function to set flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

// Function to interpret mean values for evaluation
function interpretMean($mean) {
    if ($mean >= 1.00 && $mean <= 1.80) return 'P (Poor)';
    if ($mean > 1.80 && $mean <= 2.60) return 'S (Satisfactory)';
    if ($mean > 2.60 && $mean <= 3.40) return 'NI (Needs Improvement)';
    if ($mean > 3.40 && $mean <= 4.20) return 'HS (Highly Satisfactory)';
    if ($mean > 4.20 && $mean <= 5.00) return 'O (Outstanding)';
    return 'N/A';
}

// Handle proposal deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_proposal'])) {
    $proposal_id = $_POST['proposal_id'];
    
    // Begin transaction
    $connection->begin_transaction();
    
    try {
        // Delete related records first (foreign key constraints)
        $tables = [
            'tbl_proposal_signatories',
            'tbl_proposal_sdgs',
            'tbl_mvc',
            'tbl_proposal_details'
        ];
        
        foreach ($tables as $related_table) {
            $sql = "DELETE FROM $related_table WHERE proposal_id = ?";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param("i", $proposal_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete the main proposal
        $sql = "DELETE FROM tbl_proposal WHERE proposal_id = ? AND president = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("is", $proposal_id, $full_name);
        $stmt->execute();
        
        // Check if the proposal was actually deleted
        if ($stmt->affected_rows > 0) {
            $connection->commit();
            setFlashMessage('success', 'Proposal successfully deleted.');
        } else {
            // No rows affected might mean either proposal doesn't exist or doesn't belong to this user
            $connection->rollback();
            setFlashMessage('error', 'Unable to delete proposal. You may not have permission or the proposal no longer exists.');
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $connection->rollback();
        setFlashMessage('error', 'Error deleting proposal: ' . $e->getMessage());
    }
    
    // Redirect to refresh the page
    header("Location: proposal_inbox.php");
    exit();
}

// Handle soft deletion for approved proposals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soft_delete_proposal'])) {
    $proposal_id = $_POST['proposal_id'];
    
    try {
        // Mark the proposal as deleted (soft delete)
        $sql = "UPDATE tbl_proposal SET is_deleted = 1 WHERE proposal_id = ? AND president = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("is", $proposal_id, $full_name);
        $stmt->execute();
        
        // Check if the proposal was actually updated
        if ($stmt->affected_rows > 0) {
            setFlashMessage('success', 'Proposal successfully archived.');
        } else {
            setFlashMessage('error', 'Unable to archive proposal. You may not have permission or the proposal no longer exists.');
        }
        
        $stmt->close();
    } catch (Exception $e) {
        setFlashMessage('error', 'Error archiving proposal: ' . $e->getMessage());
    }
    
    // Redirect to refresh the page
    header("Location: proposal_inbox.php");
    exit();
}

// Variables for evaluation analytics
$selected_proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;
$valid_proposal_ids = [];
$first_proposal_id = 0;
$proposals_data = [];
$current_proposal_id = 0;
$questionText = [];
$questionMeans = [];
$affiliationCount = [];
$gwm = 0.00;
$eval_data_available = false;
$proposals_result = null;

// Check if full name is set, then fetch proposals
if ($full_name !== null) {
    // Query to select proposals with all necessary details
    $sql = "
    SELECT p.*,

    -- SDG numbers and descriptions
    GROUP_CONCAT(DISTINCT sdg.sdg_number ORDER BY sdg.sdg_number SEPARATOR ', ') AS sdg_number,
    GROUP_CONCAT(DISTINCT sdg.sdg_description ORDER BY sdg.sdg_description SEPARATOR ', ') AS sdg_description,

    -- Mission, Vision, Core Values
    GROUP_CONCAT(DISTINCT CASE WHEN mvc.mvc_type = 'mission' THEN mvc.mvc_value END ORDER BY mvc.mvc_value SEPARATOR '||') AS mission_values,
    GROUP_CONCAT(DISTINCT CASE WHEN mvc.mvc_type = 'vision' THEN mvc.mvc_value END ORDER BY mvc.mvc_value SEPARATOR '||') AS vision_values,
    GROUP_CONCAT(DISTINCT CASE WHEN mvc.mvc_type = 'core value' THEN mvc.mvc_value END ORDER BY mvc.mvc_value SEPARATOR '||') AS core_values,

    -- Signatories
    (SELECT COUNT(DISTINCT signatory_role) FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_count,
    (SELECT GROUP_CONCAT(COALESCE(signatory_role, '') ORDER BY signatory_order SEPARATOR '||') FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_roles,
    (SELECT GROUP_CONCAT(COALESCE(signatory_name, '') ORDER BY signatory_order SEPARATOR '||') FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_names,
    (SELECT GROUP_CONCAT(COALESCE(signatory_status, '') ORDER BY signatory_order SEPARATOR '||') FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_statuses,
    (SELECT GROUP_CONCAT(COALESCE(comments, 'No comment') ORDER BY signatory_order SEPARATOR '||') FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_comments,

    -- Budget
    GROUP_CONCAT(DISTINCT CASE WHEN pd1.category = 'Budget' THEN pd1.field1 END ORDER BY pd1.detail_id SEPARATOR '||') AS budget_particulars,
    GROUP_CONCAT(DISTINCT CASE WHEN pd1.category = 'Budget' THEN CAST(pd1.amount AS DECIMAL(10,2)) END ORDER BY pd1.detail_id SEPARATOR '||') AS budget_amounts,

    -- Syllabus
    GROUP_CONCAT(DISTINCT CASE WHEN pd2.category = 'Syllabus' THEN pd2.field1 END ORDER BY pd2.detail_id SEPARATOR '||') AS syllabus_subjects,
    GROUP_CONCAT(DISTINCT CASE WHEN pd2.category = 'Syllabus' THEN pd2.field2 END ORDER BY pd2.detail_id SEPARATOR '||') AS syllabus_topics,
    GROUP_CONCAT(DISTINCT CASE WHEN pd2.category = 'Syllabus' THEN pd2.field3 END ORDER BY pd2.detail_id SEPARATOR '||') AS syllabus_relevance,

    -- Program
    GROUP_CONCAT(DISTINCT CASE WHEN pd3.category = 'Program' THEN pd3.field1 END ORDER BY pd3.detail_id SEPARATOR '||') AS program_names,
    GROUP_CONCAT(DISTINCT CASE WHEN pd3.category = 'Program' THEN pd3.field2 END ORDER BY pd3.detail_id SEPARATOR '||') AS program_details,
    GROUP_CONCAT(DISTINCT CASE WHEN pd3.category = 'Program' THEN pd3.field3 END ORDER BY pd3.detail_id SEPARATOR '||') AS program_persons,

    -- Manpower
    GROUP_CONCAT(DISTINCT CASE WHEN pd4.category = 'Manpower' THEN pd4.field1 END ORDER BY pd4.detail_id SEPARATOR '||') AS manpower_roles,
    GROUP_CONCAT(DISTINCT CASE WHEN pd4.category = 'Manpower' THEN pd4.field2 END ORDER BY pd4.detail_id SEPARATOR '||') AS manpower_names,
    GROUP_CONCAT(DISTINCT CASE WHEN pd4.category = 'Manpower' THEN pd4.field3 END ORDER BY pd4.detail_id SEPARATOR '||') AS manpower_responsibilities

    FROM $table AS p
    LEFT JOIN tbl_proposal_sdgs AS sdg ON p.proposal_id = sdg.proposal_id
    LEFT JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
    LEFT JOIN tbl_proposal_details AS pd1 ON p.proposal_id = pd1.proposal_id AND pd1.category = 'Budget'
    LEFT JOIN tbl_proposal_details AS pd2 ON p.proposal_id = pd2.proposal_id AND pd2.category = 'Syllabus'
    LEFT JOIN tbl_proposal_details AS pd3 ON p.proposal_id = pd3.proposal_id AND pd3.category = 'Program'
    LEFT JOIN tbl_proposal_details AS pd4 ON p.proposal_id = pd4.proposal_id AND pd4.category = 'Manpower'
    LEFT JOIN tbl_proposal_signatories AS ps ON p.proposal_id = ps.proposal_id

    WHERE p.president = ? AND p.is_deleted = 0
    GROUP BY p.proposal_id
    ORDER BY p.proposal_id DESC
    ";

    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        die("Failed to prepare statement: " . $connection->error);
    }
    
    $stmt->bind_param("s", $full_name);
    if (!$stmt->execute()) {
        die("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    
    // Connect to evaluation database
    $conn = new mysqli("localhost", "root", "", "db_mis");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Get proposals with evaluations for this user
    $sql_proposals = "SELECT p.proposal_id, p.title, COUNT(e.evaluation_id) as eval_count 
                    FROM tbl_proposal p 
                    JOIN tbl_evaluation e ON p.proposal_id = e.proposal_id 
                    WHERE p.president = ? AND p.is_deleted = 0 
                    GROUP BY p.proposal_id 
                    HAVING eval_count > 0
                    ORDER BY eval_count DESC";
    $stmt_proposals = $conn->prepare($sql_proposals);
    $stmt_proposals->bind_param("s", $full_name);
    $stmt_proposals->execute();
    $proposals_result = $stmt_proposals->get_result();
    
    // If proposals with evaluations exist, process them
    if ($proposals_result->num_rows > 0) {
        $eval_data_available = true;
        
        // Store all proposal IDs to check if selected ID is valid
        while ($row = $proposals_result->fetch_assoc()) {
            $valid_proposal_ids[] = $row['proposal_id'];
            $proposals_data[] = $row;
            
            if ($first_proposal_id === 0) {
                $first_proposal_id = $row['proposal_id'];
            }
        }
        
        // Determine which proposal to display
        $current_proposal_id = in_array($selected_proposal_id, $valid_proposal_ids) ? 
                              $selected_proposal_id : $first_proposal_id;
        
        // Reset result pointer for later use in dropdown
        $proposals_result->data_seek(0);
        
        // Real questions in order
        $questions = [
            'A. Activity Title/Theme' => [
                'The title/theme was appropriate to the nature of the activity.'
            ],
            'B. Objectives' => [
                'The objectives of the activity had clear instructions which were communicated to the audience.',
                'The activity objectives were in consonance with its sponsoring organization\'s objectives.'
            ],
            'C. Logistics' => [
                'The time and venue were both convenient and appropriate for the activity.',
                'The physical set-up and materials used in the activity were adequate and appropriate.'
            ],
            'D. Program' => [
                'The program was well organized in all offices concerned.',
                'The activity proper could sustain the interest of the audience.',
                'The resource speaker(s) were effective and efficient.',
                'The activity was relevant to the needs of the students.',
                'The activity was finished as scheduled in the venue during the allotted time.'
            ]
        ];

        // Flatten question text array
        foreach ($questions as $group => $qs) {
            foreach ($qs as $q) {
                $questionText[] = $q;
            }
        }

        // Response value mapping
        $responseValues = ['P' => 1, 'S' => 2, 'NI' => 3, 'HS' => 4, 'O' => 5];
        $allResponses = array_keys($responseValues);
        $affiliationTypes = ['Student', 'Faculty', 'Guest'];

        // Initialize stats
        $questionScores = $questionCounts = [];
        foreach ($questionText as $q) {
            $questionScores[$q] = 0;
            $questionCounts[$q] = 0;
        }

        // Initialize affiliation count
        $affiliationCount = array_fill_keys($affiliationTypes, 0);

        // Fetch and process evaluation data
        $sql = "SELECT name, affiliation, responses FROM tbl_evaluation WHERE proposal_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_proposal_id);
        $stmt->execute();
        $eval_result = $stmt->get_result();

        while ($row = $eval_result->fetch_assoc()) {
            $responses = json_decode($row['responses'], true);
            $answers = array_values($responses);
            $answers = array_pad($answers, count($questionText), '');

            // Normalize affiliation
            $affiliation = trim(ucfirst(strtolower($row['affiliation'])));
            $affiliationType = null;
            foreach ($affiliationTypes as $type) {
                if (stripos($affiliation, $type) !== false) {
                    $affiliationType = $type;
                    break;
                }
            }
            
            if ($affiliationType === null) {
                continue;
            }
            
            $affiliationCount[$affiliationType]++;

            // Process responses
            foreach ($answers as $index => $answer) {
                $qText = $questionText[$index] ?? null;
                if ($qText && in_array($answer, $allResponses)) {
                    $questionScores[$qText] += $responseValues[$answer];
                    $questionCounts[$qText]++;
                }
            }
        }

        // Calculate means and total score
        $totalScore = 0;
        $totalCount = 0;

        foreach ($questionText as $question) {
            $count = $questionCounts[$question];
            $mean = $count > 0 ? round($questionScores[$question] / $count, 2) : 0.00;
            $questionMeans[$question] = $mean;
            $totalScore += $questionScores[$question];
            $totalCount += $count;
        }

        $gwm = $totalCount > 0 ? round($totalScore / $totalCount, 2) : 0.00;
        
        // Close evaluation database statement
        $stmt->close();
    }
    
    // Close evaluation database connection
    $conn->close();
} else {
    setFlashMessage('error', 'User not logged in.');
}

// Retrieve flash messages from session
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposal Inbox</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">

     <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Other CSS -->
    <link rel="stylesheet" href="../resources/css/user_table_chart.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />

    <style>
        /* Equal width buttons */
        .action-btn {
            min-width: 70px;
            width: 70px;
            text-align: center;
            margin-right: 4px;
            margin-bottom: 4px;
        }
        
        /* Responsive buttons on small screens */
        @media (max-width: 768px) {
            .action-buttons {
                display: flex;
                flex-wrap: wrap;
            }
            
            .action-btn {
                flex: 1 0 40%;
            }
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
        <?php include('../resources/utilities/sidebar/proposer_sidebar.php'); ?>
        <?php include('../resources/utilities/modal/proposal_modal.php'); ?>
             
        
        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">PROPOSAL INBOX</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>
            <div class="user-wrapper">
                
                <div class="user-page">
                    <?php if (!empty($questionMeans) && $gwm !== null && !empty(array_filter($affiliationCount))): ?>
                    <!-- Evaluation Analytics Section -->  
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-3">Evaluation Analytics</h5>
                        </div>
                        <div class="card-body">
                           
                                
                                <!-- Proposal Selector -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <div class="flex-grow-1">
                                                <select id="proposalSelector" class="form-select">
                                                    <?php while ($row = $proposals_result->fetch_assoc()): ?>
                                                        <option value="<?= $row['proposal_id'] ?>" <?= ($row['proposal_id'] == $current_proposal_id) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($row['title']) ?> (<?= $row['eval_count'] ?> evaluations)
                                                        </option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <!-- Export Button -->
                                            <div>
                                                <a id="exportBtn" href="export_evaluation.php?proposal_id=<?= $current_proposal_id ?>" 
                                                class="btn btn-secondary btn-sm action-btn">
                                                    Export
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Activity Feedback Analysis</h5>
                                            </div>
                                            <div class="small text-muted mt-3">
                                                <div>1.00 - 1.80 P: Poor | 1.81 - 2.60 S: Satisfactory | 2.61 - 3.40 NI: Needs Improvement | 3.41 - 4.20 HS: Highly Satisfactory | 4.21 - 5.00 O: Outstanding</div>
                                            </div>
                                        </div>

                                        <div class="card-body p-3">
                                            <div class="row g-3">
                                                <!-- Question Ratings Table - Now Full Width on Top -->
                                                <div class="col-12">
                                                        <div class="card">
                                                            <div class="card-body p-3">
                                                                <h6 class="card-title border-bottom pb-2">Question Ratings</h6>
                                                                <div style="max-height: 280px; overflow-y: auto;">
                                                                    <table class="table table-sm">
                                                                    <thead class="position-sticky top-0 bg-white">
                                                            <tr>
                                                                <th>Questions</th>
                                                                                <th class="text-center" style="width: 60px;">Mean</th>
                                                                                <th style="width: 32%;">Rating</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($questionText as $idx => $question): 
                                                                $mean = $questionMeans[$question];
                                                                $interpretation = interpretMean($mean);
                                                                $shortCode = substr($interpretation, 0, strpos($interpretation, ' '));
                                                                $color = '';
                                                                switch (substr($shortCode, 0, 1)) {
                                                                    case 'P': $color = 'danger'; break;
                                                                    case 'S': $color = 'warning'; break;
                                                                    case 'N': $color = 'info'; break;
                                                                    case 'H': $color = 'success'; break;
                                                                    case 'O': $color = 'primary'; break;
                                                                }
                                                            ?>
                                                            <tr>
                                                                <td class="small"><?= htmlspecialchars($question) ?></td>
                                                                <td class="text-center"><?= number_format($mean, 2) ?></td>
                                                                <td>
                                                                    <div class="progress" style="height: 20px;">
                                                                        <div class="progress-bar bg-<?= $color ?>" 
                                                                            role="progressbar" 
                                                                            style="width: <?= min(100, $mean * 20) ?>%;" 
                                                                            aria-valuenow="<?= $mean ?>" 
                                                                            aria-valuemin="0" 
                                                                            aria-valuemax="5">
                                                                            <?= $shortCode ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>                                                                    
                                                            <!-- General Weighted Mean row -->
                                                            <?php
                                                                $gwmInterpretation = interpretMean($gwm);
                                                                $gwmShortCode = substr($gwmInterpretation, 0, strpos($gwmInterpretation, ' '));
                                                                $gwmColor = '';
                                                                switch (substr($gwmShortCode, 0, 1)) {
                                                                    case 'P': $gwmColor = 'danger'; break;
                                                                    case 'S': $gwmColor = 'warning'; break;
                                                                    case 'N': $gwmColor = 'info'; break;
                                                                    case 'H': $gwmColor = 'success'; break;
                                                                    case 'O': $gwmColor = 'primary'; break;
                                                                }
                                                            ?>
                                                            <tr class="table-light fw-bold">
                                                                <td>General Weighted Mean</td>
                                                                <td class="text-center"><?= number_format($gwm, 2) ?></td>
                                                                <td>
                                                                    <div class="progress" style="height: 20px;">
                                                                        <div class="progress-bar bg-<?= $gwmColor ?>" 
                                                                            role="progressbar" 
                                                                            style="width: <?= min(100, $gwm * 20) ?>%;" 
                                                                            aria-valuenow="<?= $gwm ?>" 
                                                                            aria-valuemin="0" 
                                                                            aria-valuemax="5">
                                                                            <?= $gwmShortCode ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                </div>

                                                    <!-- Overall Performance & Affiliation - Now Below Questions -->
                                                    <div class="col-12">
                                                        <div class="card bg-light">
                                                            <div class="card-body p-3">
                                                                <div class="row">
                                                                    <!-- Distribution of Evaluators - Left Section -->
                                                                    <div class="col-md-6 border-end">
                                                                        <h6 class="card-title border-bottom pb-2">Distribution of Evaluators</h6>
                                                                        <div class="d-flex justify-content-center align-items-center" style="height: 225px;">
                                                                            <div style="width: 200px; height: 200px;">
                                                                                <canvas id="affiliationPieChart"></canvas>
                                                                            </div>
                                                                            <div class="ms-4 d-flex flex-column gap-4">
                                                                                <?php 
                                                                                // Check if $affiliationTypes is defined before using it
                                                                                if (isset($affiliationTypes) && is_array($affiliationTypes)): 
                                                                                    foreach ($affiliationTypes as $type): 
                                                                                ?>
                                                                                <div class="d-flex align-items-center">
                                                                                    <div style="width: 12px; height: 12px; border-radius: 50%; background-color: 
                                                                                        <?php 
                                                                                        switch($type) {
                                                                                            case 'Student': echo 'rgba(255, 99, 132, 0.7)'; break;
                                                                                            case 'Faculty': echo 'rgba(54, 162, 235, 0.7)'; break;
                                                                                            case 'Guest': echo 'rgba(75, 192, 192, 0.7)'; break;
                                                                                        }
                                                                                        ?>; margin-right: 8px;"></div>
                                                                                    <div>
                                                                                        <div class="fw-bold"><?= $type ?></div>
                                                                                        <div class="text-secondary small"><?= $affiliationCount[$type] ?> evaluator<?= $affiliationCount[$type] > 1 ? 's' : '' ?></div>
                                                                                    </div>
                                                                                </div>
                                                                                <?php 
                                                                                    endforeach; 
                                                                                else: 
                                                                                ?>
                                                                                <div class="text-center text-muted">
                                                                                    <p>No evaluation data available</p>
                                                                                </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <!-- Overall Performance - Right Section -->
                                                                    <div class="col-md-6">
                                                                        <h6 class="card-title border-bottom pb-2">Overall Performance</h6>
                                                                        <div class="text-center py-2" style="height: 225px;">
                                                                            <div class="mt-4 pt-4">
                                                                                <div class="display-4 fw-bold"><?= number_format($gwm, 2) ?></div>
                                                                                <div class="h5 mb-3"><?= interpretMean($gwm) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                           
                        ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="alert alert-info alert-dismissible fade show text-center" role="alert">
                            Evaluation analytics will be available once activity responses are submitted.
                            
                        </div>
                    <?php endif; ?>
                </div>
                <div class="user-page">                      
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

                    
                    <div class="table-responsive">
                        <table id="proposalTable" class="table table-striped ">
                        <thead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Type</th>
                                <th scope="col">Date Submitted</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($full_name !== null): ?>
                                <?php $result->data_seek(0); ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['title']) ?></td>
                                        <td><?= htmlspecialchars($row['type']) ?></td>
                                        <td><?= date('F j, Y', strtotime($row['submitted_at'])) ?></td>
                                        <td>
                                            <?php
                                            // Define the color based on the status
                                            $statusColor = '';
                                            switch ($row['status']) {
                                                case 'Approved':
                                                    $statusColor = 'green';
                                                    break;
                                                case 'Rejected':
                                                    $statusColor = '#8c220f';
                                                    break;
                                                case 'Pending':
                                                    $statusColor = '#bdab11'; // Dark orange
                                                    break;
                                                case 'Revise':
                                                    $statusColor = '#c4720e'; // Dark orange
                                                    break;
                                                default:
                                                    $statusColor = 'black'; // Fallback color if status is unknown
                                                    break;
                                            }
                                            ?>
                                            <span style="color: <?= $statusColor; ?>;">
                                                <?= htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-primary btn-sm action-btn" data-bs-toggle="modal" data-bs-target="#proposalModal"
                                                    data-proposal-id="<?= $row['proposal_id'] ?>">
                                                    View
                                                </button>
                                                <?php if ($row['status'] === 'Revise' || $row['status'] === 'Reject'): ?>
                                                    <a href="proposal_edit.php?id=<?= $row['proposal_id'] ?>" class="btn btn-secondary btn-sm action-btn">
                                                        Edit
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm action-btn" disabled>
                                                        <span class="material-icons" style="vertical-align: middle;">lock</span>
                                                    </button>
                                                <?php endif; ?>



                                                <a href="proposal_print.php?id=<?= $row['proposal_id'] ?>" target="_blank" class="btn btn-secondary btn-sm action-btn">
                                                    Print
                                                </a>
                                                
                                                <a href="generate_qr.php?proposal_id=<?= $row['proposal_id'] ?>&purpose=attendance" class="btn btn-success btn-sm action-btn">
                                                    QR 
                                                </a>

                                                <?php if ($row['status'] !== 'Approved'): ?>
                                                <button type="button" class="btn btn-danger btn-sm action-btn delete-btn"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal"
                                                    data-proposal-id="<?= $row['proposal_id'] ?>"
                                                    data-proposal-title="<?= htmlspecialchars($row['title']) ?>">
                                                    Delete
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-warning btn-sm action-btn soft-delete-btn"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#softDeleteModal"
                                                    data-proposal-id="<?= $row['proposal_id'] ?>"
                                                    data-proposal-title="<?= htmlspecialchars($row['title']) ?>">
                                                    Archive
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>         
                                
                            <?php endif; ?>
                        </tbody>
                    </div>              
                </div>           
            </div>
           
        </div>
       
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this proposal?</p>
                    <p>Title: <span id="delete-proposal-title"></span></p>
                    
                </div>
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="proposal_id" id="delete-proposal-id">
                        <input type="hidden" name="delete_proposal" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Proposal</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Soft Delete Confirmation Modal -->
    <div class="modal fade" id="softDeleteModal" tabindex="-1" aria-labelledby="softDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="softDeleteModalLabel">Archive Approved Proposal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    
                    <p>Title: <span id="soft-delete-proposal-title"></span> will be archive and will no longer appear in your inbox</p>
                    
                <div class="modal-footer">
                    <form method="POST" action="">
                        <input type="hidden" name="proposal_id" id="soft-delete-proposal-id">
                        <input type="hidden" name="soft_delete_proposal" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Archive Proposal</button>
                    </form>
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
    <!-- DataTables Responsive JS -->
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- Chart JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!--  JS -->
    <script src="../resources/js/proposal_inbox_dataTable.js"></script>
    <script src="../resources/js/universal.js"></script>
    <script src="../resources/js/closefunction.js"></script>
    <!-- Script to populate modal with data from the link -->                          
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all buttons that open the proposal modal
        const proposalButtons = document.querySelectorAll('button[data-bs-target="#proposalModal"]');
        
        // Add click event listener to each button
        proposalButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                // Prevent default button behavior (which would just toggle the modal)
                event.preventDefault();
                
                // Get proposal ID from the button's data attribute
                const proposalId = this.getAttribute('data-proposal-id');
                
                if (proposalId) {
                    // Create a URL for the AJAX request
                    const ajaxUrl = '../resources/utilities/modal/proposal_modal.php?proposal_id=' + proposalId;
                    
                    // Use fetch API to load the modal content with proper AJAX headers
                    fetch(ajaxUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.text())
                    .then(html => {
                        // Parse the HTML
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newModalContent = doc.querySelector('.modal-content');
                        
                        // Get the current modal element and replace its content
                        const modalElement = document.getElementById('proposalModal');
                        const currentModalContent = modalElement.querySelector('.modal-content');
                        
                        if (newModalContent && currentModalContent) {
                            currentModalContent.innerHTML = newModalContent.innerHTML;
                        }
                        
                        // Show the modal using Bootstrap's modal method
                        const proposalModal = new bootstrap.Modal(modalElement);
                        proposalModal.show();
                    })
                    .catch(error => {
                        console.error('Error loading modal content:', error);
                        alert('There was an error loading the proposal details. Please try again.');
                    });
                }
            });
        });
        
        // Set up delete modal
        const deleteButtons = document.querySelectorAll('.delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-proposal-id');
                const proposalTitle = this.getAttribute('data-proposal-title');
                
                document.getElementById('delete-proposal-id').value = proposalId;
                document.getElementById('delete-proposal-title').textContent = proposalTitle;
            });
        });
        
        // Set up soft delete modal
        const softDeleteButtons = document.querySelectorAll('.soft-delete-btn');
        
        softDeleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-proposal-id');
                const proposalTitle = this.getAttribute('data-proposal-title');
                
                document.getElementById('soft-delete-proposal-id').value = proposalId;
                document.getElementById('soft-delete-proposal-title').textContent = proposalTitle;
            });
        });

        // Chart colors
        const chartColors = {
            'Student': 'rgba(255, 99, 132, 0.7)', // Pink
            'Faculty': 'rgba(54, 162, 235, 0.7)', // Blue
            'Guest': 'rgba(75, 192, 192, 0.7)'    // Teal
        };

        // Affiliation Pie Chart
        const affiliationCtx = document.getElementById('affiliationPieChart');
        if (affiliationCtx) {
            new Chart(affiliationCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_keys($affiliationCount)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($affiliationCount)) ?>,
                        backgroundColor: Object.values(chartColors),
                        borderWidth: 1,
                        borderColor: '#ffffff',
                        spacing: 5,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false // Hide the legend as we'll create custom ones
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    return label + ': ' + value;
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    beforeDraw: function(chart) {
                        const width = chart.width;
                        const height = chart.height;
                        const ctx = chart.ctx;
                        
                        ctx.restore();
                        
                        // Calculate total from current data
                        const totalCount = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        
                        // Font settings
                        ctx.font = 'bold 20px Arial';
                        ctx.textBaseline = 'middle';
                        ctx.textAlign = 'center';
                        
                        // Draw total count
                        ctx.fillStyle = '#000';
                        ctx.fillText(totalCount, width / 2, height / 2 - 5);
                        
                        // Draw "evaluations" text below
                        ctx.font = '11px Arial';
                        ctx.fillText('evaluations', width / 2, height / 2 + 15);
                        
                        ctx.save();
                    }
                }]
            });
        }

        // Proposal Selector Change Handler
        const proposalSelector = document.getElementById('proposalSelector');
        if (proposalSelector) {
            proposalSelector.addEventListener('change', function() {
                window.location.href = 'proposal_inbox.php?proposal_id=' + this.value;
            });
        }
    });
    </script>
</body>
</html>
