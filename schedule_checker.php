<div class="proposal-page">
    <form action="../resources/utilities/functions/sched_checker.php" method="POST">
        <div class="permit-header">
            <img src="../images/login/cefi-logo.png" alt="cefi-logo" class="permit-logo">                       
            <div>
                <h1 class="permit-osa">OFFICE FOR STUDENT AFFAIRS</h1>
                <p class="permit-cefi">CALAYAN EDUCATIONAL FOUNDATION, INC.</p>
            </div>
        </div>
        <h2>SCHEDULE CHECKER</h2>

        <?php
        // Check if the conflict flash message exists
        if (isset($_SESSION['conflict_flash_message'])) {
            $conflict_flash_message = $_SESSION['conflict_flash_message'];
            unset($_SESSION['conflict_flash_message']); // Clear the message after displaying it

            // Define inline styles based on message type
            $backgroundColor = '';
            $textColor = '';

            switch ($conflict_flash_message['type']) {
                case 'success':
                    $backgroundColor = '#d4edda';
                    $textColor = '#155724';
                    break;
                case 'warning':
                    $backgroundColor = '#fff3cd';
                    $textColor = '#856404';
                    break;
                case 'danger':
                    $backgroundColor = '#f8d7da';
                    $textColor = '#721c24';
                    break;
                default:
                    $backgroundColor = '#f8f9fa';
                    $textColor = '#212529';
                    break;
            }
            ?>
            <div class="alert d-flex align-items-center ms-3" role="alert"
                style="background-color: <?= $backgroundColor ?>; color: <?= $textColor ?>;">
                <span class="material-symbols-outlined me-2">
                    <?= $conflict_flash_message['type'] === 'success' ? 'check_circle' : 'error' ?>
                </span>
                <div><?= htmlspecialchars($conflict_flash_message['message']) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php
        }
        ?>


        <!-- Date and Time Fields -->
        <div class="row mb-3">
            <div class="col">
                <label for="datetime_start" class="form-label">Date and Time Start</label>
                <input type="datetime-local" class="form-control" id="datetime_start" name="datetime_start" required>
            </div>
            <div class="col">
                <label for="datetime_end" class="form-label">Date and Time End</label>
                <input type="datetime-local" class="form-control" id="datetime_end" name="datetime_end" required>
            </div>
        </div>

        <!-- Venue Selection -->
        <div class="row mb-3">
            <div class="col">
                <label for="venue" class="form-label">Venue</label>
                
                <div class="input-group">
                    <select id="venue" name="venue" class="form-select">
                        <option value="">- Select Venue -</option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?php echo htmlspecialchars($venue['venue_name']); ?>">
                                <?php echo htmlspecialchars($venue['venue_name'] . ' - ' . $venue['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <!-- Submit Button -->
        <div class="text-end mt-3 ">
            <button type="submit" class="btn btn-success">Check</button>
        </div>
    </form>
</div>
