<?php
require_once __DIR__ . '/../resources/fpdf.php';

require '../config/system_db.php'; // or include '../config/system_db.php';

// Get evaluation_id from GET
$evaluation_id = isset($_GET['evaluation_id']) ? intval($_GET['evaluation_id']) : 0;
if ($evaluation_id <= 0) {
    die('Invalid evaluation ID.');
}

// Fetch evaluation and proposal details
$stmt = $connection->prepare("SELECT e.name, p.title, p.type, p.datetime_start, p.datetime_end FROM tbl_evaluation e JOIN tbl_proposal p ON e.proposal_id = p.proposal_id WHERE e.evaluation_id = ?");
$stmt->bind_param("i", $evaluation_id);
$stmt->execute();
$stmt->bind_result($participant_name, $event_title, $event_type, $date_start, $date_end);
if (!$stmt->fetch()) {
    die('Certificate data not found.');
}
$stmt->close();
$connection->close();

// Format event date
$event_date = ($date_start == $date_end) 
    ? date('F j, Y', strtotime($date_start)) 
    : (date('F j', strtotime($date_start)) . '-' . date('j, Y', strtotime($date_end)));

// School details
$school_name = "CALAYAN EDUCATIONAL FOUNDATION, INC.";
$logo_path = __DIR__ . '/../images/login/cefi-logo.png';

// Create PDF
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// Add background image
$pdf->Image(__DIR__ . '/../images/certificate_bg.png', 0, 0, 297, 210);

// Logo - moved up a bit
$logo_x = 20;
$logo_y = 10; // Changed from 15 to 10 to move up
$logo_w = 30;
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, $logo_x, $logo_y, $logo_w);
}

// School Name with adjusted styling and color
$pdf->SetXY($logo_x + $logo_w + 2, $logo_y + 3); // Adjusted Y position to align better
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(91, 94, 94); // Changed to your requested color
$pdf->Cell(0, 10, $school_name, 0, 1, 'L');

// Office for Student Affairs - added below school name
$pdf->SetXY($logo_x + $logo_w + 2, $logo_y + 10); // Position below school name
$pdf->SetFont('Arial', '', 14); // Smaller font
$pdf->SetTextColor(91, 94, 94); // Same color as school name
$pdf->Cell(0, 10, 'Office for Student Affairs', 0, 1, 'L');

// Adjusted spacing to ensure everything fits on one page
$pdf->Ln(25); // Reduced space after header (was 30)

// Certificate title with improved styling
$pdf->SetFont('Arial', 'B', 36);
$pdf->SetTextColor(0, 51, 102); // Navy blue
$pdf->Cell(0, 20, 'CERTIFICATE OF PARTICIPATION', 0, 1, 'C');
$pdf->Ln(2); // Reduced spacing (was 5)

// Awarded to with italic styling
$pdf->SetFont('Arial', 'I', 16);
$pdf->SetTextColor(80, 80, 80); // Dark gray
$pdf->Cell(0, 10, 'is hereby awarded to', 0, 1, 'C');
$pdf->Ln(2); // Reduced spacing (was 5)

// Participant name with improved styling
$pdf->SetFont('Arial', 'B', 30);
$pdf->SetTextColor(0, 51, 102); // Navy blue
$pdf->Cell(0, 20, $participant_name, 0, 1, 'C');
$pdf->Ln(2); // Reduced spacing (was 5)

// Certificate body with improved styling
$pdf->SetFont('Arial', '', 14);
$pdf->SetTextColor(50, 50, 50); // Dark gray
$body_text = "in grateful recognition of their active participation during the";
$pdf->Cell(0, 10, $body_text, 0, 1, 'C');

// Event title with emphasis
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 102, 0); // Dark green
$pdf->Cell(0, 10, "\"$event_title\"", 0, 1, 'C');

// Event date
$pdf->SetFont('Arial', '', 14);
$pdf->SetTextColor(50, 50, 50); // Dark gray
$pdf->Cell(0, 10, "held on $event_date.", 0, 1, 'C');
$pdf->Ln(8); // Reduced spacing (was 10)

// Date issued with improved styling
$pdf->SetFont('Arial', 'I', 12);
$pdf->Cell(0, 10, 'Given this ' . date('jS \d\a\y \o\f F, Y'), 0, 1, 'C');
$pdf->Ln(5);

// Footer: system-generated note - ensuring it stays on the first page
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(130, 130, 130); // Light gray
// Calculate Y position to ensure it's on the first page
// A4 landscape is 210mm high, leave margin of 15mm from bottom
$footer_y = 180; 
$pdf->SetY($footer_y);
$pdf->Cell(0, 8, 'This certificate is system-generated and does not require a signature.', 0, 1, 'C');

// Output PDF
$pdf->Output('D', 'certificate.pdf');
exit; 