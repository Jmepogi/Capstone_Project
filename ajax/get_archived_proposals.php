<?php

require '../config/system_db.php'; // or include '../config/system_db.php';

// Fetch all archived proposals (is_deleted = 1)
$sql = "SELECT 
        proposal_id, 
        title, 
        type, 
        organization, 
        president, 
        submitted_at, 
        status,
        modified_at AS archived_at
    FROM tbl_proposal 
    WHERE is_deleted = 1 
    ORDER BY proposal_id DESC";

$result = $connection->query($sql);

// Check if query was successful
if (!$result) {
    die(json_encode([
        'error' => true,
        'message' => 'Query failed: ' . $connection->error
    ]));
}

// Convert results to array
$archivedProposals = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $archivedProposals[] = $row;
    }
}

// Close connection
$connection->close();

// Set headers for JSON response
header('Content-Type: application/json');

// Return JSON response
echo json_encode($archivedProposals); 