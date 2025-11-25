<?php
session_start();

require '../../config/system_db.php'; // or include '../../config/system_db.php';

$table = "tbl_proposal";


// Function to sanitize input
function sanitizeInput($input) {
    global $connection;
    return $connection->real_escape_string(trim($input));
}

// Get filter parameters
$chartType = isset($_GET['chartType']) ? sanitizeInput($_GET['chartType']) : 'organization';
$proposalType = isset($_GET['proposalType']) ? sanitizeInput($_GET['proposalType']) : 'all';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$month = isset($_GET['month']) ? sanitizeInput($_GET['month']) : 'All';
$year = isset($_GET['year']) ? sanitizeInput($_GET['year']) : date('Y');
$department = isset($_GET['department']) ? sanitizeInput($_GET['department']) : 'all';

// Prepare base condition for filters
$conditions = [];
$params = [];
$types = '';

if ($proposalType !== 'all') {
    $conditions[] = "p.type = ?";
    $params[] = $proposalType;
    $types .= 's';
}

if ($status !== 'all') {
    $conditions[] = "p.status = ?";
    $params[] = $status;
    $types .= 's';
}

// Handle month filtering
if ($month !== 'All') {
    // Convert month name to number
    $monthNum = date('m', strtotime("1 $month 2000"));
    $conditions[] = "MONTH(p.submitted_at) = ?";
    $params[] = $monthNum;
    $types .= 's';
}

// Handle year filtering
if ($year !== 'All') {
    $conditions[] = "YEAR(p.submitted_at) = ?";
    $params[] = $year;
    $types .= 's';
}

// Department filter
if ($department !== 'all') {
    $conditions[] = "p.organization = ?";
    $params[] = $department;
    $types .= 's';
}

// Build WHERE clause
$whereClause = '';
if (count($conditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Initialize response data
$response = [
    'labels' => [],
    'counts' => [],
    'shortLabels' => [], // For SDG
    'success' => true
];

// Execute query based on chart type
switch ($chartType) {
    case 'organization':
        $sql = "SELECT organization, COUNT(*) AS count 
                FROM $table AS p 
                $whereClause
                GROUP BY organization
                ORDER BY count DESC";
        break;
    
    case 'core values':
        $sql = "SELECT mvc.mvc_value AS label, COUNT(*) AS count
                FROM $table AS p
                JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
                WHERE mvc.mvc_type = 'core value'
                " . (empty($whereClause) ? '' : 'AND ' . substr($whereClause, 6)) . "
                GROUP BY mvc.mvc_value
                ORDER BY count DESC";
        break;
    
    case 'mission':
        $sql = "SELECT mvc.mvc_value AS label, COUNT(*) AS count
                FROM $table AS p
                JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
                WHERE mvc.mvc_type = 'mission'
                " . (empty($whereClause) ? '' : 'AND ' . substr($whereClause, 6)) . "
                GROUP BY mvc.mvc_value
                ORDER BY count DESC";
        break;
    
    case 'vision':
        $sql = "SELECT mvc.mvc_value AS label, COUNT(*) AS count
                FROM $table AS p
                JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
                WHERE mvc.mvc_type = 'vision'
                " . (empty($whereClause) ? '' : 'AND ' . substr($whereClause, 6)) . "
                GROUP BY mvc.mvc_value
                ORDER BY count DESC";
        break;
    
    case 'sdg':
        $sql = "SELECT sdg.sdg_number, sdg.sdg_description, COUNT(*) as count 
                FROM $table AS p
                JOIN tbl_proposal_sdgs AS sdg ON p.proposal_id = sdg.proposal_id
                $whereClause
                GROUP BY sdg.sdg_number, sdg.sdg_description
                ORDER BY sdg.sdg_number";
        break;
    
    case 'proposals_passed':
        $sql = "SELECT 
                DATE_FORMAT(p.submitted_at, '%Y-%m') as month_year,
                DATE_FORMAT(p.submitted_at, '%b') as month_short,
                COUNT(*) as count
                FROM $table AS p
                WHERE p.status IN ('Approved', 'Rejected', 'Revised', 'Pending')
                " . (empty($whereClause) ? '' : 'AND ' . substr($whereClause, 6)) . "
                GROUP BY DATE_FORMAT(p.submitted_at, '%Y-%m'), DATE_FORMAT(p.submitted_at, '%b')
                ORDER BY month_year";
        break;
        
        case 'evaluation_ratings':
            // 1. Get raw data from proposals and their evaluations
            $sql = "SELECT 
                        p.proposal_id, 
                        p.title, 
                        e.evaluation_id,
                        e.responses
                    FROM $table AS p
                    JOIN tbl_evaluation e ON p.proposal_id = e.proposal_id
                    " . (empty($whereClause) ? '' : 'WHERE ' . substr($whereClause, 6));
        
            $stmt = $connection->prepare($sql);
            
            // Bind parameters if any
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            // 2. Structure to collect data
            $proposals = [];
        
            // 3. Define the score mapping
            $ratingMap = [
                'P' => 1,
                'S' => 2,
                'NI' => 3,
                'HS' => 4,
                'O' => 5,
                'NA' => null
            ];
        
            // 4. Loop through results and decode JSON responses
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $pid = $row['proposal_id'];
                    $title = $row['title'];
                    $responses = json_decode($row['responses'], true);
            
                    if (!isset($proposals[$pid])) {
                        $proposals[$pid] = [
                            'title' => $title,
                            'total_evaluations' => 0,
                            'ratings' => []
                        ];
                    }
            
                    $proposals[$pid]['total_evaluations']++;
            
                    foreach ($responses as $answer) {
                        if (isset($ratingMap[$answer])) {
                            $mappedValue = $ratingMap[$answer];
                            if ($mappedValue !== null) {
                                $proposals[$pid]['ratings'][] = $mappedValue;
                            }
                        }
                    }
                }
            
                // 5. Compute average rating per proposal
                $results = [];
                foreach ($proposals as $pid => $data) {
                    $ratings = $data['ratings'];
                    $avgRating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;
                    $results[] = [
                        'proposal_id' => $pid,
                        'title' => $data['title'],
                        'total_evaluations' => $data['total_evaluations'],
                        'avg_rating' => $avgRating
                    ];
                }
            
                // 6. Sort by avg_rating descending
                usort($results, function($a, $b) {
                    return $b['avg_rating'] <=> $a['avg_rating'];
                });
            
                // 7. Limit to top 5
                $results = array_slice($results, 0, 5);
                
                // 8. Add results to response
                foreach ($results as $item) {
                    $response['labels'][] = $item['title'];
                    $response['counts'][] = round((float)$item['avg_rating'], 2);
                    $response['evaluations'][] = (int)$item['total_evaluations'];
                }
                
                // Skip the standard processing since we've already populated the response
                $stmt->close();
                $connection->close();
                
                // Send JSON response
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            } else {
                $response['success'] = false;
                $response['error'] = $stmt->error;
            }
            
            break;
        
    
    default:
        die(json_encode(['error' => 'Invalid chart type', 'success' => false]));
}

$stmt = $connection->prepare($sql);

if ($stmt) {
    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($chartType === 'sdg') {
                $response['labels'][] = "SDG " . $row['sdg_number'] . ": " . $row['sdg_description'];
                $response['shortLabels'][] = "SDG" . $row['sdg_number'];
                $response['counts'][] = (int)$row['count'];
            } else if ($chartType === 'organization') {
                $response['labels'][] = $row['organization'];
                $response['counts'][] = (int)$row['count'];
            } else if ($chartType === 'proposals_passed') {
                $response['labels'][] = $row['month_short'];
                $response['counts'][] = (int)$row['count'];
            } else if ($chartType === 'evaluation_ratings') {
                $response['labels'][] = $row['title'];
                $response['counts'][] = round((float)$row['avg_rating'], 2);
                $response['evaluations'][] = (int)$row['total_evaluations'];
            } else {
                $response['labels'][] = $row['label'];
                $response['counts'][] = (int)$row['count'];
            }
        }
    } else {
        $response['success'] = false;
        $response['error'] = $stmt->error;
    }
    
    $stmt->close();
} else {
    $response['success'] = false;
    $response['error'] = $connection->error;
}

// Close connection
$connection->close();

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 