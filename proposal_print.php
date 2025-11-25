<?php
// Start session if needed
if (session_status() === PHP_SESSION_NONE) session_start();

require '../config/system_db.php'; // or include '../config/system_db.php';

$table = "tbl_proposal";

// Get proposal ID from URL
$proposal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$proposal_id) {
    die("No proposal ID provided.");
}


// Fetch proposal data (reuse your inbox query, but filter by proposal_id)
$sql = "
SELECT p.*,
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
WHERE p.proposal_id = ?
GROUP BY p.proposal_id
LIMIT 1

";
$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $proposal_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
$connection->close();
if (!$row) die("Proposal not found.");

// Helper to explode or return empty array
function explode_or_empty($sep, $str) {
    return !empty($str) ? explode($sep, $str) : [];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Proposal</title>
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        @media print {
            @page {
                margin-top: 90px; /* Large top margin for 2nd and subsequent pages */
                margin-right: 20mm;
                margin-bottom: 20mm;
                margin-left: 20mm;
            }
            @page :first {
                margin-top: 5mm; /* Smaller top margin for the first page */
            }
            body { margin: 0; font-size: 10.5pt; background: #fff; }
            .container { width: 100%; max-width: 100%; margin: 0; padding: 0; }
            .proposal-section { page-break-inside: avoid; margin-bottom: 15px; }
            .table { width: 100%; margin-bottom: 1rem; color: #212529; page-break-inside: auto; }
            .table td, .table th { padding: 6px; border: 1px solid #dee2e6; }
            .table thead th { background-color: #f8f9fa; }
            .table-bordered { border: 1px solid #dee2e6; }
            .table-light { background-color: #f8f9fa !important; }
        }

        .proposal-section { margin-bottom: 15px; } /* Adjusted margin */
        .strong-label { font-size: .85rem; font-weight: 500; }
        .activity-paragraph { margin: 8px 0 12px; line-height: 1.5; text-align: justify; } /* Adjusted line height and margin */


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

        .table th, .table td {
            text-align: left;
            padding: 10px;
        }
        .table td small {
            font-size: 0.85rem;
            color: gray;
        }

    </style>
</head>
<body onload="window.print()">
<div class="permit-header">
    <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="permit-logo">
    <div class="permit-label">
        <div class="permit-osa">OFFICE FOR STUDENT AFFAIRS</div>
        <div class="permit-cefi">CALAYAN EDUCATIONAL FOUNDATION, INC.</div>
    </div>
</div>
<div class="container mt-4 mb-4">
    <div class="proposal-section">
        
    <div class="row">
        <div class="col"></div>
        <div class="col text-end">
            <div><span class="activity-paragraph"><strong>Proposed Date: <br></strong></span> <?= htmlspecialchars($row['submitted_at']) ?></div><br><br>
        </div>
    </div>

    <div class="row">
        <div class="col text-start">
            <div class="activity-paragraph"><strong>Title: <br></strong> <?= htmlspecialchars($row['title']) ?></div>
        </div>
        <div class="col text-end">
            <div><span class="activity-paragraph"><strong>Type: <br></strong></pan> <?= htmlspecialchars($row['type']) ?></div>
        </div>
    </div>
    </div>
    <div class="proposal-section">
        <div class="activity-paragraph"><strong>Nature of the Proposed Activity/Project: <br></strong> <?= htmlspecialchars($row['description']) ?></div>
        <div class="activity-paragraph"><strong>Beneficiaries: <br></strong> <?= htmlspecialchars($row['beneficiaries']) ?></div>
        <div class="activity-paragraph">
            <strong>Organization's Objectives:</strong> <br>
            <?= htmlspecialchars($row['org_obj']) ?: 'Not required in this proposal ' ?>
        </div>
        <div class="activity-paragraph">
            <strong>Activity Objectives:</strong> <br>
            <?= htmlspecialchars($row['act_obj']) ?: 'Not required in this proposal ' ?>
        </div>
        <div class="activity-paragraph">
            <strong>Program Educational Objective(PEO) Targeted by the Proposed Activity:</strong> <br>
            <?= htmlspecialchars($row['peo_obj']) ?: 'Not required in this proposal ' ?>
        </div>

    </div>
    <div class="proposal-section">
        <strong>Aligned Sustainable Development Goals (SDGs)</strong><br>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 5px; margin-bottom: 15px;">
            <?php foreach (explode_or_empty(', ', $row['sdg_number']) as $i => $num): ?>
                <div style="width: 48%; margin-bottom: -5px;">
                    <strong>SDG <?= htmlspecialchars($num) ?>:</strong> <?= htmlspecialchars((explode_or_empty(', ', $row['sdg_description'])[$i] ?? '')) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="proposal-section">
        <div id="MVCList" class="mvc-section" style="display: flex; flex-wrap: wrap; gap: 20px;">
            <?php
            // Mission values
            if (!empty($row['mission_values'])): ?>
                <div style="flex: 1 1 100%; min-width: 250px;">
                    <div class="mvc-type-header">
                        <strong>Institutional Mission</strong><br>
                    </div>
                    <ul class="mvc-values-list" style="list-style-type: disc; padding-left: 20px; margin-top: 5px;  display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <?php foreach (explode_or_empty('||', $row['mission_values']) as $value): ?>
                            <li style="margin-bottom: -10px;"><?= htmlspecialchars($value) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;

            // Vision values
            if (!empty($row['vision_values'])): ?>
                <div style="flex: 1 1 100%; min-width: 250px;">
                    <div class="mvc-type-header">
                        <strong>InstitutionalVision</strong>
                    </div>
                    <ul class="mvc-values-list" style="list-style-type: disc; padding-left: 20px; margin-top: 5px;  display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <?php foreach (explode_or_empty('||', $row['vision_values']) as $value): ?>
                            <li style="margin-bottom: -10px;"><?= htmlspecialchars($value) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif;

            // Core values
            if (!empty($row['core_values'])): ?>
                <div style="flex: 1 1 100%; min-width: 250px;">
                    <div class="mvc-type-header">
                        <strong>Institutional Values</strong><br>
                    </div>
                    <ul class="mvc-values-list" style="list-style-type: disc; padding-left: 20px; margin-top: 5px;  display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <?php foreach (explode_or_empty('||', $row['core_values']) as $value): ?>
                            <li style="margin-bottom: -10px;"><?= htmlspecialchars($value) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>


    <div class="proposal-section">
        <strong>Budget Details</strong>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr><th style="width: 25%;">Particular</th><th style="width: 25%;" class="text-end">Amount</th></tr>
            </thead>
            <tbody>
                <?php 
                    $parts = explode_or_empty('||', $row['budget_particulars']);
                    $amts = explode_or_empty('||', $row['budget_amounts']);
                    $totalAmount = 0; // Initialize total amount variable
                    
                    if ($parts):
                        foreach ($parts as $i => $part): 
                            $amount = floatval($amts[$i] ?? 0);
                            $totalAmount += $amount; // Add the amount to the total
                ?>
                    <tr><td style="width: 25%;"><?= htmlspecialchars($part) ?></td>
                        <td style="width: 25%;" class="text-end">₱<?= number_format($amount, 2) ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="2" class="text-center">No budget details available</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($totalAmount > 0): ?>
            <tfoot>
                <tr><td><strong>Total</strong></td>
                    <td class="text-end"><strong>₱<?= number_format($totalAmount, 2) ?></strong></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <div class="proposal-section">
        <strong>Syllabus Details</strong>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr><th style="width: 25%;">Subject</th><th style="width: 25%;">Topics</th><th style="width: 25%;">Relevance</th></tr>
            </thead>
            <tbody>
                <?php $subs = explode_or_empty('||', $row['syllabus_subjects']);
                    $tops = explode_or_empty('||', $row['syllabus_topics']);
                    $rels = explode_or_empty('||', $row['syllabus_relevance']);
                    if ($subs):
                        foreach ($subs as $i => $sub): ?>
                    <tr><td style="width: 25%;"><?= htmlspecialchars($sub) ?></td><td style="width: 25%;"><?= htmlspecialchars($tops[$i] ?? '') ?></td><td style="width: 25%;"><?= htmlspecialchars($rels[$i] ?? '') ?></td></tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" class="text-center">Not required in this proposal</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="proposal-section">
        <strong>Program Flow</strong>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr><th style="width: 25%;">Activity</th><th style="width: 25%;">Details</th><th style="width: 25%;">Person In-Charge</th></tr>
            </thead>
            <tbody>
                <?php $pnames = explode_or_empty('||', $row['program_names']);
                    $pdetails = explode_or_empty('||', $row['program_details']);
                    $ppersons = explode_or_empty('||', $row['program_persons']);
                    if ($pnames):
                        foreach ($pnames as $i => $pname): ?>
                    <tr><td style="width: 25%;"><?= htmlspecialchars($pname) ?></td><td style="width: 25%;"><?= htmlspecialchars($pdetails[$i] ?? '') ?></td><td style="width: 25%;"><?= htmlspecialchars($ppersons[$i] ?? '') ?></td></tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" class="text-center">No program flow available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="proposal-section">
        <strong>Manpower Details</strong>
        <table class="table table-bordered">
            <thead class="table-light">
                <tr><th style="width: 25%;">Role</th><th style="width: 25%;">Name</th><th style="width: 25%;">Responsibilities</th></tr>
            </thead>
            <tbody>
                <?php $mroles = explode_or_empty('||', $row['manpower_roles']);
                    $mnames = explode_or_empty('||', $row['manpower_names']);
                    $mresps = explode_or_empty('||', $row['manpower_responsibilities']);
                    if ($mroles):
                        foreach ($mroles as $i => $mrole): ?>
                    <tr><td style="width: 25%;"><?= htmlspecialchars($mrole) ?></td><td style="width: 25%;"><?= htmlspecialchars($mnames[$i] ?? '') ?></td><td style="width: 25%;"><?= htmlspecialchars($mresps[$i] ?? '') ?></td></tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" class="text-center">No manpower details available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="proposal-section">
        <strong>Prepared  by</strong>
        <div>Organization: <?= htmlspecialchars($row['organization']) ?></div>
        <div>President: <?= htmlspecialchars($row['president']) ?></div>
    </div>
    <div class="proposal-section">
        <strong>Evaluated & Signed by</strong>
        <table class="table table-bordered" >
            <thead >
                <tr>
                    <th style="width: 25%;">Name</th>
                    <th style="width: 25%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $snames = explode_or_empty('||', $row['signatory_names']);
                    $sroles = explode_or_empty('||', $row['signatory_roles']);
                    $sstatuses = explode_or_empty('||', $row['signatory_statuses']);
                    
                    if ($snames):
                        foreach ($snames as $i => $sname): ?>
                    <tr><td style="width: 25%;"><?= htmlspecialchars($sname) ?><br><small><?= htmlspecialchars($sroles[$i] ?? '') ?></small></td><td style="width: 25%;"><?= htmlspecialchars($sstatuses[$i] ?? '') ?></td></tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3" class="text-center">No signatories available</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html> 