<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../images/login/cefi-logo.png" type="image/png">
    <link rel="stylesheet" href="../resources/css/loginn.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://getbootstrap.com/docs/5.3/assets/css/docs.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <title>OSA Login</title>


    <style>
        .manual-container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .step {
            margin-bottom: 30px;
            padding: 20px;
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .step h3 {
            color: #28a745;
            margin-bottom: 15px;
        }
        .back-button {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <header class="cefi-header">
        <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="logo img-fluid">
        <div>
            <h1 class="osa">OFFICE FOR STUDENT AFFAIRS</h1>
            <p class="system">Management Information System</p>
        </div>
    </header>

    <div class="manual-container"><br><br>
    <a href="login.php" class="btn btn-secondary back-button">← Back to Login</a>
        
        <h1 class="mb-4">Student Manual: How to Make a Proposal</h1>
        
        <div class="step">
            <h3>Step 1: Login to Your Account</h3>
            <p>Access the system using your student credentials (username and password).</p>
             <img src="../images/login.png" alt="Login" class="img-fluid mb-3 d-block mx-auto" style="max-width:1100px; width:100%; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        </div>

        <div class="step">
            <h3>Step 2: Navigate to Proposal Creation</h3>
            <p>Complete all required fields in the proposal form:</p>
            <ul>
                <li>Fill Out the Proposal Form</li>
                <li>Proposal Title</li>
                <li>Project Description</li>
                <li>Objectives</li>
                <li>Timeline</li>
                <li>Budget (if applicable)</li>
                <li>Required Resources</li>
            </ul>
            <img src="../images/create_proposal.png" alt="Create Proposal Screenshot" class="img-fluid mb-3 d-block mx-auto" style="max-width:1100px; width:100%; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            
            <p>After logging in, go to the "Create New Proposal" section in your dashboard.</p>
            <img src="../images/proposal.png" alt="Proposal" class="img-fluid mb-3 d-block mx-auto" style="max-width:1100px; width:100%; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
        </div>

        <div class="step">
            <h3>Step 3: Review and Submit</h3>
            <p>Before submitting:</p>
            <img src="../images/submit.png" alt="submit" class="img-fluid mb-3 d-block mx-auto" style="max-width:1100px; width:100%; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <ul>
                <li>Review all entered information</li>
                <li>Check for any errors or missing information</li>
                <li>Ensure all required documents are attached</li>
                <li>Click the "Submit Proposal" button</li>
            </ul>
        </div>

        <div class="step">
            <h3>Step 4: Track Your Proposal</h3>
            <p>After submission, you can:</p>
            <img src="../images/view.png" alt="view" class="img-fluid mb-3 d-block mx-auto" style="max-width:1100px; width:100%; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <ul>
                <li>Monitor the status of your proposal</li>
                <li>View feedback from reviewers</li>
                <li>Make revisions if requested</li>
                <li>Check approval status</li>
            </ul>
        </div>

    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
   
    
    <!--  <script src="../resources/js/background.js"></script>   -->
</body>



</html>
