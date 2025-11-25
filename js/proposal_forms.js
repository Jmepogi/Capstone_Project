document.addEventListener("DOMContentLoaded", function() {
    // Proposal Type selection handling
    const proposalType = document.getElementById("proposal_type");
    const orgObjective = document.getElementById("org_obj")?.parentElement || null;
    const activityObjective = document.getElementById("act_obj")?.parentElement || null;
    const proObjective = document.getElementById("peo_obj")?.parentElement || null;
    const syllabusTable = document.getElementById("collapseSyllabus");

    if (proposalType) {
        proposalType.addEventListener("change", function() {
            console.log('Selected proposal type: ' + proposalType.value);
            if (
                proposalType.value === "Co-Curricular Activity Proposal" || 
                proposalType.value === "Co-Curricular Activity Proposal (Community Project)"
            ) {
                if (orgObjective) orgObjective.style.display = "none";
                
                if (proObjective) proObjective.style.display = "block";
                if (syllabusTable) syllabusTable.style.display = "block"; 
            } else if (
                proposalType.value === "Extra-Curricular Activity Proposal" || 
                proposalType.value === "Extra-Curricular Activity Proposal (Community Project)"
            ) {
                if (orgObjective) orgObjective.style.display = "block";
                if (activityObjective) activityObjective.style.display = "block";
                if (proObjective) proObjective.style.display = "none";
                if (syllabusTable) syllabusTable.style.display = "none";
            } else {
                if (orgObjective) orgObjective.style.display = "block";
                if (activityObjective) activityObjective.style.display = "block";
                if (proObjective) proObjective.style.display = "block";
                console.log('Hiding syllabus section for proposal type: ' + proposalType.value);
                if (syllabusTable) syllabusTable.style.display = "none"; 
            }
        });

        // Trigger the change event on page load to set initial state
        proposalType.dispatchEvent(new Event('change'));
    }

    const othersCheckbox = document.getElementById("othersCheckbox");
    const othersInput = document.getElementById("othersInput");
    const sourceFundField = document.getElementById("sourceFund");

    function updateSourceFund() {
        let selectedFunds = [...document.querySelectorAll('input[name="source_fund[]"]:checked')]
            .map(checkbox => checkbox.value);

        if (othersCheckbox?.checked) {
            const otherValue = othersInput?.value.trim();
            selectedFunds.push(otherValue || "Others, please specify");
        }

        if (sourceFundField) {
            sourceFundField.value = selectedFunds.join(', ');
        }
    }

    if (othersCheckbox) {
        othersCheckbox.addEventListener("change", function() {
            if (othersInput) {
                othersInput.style.display = othersCheckbox.checked ? "block" : "none";
                updateSourceFund();
            }
        });

        document.addEventListener("change", function(event) {
            if (event.target.matches('input[name="source_fund[]"]')) {
                updateSourceFund();
            }
        });

        othersInput?.addEventListener("input", updateSourceFund);
    }
});
