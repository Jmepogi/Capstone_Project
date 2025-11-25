<?php
// This code will execute when the modal is opened
// The proposal ID will be passed via GET parameter or from the parent page

// Determine if this file is being accessed directly (via AJAX) or included
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Function to safely output data
function outputSafe($value, $default = 'Not specified') {
    return htmlspecialchars($value ?: $default);
}

// Helper to explode or return empty array
function explode_or_empty($sep, $str) {
    return !empty($str) ? explode($sep, $str) : [];
}

// Initialize proposal_data
$proposal_data = null;
$audit_logs = [];

// If this is an AJAX request and we need to fetch data separately
if ($isAjaxRequest && isset($_GET['proposal_id'])) {
    $proposal_id = intval($_GET['proposal_id']);
    
    // Check if we have a user_id in the session (required for the query)
    session_start();
    if (!isset($_SESSION['user_id'])) {
        die("User is not logged in.");
    }
    $user_id = $_SESSION['user_id'];
    
    require '../../../config/system_db.php'; // include '../config/system_db.php';
    
    // Use the same query as in proposal_table_faculty.php
    $table = "tbl_proposal";
    // Use a simplified query that just gets the proposal with the given ID
    $sql = "
    SELECT 
        p.proposal_id, 
        p.*, 
        ps.approval_id,
        
       GROUP_CONCAT(DISTINCT sdg.sdg_number ORDER BY sdg.sdg_number SEPARATOR ', ') AS sdg_number,
        GROUP_CONCAT(DISTINCT sdg.sdg_description ORDER BY sdg.sdg_description SEPARATOR ', ') AS sdg_description,
        GROUP_CONCAT(DISTINCT CASE WHEN mvc.mvc_type = 'mission' THEN mvc.mvc_value END ORDER BY mvc.mvc_value SEPARATOR '||') AS mission_values,
        GROUP_CONCAT(DISTINCT CASE WHEN mvc.mvc_type = 'vision' THEN mvc.mvc_value END ORDER BY mvc.mvc_value SEPARATOR '||') AS vision_values,
        GROUP_CONCAT(DISTINCT CASE WHEN mvc.mvc_type = 'core value' THEN mvc.mvc_value END ORDER BY mvc.mvc_value SEPARATOR '||') AS core_values,

        -- Signatory subqueries
        (SELECT GROUP_CONCAT(COALESCE(signatory_role, '') ORDER BY signatory_order ASC SEPARATOR '||')
        FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_roles,
        (SELECT GROUP_CONCAT(COALESCE(signatory_name, '') ORDER BY signatory_order ASC SEPARATOR '||')
        FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_names,
        (SELECT GROUP_CONCAT(COALESCE(signatory_status, '') ORDER BY signatory_order ASC SEPARATOR '||')
        FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_statuses,
        (SELECT GROUP_CONCAT(COALESCE(comments, 'No comment') ORDER BY signatory_order ASC SEPARATOR '||')
        FROM tbl_proposal_signatories WHERE proposal_id = p.proposal_id) AS signatory_comments,

        -- Budget subquery
        (SELECT GROUP_CONCAT(field1 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Budget' AND proposal_id = p.proposal_id) AS budget_particulars,
        (SELECT GROUP_CONCAT(CAST(amount AS DECIMAL(10,2)) ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Budget' AND proposal_id = p.proposal_id) AS budget_amounts,

        -- Syllabus subquery
        (SELECT GROUP_CONCAT(field1 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Syllabus' AND proposal_id = p.proposal_id) AS syllabus_subjects,
        (SELECT GROUP_CONCAT(field2 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Syllabus' AND proposal_id = p.proposal_id) AS syllabus_topics,
        (SELECT GROUP_CONCAT(field3 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Syllabus' AND proposal_id = p.proposal_id) AS syllabus_relevance,

        -- Program subquery
        (SELECT GROUP_CONCAT(field1 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Program' AND proposal_id = p.proposal_id) AS program_names,
        (SELECT GROUP_CONCAT(field2 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Program' AND proposal_id = p.proposal_id) AS program_details,
        (SELECT GROUP_CONCAT(field3 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Program' AND proposal_id = p.proposal_id) AS program_persons,

        -- Manpower subquery
        (SELECT GROUP_CONCAT(field1 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Manpower' AND proposal_id = p.proposal_id) AS manpower_roles,
        (SELECT GROUP_CONCAT(field2 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Manpower' AND proposal_id = p.proposal_id) AS manpower_names,
        (SELECT GROUP_CONCAT(field3 ORDER BY detail_id SEPARATOR '||')
        FROM tbl_proposal_details WHERE category = 'Manpower' AND proposal_id = p.proposal_id) AS manpower_responsibilities

    FROM tbl_proposal AS p
    LEFT JOIN tbl_proposal_sdgs AS sdg ON p.proposal_id = sdg.proposal_id
    LEFT JOIN tbl_mvc AS mvc ON p.proposal_id = mvc.proposal_id
    LEFT JOIN tbl_proposal_signatories AS ps ON p.proposal_id = ps.proposal_id
    WHERE p.proposal_id = ? AND ps.user_id = ?
    GROUP BY 
        p.proposal_id";
    
    $stmt = $connection->prepare($sql);
    if (!$stmt) {
        die("Error preparing proposal query: " . $connection->error);
    }
    
    $stmt->bind_param("ii", $proposal_id, $user_id);
    if (!$stmt->execute()) {
        die("Error executing proposal query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $proposal_data = $result->fetch_assoc();
    $stmt->close();

    // Fetch audit logs - fixed implementation with proper error handling
    if ($proposal_id) {
        try {
            // Create a more efficient query that properly handles the different timestamp fields
            // and ensures consistent field names for your existing UI
            $log_sql = "
            SELECT 
                CONCAT('audit_', al.audit_id) AS log_id,
                al.proposal_id,
                al.action,
                al.old_value,
                al.new_value,
                al.timestamp,
                u1.user_id,
                u1.first_name,
                u1.last_name,
                CAST('audit' AS CHAR) AS log_source
            FROM 
                tbl_audit_log AS al
            LEFT JOIN 
                tbl_users AS u1 ON al.user_id = u1.user_id
            WHERE 
                al.proposal_id = ?
            
            UNION ALL
            
            SELECT 
                CONCAT('proposal_', pl.log_id) AS log_id,
                pl.proposal_id,
                pl.action,
                NULL AS old_value,
                pl.remarks AS new_value,
                pl.created_at AS timestamp,
                u2.user_id,
                u2.first_name,
                u2.last_name,
                CAST('proposal' AS CHAR) AS log_source
            FROM 
                tbl_proposal_logs AS pl
            LEFT JOIN 
                tbl_users AS u2 ON pl.user_id = u2.user_id
            WHERE 
                pl.proposal_id = ?
            
            ORDER BY 
                timestamp DESC
        ";
        
            $stmt = $connection->prepare($log_sql);
            if (!$stmt) {
                throw new Exception("Error preparing log query: " . $connection->error);
            }
            
            $stmt->bind_param("ii", $proposal_id, $proposal_id);
            if (!$stmt->execute()) {
                throw new Exception("Error executing log query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $audit_logs = [];
            while ($row = $result->fetch_assoc()) {
                // Handle NULL user data
                if (empty($row['first_name']) && empty($row['last_name'])) {
                    $row['first_name'] = 'Unknown';
                    $row['last_name'] = 'User';
                }
                $audit_logs[] = $row;
            }
            $stmt->close();
        } 
        catch (Exception $e) {
            // Log error but don't show to user
            error_log("Error fetching audit logs: " . $e->getMessage());
            // Set empty array to prevent null errors in rendering
            $audit_logs = [];
        }
    }

    $connection->close();
}

// Helper function to format dates
function formatDate($dateString) {
    if (!$dateString) return 'Not specified';
    $date = new DateTime($dateString);
    return $date->format('F j, Y, g:i A');
}

// If accessed directly via AJAX, set appropriate headers
if ($isAjaxRequest) {
    header('Content-Type: text/html; charset=utf-8');
}
?>

<style>
.activity-paragraph {
margin: 8px 0 16px; /* Space between paragraphs */
line-height: 1.6; /* Adjust line height for readability */
text-align: justify; /* Align text for a clean block paragraph look */

}
.permit-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 0 0 24px 0;
    padding: 8px 0 16px 0;
    border-bottom: 2px solid #e0e0e0;
}

.permit-logo {
    width: 62px;
    height: 62px;
    object-fit: contain;
}

.permit-label {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.permit-osa {
    font-size: 1.1rem;
    font-weight: 650;
    color: #222;
    letter-spacing: 0.5px;
}

.permit-cefi {
    font-size: 0.95rem;
    color: #555;
    margin-top: -5px;
    letter-spacing: 0.2px;
}

.strong-label {
    font-size: .9rem;
    font-weight: 500;
}

.modal-body {
    padding: 0;
}

.modal-body .row {
    display: flex;
    flex-wrap: nowrap; /* Prevent wrapping of columns */
}

.col-lg-8 {
    overflow-y: auto;
}

.col-lg-4 .sticky-top {
    top: 1rem; /* Space from the top of the modal */
}

.col-lg-8::-webkit-scrollbar {
    width: 6px; /* Optional: style scrollbars */
}

.col-lg-8::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 10px;
}

.col-lg-8::-webkit-scrollbar-thumb:hover {
    background: #888;
}

.timeline {
    position: relative;
    padding-left: 20px;
    border-left: 2px solid #dee2e6;
}

.timeline li::before {
    content: "";
    position: absolute;
    left: -7px;
    top: 8px;
    width: 14px;
    height: 14px;
    background-color:rgb(176, 182, 185); /* Bootstrap primary */
    border-radius: 50%;
    border: 2px solid #fff;
    z-index: 1;
}

.timeline li {
    position: relative;
    padding-left: 20px;
}


</style>
<div class="modal fade" id="SignproposalModal" tabindex="-1" aria-labelledby="SignproposalModal" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            
            <div class="modal-header">
                <h5 class="modal-title" id="#SignproposalModal">Activity Proposal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="forceCloseModal('proposalModal')"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row">
                    <!-- Scrollable Content -->
                    <div class="col-lg-8 overflow-auto"  >
                        <!-- Permit Header Section -->
                    <!-- Permit Header Section -->
                <div class="permit-header">
                    <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="permit-logo">
                    <div class="permit-label">
                        <div class="permit-osa">OFFICE FOR STUDENT AFFAIRS</div>
                        <div class="permit-cefi">CALAYAN EDUCATIONAL FOUNDATION, INC.</div>
                    </div>
                </div>
                <!-- Proposal Details Section -->
                <div class="proposal-section">
                    <div class="mb-3">
                        <h6><strong>Proposal Details</strong></h6>
                    </div>
                    <div class="row">
                        <div class="col">
                            <p class="strong-label">Title/Theme of Proposed Activity/Project:</p> 
                            <p><?= $proposal_data ? outputSafe($proposal_data['title']) : 'Loading...' ?></p> 

                        </div>
                        <div class="col" style="margin-left: 20%;">
                            <p class="strong-label">Student Activity Proposal Type:</p>
                            <p><?= $proposal_data ? outputSafe($proposal_data['type']) : 'Loading...' ?></p>
                        </div>
                    </div>
                    <p class="strong-label">Nature of the Proposed Activity/Project:</p>
                    <p class="activity-paragraph"><?= $proposal_data ? outputSafe($proposal_data['description']) : 'Loading...' ?></p>
                    <p class="strong-label">Beneficiaries:</p>
                    <p class="activity-paragraph"><?= $proposal_data ? outputSafe($proposal_data['beneficiaries']) : 'Loading...' ?></p>
                    <p class="strong-label">Organization's Objectives</p>
                    <p class="activity-paragraph"><?= $proposal_data ? outputSafe($proposal_data['org_obj']) : 'Loading...' ?></p>
                    <p class="strong-label">Activity/Project Objectives:</p>
                    <p class="activity-paragraph"><?= $proposal_data ? outputSafe($proposal_data['act_obj']) : 'Loading...' ?></p>
                    <p class="strong-label">Program Educational Objective(PEO) Targeted by the Proposed Activity:</p>
                    <p class="activity-paragraph"><?= $proposal_data ? outputSafe($proposal_data['peo_obj']) : 'Loading...' ?></p>
                </div>

                <!-- Schedule Section -->
                <div class="proposal-section mt-4">
                    <div class="mb-3">
                        <h6><strong>Schedule, Location & Participants</strong></h6>
                    </div>
                    <p>Activity Setting: <?= $proposal_data ? outputSafe($proposal_data['campus_act']) : 'Loading...' ?> / <?= $proposal_data ? outputSafe($proposal_data['place_act']) : '' ?></p>
                    <p>Date and Time Start: <?= $proposal_data ? formatDate($proposal_data['datetime_start']) : 'Loading...' ?></p>
                    <p>Date and Time End: <?= $proposal_data ? formatDate($proposal_data['datetime_end']) : 'Loading...' ?></p>
                    <p>Venue: <?= $proposal_data ? outputSafe($proposal_data['venue']) : 'Loading...' ?></p>
                    <p>Expected Number of Participants: <?= $proposal_data ? outputSafe($proposal_data['participants_num']) : 'Loading...' ?></p>
                </div>

                <!-- SDG Section as List -->
                <div class="proposal-section mt-4">
                    <h6><strong>SDG</strong></h6>
                    <div>
                        <ul class="list-unstyled mb-0">
                            <?php if ($proposal_data && !empty($proposal_data['sdg_number'])): 
                                $sdg_numbers = explode(', ', $proposal_data['sdg_number']);
                                $sdg_descriptions = explode(', ', $proposal_data['sdg_description']);
                                foreach ($sdg_numbers as $i => $num): ?>
                                    <li>SDG <?= outputSafe($num) ?>: <?= outputSafe($sdg_descriptions[$i] ?? '') ?></li>
                                <?php endforeach;
                            else: ?>
                                <li>No SDGs specified</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- MVC Section as List -->
                <div class="proposal-section mt-4">
                   <div class="mvc-section" style="display: flex; flex-wrap: wrap; gap: 20px;">
                        <?php
                        // Mission values
                        if ($proposal_data && !empty($proposal_data['mission_values'])): ?>
                            <div style="flex: 1 1 100%; min-width: 250px;">
                                <div class="mvc-type-header">
                                    <strong>Institutional Mission</strong><br>
                                </div>
                                <ul class="mvc-values-list" style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">
                                    <?php foreach (explode_or_empty('||', $proposal_data['mission_values']) as $value): ?>
                                        <li><?= outputSafe($value) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif;

                        // Vision values
                        if ($proposal_data && !empty($proposal_data['vision_values'])): ?>
                            <div style="flex: 1 1 100%; min-width: 250px;">
                                <div class="mvc-type-header">
                                    <strong>Institutional Vision</strong>
                                </div>
                                <ul class="mvc-values-list" style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">
                                    <?php foreach (explode_or_empty('||', $proposal_data['vision_values']) as $value): ?>
                                        <li><?= outputSafe($value) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif;

                        // Core values
                        if ($proposal_data && !empty($proposal_data['core_values'])): ?>
                            <div style="flex: 1 1 100%; min-width: 250px;">
                                <div class="mvc-type-header">
                                    <strong>Institutional Values</strong><br>
                                </div>
                                <ul class="mvc-values-list" style="list-style-type: disc; padding-left: 20px; margin-top: 5px;">
                                    <?php foreach (explode_or_empty('||', $proposal_data['core_values']) as $value): ?>
                                        <li><?= outputSafe($value) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="proposal-section mt-4">
                    <h6><strong>Source of Fund</strong></h6>
                    <p><?= $proposal_data ? outputSafe($proposal_data['source_fund']) : 'Loading...' ?></p>
                </div>

                <!-- Budget Section -->
                <div class="mb-4">
                    <h6><strong>Budget Details</strong></h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Particular</th>
                                    <th class="text-end" style="width: 200px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($proposal_data) {
                                    $parts = explode_or_empty('||', $proposal_data['budget_particulars']);
                                    $amts = explode_or_empty('||', $proposal_data['budget_amounts']);
                                    $totalAmount = 0; // Initialize total amount variable
                                    
                                    if (!empty($parts)):
                                        foreach ($parts as $i => $part): 
                                            $amount = floatval($amts[$i] ?? 0);
                                            $totalAmount += $amount; // Add the amount to the total
                                ?>
                                    <tr>
                                        <td><?= outputSafe($part) ?></td>
                                        <td class="text-end">₱<?= number_format($amount, 2) ?></td>
                                    </tr>
                                <?php endforeach; 
                                    if ($totalAmount > 0): ?>
                                        <tr>
                                            <td><strong>Total</strong></td>
                                            <td class="text-end"><strong>₱<?= number_format($totalAmount, 2) ?></strong></td>
                                        </tr>
                                    <?php endif;
                                    else: ?>
                                    <tr><td colspan="2" class="text-center">No budget details available</td></tr>
                                <?php endif;
                                } else { ?>
                                    <tr><td colspan="2" class="text-center">Loading...</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Syllabus Section -->
                <div class="mb-4">
                    <h6><strong>Syllabus Details</strong></h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Subject</th>
                                    <th style="width: 45%;">Topics</th>
                                    <th style="width: 30%;">Relevance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($proposal_data) {
                                    $subs = explode_or_empty('||', $proposal_data['syllabus_subjects']);
                                    $tops = explode_or_empty('||', $proposal_data['syllabus_topics']);
                                    $rels = explode_or_empty('||', $proposal_data['syllabus_relevance']);
                                    if (!empty($subs)):
                                        foreach ($subs as $i => $sub): ?>
                                        <tr>
                                            <td><?= outputSafe($sub) ?></td>
                                            <td><?= outputSafe($tops[$i] ?? '') ?></td>
                                            <td><?= outputSafe($rels[$i] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; 
                                    else: ?>
                                        <tr><td colspan="3" class="text-center">Not required in this proposal</td></tr>
                                    <?php endif;
                                } else { ?>
                                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Program Flow Section -->
                <div class="mb-4">
                <h6><strong>Program Flow</strong></h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Activity</th>
                                    <th style="width: 45%;">Details</th>
                                    <th style="width: 30%;">Person In-Charge</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($proposal_data) {
                                    $pnames = explode_or_empty('||', $proposal_data['program_names']);
                                    $pdetails = explode_or_empty('||', $proposal_data['program_details']);
                                    $ppersons = explode_or_empty('||', $proposal_data['program_persons']);
                                    if (!empty($pnames)):
                                        foreach ($pnames as $i => $pname): ?>
                                        <tr>
                                            <td><?= outputSafe($pname) ?></td>
                                            <td><?= outputSafe($pdetails[$i] ?? '') ?></td>
                                            <td><?= outputSafe($ppersons[$i] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; 
                                    else: ?>
                                        <tr><td colspan="3" class="text-center">No program flow available</td></tr>
                                    <?php endif;
                                } else { ?>
                                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Manpower Section -->
                <div class="mb-4">
                    <h6><strong>Manpower Details</strong></h6>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Role</th>
                                    <th style="width: 45%;">Name</th>
                                    <th style="width: 30%;">Responsibilities</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($proposal_data) {
                                    $mroles = explode_or_empty('||', $proposal_data['manpower_roles']);
                                    $mnames = explode_or_empty('||', $proposal_data['manpower_names']);
                                    $mresps = explode_or_empty('||', $proposal_data['manpower_responsibilities']);
                                    if (!empty($mroles)):
                                        foreach ($mroles as $i => $mrole): ?>
                                        <tr>
                                            <td><?= outputSafe($mrole) ?></td>
                                            <td><?= outputSafe($mnames[$i] ?? '') ?></td>
                                            <td><?= outputSafe($mresps[$i] ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; 
                                    else: ?>
                                        <tr><td colspan="3" class="text-center">No manpower details available</td></tr>
                                    <?php endif;
                                } else { ?>
                                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Participants & Organization Section -->
                <div class="proposal-section mt-4">
                    <h6><strong>Prepared by</strong></h6>
                    <p>Organization: <?= $proposal_data ? outputSafe($proposal_data['organization']) : 'Loading...' ?></p>
                    <p>President: <?= $proposal_data ? outputSafe($proposal_data['president']) : 'Loading...' ?></p>
                </div>

              <!-- Evaluated & Signed by Section -->
                <div class="proposal-section mt-4">
                    <h6><strong>Evaluated & Signed by</strong></h6>
                    <div class="table-responsive">
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: 30%; text-align: left;">Name</th>
                                    <th style="width: 30%; text-align: center;">Status</th>
                                    <th style="width: 40%; text-align: left;">Comments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($proposal_data) {
                                    $snames = explode_or_empty('||', $proposal_data['signatory_names']);
                                    $sroles = explode_or_empty('||', $proposal_data['signatory_roles']);
                                    $sstatuses = explode_or_empty('||', $proposal_data['signatory_statuses']);
                                    $scomments = explode_or_empty('||', $proposal_data['signatory_comments']);
                                    
                                    if (!empty($snames)):
                                        foreach ($snames as $i => $sname): ?>
                                        <tr>
                                            <td>
                                                <?= outputSafe($sname) ?><br>
                                                <small><?= outputSafe($sroles[$i] ?? '') ?></small>
                                            </td>
                                            <td style="text-align: center;"><?= outputSafe($sstatuses[$i] ?? '') ?></td>
                                            <td><?= outputSafe($scomments[$i] ?? 'No comment') ?></td>
                                        </tr>
                                    <?php endforeach; 
                                    else: ?>
                                        <tr><td colspan="3" class="text-center">No signatories available</td></tr>
                                    <?php endif;
                                } else { ?>
                                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>


                </div>

                    <!-- Fixed Signatory Form + Audit Log (All inside sticky-top box) -->
                <div class="col-lg-4 mb-5">
                    <div class="sticky-top p-3 bg-light border rounded">

                        <!-- Signatory Form -->
                        <h6><strong>Signatory Actions</strong></h6>
                        <form id="signatoryForm" method="POST">
                            <input type="hidden" id="approvalId" name="approval_id" value="">
                            <input type="hidden" id="proposalId" name="proposal_id" value="">

                            <div class="mb-3">
                                <label for="signatoryStatus" class="form-label">Status</label>
                                <select id="signatoryStatus" name="signatory_status" class="form-select" required>
                                    <option value="Approved">Approve</option>
                                    <option value="Denied">Deny</option>
                                    <option value="Revise">Revise</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="comments" class="form-label">Comments</label>
                                <textarea id="comments" name="comments" class="form-control" rows="4" placeholder="Add your comments here..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Submit</button>
                        </form><br><br>

                        <!-- Audit Log Header -->
                        <h6><strong>Proposal Audit Log</strong></h6>
                        <div class="mb-4" style="max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($audit_logs)): ?>
                                <ul class="timeline list-unstyled">
                                    <?php foreach ($audit_logs as $log): ?>
                                        <li class="mb-4">
                                            <div>
                                                <strong><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></strong>
                                                <?= htmlspecialchars($log['action']) ?>

                                               

                                                <div class="text-muted small">
                                                    <?= (new DateTime($log['timestamp']))->format('F j, Y g:i A') ?>
                                                </div>

                                                <!-- Show old and new values -->
                                                <div class="mt-2">
                                                    <?php if ($log['log_source'] === 'audit'): ?>
                                                        <?php if (!empty($log['old_value'])): ?>
                                                            <div><strong>Old Value:</strong> <?= nl2br(htmlspecialchars($log['old_value'])) ?></div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($log['new_value'])): ?>
                                                            <div><strong>New Value:</strong> <?= nl2br(htmlspecialchars($log['new_value'])) ?></div>
                                                        <?php endif; ?>
                                                    <?php elseif ($log['log_source'] === 'proposal' && !empty($log['new_value'])): ?>
                                                        <div><strong>Remarks:</strong> <?= nl2br(htmlspecialchars($log['new_value'])) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No audit logs found for this proposal.</p>
                            <?php endif; ?>
                        </div>




                    </div>
                </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="forceCloseModal('SignproposalModal')"> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include the shared modal close functions -->
<script src="../resources/js/closefunction.js"></script>

<?php if (!$isAjaxRequest): ?>
<script>
// This script is only needed when the file is included directly in a page
// For AJAX requests, we'll use the JS in the parent page
if (!isset($_GET['proposal_id'])) {
    // Only attach click handlers if this is not an AJAX request
    document.addEventListener('DOMContentLoaded', function() {
        // Get all buttons that open this modal
        const buttons = document.querySelectorAll('button[data-bs-target="#SignproposalModal"]');
        
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const proposalId = this.getAttribute('data-proposal-id');
                if (proposalId) {
                    // For included mode, we'll use an AJAX request to load the content
                    // This will be handled by the parent page's JavaScript
                }
            });
        });
    });
}
</script>
<?php endif; ?>




