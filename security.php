<?php
// security.php

// Function to escape HTML for output to prevent XSS
function escapeHTML($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Start output buffering to apply XSS protection across the page output
ob_start(function ($buffer) {
    return htmlspecialchars($buffer, ENT_QUOTES, 'UTF-8');
});

?>
