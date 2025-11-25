document.addEventListener('DOMContentLoaded', function() {
    // Common function to handle row removal
    function handleRemoveRow(e) {
        if (e.target.classList.contains('remove-row')) {
            const row = e.target.closest('tr');
            const table = row.closest('table');
            row.remove();
            
            // Update total if it's the budget table
            if (table.id === 'budgetTable') {
                updateTotalAmount();
            }
        }
    }

    // Budget Table Management
    const budgetTable = document.getElementById('budgetTable');
    if (budgetTable) {
        document.getElementById('addRowBudget').addEventListener('click', function() {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" class="form-control" name="budget_particular[]" placeholder="Enter Particular (e.g., Food)" required></td>
                <td><input type="number" class="form-control amount" name="budget_amount[]" placeholder="Enter Amount" required></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            `;
            budgetTable.querySelector('tbody').appendChild(newRow);
        });

        budgetTable.querySelector('tbody').addEventListener('click', handleRemoveRow);
        
        // Handle amount changes and total calculation
        budgetTable.addEventListener('input', function(e) {
            if (e.target.classList.contains('amount')) {
                updateTotalAmount();
            }
        });
    }

    // Syllabus Table Management
    const syllabusTable = document.getElementById('syllabusTable');
    if (syllabusTable) {
        document.getElementById('addRowSyllabus').addEventListener('click', function() {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" class="form-control" name="syllabus_subject[]" placeholder="Enter Subject/Course" required></td>
                <td><input type="text" class="form-control" name="syllabus_topic[]" placeholder="Enter Topic" required></td>
                <td><input type="text" class="form-control" name="syllabus_relevance[]" placeholder="Enter Relevance" required></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            `;
            syllabusTable.querySelector('tbody').appendChild(newRow);
        });

        syllabusTable.querySelector('tbody').addEventListener('click', handleRemoveRow);
    }

    // Program Table Management
    const programTable = document.getElementById('programTable');
    if (programTable) {
        document.getElementById('addRowProgram').addEventListener('click', function() {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" class="form-control" name="program_name[]" placeholder="Enter Program" required></td>
                <td><input type="text" class="form-control" name="program_detail[]" placeholder="Enter Detail" required></td>
                <td><input type="text" class="form-control" name="program_pic[]" placeholder="Enter Person-in-Charge" required></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            `;
            programTable.querySelector('tbody').appendChild(newRow);
        });

        programTable.querySelector('tbody').addEventListener('click', handleRemoveRow);
    }

    // Manpower Table Management
    const manpowerTable = document.getElementById('manpowerTable');
    if (manpowerTable) {
        document.getElementById('addRowManpower').addEventListener('click', function() {
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" class="form-control" name="manpower_role[]" placeholder="Enter Role/Position" required></td>
                <td><input type="text" class="form-control" name="manpower_name[]" placeholder="Enter Name" required></td>
                <td><input type="text" class="form-control" name="manpower_responsibilities[]" placeholder="Enter Responsibilities" required></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            `;
            manpowerTable.querySelector('tbody').appendChild(newRow);
        });

        manpowerTable.querySelector('tbody').addEventListener('click', handleRemoveRow);
    }

    // Function to update total amount in budget table
    function updateTotalAmount() {
        const amounts = document.querySelectorAll('#budgetTable .amount');
        let total = 0;
        amounts.forEach(input => {
            const value = parseFloat(input.value) || 0;
            total += value;
        });
        document.getElementById('totalAmount').textContent = total.toFixed(2);
    }

    // Initialize total amount on page load
    updateTotalAmount();


    const othersCheckbox = document.getElementById("othersCheckbox");
    const othersInput = document.getElementById("othersInput");

    function toggleOthersInput() {
        if (!othersInput) return;
        
        // Set initial display style
        othersInput.style.display = "none";
        
        if (othersCheckbox?.checked) {
            othersInput.style.display = "block";
            othersInput.required = true;
        } else {
            othersInput.style.display = "none";
            othersInput.required = false;
            // Only clear the value if it's being hidden
            if (othersInput.style.display === "none") {
                othersInput.value = "";
            }
        }
    }

    if (othersCheckbox && othersInput) {
        // Add event listener to checkbox
        othersCheckbox.addEventListener("change", toggleOthersInput);

        // Set initial state
        toggleOthersInput();
    }
});
