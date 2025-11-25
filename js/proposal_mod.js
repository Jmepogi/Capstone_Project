document.addEventListener('DOMContentLoaded', function () {
    const proposalModal = document.getElementById('proposalModal');

    proposalModal.addEventListener('show.bs.modal', function (event) {
        try {
            // Select the modal element by its ID
            const modal = document.getElementById('proposalModal'); // Correctly reference the modal by its ID

            // Add event listener to the modal for when it's shown
            modal.addEventListener('show.bs.modal', function(event) {
                const relatedTarget = event.relatedTarget; // The button or link that triggered the modal
                const proposalId = relatedTarget?.dataset.proposalId; // Access the proposal ID from the data attribute

                if (!proposalId) {
                    return console.error('Missing ID'); // If ID is not found, log an error
                }

                // Find the print link and update its href
                const link = document.getElementById('printProposalLink');
                link.href = `proposal_print.php?id=${proposalId}`; // Set the link to the correct print URL
                link.classList.remove('disabled'); // Enable the link (if it was disabled)
            });

            
            // Extract modal data with debug logging
            console.log('Getting modal data...');
            
            // Extract modal data
            const title = getAttr('data-title');
            const type = getAttr('data-type');
            const description = getAttr('data-description');
            const beneficiaries = getAttr('data-beneficiaries');
            const orgObj = getAttr('data-org_obj');
            const actObj = getAttr('data-act_obj');
            const peoObj = getAttr('data-peo_obj');
            const campusAct = getAttr('data-campus_act');
            const placeAct = getAttr('data-place_act');
            const startDate = getAttr('data-datetime-start');
            const endDate = getAttr('data-datetime-end');
            const venue = getAttr('data-venue');
            const participants = getAttr('data-participants-num');
            const sourceFund = getAttr('data-source_fund');
            const organization = getAttr('data-organization');
            const president = getAttr('data-president');

            // Arrays (these are now JSON arrays)
            const sdgNumber = getAttr('data-sdg-number', true);
            const sdgDescription = getAttr('data-sdg-description', true);
            const mvcValue = getAttr('data-mvc-value', true);
            const mvcType = getAttr('data-mvc-type', true);

            const particulars = getAttr('data-budget-particulars', true);
            const amounts = getAttr('data-budget-amounts', true);

            const syllabusSubjects = getAttr('data-syllabus-subjects', true);
            const syllabusTopics = getAttr('data-syllabus-topics', true);
            const syllabusRelevance = getAttr('data-syllabus-relevance', true);

            const programNames = getAttr('data-program-names', true);
            const programDetails = getAttr('data-program-details', true);
            const programPersons = getAttr('data-program-persons', true);

            const manpowerRoles = getAttr('data-manpower-roles', true);
            const manpowerNames = getAttr('data-manpower-names', true);
            const manpowerResponsibilities = getAttr('data-manpower-responsibilities', true);

            const signatoryRoles = getAttr('data-signatory-roles', true);
            const signatoryNames = getAttr('data-signatory-names', true);
            const signatoryStatuses = getAttr('data-signatory-statuses', true);
            const signatoryComments = getAttr('data-signatory-comments', true);

            // Debug information
            console.log('Modal data loaded:', {
                title, type, description, sdgNumber, sdgDescription,
                mvcValue, mvcType,
                debug_mvc: getAttr('data-debug-mvc', true)
            });

            // Function to set text content
            function setText(id, value, defaultText = 'Not specified') {
                const element = document.getElementById(id);
                if (element) element.textContent = value || defaultText;
            }

            // Populate modal fields
            setText('modalTitle', title);
            setText('modalType', type);
            setText('modalDescription', description);
            setText('modalBeneficiaries', beneficiaries);
            setText('modalOrgObj', orgObj);
            setText('modalActObj', actObj);
            setText('modalPeoObj', peoObj);
            setText('modalCampusAct', campusAct);
            setText('modalPlaceAct', placeAct);
            setText('modalStartDate', formatDate(startDate));
            setText('modalEndDate', formatDate(endDate));
            setText('modalVenue', venue);
            setText('modalParticipants', participants);
            setText('modalSourceFund', sourceFund);
            setText('modalOrganization', organization);
            setText('modalPresident', president);

            // Update SDG List
            updateList('SdgList', sdgNumber, sdgDescription, 'No SDGs specified', (num, desc) => `SDG ${num}: ${desc}`);

            // Update MVC List
            const MVCList = document.getElementById('MVCList');
            if (MVCList && mvcValue.length > 0 && mvcType.length > 0) {
                MVCList.innerHTML = '';
                
                // Group MVC values by type
                const mvcGroups = {};
                mvcValue.forEach((value, index) => {
                    const type = mvcType[index] || '';
                    if (!mvcGroups[type]) {
                        mvcGroups[type] = [];
                    }
                    mvcGroups[type].push(value);
                });

                // Create elements for each MVC type with both data attributes and visual interface
                Object.entries(mvcGroups).forEach(([type, values]) => {
                    // Container div with data attributes
                    const container = document.createElement('div');
                    container.setAttribute('data-mvc-type', type);
                    container.setAttribute('data-mvc-value', values.join('|'));
                    
                    // Type header for visual interface
                    const typeHeader = document.createElement('div');
                    typeHeader.className = 'mvc-type-header';
                    const formattedType = type.charAt(0).toUpperCase() + type.slice(1);
                    typeHeader.innerHTML = `<strong>${formattedType}</strong>`;
                    container.appendChild(typeHeader);

                    // Values list for visual interface
                    const valuesList = document.createElement('ul');
                    valuesList.className = 'mvc-values-list';
                    valuesList.style.listStyle = 'none';
                    valuesList.style.paddingLeft = '20px';
                    valuesList.style.marginTop = '5px';
                    valuesList.style.marginBottom = '15px';

                    values.forEach(value => {
                        const li = document.createElement('li');
                        li.innerHTML = `• ${value}`;
                        li.style.marginBottom = '5px';
                        valuesList.appendChild(li);
                    });

                    container.appendChild(valuesList);
                    MVCList.appendChild(container);
                });
            } else if (MVCList) {
                MVCList.innerHTML = '<div>No MVC data specified</div>';
            }

            // Update tables
            updateTable('manpowerTableBody', manpowerRoles, manpowerNames, manpowerResponsibilities, 
                ['Role', 'Name', 'Responsibilities'],
                (role, name, resp) => [role, name, resp]
            );

            updateTable('syllabusTableBody', syllabusSubjects, syllabusTopics, syllabusRelevance,
                ['Subject/Course', 'Topic', 'Relevance'],
                (subj, topic, rel) => [subj, topic, rel]
            );

            updateTable('programTableBody', programNames, programDetails, programPersons,
                ['Activity', 'Details', 'Person In-charge'],
                (name, detail, person) => [name, detail, person]
            );

            const budgetTableBody = document.getElementById('budgetTableBody');
            if (budgetTableBody) {
                budgetTableBody.innerHTML = '';

                if (particulars && amounts && particulars.length > 0) {
                    particulars.forEach((particular, index) => {
                        const amount = parseFloat(amounts[index]) || 0;

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${particular}</td>
                            <td class="text-end">₱${amount.toFixed(2)}</td>
                        `;
                        budgetTableBody.appendChild(row);
                    });
                } else {
                    budgetTableBody.innerHTML = '<tr><td colspan="2" class="text-center">No budget details available</td></tr>';
                }
            }

            // Update signatories table
            const signatoriesTableBody = document.getElementById('signatoriesTableBody');
            if (signatoriesTableBody) {
                signatoriesTableBody.innerHTML = '';

                if (signatoryRoles.length > 0) {
                    signatoryRoles.forEach((role, index) => {
                        const name = signatoryNames[index] || '';
                        const status = signatoryStatuses[index] || '';
                        const comment = signatoryComments[index] || 'No comment';

                        const row = document.createElement('tr');
                        row.style.borderBottom = "none"; // Remove row borders

                        // Name & Role Column (40%)
                        const nameCell = document.createElement('td');
                        nameCell.style.padding = "2px";
                        nameCell.style.border = "none"; // Remove cell border
                        nameCell.innerHTML = `
                            <span style="display: block; margin-bottom: 1px; line-height: 1;">${name}</span>
                            <strong style="display: block; line-height: 1;">${role}</strong>
                        `;

                        // Status Column (30%)
                        const statusCell = document.createElement('td');
                        statusCell.style.textAlign = "center";
                        statusCell.style.padding = "8px";
                        statusCell.style.border = "none"; // Remove cell border
                        statusCell.innerHTML = `${status}`;

                        // Comment Column (30%)
                        const commentCell = document.createElement('td');
                        commentCell.style.textAlign = "left";
                        commentCell.style.padding = "8px";
                        commentCell.style.border = "none"; // Remove cell border
                        commentCell.innerHTML = `${comment}`;

                        // Append cells to row
                        row.appendChild(nameCell);
                        row.appendChild(statusCell);
                        row.appendChild(commentCell);

                        // Append row to table
                        signatoriesTableBody.appendChild(row);
                    });
                } else {
                    signatoriesTableBody.innerHTML = '<tr><td colspan="3" class="text-center">No signatories available</td></tr>';
                }
            }

        } catch (error) {
            console.error('Error loading proposal details:', error);
            alert('There was an error loading the proposal details. Please try again.');
        }
    });
});

// Helper function to format dates
function formatDate(dateString) {
    if (!dateString) return 'Not specified';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Helper function to update lists
function updateList(id, items, extraItems, emptyMessage, formatFunc) {
    const list = document.getElementById(id);
    if (!list) return;
    
    if (items.length > 0) {
        list.innerHTML = items.map((item, index) => 
            `<li>${formatFunc(item, extraItems[index])}</li>`
        ).join('');
    } else {
        list.innerHTML = `<li>${emptyMessage}</li>`;
    }
}

// Helper function to update tables
function updateTable(id, col1, col2, col3, headers, rowFormatter) {
    const tableBody = document.getElementById(id);
    if (!tableBody) return;
    tableBody.innerHTML = '';

    if (col1.length > 0) {
        col1.forEach((item, index) => {
            const row = document.createElement('tr');
            const rowData = rowFormatter(item, col2[index] || '', col3[index] || '');
            row.innerHTML = `<td>${rowData[0]}</td><td>${rowData[1]}</td><td>${rowData[2]}</td>`;
            tableBody.appendChild(row);
        });
    } else {
        tableBody.innerHTML = `<tr><td colspan="3" class="text-center">No ${headers[0].toLowerCase()} available</td></tr>`;
    }
}


