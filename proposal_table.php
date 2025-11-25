<?php
session_start(); // Ensure session is started

// Retrieve flash message from session, if it exists
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
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

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_proposal";  // Main table
$signatories = "tbl_proposal_signatories";  // Second table

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

// Departments to exclude
$excludedDepartments = [
    'Vice President for Academic Affairs',
    'Supreme College Student Council Adviser',
    'Council Adviser',
    'Community Affairs',
    'External Affairs',
    'Office for Student Affairs'
];

// Escape and wrap each value for SQL
$escapedValues = array_map(function($val) use ($connection) {
    return "'" . $connection->real_escape_string($val) . "'";
}, $excludedDepartments);

// Build the NOT IN clause
$notInClause = implode(',', $escapedValues);

// Fetch departments excluding specified ones
$departments = [];
$deptQuery = "SELECT department FROM tbl_department WHERE department NOT IN ($notInClause) ORDER BY department ASC";
$deptResult = $connection->query($deptQuery);

if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $approval_id = $_POST['approval_id'];
    $status = $_POST['signatory_status'];
    $comments = $_POST['comments'];
    $proposal_id = $_POST['proposal_id'];

    if (!isset($proposal_id) || empty($proposal_id)) {
        die("Proposal ID is not set or invalid.");
    }

    // Initialize $stmt outside conditionals
    $stmt = null;
    
    if ($status === 'Approved') {
        // First, check if approved_at already has a value
        $checkStmt = $connection->prepare("SELECT approved_at FROM tbl_proposal_signatories WHERE approval_id = ?");
        $checkStmt->bind_param('i', $approval_id);
        $checkStmt->execute();
        $checkStmt->bind_result($existing_approved_at);
        $checkStmt->fetch();
        $checkStmt->close();
    
        // Set approved_at only if it doesn't already exist
        if (empty($existing_approved_at)) {
            $approved_at = date('Y-m-d H:i:s');
        } else {
            $approved_at = $existing_approved_at;
        }
    
        $stmt = $connection->prepare("
            UPDATE tbl_proposal_signatories 
            SET signatory_status = ?, comments = ?, approved_at = ?
            WHERE approval_id = ?
        ");
        $stmt->bind_param('sssi', $status, $comments, $approved_at, $approval_id);
    } else {
        // For non-Approved statuses (no approved_at needed)
        $stmt = $connection->prepare("
            UPDATE tbl_proposal_signatories 
            SET signatory_status = ?, comments = ?
            WHERE approval_id = ?
        ");
        $stmt->bind_param('ssi', $status, $comments, $approval_id);
    }
    
    // Execute the statement once
    if ($stmt->execute()) {
        setFlashMessage('success', 'Signatory status and comments updated successfully!');
        
        // Log the action based on status
        $user_id = $_SESSION['user_id'] ?? null;
        $user_name = $_SESSION['username'] ?? 'System';
        
        if ($status === 'Approved') {
            $log_action = "Proposal Approved";
            $log_remarks = "Proposal approved by $user_name. Comment: $comments.";
            
            $logStmt = $connection->prepare("
                INSERT INTO tbl_proposal_logs (proposal_id, action, user_id, remarks, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $logStmt->bind_param('ssis', $proposal_id, $log_action, $user_id, $log_remarks);
            $logStmt->execute();
            $logStmt->close();
        }

        // 🔄 Reset ALL signatories if proposal needs revision
        if ($status === 'Revise') {
            $resetStmt = $connection->prepare("
                UPDATE tbl_proposal_signatories
                SET signatory_status = 'Pending'
                WHERE proposal_id = ?
            ");
            $resetStmt->bind_param('i', $proposal_id);
            if ($resetStmt->execute()) {
                setFlashMessage('warning', 'All signatory statuses reset to Pending. Proposal marked for revision.');
        
                // ✅ Set proposal status directly to 'Revise'
                $reviseStmt = $connection->prepare("
                    UPDATE tbl_proposal SET status = 'Revise' WHERE proposal_id = ?
                ");
                $reviseStmt->bind_param('i', $proposal_id);
                if ($reviseStmt->execute()) {
                    setFlashMessage('info', 'Proposal status updated to Revise.');
                } else {
                    setFlashMessage('danger', 'Failed to update proposal status to Revise: ' . $reviseStmt->error);
                }
                $reviseStmt->close();
        
                // ✅ Log the revision action
                $user_id = $_SESSION['user_id'] ?? null;
                $user_name = $_SESSION['username'] ?? 'System';
                $remarks = "Proposal set to Revise by $user_name. All signatory statuses reset.";
        
                $logStmt = $connection->prepare("
                    INSERT INTO tbl_proposal_logs (proposal_id, action, user_id, remarks, created_at)
                    VALUES (?, 'Revision Initiated', ?, ?, NOW())
                ");
                $logStmt->bind_param('iis', $proposal_id, $user_id, $remarks);
                $logStmt->execute();
                $logStmt->close();
            } else {
                setFlashMessage('danger', 'Failed to reset signatory statuses: ' . $resetStmt->error);
            }
            $resetStmt->close();
        
            // ✅ Skip recalculation and update (Revise is final here)
            $stmt->close();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        

        // 2. Recalculate overall proposal status
        $statusStmt = $connection->prepare("
            SELECT 
                SUM(signatory_status = 'Denied') AS denied,
                SUM(signatory_status = 'Revise') AS revise,
                SUM(signatory_status = 'Pending') AS pending,
                SUM(signatory_status = 'Approved') AS approved
            FROM tbl_proposal_signatories 
            WHERE proposal_id = ?
        ");
        $statusStmt->bind_param('i', $proposal_id);
        $statusStmt->execute();
        $statusStmt->bind_result($denied, $revise, $pending, $approved);
        $statusStmt->fetch();
        $statusStmt->close();

        $newStatus = null;
        $msgType = 'info';
        $msgText = 'Proposal status updated.';

        if ($denied > 0) {
            $newStatus = 'Rejected';
            $msgType = 'danger';
            $msgText = 'Proposal status updated to Rejected.';
        } elseif ($revise > 0) {
            $newStatus = 'Revise';
            $msgType = 'warning';
            $msgText = 'Proposal requires revision.';
        } elseif ($pending == 0 && $approved > 0) {
            $newStatus = 'Approved';
            $msgType = 'success';
            $msgText = 'Proposal fully approved!';
        } else {
            $newStatus = 'Pending';
            $msgType = 'info';
            $msgText = 'Proposal status set to Pending.';
        }

        // 3. Update proposal status
        $updateStmt = $connection->prepare("
            UPDATE tbl_proposal SET status = ? WHERE proposal_id = ?
        ");
        $updateStmt->bind_param('si', $newStatus, $proposal_id);
        if ($updateStmt->execute()) {
            setFlashMessage($msgType, $msgText);
        } else {
            setFlashMessage('danger', 'Error updating proposal status: ' . $updateStmt->error);
        }
        $updateStmt->close();

    } else {
        setFlashMessage('danger', 'Error updating signatory status: ' . $stmt->error);
    }

    $stmt->close();

    // Avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}



// Add new query for core values analysis based on the updated table structure
$sqlCoreValues = "
    SELECT mvc.mvc_value AS core_value, 
           COUNT(*) AS value_count,
           GROUP_CONCAT(DISTINCT mvc.mvc_type SEPARATOR '|') AS descriptions
    FROM tbl_mvc mvc
    WHERE mvc.mvc_type = 'core value'
    GROUP BY mvc.mvc_value
    ORDER BY value_count DESC";
$resultCoreValues = $connection->query($sqlCoreValues);

// Initialize arrays for core values data
$coreValueLabels = [];
$coreValueCounts = [];
$coreValueDescriptions = [];

// Check if the query was successful
if ($resultCoreValues) {
    while ($row = $resultCoreValues->fetch_assoc()) {
        $coreValueLabels[] = $row['core_value'];
        $coreValueCounts[] = (int)$row['value_count'];
        
        // Handle description extraction (assuming descriptions are part of the mvc_type)
        // Since `mvc_type` contains the type, we'll just return 'core value' as a placeholder.
        $coreValueDescriptions[] = explode('|', $row['descriptions'])[0];  // Assuming descriptions are concatenated
    }
}

// Get most common core value
$mostCommonCoreValue = !empty($coreValueLabels) ? $coreValueLabels[0] : 'N/A';
$mostCommonCount = !empty($coreValueCounts) ? $coreValueCounts[0] : 0;

// Get unique core values count
$sqlUniqueValues = "SELECT COUNT(DISTINCT mvc_value) AS unique_count 
                    FROM tbl_mvc 
                    WHERE mvc_type = 'core value'";
$resultUniqueValues = $connection->query($sqlUniqueValues);
$uniqueValuesCount = 0;
if ($resultUniqueValues && $row = $resultUniqueValues->fetch_assoc()) {
    $uniqueValuesCount = $row['unique_count'];
}


// Add new SDG count query
$sqlSdgCount = "SELECT sdg.sdg_number, sdg.sdg_description, COUNT(*) as count 
                FROM tbl_proposal_sdgs sdg
                GROUP BY sdg.sdg_number, sdg.sdg_description
                ORDER BY sdg.sdg_number";
$resultSdgCount = $connection->query($sqlSdgCount);


// Top 10 proposals by budget (only items with category = 'Budget')
$sqlBudgetByProposal = "
    SELECT p.proposal_id, p.title, SUM(d.amount) as total_budget
    FROM $table p
    JOIN tbl_proposal_details d ON p.proposal_id = d.proposal_id
    WHERE d.category = 'Budget'  -- Only budget items
    GROUP BY p.proposal_id, p.title
    ORDER BY total_budget DESC
    LIMIT 100";
$resultBudgetByProposal = $connection->query($sqlBudgetByProposal);

// Initialize arrays for budget data
$proposalTitles = [];
$proposalBudgets = [];

if ($resultBudgetByProposal) {
    while ($row = $resultBudgetByProposal->fetch_assoc()) {
        $proposalTitles[] = substr($row['title'], 0, 20) . '...'; // Truncate long titles
        $proposalBudgets[] = (float)$row['total_budget'];
    }
}

// Total budget calculation (all budget items across all proposals)
$sqlTotalBudget = "SELECT SUM(amount) as total FROM tbl_proposal_details WHERE category = 'Budget'";
$resultTotalBudget = $connection->query($sqlTotalBudget);
$totalBudget = 0;
if ($resultTotalBudget && $row = $resultTotalBudget->fetch_assoc()) {
    $totalBudget = $row['total'];
}

// Average budget per proposal (only considering proposals with budget items)
$sqlAvgBudget = "
    SELECT AVG(total_budget) as avg_budget
    FROM (
        SELECT proposal_id, SUM(amount) as total_budget
        FROM tbl_proposal_details
        WHERE category = 'Budget'
        GROUP BY proposal_id
    ) as proposal_totals";
$resultAvgBudget = $connection->query($sqlAvgBudget);
$avgBudget = 0;
if ($resultAvgBudget && $row = $resultAvgBudget->fetch_assoc()) {
    $avgBudget = $row['avg_budget'];
}


// Query for calculating average processing time (in days)
$sqlAvgProcessingTime = "
    SELECT AVG(diff_hours) AS avg_processing_time
    FROM (
        SELECT 
            p.proposal_id,
            TIMESTAMPDIFF(HOUR, 
                (SELECT MIN(approved_at) 
                 FROM tbl_proposal_signatories 
                 WHERE proposal_id = p.proposal_id AND approved_at IS NOT NULL), 
                (SELECT MAX(approved_at) 
                 FROM tbl_proposal_signatories 
                 WHERE proposal_id = p.proposal_id AND approved_at IS NOT NULL)
            ) / 24.0 AS diff_hours
        FROM tbl_proposal p
        WHERE p.status = 'Approved'
        AND EXISTS (
            SELECT 1 
            FROM tbl_proposal_signatories s1 
            WHERE s1.proposal_id = p.proposal_id AND s1.approved_at IS NOT NULL
        )
        AND EXISTS (
            -- Make sure there are at least two different timestamps
            SELECT 1 
            FROM tbl_proposal_signatories s1 
            JOIN tbl_proposal_signatories s2 ON s1.proposal_id = s2.proposal_id 
            WHERE s1.proposal_id = p.proposal_id 
            AND s1.approved_at < s2.approved_at
        )
    ) AS processing_times
";

$resultAvgProcessingTime = $connection->query($sqlAvgProcessingTime);

$avgProcessingTime = 0;
if ($resultAvgProcessingTime && $row = $resultAvgProcessingTime->fetch_assoc()) {
    $avgProcessingTime = round($row['avg_processing_time'], 1);
}

// Query to calculate the number of pending proposals
// Query to get the count of pending proposals
$sqlPendingProposals = "
    SELECT COUNT(*) AS pending 
    FROM $table 
    WHERE status = 'Pending'";
$resultPending = $connection->query($sqlPendingProposals);

// Evaluation ratings data is now fetched via AJAX in chart_data_ajax.php

// Query to get the total number of proposals
$sqlTotalProposals = "
    SELECT COUNT(*) AS total 
    FROM $table";
$resultTotal = $connection->query($sqlTotalProposals);

// Fetch pending proposals count
$pendingProposals = 0;
if ($resultPending && $row = $resultPending->fetch_assoc()) {
    $pendingProposals = $row['pending'];
}

// Fetch total proposals count
$totalProposals = 0;
if ($resultTotal && $row = $resultTotal->fetch_assoc()) {
    $totalProposals = $row['total'];
}


// Query to calculate the number of in-progress proposals
$sqlInProgressProposals = "
    SELECT COUNT(*) AS in_progress_count 
    FROM $table 
    WHERE status = 'In Progress'";
$resultInProgressProposals = $connection->query($sqlInProgressProposals);


// Fetch in-progress proposals count
$inProgressProposals = 0;
if ($resultInProgressProposals && $row = $resultInProgressProposals->fetch_assoc()) {
    $inProgressProposals = $row['in_progress_count'];
}


// Query to fetch the total count of all pending proposals
$sqlPendingProposals = "
    SELECT COUNT(*) AS total_pending 
    FROM $table 
    WHERE status = 'Pending'";

$resultPending = $connection->query($sqlPendingProposals);

// Fetch the count
$pendingProposals = 0;
if ($resultPending && $row = $resultPending->fetch_assoc()) {
    $pendingProposals = $row['total_pending'];
}


// Fetch Mission Values
$sqlMissionValues = "
    SELECT mvc.mvc_value AS mission_value, 
           COUNT(*) AS value_count
    FROM tbl_mvc mvc
    WHERE mvc.mvc_type = 'mission'
    GROUP BY mvc.mvc_value
    ORDER BY value_count DESC";
$resultMissionValues = $connection->query($sqlMissionValues);

// Process mission values
$missionLabels = [];
$missionCounts = [];
if ($resultMissionValues) {
    while ($row = $resultMissionValues->fetch_assoc()) {
        $missionLabels[] = $row['mission_value'];
        $missionCounts[] = (int)$row['value_count'];
    }
}

// Fetch Vision Values
$sqlVisionValues = "
    SELECT mvc.mvc_value AS vision_value, 
           COUNT(*) AS value_count
    FROM tbl_mvc mvc
    WHERE mvc.mvc_type = 'vision'
    GROUP BY mvc.mvc_value
    ORDER BY value_count DESC";
$resultVisionValues = $connection->query($sqlVisionValues);

// Process vision values
$visionLabels = [];
$visionCounts = [];
if ($resultVisionValues) {
    while ($row = $resultVisionValues->fetch_assoc()) {
        $visionLabels[] = $row['vision_value'];
        $visionCounts[] = (int)$row['value_count'];
    }
}

// Function to get the slowest processing organization
function getSlowestProcessingOrganization($connection) {
    $sql = "
        SELECT 
            p.organization,
            AVG(TIMESTAMPDIFF(HOUR, 
                (SELECT MIN(approved_at) 
                 FROM tbl_proposal_signatories 
                 WHERE proposal_id = p.proposal_id AND approved_at IS NOT NULL), 
                (SELECT MAX(approved_at) 
                 FROM tbl_proposal_signatories 
                 WHERE proposal_id = p.proposal_id AND approved_at IS NOT NULL)
            )) AS avg_processing_time
        FROM tbl_proposal p
        WHERE p.status = 'Approved'
        AND EXISTS (
            SELECT 1 
            FROM tbl_proposal_signatories s1 
            WHERE s1.proposal_id = p.proposal_id AND s1.approved_at IS NOT NULL
        )
        AND EXISTS (
            -- Make sure there are at least two different timestamps
            SELECT 1 
            FROM tbl_proposal_signatories s1 
            JOIN tbl_proposal_signatories s2 ON s1.proposal_id = s2.proposal_id 
            WHERE s1.proposal_id = p.proposal_id 
            AND s1.approved_at < s2.approved_at
        )
        GROUP BY p.organization
        ORDER BY avg_processing_time DESC
        LIMIT 1
    ";

    $result = $connection->query($sql);

    $slowestOrganization = "N/A";
    $averageProcessingTime = 0;

    if ($result && $row = $result->fetch_assoc()) {
        $slowestOrganization = $row['organization'];
        $averageProcessingTime = $row['avg_processing_time'];
    }

    return [
        'organization' => htmlspecialchars($slowestOrganization),
        'average_time' => $averageProcessingTime
    ];
}


// Example usage
$slowestProcessingInfo = getSlowestProcessingOrganization($connection, $table);


// Fetch data for the bar graph
$sqlTypeCount = "SELECT type, COUNT(*) AS count FROM $table GROUP BY type";
$resultTypeCount = $connection->query($sqlTypeCount);


$sqlOrganizationCount = "SELECT organization, COUNT(*) AS count FROM $table GROUP BY organization";
$resultOrganizationCount = $connection->query($sqlOrganizationCount);


$sqlStatusCount = "SELECT status, COUNT(*) AS count FROM $table GROUP BY status";
$resultStatusCount = $connection->query($sqlStatusCount);


$types = [];
$typeCounts = [];
$organizations = [];
$organizationCounts = [];
$statuses = [];
$statusCounts = [];
$sdgDescriptions = [];
$sdgCounts = [];


if ($resultTypeCount) {
    while ($row = $resultTypeCount->fetch_assoc()) {
        $types[] = $row['type'];
        $typeCounts[] = (int)$row['count'];
    }
}


if ($resultOrganizationCount) {
    while ($row = $resultOrganizationCount->fetch_assoc()) {
        $organizations[] = $row['organization'];
        $organizationCounts[] = (int)$row['count'];
    }
}


if ($resultStatusCount) {
    while ($row = $resultStatusCount->fetch_assoc()) {
        $statuses[] = $row['status'];
        $statusCounts[] = (int)$row['count'];
    }
}


// Add new SDG data collection
if ($resultSdgCount) {
    while ($row = $resultSdgCount->fetch_assoc()) {
        $sdgDescriptions[] = "SDG " . $row['sdg_number'] . ": " . $row['sdg_description'];
        $sdgCounts[] = (int)$row['count'];
    }
}

// Fetch venue data
$venues = [];
$venueCounts = [];
$monthlyVenueData = [];

$venueQuery = "
    SELECT 
        venue, 
        COUNT(*) as count 
    FROM 
        tbl_proposal 
    GROUP BY 
        venue
";
$venueResult = $connection->query($venueQuery);

if ($venueResult && $venueResult->num_rows > 0) {
    while ($row = $venueResult->fetch_assoc()) {
        $venues[] = $row['venue'] ?: 'Unknown'; // Handle NULL values
        $venueCounts[] = $row['count'];
    }
}

// Fetch monthly venue data (to track venue usage over time)
$venueQueryByMonth = "
    SELECT 
        venue, 
        DATE_FORMAT(submitted_at, '%Y-%m') as month, 
        COUNT(*) as count 
    FROM 
        tbl_proposal 
    GROUP BY 
        venue, 
        DATE_FORMAT(submitted_at, '%Y-%m')
";
$venueResultByMonth = $connection->query($venueQueryByMonth);

if ($venueResultByMonth && $venueResultByMonth->num_rows > 0) {
    while ($row = $venueResultByMonth->fetch_assoc()) {
        $venue = $row['venue'] ?: 'Unknown'; // Handle NULL values
        $monthlyVenueData[$venue][$row['month']] = $row['count'];
    }
}
// After your existing SQL queries, add:
$sqlCampusLocation = "
    SELECT 
        CASE 
            WHEN campus_act = 'In-Campus' THEN 'In-Campus'
            WHEN campus_act = 'Off-Campus' THEN 'Off-Campus'
            ELSE 'Unspecified'
        END as location,
        COUNT(*) as count 
    FROM tbl_proposal 
    GROUP BY location";
$resultCampusLocation = $connection->query($sqlCampusLocation);

// Initialize arrays for campus location data
$locationLabels = [];
$locationCounts = [];

if ($resultCampusLocation) {
    while ($row = $resultCampusLocation->fetch_assoc()) {
        $locationLabels[] = $row['location'];
        $locationCounts[] = (int)$row['count'];
    }
}

// Add queries for monthly submitted and approved proposals
$sqlMonthlyData = "
    SELECT 
        DATE_FORMAT(submitted_at, '%Y-%m') as month_year,
        COUNT(CASE WHEN status = 'Submitted' THEN 1 END) as submitted_count,
        COUNT(CASE WHEN status = 'Approved' THEN 1 END) as approved_count
    FROM $table 
    WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
    ORDER BY month_year";

$resultMonthlyData = $connection->query($sqlMonthlyData);

$monthLabels = [];
$submittedCounts = [];
$approvedCounts = [];

// Query for proposals passed by month
$sqlProposalsPassed = "
    SELECT 
        DATE_FORMAT(submitted_at, '%Y-%m') as month_year,
        DATE_FORMAT(submitted_at, '%b') as month_short,
        COUNT(*) as passed_count
    FROM $table 
    WHERE status = 'Approved'
    AND submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(submitted_at, '%Y-%m'), DATE_FORMAT(submitted_at, '%b')
    ORDER BY month_year";

$resultProposalsPassed = $connection->query($sqlProposalsPassed);

$passedMonthLabels = [];
$passedProposalCounts = [];

if ($resultProposalsPassed) {
    while ($row = $resultProposalsPassed->fetch_assoc()) {
        $passedMonthLabels[] = $row['month_short'];
        $passedProposalCounts[] = (int)$row['passed_count'];
    }
}

if ($resultMonthlyData) {
    while ($row = $resultMonthlyData->fetch_assoc()) {
        $monthLabels[] = date('M Y', strtotime($row['month_year'] . '-01'));
        $submittedCounts[] = (int)$row['submitted_count'];
        $approvedCounts[] = (int)$row['approved_count'];
    }
}

// Check if the session variable is set
if (!isset($_SESSION['user_id'])) {
    die("User is not logged in."); // Or redirect to login page
}

// Retrieve the user_id from the session
$user_id = $_SESSION['user_id'];

$sql = "
    SELECT 
        p.proposal_id, 
        p.*, 
        ps.approval_id,
        
        GROUP_CONCAT(DISTINCT sdg.sdg_number ORDER BY sdg.sdg_number SEPARATOR ', ') AS sdg_number,
        GROUP_CONCAT(DISTINCT sdg.sdg_description ORDER BY sdg.sdg_description SEPARATOR ', ') AS sdg_description,
        
        GROUP_CONCAT(DISTINCT 
            CASE WHEN mvc.mvc_type = 'mission' THEN mvc.mvc_value END 
            ORDER BY mvc.mvc_value SEPARATOR '||'
        ) AS mission_values,

        GROUP_CONCAT(DISTINCT 
            CASE WHEN mvc.mvc_type = 'vision' THEN mvc.mvc_value END 
            ORDER BY mvc.mvc_value SEPARATOR '||'
        ) AS vision_values,

        GROUP_CONCAT(DISTINCT 
            CASE WHEN mvc.mvc_type = 'core value' THEN mvc.mvc_value END 
            ORDER BY mvc.mvc_value SEPARATOR '||'
        ) AS core_values,

        COUNT(DISTINCT ps.signatory_role) AS signatory_count,
        
        GROUP_CONCAT(DISTINCT COALESCE(ps.signatory_role, '') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_roles,
        GROUP_CONCAT(DISTINCT COALESCE(ps.signatory_name, '') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_names,
        GROUP_CONCAT(DISTINCT COALESCE(ps.signatory_status, '') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_statuses,
        GROUP_CONCAT(DISTINCT COALESCE(ps.comments, 'No comment') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_comments,

        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd1.category = 'Budget' 
            THEN pd1.field1 END 
            ORDER BY pd1.detail_id SEPARATOR '||'
        ) AS budget_particulars,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd1.category = 'Budget' 
            THEN CAST(pd1.amount AS DECIMAL(10,2)) END 
            ORDER BY pd1.detail_id SEPARATOR '||'
        ) AS budget_amounts,
        
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd2.category = 'Syllabus' 
            THEN pd2.field1 END 
            ORDER BY pd2.detail_id SEPARATOR '||'
        ) AS syllabus_subjects,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd2.category = 'Syllabus' 
            THEN pd2.field2 END 
            ORDER BY pd2.detail_id SEPARATOR '||'
        ) AS syllabus_topics,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd2.category = 'Syllabus' 
            THEN pd2.field3 END 
            ORDER BY pd2.detail_id SEPARATOR '||'
        ) AS syllabus_relevance,
        
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd3.category = 'Program' 
            THEN pd3.field1 END 
            ORDER BY pd3.detail_id SEPARATOR '||'
        ) AS program_names,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd3.category = 'Program' 
            THEN pd3.field2 END 
            ORDER BY pd3.detail_id SEPARATOR '||'
        ) AS program_details,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd3.category = 'Program' 
            THEN pd3.field3 END 
            ORDER BY pd3.detail_id SEPARATOR '||'
        ) AS program_persons,
        
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd4.category = 'Manpower' 
            THEN pd4.field1 END 
            ORDER BY pd4.detail_id SEPARATOR '||'
        ) AS manpower_roles,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd4.category = 'Manpower' 
            THEN pd4.field2 END 
            ORDER BY pd4.detail_id SEPARATOR '||'
        ) AS manpower_names,
        GROUP_CONCAT(DISTINCT 
            CASE WHEN pd4.category = 'Manpower' 
            THEN pd4.field3 END 
            ORDER BY pd4.detail_id SEPARATOR '||'
        ) AS manpower_responsibilities

    FROM $table AS p
    LEFT JOIN tbl_proposal_sdgs AS sdg ON p.proposal_id = sdg.proposal_id
    LEFT JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
    LEFT JOIN tbl_proposal_details AS pd1 ON p.proposal_id = pd1.proposal_id AND pd1.category = 'Budget'
    LEFT JOIN tbl_proposal_details AS pd2 ON p.proposal_id = pd2.proposal_id AND pd2.category = 'Syllabus'
    LEFT JOIN tbl_proposal_details AS pd3 ON p.proposal_id = pd3.proposal_id AND pd3.category = 'Program'
    LEFT JOIN tbl_proposal_details AS pd4 ON p.proposal_id = pd4.proposal_id AND pd4.category = 'Manpower'
    LEFT JOIN tbl_proposal_signatories AS ps ON p.proposal_id = ps.proposal_id
    WHERE ps.user_id = ?
    AND (p.is_deleted = 0 OR p.is_deleted IS NULL)
    AND p.status != 'Revise'
    AND (
        -- Case 1: Approved or Denied status (removing Revise from here)
        ps.signatory_status IN ('Approved', 'Denied')
        OR
        -- Case 1b: Revise status (handle separately)
        ps.signatory_status = 'Revise'
        OR
        -- Case 2: Current user is the next Pending signatory 
        -- (but only if no previous signatory has Revise status)
        (
            ps.signatory_status = 'Pending'
            AND ps.signatory_order = (
                SELECT MIN(ps2.signatory_order)
                FROM tbl_proposal_signatories ps2
                WHERE ps2.proposal_id = ps.proposal_id
                AND ps2.signatory_status = 'Pending'
            )
            AND NOT EXISTS (
                -- Check that no previous signatory has Revise status
                SELECT 1
                FROM tbl_proposal_signatories ps3
                WHERE ps3.proposal_id = ps.proposal_id
                AND ps3.signatory_status = 'Revise'
                AND ps3.signatory_order < ps.signatory_order
            )
        )
    )
    GROUP BY 
        p.proposal_id
    ORDER BY 
        p.proposal_id DESC";

// Prepare the statement for active proposals
$stmt = $connection->prepare($sql);

if ($stmt) {
    // Bind the parameter
    $stmt->bind_param('i', $user_id);

    // Execute the statement
    $stmt->execute();

    // Get the result for active proposals
    $result = $stmt->get_result();
   
    // Close the statement
    $stmt->close();
} else {
    // Handle errors in statement preparation
    echo "Error preparing the SQL statement: " . $connection->error;
}

// Now add a query for archived proposals
$sqlArchived = "
    SELECT 
        p.proposal_id, 
        p.*, 
        ps.approval_id,
        
        GROUP_CONCAT(DISTINCT sdg.sdg_number ORDER BY sdg.sdg_number SEPARATOR ', ') AS sdg_number,
        GROUP_CONCAT(DISTINCT sdg.sdg_description ORDER BY sdg.sdg_description SEPARATOR ', ') AS sdg_description,
        
        GROUP_CONCAT(DISTINCT 
            CASE WHEN mvc.mvc_type = 'mission' THEN mvc.mvc_value END 
            ORDER BY mvc.mvc_value SEPARATOR '||'
        ) AS mission_values,

        GROUP_CONCAT(DISTINCT 
            CASE WHEN mvc.mvc_type = 'vision' THEN mvc.mvc_value END 
            ORDER BY mvc.mvc_value SEPARATOR '||'
        ) AS vision_values,

        GROUP_CONCAT(DISTINCT 
            CASE WHEN mvc.mvc_type = 'core value' THEN mvc.mvc_value END 
            ORDER BY mvc.mvc_value SEPARATOR '||'
        ) AS core_values,

        COUNT(DISTINCT ps.signatory_role) AS signatory_count,
        
        GROUP_CONCAT(DISTINCT COALESCE(ps.signatory_role, '') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_roles,
        GROUP_CONCAT(DISTINCT COALESCE(ps.signatory_name, '') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_names,
        GROUP_CONCAT(DISTINCT COALESCE(ps.signatory_status, '') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_statuses,
        GROUP_CONCAT(DISTINCT COALESCE(ps.comments, 'No comment') ORDER BY ps.signatory_order SEPARATOR '||') AS signatory_comments

    FROM $table AS p
    LEFT JOIN tbl_proposal_sdgs AS sdg ON p.proposal_id = sdg.proposal_id
    LEFT JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
    LEFT JOIN tbl_proposal_signatories AS ps ON p.proposal_id = ps.proposal_id
    WHERE ps.user_id = ?
    AND p.is_deleted = 1
    GROUP BY 
        p.proposal_id
    ORDER BY 
        p.proposal_id DESC";

// Prepare the statement for archived proposals
$stmtArchived = $connection->prepare($sqlArchived);

if ($stmtArchived) {
    // Bind the parameter
    $stmtArchived->bind_param('i', $user_id);

    // Execute the statement
    $stmtArchived->execute();

    // Get the result for archived proposals
    $resultArchived = $stmtArchived->get_result();
   
    // Close the statement
    $stmtArchived->close();
} else {
    // Handle errors in statement preparation
    echo "Error preparing the archived SQL statement: " . $connection->error;
}

// Get proposals with evaluations for evaluation analytics
$sql_proposals = "SELECT p.proposal_id, p.title, COUNT(e.evaluation_id) as eval_count 
                FROM tbl_proposal p 
                JOIN tbl_evaluation e ON p.proposal_id = e.proposal_id 
                WHERE p.is_deleted = 0 
                GROUP BY p.proposal_id 
                HAVING eval_count > 0
                ORDER BY eval_count DESC";
$stmt_proposals = $connection->prepare($sql_proposals);
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
    $stmt = $connection->prepare($sql);
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
    
    // Close evaluation statement
    $stmt->close();
}

// Close the statement for proposals with evaluations
$stmt_proposals->close();

$connection->close();
?> 


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Proposal Management</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    
    <!-- System CSS -->
    <link rel="stylesheet" href="../resources/css/user.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <!-- Other CSS -->
    <link rel="stylesheet" href="../resources/css/gm_table_chart.css">
    
    <!-- Add this simple CSS for tabs -->
    <style>
      .nav-tabs .nav-link.active {
        color: #0d6efd;
        font-weight: 500;
      }
      .nav-tabs .nav-link:not(.active) {
        color: #6c757d;
      }
    </style>
    
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">  
   
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
        <?php include('../resources/utilities/modal/sign_proposal_modal.php'); ?>

        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">PROPOSAL MANAGEMENT</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>

            <div class="user-wrapper">
                <div class="insight-wrapper row">
                    <!-- First Row -->
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Average Processing Time</h6>
                            <h4>
                                <?php
                                if ($avgProcessingTime > 0) {
                                    $days = floor($avgProcessingTime);
                                    $hours = floor(($avgProcessingTime - $days) * 24);
                                    $minutes = round((($avgProcessingTime - $days) * 24 - $hours) * 60);

                                    echo $days . ' Day' . ($days != 1 ? 's' : '');
                                    if ($hours > 0) echo ' ' . $hours . ' Hour' . ($hours != 1 ? 's' : '');
                                    if ($minutes > 0) echo ' ' . $minutes . ' Minute' . ($minutes != 1 ? 's' : '');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </h4>
                            <p><span style="color: green;">Based on processed requests</span></p>
                        </div>
                    </div>

                
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Pending Requests</h6>
                            <h4><?php echo $pendingProposals; ?></h4>
                            <p><span style="color: green;">Total of proposals submitted: <?php echo $totalProposals; ?></span></p>
                            
                        </div>
                    </div>


                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Total Proposed Budget</h6>
                            <h4>₱<?php echo number_format($totalBudget, 2); ?></h4>
                            <p>Average: ₱<?php echo number_format($avgBudget, 2); ?> per proposal</p>
                        </div>
                    </div>

                    <!-- Second Row -->
                    <div class="col-md-3">
                        <div class="info-card">
                            <h6>Slowest Process for the Month</h6>
                            <h4><?php echo $slowestProcessingInfo['organization']; ?></h4>
                            <p style="color: #bf5615;">Processing Time: 
                                <?php
                                    $totalHours = $slowestProcessingInfo['average_time'];
                                    if ($totalHours > 0) {
                                        $days = floor($totalHours / 24);
                                        $remainingHours = floor($totalHours % 24);
                                        $minutes = round(($totalHours - floor($totalHours)) * 60);

                                        $parts = [];
                                        if ($days > 0) {
                                            $parts[] = $days . ' Day' . ($days > 1 ? 's' : '');
                                        }
                                        if ($remainingHours > 0) {
                                            $parts[] = $remainingHours . ' Hour' . ($remainingHours > 1 ? 's' : '');
                                        }
                                        if ($minutes > 0) {
                                            $parts[] = $minutes . ' Minute' . ($minutes > 1 ? 's' : '');
                                        }

                                        echo implode(', ', $parts);
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-wrapper" style="height: auto;">
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs mb-3" id="chartTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="proposal-tab" data-bs-toggle="tab" data-bs-target="#proposal-chart"
                            type="button" role="tab" aria-controls="proposal-chart" aria-selected="true">
                            Proposal Chart
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="evaluation-tab" data-bs-toggle="tab" data-bs-target="#evaluation-chart"
                            type="button" role="tab" aria-controls="evaluation-chart" aria-selected="false">
                            Evaluation Chart
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">                   
                        <!-- Evaluation Analytics Section -->  
                        <div class="tab-pane fade" id="evaluation-chart"
                        role="tabpanel" aria-labelledby="evaluation-tab">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-3">Evaluation Analytics</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // If no proposals with evaluations, show a message
                                if (!$eval_data_available) {
                                    echo '<div class="alert alert-info">No evaluation data available. Once proposals receive evaluations, analytics will be displayed here.</div>';
                                } else {
                                ?>
                                    
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
                                                    <a id="exportBtn" href="../01_student/export_evaluation.php?proposal_id=<?= $current_proposal_id ?>" class="btn btn-secondary btn-sm action-btn">
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
                                                                                    <?php foreach ($affiliationTypes as $type): ?>
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
                                                                                    <?php endforeach; ?>
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
                                }
                                ?>
                            </div>
                        </div><!-- End of evaluation tab -->
                   
                        <div class="tab-pane fade show active" id="proposal-chart"
                            role="tabpanel" aria-labelledby="proposal-tab">
                            <!-- Filters row at the top -->
                            <div class="mb-1 pb-4" style="border-bottom: 1px solid rgb(225, 228, 228);">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Chart Type -->
                                        <div class="col-md-2 mb-2">
                                            <label for="chartDropdownSelector" class="form-label">Chart Type</label>
                                            <select class="form-select form-select-sm" id="chartDropdownSelector">
                                                <option value="organization">Departments/Organizations</option>
                                                <option value="core values">Core Values</option>
                                                <option value="mission">Mission Distribution</option>
                                                <option value="vision">Vision Distribution</option>
                                                <option value="sdg">SDG Distribution</option>
                                                <option value="proposals_passed">Proposals Passed</option>
                                                <option value="evaluation_ratings">Top 5 Evaluation Ratings</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Type of Proposal -->
                                        <div class="col-md-2 mb-2">
                                            <label for="proposalTypeFilter" class="form-label">Type of Proposal</label>
                                            <select class="form-select form-select-sm" id="proposalTypeFilter">
                                                <option value="all">All Types</option>
                                                <option value="Extra-Curricular Activity Proposal">Extra-Curricular Activity Proposal</option>
                                                <option value="Extra-Curricular Activity Proposal (Community Project)">Extra-Curricular Activity Proposal (Community Project)</option>
                                                <option value="Co-Curricular Activity Proposal">Co-Curricular Activity Proposal</option>
                                                <option value="Co-Curricular Activity Proposal (Community Project)">Co-Curricular Activity Proposal (Community Project)</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Status -->
                                        <div class="col-md-2 mb-2">
                                            <label for="statusFilter" class="form-label">Status</label>
                                            <select class="form-select form-select-sm" id="statusFilter">
                                                <option value="all">All Statuses</option>
                                                <option value="Approved">Approved</option>
                                                <option value="Pending">Pending</option>
                                                <option value="Revise">Revise</option>
                                                <option value="Rejected">Rejected</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Month/Year -->
                                        <div class="col-md-2 mb-2">
                                            <label for="monthFilter" class=" mb-2">Month</label>
                                            <select id="monthFilter" name="month" class="form-select form-select-sm">
                                                <option value="All">All</option>
                                                <option value="Jan">January</option>
                                                <option value="Feb">February</option>
                                                <option value="Mar">March</option>
                                                <option value="Apr">April</option>
                                                <option value="May">May</option>
                                                <option value="Jun">June</option>
                                                <option value="Jul">July</option>
                                                <option value="Aug">August</option>
                                                <option value="Sep">September</option>
                                                <option value="Oct">October</option>
                                                <option value="Nov">November</option>
                                                <option value="Dec">December</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-2 mb-2">
                                            <label for="yearFilter" class=" mb-2">Year</label>
                                            <select id="yearFilter" name="year" class="form-select form-select-sm">
                                                <?php 
                                                $currentYear = date("Y");
                                                // Display all years in ascending order from 2020 to 2050
                                                for ($y = 2020; $y <= 2050; $y++) {
                                                    echo "<option value=\"$y\"" . ($y == $currentYear ? " selected" : "") . ">$y</option>";
                                                } 
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-2 mb-2">
                                            <label for="departmentFilter" class="form-label">Department/Organization</label>
                                            <select class="form-select form-select-sm" id="departmentFilter">
                                                <option value="all">All Departments/Organizations</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div> <br>
                            
                            <!-- Chart area below (full width) -->
                            <div style="background-color: #fff; border-radius: 0.25rem;">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <h6 class="mb-0 me-3">Proposal Analytics</h6>
                                        <div id="chartLegendContainer" class="d-flex flex-wrap align-items-center pt-1"></div>
                                    </div>
                                    <div class="chart-container" style="height: 200px;">
                                        <canvas id="proposalChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>                       
                </div> <!-- End of dashboard-wrapper -->

                <div class="user-page">
                    <?php if ($flash_message): ?>
                        <?php
                        $icon = match ($flash_message['type']) {
                            'success' => 'check_circle',
                            'warning' => 'warning',
                            'danger'  => 'error',
                            'info'    => 'info',
                            default   => 'notification_important'
                        };
                        ?>
                        <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> d-flex align-items-center ms-3" role="alert">
                            <span class='material-symbols-outlined me-2'><?= $icon ?></span>
                            <div><?= htmlspecialchars($flash_message['message']) ?></div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs mb-3" id="proposalTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="active-proposals-tab" data-bs-toggle="tab" data-bs-target="#active-proposals" 
                                type="button" role="tab" aria-controls="active-proposals" aria-selected="true">
                                Active Proposals
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="archived-proposals-tab" data-bs-toggle="tab" data-bs-target="#archived-proposals" 
                                type="button" role="tab" aria-controls="archived-proposals" aria-selected="false">
                                Archived Proposals
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tabs Content -->
                    <div class="tab-content" id="proposalTabsContent">
                        <!-- Active Proposals Tab -->
                        <div class="tab-pane fade show active" id="active-proposals" role="tabpanel" aria-labelledby="active-proposals-tab">
                            <form id="gmform" method="POST" action="request_table.php">
                                <input type="hidden" name="action" id="action-input" value="">
                                
                                <div class="table-responsive">
                                    <table id="facultyproposalTable" class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Organization</th>
                                                <th>President</th>
                                                <th>Proposed Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($result && $result->num_rows > 0): ?>
                                                <?php while ($row = $result->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['title']); ?></td>
                                                        <td><?= htmlspecialchars($row['type']); ?></td>
                                                        <td><?= htmlspecialchars($row['organization']); ?></td>
                                                        <td><?= htmlspecialchars($row['president']); ?></td>
                                                        <td><?= date('F j, Y', strtotime($row['submitted_at'])) ?></td>
                                                        <td>
                                                            <?php
                                                            // Define the color based on the status
                                                            $statusColor = '';
                                                            switch ($row['signatory_statuses']) {
                                                                case 'Approved':
                                                                    $statusColor = 'green';
                                                                    break;
                                                                case 'Denied':
                                                                    $statusColor = '#8c220f';
                                                                    break;
                                                                case 'Pending':
                                                                    $statusColor = '#bdab11';
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
                                                                <?= htmlspecialchars($row['signatory_statuses']); ?>
                                                            </span>
                                                        </td>

                                                        <?php
                                                            $approval_id = $row['approval_id'] ?? '';
                                                            $proposal_id = $row['proposal_id'] ?? '';
                                                        ?>
                                                        <td>
                                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#SignproposalModal"
                                                                data-approval-id="<?= $approval_id ?>" data-proposal-id="<?= $proposal_id ?>">
                                                                View
                                                            </button>
                                                            <input type="hidden" name="proposal_ids[]" value="<?= $proposal_id ?>">
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7">No records found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Archived Proposals Tab -->
                        <div class="tab-pane fade" id="archived-proposals" role="tabpanel" aria-labelledby="archived-proposals-tab">
                            <div class="table-responsive">
                                <table id="archivedProposalTable" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Organization</th>
                                            <th>President</th>
                                            <th>Proposed Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($resultArchived && $resultArchived->num_rows > 0): ?>
                                            <?php while ($row = $resultArchived->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['title']); ?></td>
                                                    <td><?= htmlspecialchars($row['type']); ?></td>
                                                    <td><?= htmlspecialchars($row['organization']); ?></td>
                                                    <td><?= htmlspecialchars($row['president']); ?></td>
                                                    <td><?= date('F j, Y', strtotime($row['submitted_at'])) ?></td>
                                                    <td>
                                                        <?php
                                                        // Define the color based on the status
                                                        $statusColor = '';
                                                        switch ($row['signatory_statuses']) {
                                                            case 'Approved':
                                                                $statusColor = 'green';
                                                                break;
                                                            case 'Denied':
                                                                $statusColor = '#8c220f';
                                                                break;
                                                            case 'Pending':
                                                                $statusColor = '#bdab11';
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
                                                            <?= htmlspecialchars($row['signatory_statuses']); ?>
                                                        </span>
                                                    </td>
                                                    <?php
                                                        $approval_id = $row['approval_id'] ?? '';
                                                        $proposal_id = $row['proposal_id'] ?? '';
                                                    ?>
                                                    <td>
                                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#SignproposalModal"
                                                            data-approval-id="<?= $approval_id ?>" data-proposal-id="<?= $proposal_id ?>">
                                                            View
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7">No archived proposals found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- End of tab-content -->
    

    

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all buttons that open the proposal modal
        const proposalButtons = document.querySelectorAll('button[data-bs-target="#SignproposalModal"]');
        
        // Add click event listener to each button
        proposalButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                // Get the approval_id and proposal_id from the button's data attributes
                const approvalId = this.getAttribute('data-approval-id');
                const proposalId = this.getAttribute('data-proposal-id');
                
                // Set the form hidden input values
                if (approvalId) {
                    document.getElementById('approvalId').value = approvalId;
                }
                
                if (proposalId) {
                    document.getElementById('proposalId').value = proposalId;
                }
                
                // Load the modal using AJAX to get the latest data
                if (proposalId) {
                    // Create a URL for the AJAX request
                    const ajaxUrl = '../resources/utilities/modal/sign_proposal_modal.php?proposal_id=' + proposalId;
                    
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
                        const modalElement = document.getElementById('SignproposalModal');
                        const currentModalContent = modalElement.querySelector('.modal-content');
                        
                        if (newModalContent && currentModalContent) {
                            currentModalContent.innerHTML = newModalContent.innerHTML;
                            
                            // Ensure the form still has the approval ID and proposal ID
                            document.getElementById('approvalId').value = approvalId;
                            document.getElementById('proposalId').value = proposalId;
                        }
                        
                        // Show the modal using Bootstrap's modal method
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    })
                    .catch(error => {
                        console.error('Error loading modal content:', error);
                        alert('There was an error loading the proposal details. Please try again.');
                    });
                }
            });
        });
    });
    </script>
   
    <!-- DataTables and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../resources/js/closefunction.js"></script>    
    <script src="../resources/js/universal.js"></script>
    <script src="../resources/js/faculty_proposal_dataTABLE.js"></script>
    
    <script>
    // Chart.js dynamic charting interface
    let proposalChartInstance = null;

    // Data from PHP
    const typeLabels = <?php echo json_encode($types); ?>;
    const typeCounts = <?php echo json_encode($typeCounts); ?>;
    const statusLabels = <?php echo json_encode($statuses); ?>;
    const statusCounts = <?php echo json_encode($statusCounts); ?>;
    const orgLabels = <?php echo json_encode($organizations); ?>;
    const orgCounts = <?php echo json_encode($organizationCounts); ?>;
    const coreValueLabels = <?php echo json_encode($coreValueLabels); ?>;
    const coreValueCounts = <?php echo json_encode($coreValueCounts); ?>;
    const missionLabels = <?php echo json_encode($missionLabels); ?>;
    const missionCounts = <?php echo json_encode($missionCounts); ?>;
    const visionLabels = <?php echo json_encode($visionLabels); ?>;
    const visionCounts = <?php echo json_encode($visionCounts); ?>;
    const sdgLabels = <?php echo json_encode($sdgDescriptions); ?>;
    const sdgCounts = <?php echo json_encode($sdgCounts); ?>;

    // Create short SDG labels (SDG1, SDG2, etc.)
    const sdgShortLabels = sdgLabels.map(label => {
        // Extract just the SDG number from labels like "SDG 1: No Poverty"
        const match = label.match(/SDG\s+(\d+)/i);
        return match ? `SDG${match[1]}` : label;
    });

    // Store original data to use for filtering
    const originalData = {
        types: {
            labels: [...typeLabels],
            counts: [...typeCounts]
        },
        statuses: {
            labels: [...statusLabels],
            counts: [...statusCounts]
        },
        organizations: {
            labels: [...orgLabels],
            counts: [...orgCounts]
        },
        coreValues: {
            labels: [...coreValueLabels],
            counts: [...coreValueCounts]
        },
        missions: {
            labels: [...missionLabels],
            counts: [...missionCounts]
        },
        visions: {
            labels: [...visionLabels],
            counts: [...visionCounts]
        },
        sdgs: {
            labels: [...sdgLabels],
            shortLabels: [...sdgShortLabels],
            counts: [...sdgCounts]
        }
    };

    // Define the green color palette
    const greenColorPalette = ['#135626', '#2B6D3D', '#4C8D5D', '#69A979', '#7FBE8F', '#91CFA0'];

    // Function to get a color based on value magnitude
    const getColorByMagnitude = (value, dataArray) => {
        if (dataArray.length === 0) return greenColorPalette[0];
        const maxValue = Math.max(...dataArray);
        // Get index based on relative magnitude (0-5)
        const index = Math.min(Math.floor((value / maxValue) * 5), 5);
        return greenColorPalette[index];
    };

    // Current active filters
    let filters = {
        type: 'all',
        status: 'all',
        month: 'All',
        year: new Date().getFullYear().toString(),
        department: 'all'
    };

    // Function to filter data based on current filter settings using AJAX
    async function filterData() {
        // Get current chart type
        const currentChartType = document.getElementById('chartDropdownSelector').value;
        
        // Prepare AJAX request - Use the correct path to the admin version of the file
        const url = new URL('../resources/ajax/chart_data_ajax.php', window.location.href);
        
        // Add parameters
        url.searchParams.append('chartType', currentChartType);
        url.searchParams.append('proposalType', filters.type);
        url.searchParams.append('status', filters.status);
        url.searchParams.append('month', filters.month);
        url.searchParams.append('year', filters.year);
        url.searchParams.append('department', filters.department);
        
        try {
            // Make AJAX request
            const response = await fetch(url.toString());
            if (!response.ok) {
                throw new Error(`HTTP error: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                console.error('Error fetching data:', data.error);
                return {
                    labels: [],
                    counts: [],
                    shortLabels: []
                };
            }
            
            // Return filtered data in the format expected by chart renderer
            const result = {
                types: { labels: [], counts: [] },
                statuses: { labels: [], counts: [] },
                organizations: { labels: [], counts: [] },
                coreValues: { labels: [], counts: [] },
                missions: { labels: [], counts: [] },
                visions: { labels: [], counts: [] },
                sdgs: { labels: [], counts: [], shortLabels: [] },
                proposalsPassed: { labels: [], counts: [] },
                evaluation_ratings: { labels: [], counts: [], evaluations: [] }
            };
            
            // Set the data for the active chart type
            switch (currentChartType) {
                case 'organization':
                    result.organizations.labels = data.labels;
                    result.organizations.counts = data.counts;
                    break;
                case 'core values':
                    result.coreValues.labels = data.labels;
                    result.coreValues.counts = data.counts;
                    break;
                case 'mission':
                    result.missions.labels = data.labels;
                    result.missions.counts = data.counts;
                    break;
                case 'vision':
                    result.visions.labels = data.labels;
                    result.visions.counts = data.counts;
                    break;
                case 'sdg':
                    result.sdgs.labels = data.labels;
                    result.sdgs.counts = data.counts;
                    result.sdgs.shortLabels = data.shortLabels;
                    break;
                case 'proposals_passed':
                    // We need to get filtered data for this chart type too
                    result.proposalsPassed.labels = data.labels || [];
                    result.proposalsPassed.counts = data.counts || [];
                    break;
                case 'evaluation_ratings':
                    // Handle evaluation ratings data
                    result.evaluation_ratings.labels = data.labels || [];
                    result.evaluation_ratings.counts = data.counts || [];
                    result.evaluation_ratings.evaluations = data.evaluations || [];
                    break;
            }
            
            return result;
        } catch (error) {
            console.error('Error in AJAX request:', error);
            return {
                types: { labels: [], counts: [] },
                statuses: { labels: [], counts: [] },
                organizations: { labels: [], counts: [] },
                coreValues: { labels: [], counts: [] },
                missions: { labels: [], counts: [] },
                visions: { labels: [], counts: [] },
                sdgs: { labels: [], counts: [], shortLabels: [] },
                proposalsPassed: { labels: [], counts: [] },
                evaluation_ratings: { labels: [], counts: [], evaluations: [] }
            };
        }
    }

    // Function to apply filters to chart data
    async function applyFiltersToChart() {
        // Update our filter state
        filters = {
            type: document.getElementById('proposalTypeFilter').value,
            status: document.getElementById('statusFilter').value,
            month: document.getElementById('monthFilter').value,
            year: document.getElementById('yearFilter').value,
            department: document.getElementById('departmentFilter').value
        };
        
        // Re-render the current chart with filters applied
        await renderChart(document.getElementById('chartDropdownSelector').value);
    }

    // Enhanced chart rendering function with filter support
    async function renderChart(type) {
        const ctx = document.getElementById('proposalChart').getContext('2d');
        if (proposalChartInstance) proposalChartInstance.destroy();

        // Update the heading based on chart type
        const chartHeading = document.querySelector('.card-body .d-flex h6');
        const legendContainer = document.getElementById('chartLegendContainer');
        legendContainer.innerHTML = ''; // Clear previous legend
        
        // Show loading state
        if (chartHeading) {
            chartHeading.textContent = 'Loading data...';
        }
        
        // Dispatch loading event to disable filters
        document.dispatchEvent(new Event('chart:loading'));
        
        let titleText = 'Proposal Analytics';

        try {
            // Get filtered data based on current filters (now async)
            const filteredData = await filterData();
            
            // Build filter text for the title
            let filterText = '';
            
            if (filters.type !== 'all') {
                filterText += ' (Type: ' + filters.type + ')';
            }
            
            if (filters.status !== 'all') {
                filterText += ' (Status: ' + filters.status + ')';
            }
            
            if (filters.month !== 'All') {
                filterText += ' (Month: ' + filters.month + ')';
            }
            
            if (filters.year) {
                filterText += ' (Year: ' + filters.year + ')';
            }
            
            if (filterText) {
                titleText += ' - Filtered by' + filterText;
            }

            let chartType = 'bar';
            let data = {};
            let options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false } // Hide Chart.js legend since we're using our custom legend
                }
            };

            // Legend items array
            let legendItems = [];

            switch (type) {
                case 'evaluation_ratings':
                    titleText = 'Top 5 Proposals by Evaluation Rating' + filterText;
                    
                    chartType = 'bar';
                    
                    // Use the filtered data from AJAX
                    const proposalTitles = filteredData.evaluation_ratings.labels || [];
                    const proposalRatings = filteredData.evaluation_ratings.counts || [];
                    const proposalEvaluations = filteredData.evaluation_ratings.evaluations || [];
                    
                    // Create unique colors for each proposal
                    const barColors = proposalRatings.map((_, index) => {
                        return greenColorPalette[index % greenColorPalette.length];
                    });
                    
                    data = {
                        labels: proposalTitles,
                        datasets: [{
                            label: 'Average Rating',
                            data: proposalRatings,
                            backgroundColor: barColors,
                            borderColor: barColors,
                            borderWidth: 1
                        }]
                    };
                    
                    options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    afterLabel: function(context) {
                                        const index = context.dataIndex;
                                        return [
                                            `Number of Attendees: ${proposalEvaluations[index]}`
                                        ];
                                    }
                                }
                            },
                            legend: {
                                display: false
                            },
                            title: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 5,
                                title: {
                                    display: true,
                                    text: 'Average Rating (1-5)'
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Proposal Title'
                                }
                            }
                        }
                    };
                    
                    // Add color legend
                    if (proposalRatings.length > 0) {
                        const maxRating = Math.max(...proposalRatings);
                        const minRating = Math.min(...proposalRatings);
                        legendItems.push(
                            { color: greenColorPalette[0], label: `Lowest: ${minRating.toFixed(2)}` },
                            { color: greenColorPalette[greenColorPalette.length-1], label: `Highest: ${maxRating.toFixed(2)}` }
                        );
                    }
                    break;
                    
                case 'organization':
                    titleText = 'Departments/Organizations' + filterText;
                    chartType = 'bar';
                    
                    data = {
                        labels: filteredData.organizations.labels,
                        datasets: [{
                            label: 'Proposals',
                            data: filteredData.organizations.counts,
                            backgroundColor: filteredData.organizations.counts.map(count => 
                                getColorByMagnitude(count, filteredData.organizations.counts))
                        }]
                    };
                    options.indexAxis = 'y';
                    
                    // Create dynamic legend showing data-driven colors
                    if (filteredData.organizations.counts.length > 0) {
                        const minCount = Math.min(...filteredData.organizations.counts);
                        const maxCount = Math.max(...filteredData.organizations.counts);
                        legendItems.push(
                            { color: getColorByMagnitude(minCount, filteredData.organizations.counts), label: 'Low' },
                            { color: getColorByMagnitude((minCount + maxCount) / 2, filteredData.organizations.counts), label: 'Medium' },
                            { color: getColorByMagnitude(maxCount, filteredData.organizations.counts), label: 'High' }
                        );
                    } else {
                        legendItems.push({ color: greenColorPalette[0], label: 'Proposals' });
                    }
                    break;
                case 'core values':
                    titleText = 'Core Values Distribution' + filterText;
                    chartType = 'bar';
                    // Split labels after every word
                    const formattedCoreValueLabels = filteredData.coreValues.labels.map(label => {
                        return label.split(' '); // This returns an array of words (multiline label)
                    });
                    data = {
                        labels: formattedCoreValueLabels,
                        datasets: [{
                            label: 'Proposals',
                            data: filteredData.coreValues.counts,
                            backgroundColor: filteredData.coreValues.counts.map(count => 
                                getColorByMagnitude(count, filteredData.coreValues.counts))
                        }]
                    };
                    options.plugins.tooltip = {
                        callbacks: {
                            title: function(tooltipItems) {
                                const index = tooltipItems[0].dataIndex;
                                return filteredData.coreValues.labels[index]; // Show full text in tooltip
                            }
                        }
                    };
                    // Add padding for multiline labels
                    options.layout = {
                        margin: {
                            bottom: 40 // Add bottom padding for multiline labels
                        }
                    };
                    
                    // Create legend items for value ranges
                    if (filteredData.coreValues.counts.length > 0) {
                        const minCount = Math.min(...filteredData.coreValues.counts);
                        const maxCount = Math.max(...filteredData.coreValues.counts);
                        legendItems.push(
                            { color: getColorByMagnitude(minCount, filteredData.coreValues.counts), label: 'Low' },
                            { color: getColorByMagnitude((minCount + maxCount) / 2, filteredData.coreValues.counts), label: 'Medium' },
                            { color: getColorByMagnitude(maxCount, filteredData.coreValues.counts), label: 'High' }
                        );
                    } else {
                        legendItems.push({ color: greenColorPalette[0], label: 'Proposals' });
                    }
                    break;
                case 'mission':
                    titleText = 'Mission Distribution' + filterText;
                    chartType = 'bar';
                    // Split labels after every word
                    const formattedMissionLabels = filteredData.missions.labels.map(label => {
                        return label.split(' ');
                    });
                    data = {
                        labels: formattedMissionLabels,
                        datasets: [{
                            label: 'Proposals',
                            data: filteredData.missions.counts,
                            backgroundColor: filteredData.missions.counts.map(count => 
                                getColorByMagnitude(count, filteredData.missions.counts))
                        }]
                    };
                    options.plugins.tooltip = {
                        callbacks: {
                            title: function(tooltipItems) {
                                const index = tooltipItems[0].dataIndex;
                                return filteredData.missions.labels[index]; // Show full text in tooltip
                            }
                        }
                    };
                    // Add padding for multiline labels
                    options.layout = {
                        margin: {
                            bottom: 20 // Add bottom padding for multiline labels
                        }
                    };
                    
                    // Create legend items for value ranges
                    if (filteredData.missions.counts.length > 0) {
                        const minCount = Math.min(...filteredData.missions.counts);
                        const maxCount = Math.max(...filteredData.missions.counts);
                        legendItems.push(
                            { color: getColorByMagnitude(minCount, filteredData.missions.counts), label: 'Low' },
                            { color: getColorByMagnitude((minCount + maxCount) / 2, filteredData.missions.counts), label: 'Medium' },
                            { color: getColorByMagnitude(maxCount, filteredData.missions.counts), label: 'High' }
                        );
                    } else {
                        legendItems.push({ color: greenColorPalette[0], label: 'Proposals' });
                    }
                    break;
                case 'vision':
                    titleText = 'Vision Distribution' + filterText;
                    chartType = 'bar';
                    // Split labels after every word
                    const formattedVisionLabels = filteredData.visions.labels.map(label => {
                        return label.split(' ');
                    });
                    data = {
                        labels: formattedVisionLabels,
                        datasets: [{
                            label: 'Proposals',
                            data: filteredData.visions.counts,
                            backgroundColor: filteredData.visions.counts.map(count => 
                                getColorByMagnitude(count, filteredData.visions.counts))
                        }]
                    };
                    // Add tooltips to show full vision text
                    options.plugins.tooltip = {
                        callbacks: {
                            title: function(tooltipItems) {
                                const index = tooltipItems[0].dataIndex;
                                return filteredData.visions.labels[index]; // Show full text in tooltip
                            }
                        }
                    };
                    // Add padding for multiline labels
                    options.layout = {
                        margin: {
                            bottom: 40 // Add bottom padding for multiline labels
                        }
                    };
                    
                    // Create legend items for value ranges
                    if (filteredData.visions.counts.length > 0) {
                        const minCount = Math.min(...filteredData.visions.counts);
                        const maxCount = Math.max(...filteredData.visions.counts);
                        legendItems.push(
                            { color: getColorByMagnitude(minCount, filteredData.visions.counts), label: 'Low' },
                            { color: getColorByMagnitude((minCount + maxCount) / 2, filteredData.visions.counts), label: 'Medium' },
                            { color: getColorByMagnitude(maxCount, filteredData.visions.counts), label: 'High' }
                        );
                    } else {
                        legendItems.push({ color: greenColorPalette[0], label: 'Proposals' });
                    }
                    break;
                case 'sdg':
                    titleText = 'SDG Distribution' + filterText;
                    chartType = 'bar';
                    data = {
                        labels: filteredData.sdgs.shortLabels,
                        datasets: [{
                            label: 'Proposals',
                            data: filteredData.sdgs.counts,
                            backgroundColor: filteredData.sdgs.counts.map(count => 
                                getColorByMagnitude(count, filteredData.sdgs.counts))
                        }]
                    };
                    // Add tooltips to show full SDG descriptions
                    options.plugins.tooltip = {
                        callbacks: {
                            title: function(tooltipItems) {
                                const index = tooltipItems[0].dataIndex;
                                return filteredData.sdgs.labels[index]; // Show full description in tooltip
                            }
                        }
                    };
                    
                    // Create legend items for value ranges
                    if (filteredData.sdgs.counts.length > 0) {
                        const minCount = Math.min(...filteredData.sdgs.counts);
                        const maxCount = Math.max(...filteredData.sdgs.counts);
                        legendItems.push(
                            { color: getColorByMagnitude(minCount, filteredData.sdgs.counts), label: 'Low' },
                            { color: getColorByMagnitude((minCount + maxCount) / 2, filteredData.sdgs.counts), label: 'Medium' },
                            { color: getColorByMagnitude(maxCount, filteredData.sdgs.counts), label: 'High' }
                        );
                    } else {
                        legendItems.push({ color: greenColorPalette[0], label: 'Proposals' });
                    }
                    break;
                case 'proposals_passed':
                    titleText = 'Proposals Passed by Month' + filterText;
                    chartType = 'bar';
                    
                    // Check if we have filtered data, otherwise fall back to original data
                    const proposalPassedLabels = filteredData.proposalsPassed.labels || <?php echo json_encode($passedMonthLabels); ?>;
                    const proposalPassedCounts = filteredData.proposalsPassed.counts || <?php echo json_encode($passedProposalCounts); ?>;
                    
                    data = {
                        labels: proposalPassedLabels,
                        datasets: [{
                            label: 'Proposals',
                            data: proposalPassedCounts,
                            backgroundColor: proposalPassedCounts.map(count => 
                                getColorByMagnitude(count, proposalPassedCounts))
                        }]
                    };
                    
                    options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 2
                                }
                            }
                        }
                    };
                    
                    // Create legend items for value ranges with consistent styling
                    if (proposalPassedCounts.length > 0) {
                        const minCount = Math.min(...proposalPassedCounts);
                        const maxCount = Math.max(...proposalPassedCounts);
                        legendItems.push(
                            { color: getColorByMagnitude(minCount, proposalPassedCounts), label: 'Low' },
                            { color: getColorByMagnitude((minCount + maxCount) / 2, proposalPassedCounts), label: 'Medium' },
                            { color: getColorByMagnitude(maxCount, proposalPassedCounts), label: 'High' }
                        );
                    } else {
                        legendItems.push({ color: greenColorPalette[0], label: 'Proposals' });
                    }
                    break;
                default:
                    // fallback
                    chartType = 'bar';
                    data = { labels: [], datasets: [] };
            }

            // Update the heading text
            if (chartHeading) {
                chartHeading.textContent = titleText;
            }

            // Create the custom legend
            if (legendContainer) {
                legendItems.forEach(item => {
                    const legendItem = document.createElement('div');
                    legendItem.className = 'legend-item d-flex align-items-center me-3';
                    legendItem.innerHTML = `
                        <div class="legend-color me-2" style="width: 12px; height: 12px; background-color: ${item.color};"></div>
                        <div class="legend-label small" style="color: #666;">${item.label}</div>
                    `;
                    legendContainer.appendChild(legendItem);
                });
            }

            proposalChartInstance = new Chart(ctx, {
                type: chartType,
                data: data,
                options: options
            });
            
            // Dispatch loaded event to enable filters
            document.dispatchEvent(new Event('chart:loaded'));
        } catch (error) {
            console.error('Error rendering chart:', error);
            // Show error message
            if (chartHeading) {
                chartHeading.textContent = 'Error loading data. Please try again.';
            }
            
            // Re-enable filters even on error
            document.dispatchEvent(new Event('chart:loaded'));
        }
    }

    // Set up event listeners for the filter controls
    document.addEventListener('DOMContentLoaded', function () {
        const chartDropdown = document.getElementById('chartDropdownSelector');
        const proposalTypeFilter = document.getElementById('proposalTypeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const monthFilter = document.getElementById('monthFilter');
        const yearFilter = document.getElementById('yearFilter');
        const departmentFilter = document.getElementById('departmentFilter');
        
        // Initialize the chart
        renderChart(chartDropdown.value);
        
        // Chart type change
        chartDropdown.addEventListener('change', function () {
            renderChart(this.value);
        });
        
        // Add event listeners for auto-filtering
        proposalTypeFilter.addEventListener('change', applyFiltersToChart);
        statusFilter.addEventListener('change', applyFiltersToChart);
        monthFilter.addEventListener('change', applyFiltersToChart);
        yearFilter.addEventListener('change', applyFiltersToChart);
        departmentFilter.addEventListener('change', applyFiltersToChart);
        
        // Disable filters while loading data
        function setFiltersState(disabled) {
            chartDropdown.disabled = disabled;
            proposalTypeFilter.disabled = disabled;
            statusFilter.disabled = disabled;
            monthFilter.disabled = disabled;
            yearFilter.disabled = disabled;
            departmentFilter.disabled = disabled;
        }
        
        // Add event listener to handle loading state
        document.addEventListener('chart:loading', function() {
            setFiltersState(true);
        });
        
        document.addEventListener('chart:loaded', function() {
            setFiltersState(false);
        });
    });

    // Add event listener:
    const departmentFilter = document.getElementById('departmentFilter');
    departmentFilter.addEventListener('change', applyFiltersToChart);
    </script>
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#proposalTable').DataTable({
                "order": [[0, "desc"]],
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "Showing 0 to 0 of 0 entries",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "zeroRecords": "No matching records found",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                }
            });
        });

        // Pie Chart for Affiliation Distribution
        const ctx = document.getElementById('affiliationPieChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Student', 'Faculty', 'Guest'],
                    datasets: [{
                        data: [
                            <?= $affiliationCount['Student'] ?>,
                            <?= $affiliationCount['Faculty'] ?>,
                            <?= $affiliationCount['Guest'] ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Proposal Selector Change Handler
        document.getElementById('proposalSelector')?.addEventListener('change', function() {
            const proposalId = this.value;
            window.location.href = `proposal_table.php?proposal_id=${proposalId}`;
        });
    </script>

    <!-- Add this before the closing body tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the pie chart
        const ctx = document.getElementById('affiliationPieChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Student', 'Faculty', 'Guest'],
                    datasets: [{
                        data: [
                            <?= $affiliationCount['Student'] ?>,
                            <?= $affiliationCount['Faculty'] ?>,
                            <?= $affiliationCount['Guest'] ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Handle proposal selector change
        const proposalSelector = document.getElementById('proposalSelector');
        if (proposalSelector) {
            proposalSelector.addEventListener('change', function() {
                const proposalId = this.value;
                window.location.href = `proposal_table.php?proposal_id=${proposalId}`;
            });
        }
        });
    </script>
</body>
</html>