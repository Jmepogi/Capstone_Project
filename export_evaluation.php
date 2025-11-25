<?php
// export_evaluation.php
// Get proposal_id from URL
$proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;
if ($proposal_id <= 0) {
    die('Invalid proposal ID.');
}

require '../config/system_db.php'; // or include '../config/system_db.php';

$proposal_id = $_GET['proposal_id'] ?? 0;
if (!$proposal_id) {
    die("Invalid proposal ID.");
}

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
$questionText = [];
foreach ($questions as $group => $qs) {
    foreach ($qs as $q) {
        $questionText[] = $q;
    }
}

// Response value mapping
$responseValues = ['P' => 1, 'S' => 2, 'NI' => 3, 'HS' => 4, 'O' => 5];

// All possible responses and affiliation types
$allResponses = array_keys($responseValues);
$affiliationTypes = ['Student', 'Faculty', 'Guest'];

// Initialize stats
$questionScores = $questionCounts = [];
foreach ($questionText as $q) {
    $questionScores[$q] = 0;
    $questionCounts[$q] = 0;
}

// Tally arrays
$tally = [];
foreach ($questionText as $q) {
    $tally[$q] = array_fill_keys($allResponses, 0);
}
$affiliationCount = array_fill_keys($affiliationTypes, 0);

$responseByAffiliation = [];
foreach ($affiliationTypes as $type) {
    $responseByAffiliation[$type] = [];
    foreach ($questionText as $q) {
        $responseByAffiliation[$type][$q] = array_fill_keys($allResponses, 0);
    }
}

// Prepare CSV
$filename = "evaluation_means_proposal_$proposal_id.csv";
header('Content-Type: text/csv');
header("Content-Disposition: attachment; filename=\"$filename\"");
$output = fopen('php://output', 'w');

// Set UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header for individual responses
$headers = ['Name', 'Affiliation'];
$headers = array_merge($headers, $questionText, ['Outstanding', 'Improvement', 'Suggestions']);
fputcsv($output, $headers);

// Fetch and process evaluation data
$sql = "SELECT name, affiliation, responses, outstanding, improvement, suggestions FROM tbl_evaluation WHERE proposal_id = $proposal_id";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $responses = json_decode($row['responses'], true);
    $answers = array_values($responses);
    $answers = array_pad($answers, count($questionText), '');

    // Normalize affiliation - Modified section
    $affiliation = trim(ucfirst(strtolower($row['affiliation'])));
    $affiliationType = null;
    foreach ($affiliationTypes as $type) {
        if (stripos($affiliation, $type) !== false) {
            $affiliationType = $type;
            break;
        }
    }
    
    // Skip entries that don't match one of the defined affiliations
    if ($affiliationType === null) {
        continue;
    }
    
    $affiliationCount[$affiliationType]++;

    // Tally responses
    foreach ($answers as $index => $answer) {
        $qText = $questionText[$index] ?? null;
        if ($qText && in_array($answer, $allResponses)) {
            $tally[$qText][$answer]++;
            $responseByAffiliation[$affiliationType][$qText][$answer]++;
            $questionScores[$qText] += $responseValues[$answer];
            $questionCounts[$qText]++;
        }
    }

    // Output individual responses
    fputcsv($output, array_merge(
        [$row['name'], $row['affiliation']],
        $answers,
        [$row['outstanding'], $row['improvement'], $row['suggestions']]
    ));
}

// Distribution of Evaluators Based on Affiliation
fputcsv($output, []); // Blank row
fputcsv($output, ['Distribution of Evaluators Based on Affiliation']);
foreach ($affiliationCount as $type => $count) {
    fputcsv($output, [$type, $count]);
}


// Interpretation Guide
fputcsv($output, []);
fputcsv($output, ['Interval', 'Interpretation']);
fputcsv($output, ['1.00 – 1.80', 'P (Poor)']);
fputcsv($output, ['1.81 – 2.60', 'S (Satisfactory)']);
fputcsv($output, ['2.61 – 3.40', 'NI (Needs Improvement)']);
fputcsv($output, ['3.41 – 4.20', 'HS (Highly Satisfactory)']);
fputcsv($output, ['4.21 – 5.00', 'O (Outstanding)']);

// Activity Feedback Analysis
fputcsv($output, []); // Blank row
fputcsv($output, ['Activity Feedback Analysis']);
fputcsv($output, ['Questions', 'Mean', 'Interpretation']);


$totalScore = 0;
$totalCount = 0;

function interpretMean($mean) {
    if ($mean >= 1.00 && $mean <= 1.80) return 'P (Poor)';
    if ($mean > 1.80 && $mean <= 2.60) return 'S (Satisfactory)';
    if ($mean > 2.60 && $mean <= 3.40) return 'NI (Needs Improvement)';
    if ($mean > 3.40 && $mean <= 4.20) return 'HS (Highly Satisfactory)';
    if ($mean > 4.20 && $mean <= 5.00) return 'O (Outstanding)';
    return 'N/A';
}

foreach ($questionText as $question) {
    $count = $questionCounts[$question];
    $mean = $count > 0 ? round($questionScores[$question] / $count, 2) : 0.00;
    $interpretation = interpretMean($mean);

    fputcsv($output, [$question, $mean, $interpretation]);

    $totalScore += $questionScores[$question];
    $totalCount += $count;
}

// General Weighted Mean
fputcsv($output, []);
$gwm = $totalCount > 0 ? round($totalScore / $totalCount, 2) : 0.00;
fputcsv($output, ['General Weighted Mean', $gwm, interpretMean($gwm)]);



fclose($output);
$conn->close();
exit;
?>