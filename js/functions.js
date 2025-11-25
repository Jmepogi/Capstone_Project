// Budget Table Management
document.getElementById('addRowBudget').addEventListener('click', function() {
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" class="form-control" name="budget_particular[]" placeholder="Enter Particular (e.g., Food)"></td>
        <td><input type="number" class="form-control amount" name="budget_amount[]" placeholder="Enter Amount"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
    `;
    
    document.querySelector('#budgetTable tbody').appendChild(newRow);
    updateTotalAmount();
});

document.querySelector('#budgetTable tbody').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
        updateTotalAmount();
    }
});

function updateTotalAmount() {
    const amounts = document.querySelectorAll('.amount');
    let total = 0;

    amounts.forEach(input => {
        const value = parseFloat(input.value) || 0;
        total += value;
    });

    document.getElementById('totalAmount').textContent = total.toFixed(2);
}

// Syllabus Table Management
document.getElementById('addRowSyllabus').addEventListener('click', function() {
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" class="form-control" name="syllabus_subject[]" placeholder="Enter Subject/Course"></td>
        <td><input type="text" class="form-control" name="syllabus_topic[]" placeholder="Enter Topic"></td>
        <td><input type="text" class="form-control" name="syllabus_relevance[]" placeholder="Enter Relevance"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
    `;
    
    document.querySelector('#syllabusTable tbody').appendChild(newRow);
});

document.querySelector('#syllabusTable tbody').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
    }
});

// Program Table Management
document.getElementById('addRowProgram').addEventListener('click', function() {
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" class="form-control" name="program_name[]" placeholder="Enter Program"></td>
        <td><input type="text" class="form-control" name="program_detail[]" placeholder="Enter Detail"></td>
        <td><input type="text" class="form-control" name="program_person[]" placeholder="Enter Person-in-Charge"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
    `;
    
    document.querySelector('#programTable tbody').appendChild(newRow);
});

document.querySelector('#programTable tbody').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
    }
});

// Manpower Table Management
document.getElementById('addRowManpower').addEventListener('click', function() {
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input type="text" class="form-control" name="manpower_role[]" placeholder="Enter Role/Position"></td>
        <td><input type="text" class="form-control" name="manpower_name[]" placeholder="Enter Name"></td>
        <td><input type="text" class="form-control" name="manpower_responsibilities[]" placeholder="Enter Responsibilities"></td>
        <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
    `;
    
    document.querySelector('#manpowerTable tbody').appendChild(newRow);
});

document.querySelector('#manpowerTable tbody').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('tr').remove();
    }
});

// Event listener for input changes in budget amounts
document.querySelector('#budgetTable').addEventListener('input', function(e) {
    if (e.target.classList.contains('amount')) {
        updateTotalAmount();
    }
});
