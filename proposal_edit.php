<?php
session_start();

$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']); // Clear the flash message after displaying it

require '../config/system_db.php'; // include '../config/system_db.php';

// MVC Types mapping
$mvcTypes = [
    "Exemplary Instruction" => "vision",
    "Sustainable Community Extension Services" => "vision",
    "Research-Driven Programs" => "vision",
    "Develop Holistic, Self Fulfilling and Productive Citizen" => "mission",
    "Commit to National Development" => "mission",
    "Create a Legacy of Academic Excellence" => "mission",
    "Advocate Interactive Technology" => "mission",
    "Form Competent Administrators, Faculty, and Staff" => "mission",
    "Contribute to International Development" => "mission",
    "Promote Innovate Instruction" => "mission",
    "Forge a Just, Stable, and Humane" => "mission",
    "Professionalism" => "core value",
    "Personal Integrity" => "core value",
    "Moral Sensitivity" => "core value",
    "National Pride" => "core value",            
    "Critical Thinking" => "core value",
    "Academic Excellence" => "core value",
    "Discipline" => "core value",
    "Passion for Intellectual Inquiry" => "core value",
    "Compassion" => "core value",
    "Civic Consciousness" => "core value",
    "Sectoral Immersion" => "core value",
    "Social Conscience" => "core value"
];

// SDG List
$sdg_list = [
    "No Poverty",
    "Zero Hunger",
    "Good Health and Well-being",
    "Quality Education",
    "Gender Equality",
    "Clean Water and Sanitation",
    "Affordable and Clean Energy",
    "Decent Work and Economic Growth",
    "Industry, Innovation and Infrastructure",
    "Reduced Inequality",
    "Sustainable Cities and Communities",
    "Responsible Consumption and Production",
    "Climate Action",
    "Life Below Water",
    "Life on Land",
    "Peace, Justice and Strong Institutions",
    "Partnerships for the Goals"
];

// Check if proposal ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = "No proposal ID provided";
    header('Location: proposal_inbox.php');
    exit();
}

$proposal_id = $_GET['id'];

// Fetch main proposal data with all related information
$sql = "SELECT 
    p.*,
    
    GROUP_CONCAT(DISTINCT pd.category, ':', pd.field1, ':', pd.field2, ':', pd.field3 SEPARATOR '||') as proposal_details,
    GROUP_CONCAT(DISTINCT ps.sdg_number, ':', ps.sdg_description SEPARATOR '||') as sdgs,
    GROUP_CONCAT(DISTINCT psig.signatory_name, ':', psig.signatory_role, ':', psig.signatory_status, ':', psig.comments SEPARATOR '||') as signatories,
    GROUP_CONCAT(DISTINCT mvc.mvc_value, ':', mvc.mvc_type SEPARATOR '||') as mvc_values
FROM tbl_proposal p

LEFT JOIN tbl_proposal_details pd ON p.proposal_id = pd.proposal_id
LEFT JOIN tbl_proposal_sdgs ps ON p.proposal_id = ps.proposal_id
LEFT JOIN tbl_proposal_signatories psig ON p.proposal_id = psig.proposal_id
LEFT JOIN tbl_mvc mvc ON p.proposal_id = mvc.proposal_id
WHERE p.proposal_id = ?
GROUP BY p.proposal_id";

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();
$proposal = $result->fetch_assoc();

if (!$proposal) {
    $_SESSION['flash_message'] = "Proposal not found";
    header('Location: proposal_inbox.php');
    exit();
}

// Parse the concatenated data into structured arrays


$standard_funds = [
    'Organization Funds',
    'Fund-raising/income generating activity',
    'Student activity funds',
    'Others' // This refers to the "Others" checkbox
];
// Example: explode the stored field into array
$source_fund_string = $proposal['source_fund'] ?? '';
$source_fund_array = array_map('trim', explode(',', $source_fund_string));

// Extract "other" fund values
$other_funds = array_filter($source_fund_array, fn($val) => !in_array($val, $standard_funds));
$other_source_fund = implode(', ', $other_funds); // In case there's more than one



// Parse budget items
$budget_items = [];
if (!empty($proposal['budget_items'])) {
    foreach (explode('||', $proposal['budget_items']) as $item) {
        list($particular, $amount) = explode(':', $item);
        $budget_items[] = [
            'particular' => $particular,
            'amount' => $amount
        ];
    }
}

// Parse proposal details
$proposal_details = [
    'program' => [],
    'manpower' => [],
    'syllabus' => [],
    'objectives' => []
];
if (!empty($proposal['proposal_details'])) {
    foreach (explode('||', $proposal['proposal_details']) as $detail) {
        list($category, $field1, $field2, $field3) = explode(':', $detail);
        $proposal_details[$category][] = [
            'field1' => $field1,
            'field2' => $field2,
            'field3' => $field3
        ];
    }
}

// Parse SDGs
$sdgs = [];
if (!empty($proposal['sdgs'])) {
    foreach (explode('||', $proposal['sdgs']) as $sdg) {
        list($number, $description) = explode(':', $sdg);
        $sdgs[] = [
            'number' => $number,
            'description' => $description
        ];
    }
}

$sql = "SELECT signatory_role, signatory_name, signatory_status, comments 
        FROM tbl_proposal_signatories 
        WHERE proposal_id = ? AND signatory_role IN ('Adviser/Moderator', 'Dean/Department Head')";

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();

$signatories = [];
$existingSignatories = [];
while ($row = $result->fetch_assoc()) {
    $signatories[$row['signatory_role']] = $row['signatory_name'];
    $existingSignatories[$row['signatory_role']] = [
        'name' => $row['signatory_name'],
        'status' => $row['signatory_status'],
        'comments' => $row['comments']
    ];
}

// Fetch Dean/Department Head users
$deanQuery = "SELECT first_name, last_name FROM tbl_users WHERE role = 'Dean/Department Head'";
$deanResult = $connection->query($deanQuery);
$deans = [];
if ($deanResult && $deanResult->num_rows > 0) {
    while ($row = $deanResult->fetch_assoc()) {
        $deans[] = $row;
    }
}


// Parse MVC values
$mvc_values = [
    'vision' => [],
    'mission' => [],
    'core value' => []
];
if (!empty($proposal['mvc_values'])) {
    foreach (explode('||', $proposal['mvc_values']) as $mvc) {
        list($value, $type) = explode(':', $mvc);
        $mvc_values[$type][] = $value;
    }
}

// Parse source_fund string into array
$source_fund_array = !empty($proposal['source_fund']) ? explode(',', $proposal['source_fund']) : [];

// Fetch all available venues
$venues_query = "SELECT venue_name, location FROM tbl_venues ORDER BY venue_name";
$venues_result = $connection->query($venues_query);
$venues = [];
if ($venues_result) {
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
}



// Fetch proposal details for accordions
$sql = "SELECT detail_id, category, field1, field2, field3, amount FROM tbl_proposal_details WHERE proposal_id = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();

$accordion_data = [
    'budget' => [],
    'program' => [],
    'syllabus' => [],
    'manpower' => []
];

while ($row = $result->fetch_assoc()) {
    switch (strtolower($row['category'])) {
        case 'budget':
            $accordion_data['budget'][] = [
                'detail_id' => $row['detail_id'],  // Store the primary key
                'particular' => $row['field1'],
                'amount' => $row['amount']
            ];
            break;
        case 'program':
            $accordion_data['program'][] = [
                'detail_id' => $row['detail_id'],  // Store the primary key
                'name' => $row['field1'],
                'detail' => $row['field2'],
                'pic' => $row['field3']
            ];
            break;
        case 'syllabus':
            $accordion_data['syllabus'][] = [
                'detail_id' => $row['detail_id'],  // Store the primary key
                'subject' => $row['field1'],
                'topic' => $row['field2'],
                'relevance' => $row['field3']
            ];
            break;
        case 'manpower':
            $accordion_data['manpower'][] = [
                'detail_id' => $row['detail_id'],  // Store the primary key
                'role' => $row['field1'],
                'name' => $row['field2'],
                'responsibilities' => $row['field3']
            ];
            break;
    }
}

// Fetch on-campus venues
$venues_query = "SELECT venue_name, location FROM tbl_venues ORDER BY venue_name";
$venues_result = $connection->query($venues_query);
$venues = [];

if ($venues_result) {
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
}

// Format on-campus venues as "venue_name - location"
$oncampus_venues = array_map(function ($v) {
    return $v['venue_name'] . ' - ' . $v['location'];
}, $venues);

// Get saved venue from tbl_proposal
$saved_venue = $proposal['venue'] ?? '';

// Check if saved venue is on-campus
$is_oncampus = in_array($saved_venue, $oncampus_venues);


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $connection->begin_transaction();
    
    try {
        // Convert source_fund array to string
        $source_fund = isset($_POST['source_fund']) ? implode(',', $_POST['source_fund']) : '';
        
        // Update main proposal data
        $sql = "UPDATE tbl_proposal SET 
            title = ?,
            type = ?,
            description = ?,
            act_obj = ?,
            org_obj = ?,
            peo_obj = ?,
            beneficiaries = ?,
            campus_act = ?,
            place_act = ?,
            datetime_start = ?,
            datetime_end = ?,
            venue = ?,
            participants_num = ?,
            organization = ?,
            president = ?,
            source_fund = ?
            WHERE proposal_id = ?";
            
        // Store values in variables for bind_param
        $title = $_POST['proposal_title'];
        $type = $_POST['proposal_type'];
        $description = $_POST['activity_nature'];
        $act_obj = $_POST['act_obj'];
        $org_obj = $_POST['org_obj'];
        $peo_obj = $_POST['peo_obj'];
        $beneficiaries = $_POST['beneficiaries'];
        $campus_act = $_POST['campus_act'];
        $place_act = $_POST['place_act'];
        $datetime_start = date('Y-m-d H:i:s', strtotime($_POST['datetime_start']));
        $datetime_end = date('Y-m-d H:i:s', strtotime($_POST['datetime_end']));
        $venue = !empty($_POST['custom_venue']) ? trim($_POST['custom_venue']) : trim($_POST['venue_select']);
        $participants_num = $_POST['participants_num'];
        $organization = $_POST['org_name'];
        $president = $_POST['org_president'];
            
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("ssssssssssssisssi", 
            $title,
            $type,
            $description,
            $act_obj,
            $org_obj,
            $peo_obj,
            $beneficiaries,
            $campus_act,
            $place_act,
            $datetime_start,
            $datetime_end,
            $venue,
            $participants_num,
            $organization,
            $president,
            $source_fund,
            $proposal_id
        );
        $stmt->execute();

        // Update existing related records
        $tables = ['tbl_proposal_details', 'tbl_proposal_sdgs', 'tbl_mvc'];
        foreach ($tables as $table) {
            $sql = "DELETE FROM $table WHERE proposal_id = ?";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param("i", $proposal_id);
            $stmt->execute();
        }

        // Signatory Building Logic
        $proposalId = $proposal_id;
        $proposalType = $_POST['proposal_type'] ?? '';
        $deanSelection = $_POST['Dean/Department Head'] ?? '';
        $isNoDeanNeeded = ($deanSelection === 'NoDeanNeeded');
        $isOffCampus = isset($_POST['campus_act']) && $_POST['campus_act'] === 'Off-campus Activity';
        $isInternational = isset($_POST['place_act']) && $_POST['place_act'] === 'International';

        $hierarchyPattern1 = [
            'Adviser/Moderator',
            'Supreme College Student Council Adviser',
            'Community Affairs',
            'Dean/Department Head',
            'External Affairs',
            'Office for Student Affairs'
        ];

        $hierarchyPattern2 = [
            'Supreme College Student Council Adviser',
            'Community Affairs',
            'Dean/Department Head',
            'External Affairs',
            'Office for Student Affairs',
            'Vice President for Academic Affairs'
        ];

        switch ($proposalType) {
            case 'Extra-Curricular Activity Proposal':
            case 'Extra-Curricular Activity Proposal (Community Project)':
                $signatoriesOrder = $hierarchyPattern1;
                break;
            case 'Co-Curricular Activity Proposal':
            case 'Co-Curricular Activity Proposal (Community Project)':
                $signatoriesOrder = $hierarchyPattern2;
                break;
            default:
                $signatoriesOrder = [];
        }

        $filteredSignatoriesOrder = array_filter($signatoriesOrder, function($role) use ($isNoDeanNeeded, $proposalType, $isOffCampus, $isInternational) {
            if ($isNoDeanNeeded && $role === 'Dean/Department Head') return false;
            if ($role === 'Community Affairs') {
                return in_array($proposalType, [
                    'Extra-Curricular Activity Proposal (Community Project)',
                    'Co-Curricular Activity Proposal (Community Project)'
                ]);
            }
            if ($role === 'External Affairs') return $isOffCampus || $isInternational;
            return true;
        });

        $filteredSignatoriesOrder = array_values($filteredSignatoriesOrder);

        // Get existing signatories
        $sql = "SELECT * FROM tbl_proposal_signatories WHERE proposal_id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $proposalId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existingSignatories = [];
        while ($row = $result->fetch_assoc()) {
            $existingSignatories[$row['signatory_role']] = $row;
        }

        $updatedSignatories = [];
        $activeRoles = [];

        foreach ($filteredSignatoriesOrder as $role) {
            $roleKey = str_replace(' ', '_', $role);
            $name = '';
            $userId = null;

            if (in_array($role, ['Adviser/Moderator', 'Dean/Department Head'])) {
                if (!empty($_POST[$roleKey])) {
                    // Get user data by name
                    $sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name FROM tbl_users WHERE CONCAT(first_name, ' ', last_name) = ?";
                    $stmt = $connection->prepare($sql);
                    $stmt->bind_param("s", $_POST[$roleKey]);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $userData = $result->fetch_assoc();
                    
                    if ($userData) {
                        $name = $userData['full_name'];
                        $userId = $userData['user_id'];
                    }
                }
            } else {
                // Get user data by department
                $sql = "SELECT user_id, CONCAT(first_name, ' ', last_name) as full_name FROM tbl_users WHERE department = ? LIMIT 1";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("s", $role);
                $stmt->execute();
                $result = $stmt->get_result();
                $userData = $result->fetch_assoc();
                
                if ($userData) {
                    $name = $userData['full_name'];
                    $userId = $userData['user_id'];
                }
            }

            if ($userId) {
                $updatedSignatories[$role] = [
                    'user_id' => $userId,
                    'name' => $name,
                    'signatory_role' => $role,
                    'signatory_status' => 'Pending'
                ];
                $activeRoles[] = $role;
            }
        }

        // Delete signatories that are no longer needed
        foreach ($existingSignatories as $role => $existing) {
            if (!isset($updatedSignatories[$role])) {
                $sql = "DELETE FROM tbl_proposal_signatories WHERE approval_id = ?";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("i", $existing['approval_id']);
                $stmt->execute();
            }
        }

        // Update or insert signatories
        foreach ($updatedSignatories as $role => $signatory) {
            if (isset($existingSignatories[$role])) {
                $existing = $existingSignatories[$role];
                if ($existing['signatory_name'] !== $signatory['name']) {
                    $sql = "UPDATE tbl_proposal_signatories 
                            SET signatory_name = ?, user_id = ?, signatory_order = ?
                            WHERE approval_id = ?";
                    $order = array_search($role, $filteredSignatoriesOrder) + 1; // 1-based index
                    $stmt = $connection->prepare($sql);
                    $stmt->bind_param("siii", $signatory['name'], $signatory['user_id'], $order, $existing['approval_id']);
                    $stmt->execute();
                }
            } else {
                $order = array_search($role, $filteredSignatoriesOrder) + 1; // 1-based index
                $sql = "INSERT INTO tbl_proposal_signatories (proposal_id, user_id, signatory_role, signatory_name, signatory_status, signatory_order) 
                        VALUES (?, ?, ?, ?, 'Pending', ?)";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("iissi", $proposalId, $signatory['user_id'], $role, $signatory['name'], $order);
                $stmt->execute();
            }
        }
        

       // Insert budget items
       if (isset($_POST['budget_particular']) && isset($_POST['budget_amount'])) {
        // Get current user ID
        $user_id = $_SESSION['user_id'] ?? 0;
        
        // Create a lookup array from existing budget data using detail_id as the key
        $existing_budget = [];
        foreach ($accordion_data['budget'] as $budget) {
            $existing_budget[$budget['detail_id']] = [
                'particular' => $budget['particular'],
                'amount' => $budget['amount']
            ];
        }
        
        // Prepare the audit log statement
        $audit_sql = "INSERT INTO tbl_audit_log (proposal_id, user_id, action, field_changed, old_value, new_value) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        $audit_stmt = $connection->prepare($audit_sql);
        
        // Clear existing budget items
        $delete_sql = "DELETE FROM tbl_proposal_details WHERE proposal_id = ? AND category = 'Budget'";
        $delete_stmt = $connection->prepare($delete_sql);
        $delete_stmt->bind_param("i", $proposal_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Prepare the statement for budget insertion
        $sql = "INSERT INTO tbl_proposal_details (proposal_id, category, field1, field2, field3, amount) 
                VALUES (?, 'Budget', ?, '', '', ?)";
        $stmt = $connection->prepare($sql);
        
        // Track which existing items are still present
        $processed_ids = [];
        
        // Process all submitted budget items
        foreach ($_POST['budget_particular'] as $key => $particular) {
            if (!empty($particular)) {
                $amount = $_POST['budget_amount'][$key] ?? 0;
                $detail_id = isset($_POST['budget_detail_id'][$key]) ? $_POST['budget_detail_id'][$key] : '';
                
                // Insert the budget item
                $stmt->bind_param("isd", $proposal_id, $particular, $amount);
                $stmt->execute();
                
                // Audit log logic
                if (!empty($detail_id) && isset($existing_budget[$detail_id])) {
                    // This is an existing item - check if it was modified
                    $old_particular = $existing_budget[$detail_id]['particular'];
                    $old_amount = $existing_budget[$detail_id]['amount'];
                    
                    if ($old_particular != $particular || (float)$old_amount != (float)$amount) {
                        // Log the change
                        $action = "Updated budget particular";
                        $field_changed = "budget";
                        $old_value = "Particular: $old_particular, Amount: $old_amount";
                        $new_value = "Particular: $particular, Amount: $amount";
                        
                        $audit_stmt->bind_param("iissss", $proposal_id, $user_id, $action, $field_changed, $old_value, $new_value);
                        $audit_stmt->execute();
                    }
                    
                    // Mark as processed
                    $processed_ids[] = $detail_id;
                } else {
                    // This is a new item
                    $action = "Added budget particular";
                    $field_changed = "budget";
                    $old_value = "";
                    $new_value = "Particular: $particular, Amount: $amount";
                    
                    $audit_stmt->bind_param("iissss", $proposal_id, $user_id, $action, $field_changed, $old_value, $new_value);
                    $audit_stmt->execute();
                }
            }
        }
        
        // Check for deleted items
        foreach ($existing_budget as $detail_id => $item) {
            if (!in_array($detail_id, $processed_ids)) {
                // This item was deleted
                $action = "Deleted budget particular";
                $field_changed = "budget";
                $old_value = "Particular: {$item['particular']}, Amount: {$item['amount']}";
                $new_value = "";
                
                $audit_stmt->bind_param("iissss", $proposal_id, $user_id, $action, $field_changed, $old_value, $new_value);
                $audit_stmt->execute();
            }
        }
        
        // Close statements
        $stmt->close();
        $audit_stmt->close();
    }

        // Insert program details
        if (isset($_POST['program_name'])) {
            $sql = "INSERT INTO tbl_proposal_details (proposal_id, category, field1, field2, field3, amount) VALUES (?, 'program', ?, ?, ?, 0)";
            $stmt = $connection->prepare($sql);
            
            foreach ($_POST['program_name'] as $key => $name) {
                if (!empty($name)) {
                    $detail = $_POST['program_detail'][$key] ?? '';
                    $pic = $_POST['program_pic'][$key] ?? '';
                    $stmt->bind_param("isss", $proposal_id, $name, $detail, $pic);
                    $stmt->execute();
                }
            }
        }

        // Insert syllabus details
        if (isset($_POST['syllabus_subject'])) {
            $sql = "INSERT INTO tbl_proposal_details (proposal_id, category, field1, field2, field3, amount) VALUES (?, 'syllabus', ?, ?, ?, 0)";
            $stmt = $connection->prepare($sql);
            
            foreach ($_POST['syllabus_subject'] as $key => $subject) {
                if (!empty($subject)) {
                    $topic = $_POST['syllabus_topic'][$key] ?? '';
                    $relevance = $_POST['syllabus_relevance'][$key] ?? '';
                    $stmt->bind_param("isss", $proposal_id, $subject, $topic, $relevance);
                    $stmt->execute();
                }
            }
        }

        // Insert manpower details
        if (isset($_POST['manpower_role'])) {
            $sql = "INSERT INTO tbl_proposal_details (proposal_id, category, field1, field2, field3, amount) VALUES (?, 'manpower', ?, ?, ?, 0)";
            $stmt = $connection->prepare($sql);
            
            foreach ($_POST['manpower_role'] as $key => $role) {
                if (!empty($role)) {
                    $name = $_POST['manpower_name'][$key] ?? '';
                    $responsibilities = $_POST['manpower_responsibilities'][$key] ?? '';
                    $stmt->bind_param("isss", $proposal_id, $role, $name, $responsibilities);
                    $stmt->execute();
                }
            }
        }

        // Insert SDGs
        if (isset($_POST['sdgs'])) {
            $sql = "INSERT INTO tbl_proposal_sdgs (proposal_id, sdg_number, sdg_description) VALUES (?, ?, ?)";
            $stmt = $connection->prepare($sql);
            
            foreach ($_POST['sdgs'] as $sdg_index) {
                $sdg_number = $sdg_index;
                $sdg_description = $sdg_list[$sdg_index - 1]; // Get the full SDG description from the array
                $stmt->bind_param("iis", $proposal_id, $sdg_number, $sdg_description);
                $stmt->execute();
            }
        }

        // Insert MVC values
        if (isset($_POST['mvc'])) {
            $sql = "INSERT INTO tbl_mvc (proposal_id, mvc_type, mvc_value) VALUES (?, ?, ?)";
            $stmt = $connection->prepare($sql);
            
            foreach ($_POST['mvc'] as $mvc_type => $mvc_values) {
                foreach ($mvc_values as $mvc_value) {
                    if (!empty($mvc_value)) {
                        $stmt->bind_param("iss", $proposal_id, $mvc_type, $mvc_value);
                        $stmt->execute();
                    }
                }
            }
        }

        // Assume $proposal_id is already set
        $getStatusStmt = $connection->prepare("SELECT status FROM tbl_proposal WHERE proposal_id = ?");
        $getStatusStmt->bind_param('i', $proposal_id);
        $getStatusStmt->execute();
        $getStatusStmt->bind_result($currentStatus);
        $getStatusStmt->fetch();
        $getStatusStmt->close();

        if ($currentStatus === 'Revise') {
            // Update status to 'Pending'
            $updateStatusStmt = $connection->prepare("UPDATE tbl_proposal SET status = 'Pending' WHERE proposal_id = ?");
            $updateStatusStmt->bind_param('i', $proposal_id);
            if ($updateStatusStmt->execute()) {
                $_SESSION['flash_message'] = "Proposal revised and now resubmitted for approval.";
            } else {
                $_SESSION['flash_message'] = "Proposal updated but failed to resubmit: " . $updateStatusStmt->error;
            }
            $updateStatusStmt->close();

            // Optional: Log the resubmission
            $user_id = $_SESSION['user_id'] ?? null;
            $user_name = $_SESSION['username'] ?? 'System';
            $remarks = "Proposal edited by $user_name and resubmitted for approval.";

            $logStmt = $connection->prepare("
                INSERT INTO tbl_proposal_logs (proposal_id, action, user_id, remarks, created_at)
                VALUES (?, 'Proposal Resubmitted', ?, ?, NOW())
            ");
            $logStmt->bind_param('iis', $proposal_id, $user_id, $remarks);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $_SESSION['flash_message'] = "Proposal updated successfully!";
        }

        $connection->commit();
        header("Location: ../01_student/proposal_inbox.php");
        exit();
        
    } catch (Exception $e) {
        $connection->rollback();
        $_SESSION['flash_message'] = "Error updating proposal: " . $e->getMessage();
        header("Location: proposal_edit.php?id=" . $proposal_id);
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Proposal</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="../resources/css/proposalDes.css">
    <!-- Google Font Link -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <!-- Bootstrap CSS Link -->
    <link rel="stylesheet" href="../resources/css/proposalDes.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Head section -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

</head>

<body>
    <?php include('../resources/utilities/sidebar/proposer_sidebar.php'); ?>
    <!-- Header -->
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>
   
    <div class="wrapper">
        <div class="d-content">
            <div class="content-header">
                <h2 class="d-title">ACTIVITY PROPOSAL</h2>
                <div class="menu-icon">
                    <span class="material-symbols-outlined">menu</span>
                </div>
            </div>          
            <?php include('../resources/utilities/modal/schedule_checker.php'); ?>
            <form method="POST" action="" class="proposal"  enctype="multipart/form-data">
                <div class="proposal-page">
                    <div class="permit-header">
                        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="permit-logo">                       
                        <div>
                            <h1 class="permit-osa">OFFICE FOR STUDENT AFFAIRS </h1>
                            <p class="permit-cefi">CALAYAN EDUCATIONAL FOUNDATION, INC.</p>
                        </div>
                    </div>
                    
                    <?php if ($flash_message): ?>
                        <?php
                        // Default to info style
                        $backgroundColor = '#d1ecf1';
                        $textColor = '#0c5460';
                        $icon = 'info';

                        // Check if message contains certain keywords to determine type
                        if (stripos($flash_message, 'success') !== false || stripos($flash_message, 'updated') !== false) {
                            $backgroundColor = '#d4edda';
                            $textColor = '#155724';
                            $icon = 'check_circle';
                        } elseif (stripos($flash_message, 'warning') !== false) {
                            $backgroundColor = '#fff3cd';
                            $textColor = '#856404';
                            $icon = 'warning';
                        } elseif (stripos($flash_message, 'error') !== false || stripos($flash_message, 'failed') !== false) {
                            $backgroundColor = '#f8d7da';
                            $textColor = '#721c24';
                            $icon = 'error';
                        }
                        ?>
                        <div class="alert d-flex align-items-center ms-3" role="alert" 
                            style="background-color: <?= $backgroundColor ?>; color: <?= $textColor ?>;">
                            <span class="material-symbols-outlined me-2">
                                <?= $icon ?>
                            </span>
                            <div><?= htmlspecialchars($flash_message) ?></div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
    
                    <h2>PROPOSAL DETAILS</h2>
                    <!-- Proposal Title -->
                    <div class="row mb-3">
                        <div class="col">
                            <label for="title" class="form-label">Title/Theme of Proposed Activity/Project</label>
                            <input type="text" class="form-control form-control-sm" id="title" name="proposal_title" value="<?php echo htmlspecialchars($proposal['title']); ?>" required>
                        </div>
                        <div class="col">
                            <label for="proposal_type" class="form-label">Student Activity Proposal Type</label>
                            <select class="form-select form-select-sm" id="proposal_type" name="proposal_type" required>
                                <option value="" selected>- select proposal type -</option>
                                <option value="Extra-Curricular Activity Proposal" <?php echo htmlspecialchars($proposal['type']) === 'Extra-Curricular Activity Proposal' ? 'selected' : ''; ?>>Extra-Curricular Activity Proposal</option>
                                <option value="Extra-Curricular Activity Proposal (Community Project)" <?php echo htmlspecialchars($proposal['type']) === 'Extra-Curricular Activity Proposal (Community Project)' ? 'selected' : ''; ?>>Extra-Curricular Activity Proposal (Community Project)</option>
                                <option value="Co-Curricular Activity Proposal" <?php echo htmlspecialchars($proposal['type']) === 'Co-Curricular Activity Proposal' ? 'selected' : ''; ?>>Co-Curricular Activity Proposal</option>
                                <option value="Co-Curricular Activity Proposal (Community Project)" <?php echo htmlspecialchars($proposal['type']) === 'Co-Curricular Activity Proposal (Community Project)' ? 'selected' : ''; ?>>Co-Curricular Activity Proposal (Community Project)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Text Areas -->
                    <div class="mb-3">
                        <label for="activity_nature" class="form-label">Nature of the Proposed Activity/Project</label>
                        <textarea class="form-control" id="activity_nature" name="activity_nature" rows="3" required><?php echo htmlspecialchars($proposal['description']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Beneficiaries</label>
                        <textarea class="form-control" id="beneficiaries" name="beneficiaries" rows="3" required><?php echo htmlspecialchars($proposal['beneficiaries']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Organization's Objectives</label>
                        <textarea class="form-control" id="org_obj" name="org_obj" rows="3"><?php echo htmlspecialchars($proposal['org_obj']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Activity/Project Objectives</label>
                        <textarea class="form-control" id="act_obj" name="act_obj" rows="3"><?php echo htmlspecialchars($proposal['act_obj']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Program Educational Objective(PEO) Targeted by the Proposed Activity</label>
                        <textarea class="form-control" id="peo_obj" name="peo_obj" rows="3"><?php echo htmlspecialchars($proposal['peo_obj']); ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label for="datetime_start" class="form-label">In-campus or Off-campus Activity</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="campus_act" value="In-campus Activity" id="campusAct" <?php echo htmlspecialchars($proposal['campus_act']) === 'In-campus Activity' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="campusAct">In-campus Activity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="campus_act" value="Off-campus Activity" id="offCampusAct" <?php echo htmlspecialchars($proposal['campus_act']) === 'Off-campus Activity' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="offCampusAct">Off-campus Activity</label>
                            </div>
                        </div>
                        <div class="col">
                            <label for="place_act" class="form-label">Local or International</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="place_act" value="Local" id="placeLocal" <?php echo htmlspecialchars($proposal['place_act']) === 'Local' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="placeLocal">Local</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="place_act" value="International" id="placeInternational" <?php echo htmlspecialchars($proposal['place_act']) === 'International' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="placeInternational">International</label>
                            </div>
                        </div>
                        <div class="col">
                            <label for="source_funds" class="form-label">Source of Funds</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Organization Funds" id="fundOrg" 
                                    <?php echo in_array('Organization Funds', $source_fund_array) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="fundOrg">Organization Funds</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Fund-raising/income generating activity" id="fundRaising" 
                                    <?php echo in_array('Fund-raising/income generating activity', $source_fund_array) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="fundRaising">Fund-raising/income generating activity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Student activity funds" id="fundStudent" 
                                    <?php echo in_array('Student activity funds', $source_fund_array) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="fundStudent">Student activity funds</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Others" id="othersCheckbox" 
                                    <?php echo !empty($other_source_fund) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="othersCheckbox">Others, please specify</label>
                            </div>
                            <input type="text" name="other_source_fund" id="othersInput" class="form-control mt-2" 
                                placeholder="Specify other source of funds" 
                                value="<?php echo htmlspecialchars($other_source_fund); ?>">
                        </div>

                    </div>     


                    <!-- Date and Time -->
                    <div class="row mb-3">
                        <div class="col">
                            <label for="datetime_start" class="form-label">Date and Time Start</label>
                            <input type="datetime-local" class="form-control" id="datetime_start" name="datetime_start" value="<?php echo htmlspecialchars($proposal['datetime_start']); ?>" required>
                        </div>
                        <div class="col">
                            <label for="datetime_end" class="form-label">Date and Time End</label>
                            <input type="datetime-local" class="form-control" id="datetime_end" name="datetime_end" value="<?php echo htmlspecialchars($proposal['datetime_end']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label for="venue" class="form-label">Venue</label>
                            <div class="input-group">
                                <select name="venue_select" id="venue_select" class="form-select">
                                <option value="">- Select On-Campus Venue -</option>
                                <?php foreach ($oncampus_venues as $venue_option): ?>
                                    <option value="<?= htmlspecialchars($venue_option) ?>"
                                    <?= ($saved_venue === $venue_option) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($venue_option) ?>
                                    </option>
                                <?php endforeach; ?>
                                </select>
                                <span class="input-group-text">or</span>
                                <input type="text" name="custom_venue" class="form-control" id="custom_venue"
                                placeholder="Enter off-campus venue"
                                value="<?= (!in_array($saved_venue, $oncampus_venues)) ? htmlspecialchars($saved_venue) : '' ?>">
                            </div>
                        </div>


                        <div class="col">
                            <label for="participants_num" class="form-label">Expected Number of Participants</label>
                            <input type="number" class="form-control form-control-sm" id="participants_num" name="participants_num" value="<?php echo htmlspecialchars($proposal['participants_num']); ?>" required>
                        </div>
                    </div>

                    
                </div>

                <div class="proposal-page"> 
                    <div class="row">
                        <div class="col">
                            <h2>INSTITUTIONAL VISION & MISSION <i>(Check all that apply)</i></h2>

                            <h2>VISION ASPECT</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[vision][]" value="Exemplary Instruction" id="vision1" <?php echo in_array('Exemplary Instruction', $mvc_values['vision']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vision1">Exemplary Instruction</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[vision][]" value="Sustainable Community Extension Services" id="vision2" <?php echo in_array('Sustainable Community Extension Services', $mvc_values['vision']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vision2">Sustainable Community Extension Services</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[vision][]" value="Research-Driven Programs" id="vision3" <?php echo in_array('Research-Driven Programs', $mvc_values['vision']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="vision3">Research-Driven Programs</label>
                            </div>  

                            <h2>MISSION ASPECT</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Develop Holistic, Self Fulfilling and Productive Citizen" id="mission1" <?php echo in_array('Develop Holistic, Self Fulfilling and Productive Citizen', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission1">Develop Holistic, Self Fulfilling and Productive Citizens</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Commit to National Development" id="mission2" <?php echo in_array('Commit to National Development', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission2">Commit to National Development</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Create a Legacy of Academic Excellence" id="mission3" <?php echo in_array('Create a Legacy of Academic Excellence', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission3">Create a Legacy of Academic Excellence</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Advocate Interactive Technology" id="mission4" <?php echo in_array('Advocate Interactive Technology', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission4">Advocate Interactive Technology</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Form Competent Administrators, Faculty, and Staff" id="mission5" <?php echo in_array('Form Competent Administrators, Faculty, and Staff', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission5">Form Competent Administrators, Faculty, and Staff</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Contribute to International Development" id="mission6" <?php echo in_array('Contribute to International Development', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission6">Contribute to International Development</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Promote Innovate Instruction" id="mission7" <?php echo in_array('Promote Innovate Instruction', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission7">Promote Innovate Instruction</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[mission][]" value="Forge a Just, Stable, and Humane" id="mission8" <?php echo in_array('Forge a Just, Stable, and Humane', $mvc_values['mission']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mission8">Forge a Just, Stable, and Humane</label>
                            </div>
                        </div>

                        <div class="col">
                            <h2>VALUES OF CEFI PROMOTED <i>(Check all that apply)</i></h2>

                            <h2>HONOR</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Professionalism" id="honor1" <?php echo in_array('Professionalism', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="honor1">Professionalism</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Personal Integrity" id="honor2" <?php echo in_array('Personal Integrity', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="honor2">Personal Integrity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Moral Sensitivity" id="honor3" <?php echo in_array('Moral Sensitivity', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="honor3">Moral Sensitivity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="National Pride" id="honor4" <?php echo in_array('National Pride', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="honor4">National Pride</label>
                            </div>

                            <h2>SCHOLARSHIP</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Critical Thinking" id="scholarship1" <?php echo in_array('Critical Thinking', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="scholarship1">Critical Thinking</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Academic Excellence" id="scholarship2" <?php echo in_array('Academic Excellence', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="scholarship2">Academic Excellence</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Discipline" id="scholarship3" <?php echo in_array('Discipline', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="scholarship3">Discipline</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Passion for Intellectual Inquiry" id="scholarship4" <?php echo in_array('Passion for Intellectual Inquiry', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="scholarship4">Passion for Intellectual Inquiry</label>
                            </div>

                            <h2>SERVICE</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Compassion" id="service1" <?php echo in_array('Compassion', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="service1">Compassion</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Civic Consciousness" id="service2" <?php echo in_array('Civic Consciousness', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="service2">Civic Consciousness</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Sectoral Immersion" id="service3" <?php echo in_array('Sectoral Immersion', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="service3">Sectoral Immersion</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvc[core value][]" value="Social Conscience" id="service4" <?php echo in_array('Social Conscience', $mvc_values['core value']) ? 'checked' : ''; ?>>
                                <input type="hidden" name="coreValuesType[]" value="core value">
                                <label class="form-check-label" for="service4">Social Conscience</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                                <!-- SDG Checklist Section -->
                            <h2>SUSTAINABLE DEVELOPMENT GOALS (SDGs) <i>address by the activity</i></h2>
                            <div class="mb-3 sdg-checklist">
                                <?php foreach ($sdg_list as $index => $sdg): ?>
                                    <?php 
                                    $sdg_number = $index + 1;
                                    $is_checked = in_array($sdg_number, array_column($sdgs, 'number'));
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sdgs[]" 
                                            value="<?php echo $sdg_number; ?>" 
                                            id="sdg<?php echo $sdg_number; ?>" 
                                            <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sdg<?php echo $sdg_number; ?>">
                                            <?php echo htmlspecialchars($sdg); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>   
                    </div>               
                </div> 

                <div class="proposal-page">

                    <h2>OTHER FORMS</h2>

                    <div class="accordion" id="proposalAccordion">
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingBudget">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBudget" aria-expanded="true" aria-controls="collapseBudget">
                                    Proposed Budget
                                </button>
                            </h2>
                            <div id="collapseBudget" class="accordion-collapse collapse show" aria-labelledby="headingBudget">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table" id="budgetTable">
                                            <thead>
                                                <tr>
                                                    <th>Particular</th>
                                                    <th>Amount</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($accordion_data['budget'] as $budget): ?>
                                                <tr>
                                                    <td>
                                                        <input type="hidden" name="budget_detail_id[]" value="<?php echo htmlspecialchars($budget['detail_id']); ?>">
                                                        <input type="text" name="budget_particular[]" class="form-control" value="<?php echo htmlspecialchars($budget['particular']); ?>" required>
                                                    </td>
                                                    <td><input type="number" name="budget_amount[]" class="form-control" value="<?php echo htmlspecialchars($budget['amount']); ?>" required></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3">
                                                        <button type="button" class="btn btn-primary btn-sm" id="addRowBudget">Add Particular</button>
                                                        <div class="mt-3">
                                                            <h6>Added Amount: ₱<span id="totalAmount">0.00</span></h6>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Program -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingProgram">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProgram" aria-expanded="false" aria-controls="collapseProgram">
                                    Program
                                </button>
                            </h2>
                            <div id="collapseProgram" class="accordion-collapse collapse" aria-labelledby="headingProgram">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table" id="programTable">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Detail</th>
                                                    <th>Person in Charge</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($accordion_data['program'] as $program): ?>
                                                <tr>
                                                    <td><input type="text" name="program_name[]" class="form-control" value="<?php echo htmlspecialchars($program['name']); ?>" required></td>
                                                    <td><input type="text" name="program_detail[]" class="form-control" value="<?php echo htmlspecialchars($program['detail']); ?>" required></td>
                                                    <td><input type="text" name="program_pic[]" class="form-control" value="<?php echo htmlspecialchars($program['pic']); ?>" required></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4">
                                                        <button type="button" class="btn btn-primary btn-sm" id="addRowProgram">Add Entry</button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Syllabus -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSyllabus">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSyllabus" aria-expanded="false" aria-controls="collapseSyllabus">
                                    Related Syllabus/Curriculum
                                </button>
                            </h2>
                            <div id="collapseSyllabus" class="accordion-collapse collapse" aria-labelledby="headingSyllabus">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table" id="syllabusTable">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Topic</th>
                                                    <th>Relevance</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($accordion_data['syllabus'] as $syllabus): ?>
                                                <tr>
                                                    <td><input type="text" name="syllabus_subject[]" class="form-control" value="<?php echo htmlspecialchars($syllabus['subject']); ?>" required></td>
                                                    <td><input type="text" name="syllabus_topic[]" class="form-control" value="<?php echo htmlspecialchars($syllabus['topic']); ?>" required></td>
                                                    <td><input type="text" name="syllabus_relevance[]" class="form-control" value="<?php echo htmlspecialchars($syllabus['relevance']); ?>" required></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4">
                                                    <button type="button" class="btn btn-primary btn-sm" id="addRowSyllabus">Add Entry</button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Manpower -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingManpower">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseManpower" aria-expanded="false" aria-controls="collapseManpower">
                                    Manpower Requirements
                                </button>
                            </h2>
                            <div id="collapseManpower" class="accordion-collapse collapse" aria-labelledby="headingManpower">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table" id="manpowerTable">
                                            <thead>
                                                <tr>
                                                    <th>Role</th>
                                                    <th>Name</th>
                                                    <th>Responsibilities</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($accordion_data['manpower'] as $manpower): ?>
                                                <tr>
                                                    <td><input type="text" name="manpower_role[]" class="form-control" value="<?php echo htmlspecialchars($manpower['role']); ?>" required></td>
                                                    <td><input type="text" name="manpower_name[]" class="form-control" value="<?php echo htmlspecialchars($manpower['name']); ?>" required></td>
                                                    <td><input type="text" name="manpower_responsibilities[]" class="form-control" value="<?php echo htmlspecialchars($manpower['responsibilities']); ?>" required></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4">
                                                        <button type="button" class="btn btn-primary btn-sm" id="addRowManpower">Add Entry</button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                            
                    <h2>PREPARED & NOTED BY:</h2>
                    <div class="row mb3">
                        <div class="col">
                            <label for="org_name" class="form-label">Name of the Organization/Department</label>
                            <input type="text" class="form-control" id="org_name" name="org_name" 
                                value="<?php echo htmlspecialchars($proposal['organization']); ?>" 
                                required>
                        </div>
                        <div class="col">
                            <label for="org_president" class="form-label">Organization/Department President</label>
                            <input type="text" class="form-control" id="org_president" name="org_president" 
                                value="<?php echo htmlspecialchars($proposal['president']); ?>" 
                                required>
                        </div>
                    </div>

                    
                    <h2>SELECT SIGNATORIES:</h2>
                    
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label for="Adviser/Moderator" class="form-label">Adviser/Moderator</label>
                                        <input type="text" id="Adviser/Moderator" name="Adviser/Moderator" class="form-control" 
                                value="<?php echo htmlspecialchars($signatories['Adviser/Moderator'] ?? ''); ?>" 
                            >
                        </div>

                        <div class="col">
                            <label for="Dean/Department Head" class="form-label">Dean/Department Head</label>
                            <select id="Dean/Department Head" name="Dean/Department Head" class="form-select form-select-sm">
                                            <option value="">- Select Dean/Department Head -</option>
                                            <?php foreach ($deans as $dean): ?>
                                                <?php $dean_name = $dean['first_name'] . ' ' . $dean['last_name']; ?>
                                                <option value="<?php echo htmlspecialchars($dean_name); ?>" 
                                                    <?php echo ($signatories['Dean/Department Head'] ?? '') === $dean_name ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dean_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="NoDeanNeeded" <?php echo ($signatories['Dean/Department Head'] ?? '') === 'NoDeanNeeded' ? 'selected' : ''; ?>>
                                                No Dean Needed
                                            </option>
                                        </select>
                        </div>
                    </div>

                    <br><br>

                     
                    <!-- Submit Button -->
                    <div class="text-end mt-3 ">
                        <a href="proposal_inbox.php" class="btn btn-secondary me-2">Back</a>
                        <button type="submit" id="submit-btn" class="btn btn-success">Update</button>
                    </div>
                    
                    
                </div>        
            </form>
        </div>
    </div>

    <script>
    const selectVenue = document.getElementById('venue_select');
    const customVenue = document.getElementById('custom_venue');

    // When selecting a dropdown option, clear custom input
    selectVenue.addEventListener('change', () => {
        if (selectVenue.value !== "") {
        customVenue.value = "";
        }
    });

    // When typing in custom input, clear dropdown selection
    customVenue.addEventListener('input', () => {
        if (customVenue.value !== "") {
        selectVenue.value = "";
        }
    });

    flatpickr("#datetime_start", {
    enableTime: true,
    dateFormat: "Y-m-d h:i K", // 'K' gives AM/PM
    time_24hr: false
    });

    flatpickr("#datetime_end", {
        enableTime: true,
        dateFormat: "Y-m-d h:i K",
        time_24hr: false
    });

    </script>


    <!-- JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../resources/js/universal.js"></script>
    <script src="../resources/js/functions.js"></script>
    <script src="../resources/js/proposal_forms.js"></script>
    
    <script>
        // Prevent double submission with SweetAlert2
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form.proposal');
            const submitBtn = document.getElementById('submit-btn');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent the default form submission
                
                // Disable the submit button
                submitBtn.disabled = true;
                
                // Show SweetAlert2 confirmation
                Swal.fire({
                    title: 'Update Proposal',
                    text: 'Are you sure you want to update this proposal?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, update it!',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Updating...',
                            html: 'Please wait while your proposal is being updated.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        form.submit();
                    } else {
                        // Re-enable the button if user cancels
                        submitBtn.disabled = false;
                    }
                });
            });
        });
    </script>
</body>
</html> 