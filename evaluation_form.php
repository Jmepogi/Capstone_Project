<?php
// evaluation_form.php
$proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;
if ($proposal_id <= 0) {
    die('Invalid proposal ID.');
}

require '../config/system_db.php'; // or include '../config/system_db.php';

// Check if the evaluation has already been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];

    // Check if the user has already submitted an evaluation for the given proposal
    $stmt = $connection->prepare("SELECT COUNT(*) FROM tbl_evaluation WHERE proposal_id = ? AND name = ?");
    $stmt->bind_param("is", $proposal_id, $name);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // If already submitted, stop and show a message
    if ($count > 0) {
        die("You have already submitted an evaluation.");
    }

    try {
        // Convert evaluation responses to JSON
        $responses = json_encode($_POST['eval']);
        
        // Insert evaluation with responses
        $stmt = $connection->prepare("INSERT INTO tbl_evaluation (proposal_id, name, affiliation, outstanding, improvement, suggestions, responses) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", 
            $proposal_id,
            $_POST['name'],
            $_POST['affiliation'],
            $_POST['outstanding'],
            $_POST['improvement'],
            $_POST['suggestions'],
            $responses
        );
        $stmt->execute();
        $evaluation_id = $stmt->insert_id;
        
        // Show success message with certificate download link
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Thank You</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="alert alert-success">
                    <h4>Thank you for your feedback!</h4>
                    <p>Your evaluation has been submitted successfully.</p>
                    <a href="generate_certificate.php?evaluation_id=' . $evaluation_id . '" class="btn btn-primary mt-3">Download your e-certificate</a>
                </div>
            </div>
        </body>
        </html>';
        exit();
    } catch (Exception $e) {
        die("Error saving evaluation: " . $e->getMessage());
    }
}

// Legend options
$legend = ['P' => 'Poor', 'S' => 'Satisfactory', 'NI' => 'Needs Improvement', 'HS' => 'Highly Satisfactory', 'O' => 'Outstanding'];

// Questions structure
$questions = [
    'A. Activity Title/Theme' => [
        'The title/theme was appropriate to the nature of the activity.'
    ],
    'B. Objectives' => [
        '   ',
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

// Get proposal title
$stmt = $connection->prepare("SELECT title FROM tbl_proposal WHERE proposal_id = ?");
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$stmt->bind_result($proposal_title);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Evaluation Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .evaluation-form-container { 
            max-width: 900px; 
            margin: 20px auto; 
            background: #fff; 
            border-radius: 10px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
            padding: 20px;
        }
        .legend-table th, .legend-table td { 
            font-size: 0.9em; 
            padding: 2px 4px; 
        }
        .eval-table th, .eval-table td { 
            text-align: center; 
            vertical-align: middle; 
            font-size: 0.9em; 
            padding: 8px 4px;
        }
        .eval-table th.rotate { 
            writing-mode: vertical-lr; 
            transform: rotate(180deg); 
            font-size: 0.8em; 
        }
        .eval-table td.question { 
            text-align: left; 
            font-size: 0.9em;
            padding: 8px;
        }
        .logo { 
            height: 50px; 
            margin-right: 10px; 
        }
        .header-text {
            font-size: 0.9em;
        }
        .legend-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 10px 0;
            font-size: 0.85em;
        }
        .legend-item {
            white-space: nowrap;
        }
        .form-label {
            font-size: 0.95em;
            font-weight: 500;
        }
        .form-control {
            font-size: 0.9em;
        }
        .section-header {
            font-size: 0.95em;
            font-weight: bold;
            background-color: #f8f9fa;
            padding: 8px;
        }
        @media (max-width: 768px) {
            .evaluation-form-container {
                margin: 10px;
                padding: 15px;
            }
            .logo {
                height: 40px;
            }
            .header-text {
                font-size: 0.8em;
            }
            .eval-table td.question {
                font-size: 0.85em;
                padding: 6px;
            }
            .legend-container {
                font-size: 0.8em;
            }
            .form-label {
                font-size: 0.9em;
            }
            .form-control {
                font-size: 0.85em;
            }
            .btn {
                font-size: 0.9em;
                padding: 0.375rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container evaluation-form-container">
        <div class="d-flex align-items-center mb-3">
            <img src="../images/login/cefi-logo.png" alt="CEFI Logo" class="logo">
            <div class="header-text">
                <div style="font-weight: bold;">CALAYAN EDUCATIONAL FOUNDATION, INC.</div>
                <div>OFFICE FOR STUDENT AFFAIRS</div>
            </div>
        </div>
        <h4 class="text-center mb-2" style="font-size: 1.2em;">EVALUATION FORM</h4>
        <div class="mb-2" style="font-size:0.9em;">(To be answered by the audience or participants)</div>
        <div class="mb-3">
            <strong>Activity Title:</strong> <?= htmlspecialchars($proposal_title) ?>
        </div>
        <div class="legend-container">
            <strong>LEGEND:</strong>
            <span class="legend-item">P – Poor</span>
            <span class="legend-item">S – Satisfactory</span>
            <span class="legend-item">NI – Needs Improvement</span>
            <span class="legend-item">HS – Highly Satisfactory</span>
            <span class="legend-item">O – Outstanding</span>
        </div>
        <form method="post" onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            <input type="hidden" name="proposal_id" value="<?= htmlspecialchars($proposal_id) ?>">
            <div class="mb-3 row g-2">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-6">
                    <label for="affiliation" class="form-label">Affiliation</label>
                    <select class="form-select" id="affiliation" name="affiliation" required>
                        <option value="" selected disabled>Select affiliation</option>
                        <option value="Student">Student</option>
                        <option value="Faculty">Faculty</option>
                        <option value="Guest">Guest</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive mb-4">
                <table class="table table-bordered eval-table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40%">Criteria</th>
                            <?php foreach ($legend as $key => $desc): ?>
                                <th><?= $key ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $section => $qs): ?>
                            <tr class="table-secondary"><td colspan="7" class="section-header"> <?= $section ?> </td></tr>
                            <?php foreach ($qs as $q_idx => $q): ?>
                                <tr>
                                    <td class="question"> <?= htmlspecialchars($q) ?> </td>
                                    <?php foreach ($legend as $key => $desc): ?>
                                        <td><input type="radio" name="eval[<?= md5($section.$q_idx) ?>]" value="<?= $key ?>" required></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mb-3">
                <label for="outstanding" class="form-label">1. What aspects of the activity were outstanding for you?</label>
                <textarea class="form-control" id="outstanding" name="outstanding" rows="2" required></textarea>
            </div>
            <div class="mb-3">
                <label for="improvement" class="form-label">2. What aspects of the activity needs improvement?</label>
                <textarea class="form-control" id="improvement" name="improvement" rows="2" required></textarea>
            </div>
            <div class="mb-3">
                <label for="suggestions" class="form-label">3. Comments/Suggestions:</label>
                <textarea class="form-control" id="suggestions" name="suggestions" rows="2"></textarea>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-success">Submit Evaluation</button>
            </div>
        </form>
    </div>
    <script>
    document.querySelector('form').addEventListener('submit', function () {
        this.querySelector('button[type=submit]').innerText = 'Submitting...';
        this.querySelector('button[type=submit]').disabled = true;
    });
    </script>

</body>
</html> 