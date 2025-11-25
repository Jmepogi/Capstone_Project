document.addEventListener("DOMContentLoaded", function() {
    const schoolYearInput = document.getElementById("school_year");
    const errorElement = document.getElementById("form_error");

    // Function to validate school year format
    function validateSchoolYear() {
        const value = schoolYearInput.value;
        const regex = /^\d{4}-\d{4}$/;
        if (regex.test(value)) {
            schoolYearInput.classList.remove("is-invalid");
            document.getElementById("school_year_error").style.display = "none";
        } else {
            schoolYearInput.classList.add("is-invalid");
            document.getElementById("school_year_error").style.display = "block";
        }
    }

    // Validate school year on input
    schoolYearInput.addEventListener("input", validateSchoolYear);

    // Validate form on submit
    const form = document.querySelector("form");

    form.addEventListener("submit", function(event) {
        let isValid = true;
        let errorMessage = '';

        // Define the required fields
        const requiredFields = [
            "name",
            "course",
            "year_level",
            "semester",
            "school_year",
            "student_status",
            "email"
        ];

        // Check each required field
        requiredFields.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                isValid = false;
                errorMessage += `<p>${field.previousElementSibling.innerText} is required.</p>`;
                field.classList.add("is-invalid");
            } else {
                field.classList.remove("is-invalid");
            }
        });

        // Check school year format
        const schoolYear = document.getElementById("school_year");
        const schoolYearPattern = /^\d{4}-\d{4}$/;
        if (!schoolYearPattern.test(schoolYear.value)) {
            isValid = false;
            errorMessage += `<p>Please enter a valid school year in the format YYYY-YYYY.</p>`;
            schoolYear.classList.add("is-invalid");
        } else {
            schoolYear.classList.remove("is-invalid");
        }

        // Display error message if invalid
        if (!isValid) {
            errorElement.innerHTML = errorMessage;
            errorElement.style.display = "block";
            event.preventDefault(); // Prevent form submission if validation fails
        } else {
            errorElement.style.display = "none";
        }
    });
});
