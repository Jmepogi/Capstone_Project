<?php

session_start();

$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']); // Clear the flash message after displaying it

require '../config/system_db.php'; // or include '../config/system_db.php';


// Fetch Adviser/Moderator users
$moderatorQuery = "SELECT first_name, last_name FROM tbl_users WHERE role = 'Adviser/Moderator'";
$moderatorResult = $connection->query($moderatorQuery);
$moderators = [];
if ($moderatorResult && $moderatorResult->num_rows > 0) {
    while ($row = $moderatorResult->fetch_assoc()) {
        $moderators[] = $row;
    }
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

// Fetch venues from database
$venues_query = "SELECT venue_name, location FROM tbl_venues ORDER BY venue_name";
$venues_result = $connection->query($venues_query);
$venues = [];
if ($venues_result) {
    while ($row = $venues_result->fetch_assoc()) {
        $venues[] = $row;
    }
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   
    // Initialize flags based on checkbox selection
$isOffCampus = isset($_POST['campus_act']) && $_POST['campus_act'] == 'Off-campus Activity';
$isInternational = isset($_POST['place_act']) && $_POST['place_act'] == 'International Activity';

// Define the proposal type and the order of signatories based on proposal type
$proposalType = $_POST['proposal_type'] ?? '';
$signatoriesOrder = [];

// Define all possible signatories (can be expanded)
$allSignatories = [
    'Adviser/Moderator',
    'Supreme College Student Council Adviser',
    'Community Affairs',
    'Dean/Department Head',
    'External Affairs',
    'Office for Student Affairs',
    'Vice President for Academic Affairs'
];

// Define hierarchy patterns
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

// 1. Add roles based on proposal type
switch ($proposalType) {
    case 'Extra-Curricular Activity Proposal':
        $signatoriesOrder = $hierarchyPattern1;
        break;
    case 'Co-Curricular Activity Proposal':
        $signatoriesOrder = $hierarchyPattern2;
        break;
    case 'Extra-Curricular Activity Proposal (Community Project)':
        $signatoriesOrder = $hierarchyPattern1;
        break;
    case 'Co-Curricular Activity Proposal (Community Project)':
        $signatoriesOrder = $hierarchyPattern2;
        break;
}

// 2. Check for "No Dean Needed" selection and filter accordingly
$deanSelection = $_POST['Dean/Department Head'] ?? '';
$isNoDeanNeeded = ($deanSelection === 'NoDeanNeeded');

// Dynamically filter out missing roles based on selection or conditions
$filteredSignatoriesOrder = array_filter($signatoriesOrder, function($role) use ($isNoDeanNeeded, $proposalType, $isOffCampus, $isInternational) {
    // Filter out "Dean/Department Head" if "NoDeanNeeded" is selected
    if ($isNoDeanNeeded && $role === 'Dean/Department Head') {
        return false;
    }

    // Keep Community Affairs only for Community Project proposal types
    if ($role === 'Community Affairs') {
        return $proposalType === 'Extra-Curricular Activity Proposal (Community Project)' || 
               $proposalType === 'Co-Curricular Activity Proposal (Community Project)';
    }

    // Keep External Affairs only for off-campus or international activities
    if ($role === 'External Affairs') {
        return $isOffCampus || $isInternational;
    }

    return true;
});

// Reindex the array to avoid gaps
$signatoriesOrder = array_values($filteredSignatoriesOrder);

// 3. Add 'External Affairs' if activity is off-campus or international (if not already added)
if (($isOffCampus || $isInternational) && !in_array('External Affairs', $signatoriesOrder)) {
    $signatoriesOrder[] = 'External Affairs';
}

// 4. Initialize an empty array to hold signatories data
$signatoriesData = [];

// 5. Fetch the signatories data based on the order defined above
foreach ($signatoriesOrder as $role) {
    // Fetch dynamic roles by name (Adviser/Moderator, Dean/Department Head)
    if (in_array($role, ['Adviser/Moderator', 'Dean/Department Head'])) {
        $roleKey = str_replace(' ', '_', $role);  // Replace space with underscore
        if (!empty($_POST[$roleKey])) {
            $userData = getUserDataByName($_POST[$roleKey]);
            if ($userData) {
                $signatoriesData[$role] = [
                    'name' => $userData['full_name'],
                    'user_id' => $userData['user_id'],
                    'signatory_role' => $role
                ];
            }
        }
    }

    // Fetch fixed signatories based on department
    if (in_array($role, ['Office for Student Affairs', 'Supreme College Student Council Adviser', 'Community Affairs', 'Vice President for Academic Affairs', 'External Affairs'])) {
        $userData = getUserDataByDepartment($role);
        if ($userData) {
            $signatoriesData[$role] = [
                'name' => $userData['full_name'],
                'user_id' => $userData['user_id'],
                'signatory_role' => $role
            ];
        }
    }
}

// 6. Sort the signatories data based on the signatoriesOrder
$sortedSignatoriesData = [];
foreach ($signatoriesOrder as $role) {
    if (isset($signatoriesData[$role])) {
        $sortedSignatoriesData[] = $signatoriesData[$role];
    }
}

// 7. Find the next active signatory based on their current status
$activeSignatory = null;
foreach ($sortedSignatoriesData as $index => $signatory) {
    if (isset($signatory['signatory_status']) && $signatory['signatory_status'] === 'Pending') {
        $activeSignatory = $signatory;
        break;  // Stop when the first pending signatory is found
    }
}

// Output the sorted signatories (for example, for debugging purposes)
print_r($sortedSignatoriesData);



    // Step 3: Collect Proposal Data (ensure no conflict with venue/time)
    $venue = isset($_POST['venue']) ? $_POST['venue'] : '';
    $datetime_start = $_POST['datetime_start'];
    $datetime_end = $_POST['datetime_end'];

    // Ensure the date-time values are in the correct format (Y-m-d H:i:s)
    $datetime_start = date('Y-m-d H:i:s', strtotime($datetime_start)); // Convert to Y-m-d H:i:s
    $datetime_end = date('Y-m-d H:i:s', strtotime($datetime_end)); // Convert to Y-m-d H:i:s

    // SQL query to check for conflicts
    $sql_check = "SELECT status FROM tbl_proposal WHERE venue = ? AND (
        (datetime_start <= ? AND datetime_end >= ?) OR
        (datetime_start <= ? AND datetime_end >= ?)
    )";
    $stmt_check = $connection->prepare($sql_check);

    // Bind parameters
    $stmt_check->bind_param('sssss', $venue, $datetime_end, $datetime_start, $datetime_start, $datetime_end);

    // Execute the query
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    $conflictFound = false;
    $approvedConflict = false;

    // Check if any result is returned
    while ($row = $result_check->fetch_assoc()) {
        if ($row['status'] === 'Approved') {
            $approvedConflict = true;
            break;
        }
        $conflictFound = true; // Conflict found, but it's pending, not approved
    }

    // Step 4: Handle Conflicts and Flash Message
    if ($approvedConflict) {
        // If an approved proposal is found, show error message
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'A proposal with the same venue and overlapping date/time is already approved. Please choose a different schedule.'
        ];
    } elseif ($conflictFound) {
        // If a pending proposal is found, show warning message
        $_SESSION['flash_message'] = [
            'type' => 'warning',
            'message' => 'Your proposal has been submitted. However, there is already a pending proposal with the same venue and date/time. Approval will depend on the signatories.'
        ];

        // Set the flag that there is a pending conflict
        $_SESSION['conflict_pending'] = true;
    } else {
        // No conflict, proceed with the proposal submission
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Proposal submitted successfully!'
        ];
    }

    // Step 5: Prepare Proposal Data
        $proposalData = [
            'title' => $_POST['proposal_title'],
            'type' => $_POST['proposal_type'],
            'description' => $_POST['activity_nature'],
            'act_obj' => $_POST['act_obj'], 
            'org_obj' => $_POST['org_obj'], 
            'peo_obj' => $_POST['peo_obj'],
            'beneficiaries' => $_POST['beneficiaries'],
            'campus_act' => $_POST['campus_act'],    
            'place_act' => $_POST['place_act'],       
            'datetime_start' => $datetime_start,
            'datetime_end' => $datetime_end,
            'venue' => $_POST['venue'], 
            'participants_num' => $_POST['participants_num'],
            'organization' => $_POST['org_name'],
            'president' => $_POST['org_president'],
            'source_fund' => $_POST['source_fund'],
            ];

    // Make sure budgetParticulars is initialized before the loop
    $budgetParticulars = isset($_POST['budget_particular']) ? $_POST['budget_particular'] : [];
    $budgetAmounts = isset($_POST['budget_amount']) ? $_POST['budget_amount'] : [];

    // Budget Data
    $budgetData = [];
    $budgetTotal = 0;
    for ($i = 0; $i < count($budgetParticulars); $i++) {
        if (!empty($budgetParticulars[$i]) && isset($budgetAmounts[$i])) {
            $amount = floatval($budgetAmounts[$i]);
            $budgetTotal += $amount;
            $budgetData[] = [
                'field1' => $budgetParticulars[$i],
                'field2' => '',
                'field3' => '',
                'amount' => $amount
            ];
        }
    }

    // Step 6: Insert Proposal into tbl_proposal
    $proposalId = insertProposal($connection, $proposalData);

    if ($proposalId) {
    
        // Step 8: Insert selected SDGs into tbl_proposal_sdgs
        insertSDGs($connection, $proposalId, $_POST['sdgs']);

        // Step 9: Handle Vision, Mission, and Core Values
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

        // Assuming the MVC values are passed from the form
        $selectedMvcValues = isset($_POST['mvcValues']) ? array_unique($_POST['mvcValues']) : [];
        $mvcData = [];

        foreach ($selectedMvcValues as $mvcValue) {
            if (array_key_exists($mvcValue, $mvcTypes)) {
                $mvcData[] = [
                    'mvc_value' => $mvcValue,
                    'mvc_type' => $mvcTypes[$mvcValue]
                ];
            }
        }

        // Insert MVC data into the respective table
        insertMvcData($connection, $proposalId, $mvcData);

        // Step 10: Insert Signatories into tbl_proposal_signatories
        insertSignatories($connection, $proposalId, $sortedSignatoriesData, $proposalData['type']);

        // Step 11: Insert Proposal Details into tbl_proposal_details
        $syllabusDetails = [];
        if (isset($_POST['syllabus_subject'])) {
            $syllabusSubjects = $_POST['syllabus_subject'];
            $syllabusTopics = $_POST['syllabus_topic'];
            $syllabusRelevance = $_POST['syllabus_relevance'];
            for ($i = 0; $i < count($syllabusSubjects); $i++) {
                $syllabusDetails[] = [
                    'field1' => $syllabusSubjects[$i],
                    'field2' => $syllabusTopics[$i],
                    'field3' => $syllabusRelevance[$i],
                    'amount' => ''
                ];
            }
        }
        insertProposalDetails($connection, $proposalId, $syllabusDetails, 'Syllabus');

        $programDetails = [];
        if (isset($_POST['program_name'])) {
            $programNames = $_POST['program_name'];
            $programDetailsList = $_POST['program_detail'];
            $programPersons = $_POST['program_person'];
            for ($i = 0; $i < count($programNames); $i++) {
                $programDetails[] = [
                    'field1' => $programNames[$i],
                    'field2' => $programDetailsList[$i],
                    'field3' => $programPersons[$i],
                    'amount' => ''
                ];
            }
        }
        insertProposalDetails($connection, $proposalId, $programDetails, 'Program');

        $manpowerDetails = [];
        if (isset($_POST['manpower_role'])) {
            $manpowerRoles = $_POST['manpower_role'];
            $manpowerNames = $_POST['manpower_name'];
            $manpowerResponsibilities = $_POST['manpower_responsibilities'];
            for ($i = 0; $i < count($manpowerRoles); $i++) {
                $manpowerDetails[] = [
                    'field1' => $manpowerRoles[$i],
                    'field2' => $manpowerNames[$i],
                    'field3' => $manpowerResponsibilities[$i],
                    'amount' => ''
                ];
            }
        }
        insertProposalDetails($connection, $proposalId, $manpowerDetails, 'Manpower');

        // Step 12: Insert Budget Data into tbl_proposal_details
        insertProposalDetails($connection, $proposalId, $budgetData, 'Budget');

        // Commit transaction
        $connection->commit();
        
        $_SESSION['flash_message'] = [
            'type' => 'success', 
            'message' => 'Proposal submitted successfully!'
        ];
        header('Location: proposal_inbox.php');
        exit();

    } else {
        // Set error message in session
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error submitting proposal!'
        ];
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


// Function to insert proposal into the tbl_proposal
function insertProposal($connection, $proposalData) {
    // SQL query with act_obj and source_fund fields included
    $sql = "INSERT INTO tbl_proposal 
            (title, type, description, act_obj, org_obj, peo_obj, beneficiaries, campus_act, place_act, datetime_start, datetime_end, venue, participants_num, organization, president, source_fund, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        error_log("SQL error: " . $connection->error);
        return false;
    }

    // Updated bind_param to match all 17 parameters in the query
    $stmt->bind_param("ssssssssssssisss",
        $proposalData['title'],
        $proposalData['type'],
        $proposalData['description'],
        $proposalData['act_obj'],         // Activity Objective
        $proposalData['org_obj'],         // Organization Objective
        $proposalData['peo_obj'],         // Program Educational Objective
        $proposalData['beneficiaries'],   // Beneficiaries
        $proposalData['campus_act'],      // Campus Activity
        $proposalData['place_act'],       // Place Activity
        $proposalData['datetime_start'],
        $proposalData['datetime_end'],
        $proposalData['venue'],           
        $proposalData['participants_num'],
        $proposalData['organization'],
        $proposalData['president'],
        $proposalData['source_fund']      // Source of Funds
    );

    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        return false;
    }
}


// Function to get user_id, full name, and role (signatory_role) by name (first_name and last_name)
function getUserDataByName($name) {
    global $connection;

    // Split the full name into first and last name
    $nameParts = explode(' ', $name);
    if (count($nameParts) < 2) {
        return null; // Return null if there's no last name
    }

    $firstName = $nameParts[0];
    $lastName = implode(' ', array_slice($nameParts, 1)); // Handle multi-word last names

    // Query to find user_id, first name, last name, and role
    $query = "SELECT user_id, role FROM tbl_users WHERE first_name = ? AND last_name = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ss", $firstName, $lastName);
    $stmt->execute();
    $stmt->bind_result($userId, $role);

    if ($stmt->fetch()) {
        $stmt->close();
        return [
            'user_id' => $userId,
            'full_name' => "$firstName $lastName",
            'signatory_role' => $role
        ];
    }

    $stmt->close();
    return null;  // Return null if no matching user is found
}

// Function to get the role of the user by user_id
function getUserRoleByUserId($userId) {
    global $connection;

    $query = "SELECT role FROM tbl_users WHERE user_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($role);

    if ($stmt->fetch()) {
        $stmt->close();
        return $role;
    }

    $stmt->close();
    return null;  // Return null if no matching role is found
}



// Function to get user_id, full name, and role (signatory_role) by department
function getUserDataByDepartment($department) {
    global $connection;

    // Query to find the user_id, first name, last_name, and role based on department
    $query = "SELECT user_id, first_name, last_name, role FROM tbl_users WHERE department = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $stmt->bind_result($userId, $firstName, $lastName, $role);

    if ($stmt->fetch()) {
        $stmt->close();
        return [
            'user_id' => $userId,
            'full_name' => "$firstName $lastName",
            'signatory_role' => $department // Use department name as the role for fixed signatories
        ];
    }

    $stmt->close();
    return null;  // Return null if no matching user is found
}



function insertSignatories($connection, $proposalId, $signatoriesData, $proposalType = '') {
    if (empty($signatoriesData)) {
        error_log("No signatories to insert for proposal ID $proposalId.");
        return false;
    }

    // Use the correct signatory hierarchies already defined earlier in the file
    global $hierarchyPattern1, $hierarchyPattern2;
    
    // Determine which hierarchy pattern to use based on proposal type
    if (strpos($proposalType, 'Co-Curricular') !== false) {
        $signatoriesOrder = $hierarchyPattern2;
    } else {
        $signatoriesOrder = $hierarchyPattern1;
    }

    // Prepare the SQL query to insert signatories with signatory_order
    $signatoriesSql = "INSERT INTO tbl_proposal_signatories 
        (proposal_id, user_id, signatory_name, signatory_role, signatory_status, signatory_order) 
        VALUES (?, ?, ?, ?, 'Pending', ?)";

    $signatoriesStmt = $connection->prepare($signatoriesSql);

    if ($signatoriesStmt === false) {
        error_log("SQL prepare error: " . $connection->error);
        return false;
    }

    // Loop through the signatoriesData
    foreach ($signatoriesData as $signatory) {
        error_log("Inserting signatory: " . print_r($signatory, true));

        $signatoryName = isset($signatory['name']) ? $signatory['name'] : '';
        if (empty($signatoryName)) {
            error_log("Error: Signatory name is empty or invalid for proposal ID $proposalId.");
            continue;
        }

        $userId = isset($signatory['user_id']) ? $signatory['user_id'] : null;
        $signatoryRole = isset($signatory['signatory_role']) ? $signatory['signatory_role'] : '';

        if (!$userId) {
            error_log("Error: User ID not found for signatory $signatoryName.");
            continue;
        }

        // Calculate the correct signatory_order
        $order = array_search($signatoryRole, $signatoriesOrder);
        if ($order === false) {
            $order = 999; // If not found in the hierarchy, put it at the bottom
        } else {
            $order += 1; // Make it 1-based index
        }

        // Bind parameters: proposal_id, user_id, signatory_name, signatory_role, signatory_order
        $signatoriesStmt->bind_param("iissi", $proposalId, $userId, $signatoryName, $signatoryRole, $order);

        if (!$signatoriesStmt->execute()) {
            error_log("Error inserting signatory: " . $signatoriesStmt->error);
        }
    }

    $signatoriesStmt->close();
    return true;
}


// Function to insert SDGs into the tbl_proposal_sdgs
function insertSDGs($connection, $proposalId, $selectedSDGs) {
    if (!empty($selectedSDGs)) {
        $sdgSql = "INSERT INTO tbl_proposal_sdgs (proposal_id, sdg_number, sdg_description) VALUES (?, ?, ?)";
        $sdgStmt = $connection->prepare($sdgSql);
        
        if ($sdgStmt === false) {
            error_log("SQL prepare error: " . $connection->error);
            return false;
        }

    $sdgDescriptions = [
        1 => "No Poverty",
        2 => "Zero Hunger",
        3 => "Good Health and Well-being",
        4 => "Quality Education",
        5 => "Gender Equality",
        6 => "Clean Water and Sanitation",
        7 => "Affordable and Clean Energy",
        8 => "Decent Work and Economic Growth",
        9 => "Industry, Innovation and Infrastructure",
        10 => "Reduced Inequality",
        11 => "Sustainable Cities and Communities",
        12 => "Responsible Consumption and Production",
        13 => "Climate Action",
        14 => "Life Below Water",
        15 => "Life on Land",
        16 => "Peace, Justice and Strong Institutions",
        17 => "Partnerships for the Goals"
    ];

    foreach ($selectedSDGs as $sdgNumber) {
        // Check if SDG number is valid
        if (array_key_exists($sdgNumber, $sdgDescriptions)) {
            $sdgDescription = $sdgDescriptions[$sdgNumber];
            $sdgStmt->bind_param("iis", $proposalId, $sdgNumber, $sdgDescription);
            if (!$sdgStmt->execute()) {
                error_log("Error inserting SDG: " . $sdgStmt->error);
            }
        } else {
            error_log("Invalid SDG number: " . $sdgNumber);
        }
    }

    $sdgStmt->close();
}
}

// Function to insert MVC data into tbl_mvc
function insertMvcData($connection, $proposalId, $mvcData) {
    // If data is null or empty, return true as this might be valid
    if (!$mvcData || !is_array($mvcData) || empty($mvcData)) {
        return true;
    }

    // Start transaction
    $connection->begin_transaction();

    try {
        $mvcSql = "INSERT INTO tbl_mvc (proposal_id, mvc_value, mvc_type) VALUES (?, ?, ?)";
        $mvcStmt = $connection->prepare($mvcSql);
        
        if ($mvcStmt === false) {
            throw new Exception("Failed to prepare MVC statement: " . $connection->error);
        }

        foreach ($mvcData as $mvc) {
            // Validate MVC data structure
            if (!is_array($mvc) || !isset($mvc['mvc_value']) || !isset($mvc['mvc_type'])) {
                throw new Exception("Invalid MVC data structure");
            }

            // Trim and validate values
            $mvcValue = trim($mvc['mvc_value']);
            $mvcType = trim($mvc['mvc_type']);

            if (empty($mvcValue) || empty($mvcType)) {
                continue; // Skip empty entries
            }

            $mvcStmt->bind_param("iss", $proposalId, $mvcValue, $mvcType);
            if (!$mvcStmt->execute()) {
                throw new Exception("Error inserting MVC data: " . $mvcStmt->error);
            }
        }

        $mvcStmt->close();
        $connection->commit();
        return true;

    } catch (Exception $e) {
        // Log the error with context
        error_log("Error in insertMvcData: " . $e->getMessage());
        error_log("MVC Data received: " . print_r($mvcData, true));
        
        // Rollback the transaction
        $connection->rollback();
        
        // Close statement if it exists
        if (isset($mvcStmt)) {
            $mvcStmt->close();
        }
        
        return false;
    }
}

// Function to insert proposal details into tbl_proposal_details
function insertProposalDetails($connection, $proposalId, $data, $category) {
    // If data is null or empty, return true as this is valid for some categories (like Syllabus)
    if (!$data || !is_array($data) || empty($data)) {
        return true;
    }

    // Start transaction
    $connection->begin_transaction();

    try {
        // Prepare the SQL statement
        $sql = "INSERT INTO tbl_proposal_details (proposal_id, category, field1, field2, field3, amount) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $connection->error);
        }

        foreach ($data as $entry) {
            // Skip if entry is not an array
            if (!is_array($entry)) {
                continue;
            }

            // Skip empty syllabus entries
            if ($category === 'Syllabus' && 
                empty(trim($entry['field1'] ?? '')) && 
                empty(trim($entry['field2'] ?? '')) && 
                empty(trim($entry['field3'] ?? ''))) {
                continue;
            }

            // Use null coalescing operator and trim for safer data access
            $field1 = !empty(trim($entry['field1'] ?? '')) ? trim($entry['field1']) : null;
            $field2 = !empty(trim($entry['field2'] ?? '')) ? trim($entry['field2']) : null;
            $field3 = !empty(trim($entry['field3'] ?? '')) ? trim($entry['field3']) : null;
            
            // Handle amount field specially
            $amount = null;
            if (isset($entry['amount']) && $entry['amount'] !== '') {
                $amount = is_numeric($entry['amount']) ? (float)$entry['amount'] : null;
            }

            // Always use 'd' for amount (it will be NULL if not set)
            $stmt->bind_param("issssd", $proposalId, $category, $field1, $field2, $field3, $amount);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert $category details: " . $stmt->error);
            }
        }

        $stmt->close();
        $connection->commit();
        return true;

    } catch (Exception $e) {
        // Log the error with more context
        error_log("Error in insertProposalDetails for category $category: " . $e->getMessage());
        error_log("Data received: " . print_r($data, true));
        
        // Rollback the transaction
        $connection->rollback();
        
        // Close statement if it exists
        if (isset($stmt)) {
            $stmt->close();
        }
        
        return false;
    }
}

// Function to insert budget data into tbl_proposal_budget
function insertBudgetData($connection, $proposalId, $budgetData) {
    if (empty($budgetData)) {
        error_log("No budget data to insert for proposal ID $proposalId.");
        return false;
    }

    // Start transaction
    $connection->begin_transaction();

    try {
        // Prepare the SQL statement
        $sql = "INSERT INTO tbl_proposal_budget (proposal_id, field1, field2, field3, amount) VALUES (?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $connection->error);
        }

        foreach ($budgetData as $entry) {
            // Skip if entry is not an array
            if (!is_array($entry)) {
                continue;
            }

            // Use null coalescing operator and trim for safer data access
            $field1 = !empty(trim($entry['field1'] ?? '')) ? trim($entry['field1']) : null;
            $field2 = !empty(trim($entry['field2'] ?? '')) ? trim($entry['field2']) : null;
            $field3 = !empty(trim($entry['field3'] ?? '')) ? trim($entry['field3']) : null;
            
            // Handle amount field specially
            $amount = null;
            if (isset($entry['amount']) && $entry['amount'] !== '') {
                $amount = is_numeric($entry['amount']) ? (float)$entry['amount'] : null;
            }

            // Always use 'd' for amount (it will be NULL if not set)
            $stmt->bind_param("isssd", $proposalId, $field1, $field2, $field3, $amount);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert budget data: " . $stmt->error);
            }
        }

        $stmt->close();
        $connection->commit();
        return true;

    } catch (Exception $e) {
        // Log the error with more context
        error_log("Error in insertBudgetData: " . $e->getMessage());
        error_log("Budget Data received: " . print_r($budgetData, true));
        
        // Rollback the transaction
        $connection->rollback();
        
        // Close statement if it exists
        if (isset($stmt)) {
            $stmt->close();
        }
        
        return false;
    }
}

// Close database connectionection
$connection->close();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Proposal</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    
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
                        // Set inline styles based on flash message type
                        $backgroundColor = '';
                        $textColor = '';

                        switch ($flash_message['type']) {
                            case 'success':
                                $backgroundColor = '#d4edda';
                                $textColor = '#155724';
                                break;
                            case 'warning':
                                $backgroundColor = '#fff3cd';
                                $textColor = '#856404';
                                break;
                            case 'danger':
                            case 'error': // in case 'error' is used as an alias for danger
                                $backgroundColor = '#f8d7da';
                                $textColor = '#721c24';
                                break;
                            case 'info':
                                $backgroundColor = '#d1ecf1';
                                $textColor = '#0c5460';
                                break;
                            default:
                                $backgroundColor = '#f8f9fa'; // default light background
                                $textColor = '#212529'; // default dark text
                                break;
                        }
                        ?>
                        <div class="alert d-flex align-items-center ms-3" role="alert" 
                            style="background-color: <?= $backgroundColor ?>; color: <?= $textColor ?>;">
                            <span class="material-symbols-outlined me-2">
                                <?= $flash_message['type'] === 'success' ? 'check_circle' : 'error' ?>
                            </span>
                            <div><?= htmlspecialchars($flash_message['message']) ?></div>
                            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
    
                    <h2>PROPOSAL DETAILS</h2>
                    <!-- Proposal Title -->
                    <div class="row mb-3">
                        <div class="col">
                            <label for="title" class="form-label">Title/Theme of Proposed Activity/Project</label>
                            <input type="text" class="form-control form-control-sm" id="title" name="proposal_title" required>
                        </div>
                        <div class="col">
                            <label for="proposal_type" class="form-label">Student Activity Proposal Type</label>
                            <select class="form-select form-select-sm" id="proposal_type" name="proposal_type" required>
                                <option value="" selected>- select proposal type -</option>
                                <option value="Extra-Curricular Activity Proposal">Extra-Curricular Activity Proposal</option>
                                <option value="Extra-Curricular Activity Proposal (Community Project)">Extra-Curricular Activity Proposal (Community Project)</option>
                                <option value="Co-Curricular Activity Proposal">Co-Curricular Activity Proposal</option>
                                <option value="Co-Curricular Activity Proposal (Community Project)">Co-Curricular Activity Proposal (Community Project)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Text Areas -->
                    <div class="mb-3">
                        <label for="activity_nature" class="form-label">Nature of the Proposed Activity/Project</label>
                        <textarea class="form-control" id="activity_nature" name="activity_nature" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Beneficiaries</label>
                        <textarea class="form-control" id="beneficiaries" name="beneficiaries" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Organization's Objectives</label>
                        <textarea class="form-control" id="org_obj" name="org_obj" rows="3" ></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Activity/Project Objectives</label>
                        <textarea class="form-control" id="act_obj" name="act_obj" rows="3" ></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="activity_objectives" class="form-label">Program Educational Objective(PEO) Targeted by the Proposed Activity</label>
                        <textarea class="form-control" id="peo_obj" name="peo_obj" rows="3" ></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col">
                            <label for="datetime_start" class="form-label">In-campus or Off-campus Activity</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="campus_act" value="In-campus Activity" id="">
                                <label class="form-check-label" for="">In-campus Activity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="campus_act" value="Off-campus Activity" id="">
                                <label class="form-check-label" for="">Off-campus Activity</label>
                            </div>
                        </div>
                        <div class="col">
                            <label for="datetime_start" class="form-label">Local or International</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="place_act" value="Local" id="">
                                <label class="form-check-label" for="">Local</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="place_act" value="International" id="">
                                <label class="form-check-label" for="">International</label>
                            </div>
                        </div>
                        <div class="col">
                            <label for="source_funds" class="form-label">Source of Funds</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Organization Funds">
                                <label class="form-check-label">Organization Funds</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Fund-raising/income generating activity">
                                <label class="form-check-label">Fund-raising/income generating activity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Student activity funds">
                                <label class="form-check-label">Student activity funds</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="source_fund[]" value="Others" id="othersCheckbox">
                                <label class="form-check-label">Others, please specify</label>
                            </div>
                            <input type="text" name="other_source_fund" id="othersInput" class="form-control mt-2" placeholder="Specify other source of funds" style="display: none;">
                            <input type="hidden" name="source_fund" id="sourceFund">

                        </div>
                    </div>     


                    <!-- Date and Time -->
                    <div class="row mb-3">
                        <div class="col">
                            <label for="datetime_start" class="form-label">Date and Time Start</label>
                            <input type="datetime-local" class="form-control"
                                id="datetime_start" name="datetime_start"
                                placeholder="YYYY-MM-DDThh:mm"
                                pattern="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}" required>
                        </div>
                        <div class="col">
                            <label for="datetime_end" class="form-label">Date and Time End</label>
                            <input type="datetime-local" class="form-control"
                                id="datetime_end" name="datetime_end"
                                placeholder="YYYY-MM-DDThh:mm"
                                pattern="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}" required>
                        </div>
                    </div>


                    <div class="row mb-3">
                        <!-- Venue Selection -->
                        <div class="col">
                            <label for="venue_select" class="form-label">Venue <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select" id="venue_select">
                                    <option value="">Select an on-campus venue</option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?= htmlspecialchars($venue['venue_name'] . ' - ' . $venue['location']) ?>">
                                            <?= htmlspecialchars($venue['venue_name'] . ' - ' . $venue['location']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="input-group-text">or</span>
                                <input type="text" class="form-control" id="venue_input" placeholder="Enter off-campus venue">
                            </div>
                            <!-- Hidden input for final value to be submitted -->
                            <input type="hidden" name="venue" id="venue_final" required>
                        </div>


    
                        <div class="col">
                            <label for="participants_num" class="form-label">Expected Number of Participants</label>
                            <input type="number" class="form-control form-control-sm" id="participants_num" name="participants_num" required>
                        </div>
                    </div>

                    
                    

                    
                </div>

                <div class="proposal-page"> 
                    <div class="row">
                        <div class="col">
                            <h2>INSTITUTIONAL VISION & MISSION <i>(Check all that apply)</i></h2>

                            <h2>VISION ASPECT</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Exemplary Instruction" id="vision1">
                                <label class="form-check-label" for="vision1">Exemplary Instruction</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Sustainable Community Extension Services" id="vision2">
                                <label class="form-check-label" for="vision2">Sustainable Community Extension Services</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Research-Driven Programs" id="vision3">
                                <label class="form-check-label" for="vision3">Research-Driven Programs</label>
                            </div>  

                            <h2>MISSION ASPECT</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Develop Holistic, Self Fulfilling and Productive Citizen" id="mission1">
                                <label class="form-check-label" for="mission1">Develop Holistic, Self Fulfilling and Productive Citizens</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Commit to National Development" id="mission2">
                                <label class="form-check-label" for="mission2">Commit to National Development</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Create a Legacy of Academic Excellence" id="mission3">
                                <label class="form-check-label" for="mission3">Create a Legacy of Academic Excellence</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Advocate Interactive Technology" id="mission4">
                                <label class="form-check-label" for="mission4">Advocate Interactive Technology</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Form Competent Administrators, Faculty, and Staff" id="mission5">
                                <label class="form-check-label" for="mission5">Form Competent Administrators, Faculty, and Staff</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Contribute to International Development" id="mission6">
                                <label class="form-check-label" for="mission6">Contribute to International Development</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Promote Innovate Instruction" id="mission7">
                                <label class="form-check-label" for="mission7">Promote Innovate Instruction</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Forge a Just, Stable, and Humane" id="mission8">
                                <label class="form-check-label" for="mission8">Forge a Just, Stable, and Humane</label>
                            </div>
                        </div>

                        <div class="col">
                            <h2>VALUES OF CEFI PROMOTED <i>(Check all that apply)</i></h2>

                            <h2>HONOR</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Professionalism" id="honor1">
                                <label class="form-check-label" for="honor1">Professionalism</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Personal Integrity" id="honor2">
                                <label class="form-check-label" for="honor2">Personal Integrity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Moral Sensitivity" id="honor3">
                                <label class="form-check-label" for="honor3">Moral Sensitivity</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="National Pride" id="honor4">
                                <label class="form-check-label" for="honor4">National Pride</label>
                            </div>

                            <h2>SCHOLARSHIP</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Critical Thinking" id="scholarship1">
                                <label class="form-check-label" for="scholarship1">Critical Thinking</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Academic Excellence" id="scholarship2">
                                <label class="form-check-label" for="scholarship2">Academic Excellence</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Discipline" id="scholarship3">
                                <label class="form-check-label" for="scholarship3">Discipline</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Passion for Intellectual Inquiry" id="scholarship4">
                                <label class="form-check-label" for="scholarship4">Passion for Intellectual Inquiry</label>
                            </div>

                            <h2>SERVICE</h2>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Compassion" id="service1">
                                <label class="form-check-label" for="service1">Compassion</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Civic Consciousness" id="service2">
                                <label class="form-check-label" for="service2">Civic Consciousness</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Sectoral Immersion" id="service3">
                                <label class="form-check-label" for="service3">Sectoral Immersion</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mvcValues[]" value="Social Conscience" id="service4">
                                <label class="form-check-label" for="service4">Social Conscience</label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col">
                                <!-- SDG Checklist Section -->
                            <h2>SUSTAINABLE DEVELOPMENT GOALS (SDGs) <i>address by the activity</i></h2>
                            <div class="mb-3 sdg-checklist">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="1" id="sdg1">
                                    <label class="form-check-label" for="sdg1">SDG 1 - No Poverty</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="2" id="sdg2">
                                    <label class="form-check-label" for="sdg2">SDG 2 - Zero Hunger</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="3" id="sdg3">
                                    <label class="form-check-label" for="sdg3">SDG 3 -  Good Health and Well-being</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="4" id="sdg4">
                                    <label class="form-check-label" for="sdg4">SDG 4 -  Quality Education</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="5" id="sdg5">
                                    <label class="form-check-label" for="sdg5">SDG 5 -  Gender Equality</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="6" id="sdg6">
                                    <label class="form-check-label" for="sdg6">SDG 6 - Clean Water and Sanitation</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="7" id="sdg7">
                                    <label class="form-check-label" for="sdg7">SDG 7 -  Affordable and Clean Energy</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="8" id="sdg8">
                                    <label class="form-check-label" for="sdg8">SDG 8 -  Decent Work and Economic Growth</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="9" id="sdg9">
                                    <label class="form-check-label" for="sdg9">SDG 9 -  Industry, Innovation and Infrastructure</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="10" id="sdg10">
                                    <label class="form-check-label" for="sdg10">SDG 10 -  Reduced Inequality</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="11" id="sdg11">
                                    <label class="form-check-label" for="sdg11">SDG 11 -  Sustainable Cities and Communities</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="12" id="sdg12">
                                    <label class="form-check-label" for="sdg12">SDG 12 -  Responsible Consumption and Production</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="13" id="sdg13">
                                    <label class="form-check-label" for="sdg13">SDG 13 -  Climate Action</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="14" id="sdg14">
                                    <label class="form-check-label" for="sdg14">SDG 14 -  Life Below Water</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="15" id="sdg15">
                                    <label class="form-check-label" for="sdg15">SDG 15 -  Life on Land</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="16" id="sdg16">
                                    <label class="form-check-label" for="sdg16">SDG 16 -  Peace, Justice and Strong Institutions</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sdgs[]" value="17" id="sdg17">
                                    <label class="form-check-label" for="sdg17">SDG 17 -  Partnerships for the Goals</label>
                                </div>
                            </div>
                        </div>   
                    </div>               
                </div> 

                <div class="proposal-page">

                    <h2>OTHER FORMS</h2>

                    <div class="accordion" id="proposalAccordion">
                        
                        <!-- Proposed Budget -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingBudget">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBudget" aria-expanded="true" aria-controls="collapseBudget">
                                    Proposed Budget
                                </button>
                            </h2>
                            <div id="collapseBudget" class="accordion-collapse collapse show" aria-labelledby="headingBudget" data-bs-parent="#proposalAccordion">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table caption-top table-bordered" id="budgetTable">
                                            <thead>
                                                <tr>
                                                    <th>Particular</th>
                                                    <th>Amount</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="budget_particular[]" placeholder="Enter Particular (e.g., Food)"></td>
                                                    <td><input type="number" class="form-control amount" name="budget_amount[]" placeholder="Enter Amount"></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="addRowBudget">Add Particular</button>
                                    <div class="mt-3">
                                        <h6>Total Amount: ₱<span id="totalAmount">0.00</span></h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Related Syllabus/Curriculum -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingSyllabus">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapseSyllabus" aria-expanded="false" aria-controls="collapseSyllabus">
                                    Related Syllabus/Curriculum
                                </button>
                            </h2>
                            <div id="collapseSyllabus" class="accordion-collapse collapse" aria-labelledby="headingSyllabus" data-bs-parent="#proposalAccordion">
                                <div class="accordion-body">
                                    <!-- Wrapper div to hide just the table -->
                                    <div id="syllabusTableContainer">
                                        <div class="table-responsive">
                                            <table class="table caption-top table-bordered" id="syllabusTable">
                                                <thead>
                                                    <tr>
                                                        <th>Subject/Course</th>
                                                        <th>Topic</th>
                                                        <th>Relevance to Activity</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><input type="text" class="form-control" name="syllabus_subject[]" placeholder="Enter Subject/Course"></td>
                                                        <td><input type="text" class="form-control" name="syllabus_topic[]" placeholder="Enter Topic"></td>
                                                        <td><input type="text" class="form-control" name="syllabus_relevance[]" placeholder="Enter Relevance"></td>
                                                        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <button type="button" class="btn btn-primary btn-sm" id="addRowSyllabus">Add Entry</button>
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
                            <div id="collapseProgram" class="accordion-collapse collapse" aria-labelledby="headingProgram" data-bs-parent="#proposalAccordion">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table caption-top table-bordered" id="programTable">
                                            <thead>
                                                <tr>
                                                    <th>Program</th>
                                                    <th>Detail</th>
                                                    <th>Person-in-Charge</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="program_name[]" placeholder="Enter Program"></td>
                                                    <td><input type="text" class="form-control" name="program_detail[]" placeholder="Enter Detail"></td>
                                                    <td><input type="text" class="form-control" name="program_person[]" placeholder="Enter Person-in-Charge"></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="addRowProgram">Add Entry</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manpower Requirements -->
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingManpower">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseManpower" aria-expanded="false" aria-controls="collapseManpower">
                                    Manpower Requirements
                                </button>
                            </h2>
                            <div id="collapseManpower" class="accordion-collapse collapse" aria-labelledby="headingManpower" data-bs-parent="#proposalAccordion">
                                <div class="accordion-body">
                                    <div class="table-responsive">
                                        <table class="table caption-top table-bordered" id="manpowerTable">
                                            <thead>
                                                <tr>
                                                    <th>Role/Position</th>
                                                    <th>Name</th>
                                                    <th>Responsibilities</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td><input type="text" class="form-control" name="manpower_role[]" placeholder="Enter Role/Position"></td>
                                                    <td><input type="text" class="form-control" name="manpower_name[]" placeholder="Enter Name"></td>
                                                    <td><input type="text" class="form-control" name="manpower_responsibilities[]" placeholder="Enter Responsibilities"></td>
                                                    <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="addRowManpower">Add Entry</button>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                            
                    <h2>PREPARED & NOTED BY:</h2>
                    <div class="row mb3">
                        <div class="col">
                            <label for="org_name" class="form-label">Name of the Organization/Department</label>
                            <input type="text" class="form-control" id="org_name" name="org_name" 
                                value="<?php echo isset($_SESSION['department']) ? htmlspecialchars($_SESSION['department']) : ''; ?>" 
                                required>
                        </div>
                        <div class="col">
                            <label for="org_president" class="form-label">Organization/Department President</label>
                            <input type="text" class="form-control" id="org_president" name="org_president" 
                                value="<?php echo isset($_SESSION['first_name']) && isset($_SESSION['last_name']) ? htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : ''; ?>" 
                                required>
                        </div>
                    </div>

                    
                    <h2>SELECT SIGNATORIES:</h2>
                    
                    
                    <div class="row mb-3">
                        <div class="col">
                            <label for="Adviser/Moderator" class="form-label">Adviser/Moderator</label>
                            <input type="text" id="Adviser/Moderator" name="Adviser/Moderator" class="form-control" 
                                value="<?php echo isset($_SESSION['moderator_name']) ? htmlspecialchars($_SESSION['moderator_name']) : 'No moderator found'; ?>" 
                                required>
                        </div>

                        <div class="col">
                            <label for="Dean/Department Head" class="form-label">Dean/Department Head</label>
                            <select id="Dean/Department Head" name="Dean/Department Head" class="form-select form-select-sm">
                                <option value="">- Select Dean/Department Head -</option>
                                <?php foreach ($deans as $dean): ?>
                                    <option value="<?php echo htmlspecialchars($dean['first_name'] . ' ' . $dean['last_name']); ?>">
                                        <?php echo htmlspecialchars($dean['first_name'] . ' ' . $dean['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="NoDeanNeeded">No Dean Needed</option> <!-- Add this option -->
                            </select>
                        </div>
                    </div>

                    <br><br>

                     
                    <!-- Submit Button -->
                    <div class="text-end mt-3 ">
                        <button type="submit" id="submit-btn" class="btn btn-success">Submit</button>
                    </div>
                    
                    
                </div>        
            </form>
        </div>
    </div>

    <script>
     // Venue dropdown & custom input logic
     const venueSelect = document.getElementById('venue_select');
    const venueInput = document.getElementById('venue_input');
    const venueFinal = document.getElementById('venue_final');

    // Update the final venue on change in dropdown
    venueSelect.addEventListener('change', () => {
        if (venueSelect.value !== "") {
            venueFinal.value = venueSelect.value;
            venueInput.value = ""; // clear custom input
        }
    });

    // Update the final venue on typing in custom input
    venueInput.addEventListener('input', () => {
        if (venueInput.value !== "") {
            venueFinal.value = venueInput.value;
            venueSelect.value = ""; // clear select
        }
    });

    // Optional: if editing a saved value
    window.addEventListener('DOMContentLoaded', () => {
        const savedVenue = <?= json_encode($saved_venue ?? '') ?>;

        if (savedVenue) {
            // Check if it's in the dropdown list
            const found = Array.from(venueSelect.options).some(option => {
                if (option.value === savedVenue) {
                    venueSelect.value = savedVenue;
                    venueFinal.value = savedVenue;
                    return true;
                }
            });

            if (!found) {
                venueInput.value = savedVenue;
                venueFinal.value = savedVenue;
            }
        }

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

    });
    </script>


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
                    title: 'Submit Proposal',
                    text: 'Are you sure you want to submit this proposal?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Yes, submit it!',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Submitting...',
                            html: 'Please wait while your proposal is being submitted.',
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