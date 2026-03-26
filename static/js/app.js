document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('[data-password-toggle]');
    var passwordField = document.getElementById('password');

    if (toggle && passwordField) {
        toggle.addEventListener('click', function () {
            var isPassword = passwordField.getAttribute('type') === 'password';
            passwordField.setAttribute('type', isPassword ? 'text' : 'password');
            toggle.classList.toggle('is-visible', isPassword);
            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    }

    var uploadInput = document.querySelector('[data-upload-input]');
    var uploadForm = document.querySelector('[data-upload-form]');

    if (uploadInput && uploadForm) {
        uploadInput.addEventListener('change', function () {
            if (uploadInput.files && uploadInput.files.length > 0) {
                uploadForm.submit();
            }
        });
    }

    var mockLeads = [
        { lead_id: 'LD2001', name: 'Rahul Sharma', email: 'rahul@gmail.com', phone: '9876543210', course: 'MBA', city: 'Bangalore', state: 'Karnataka', region: 'South' },
        { lead_id: 'LD2002', name: 'Megha Nair', email: 'megha@gmail.com', phone: '9812345670', course: 'BCA', city: 'Chennai', state: 'Tamil Nadu', region: 'South' },
        { lead_id: 'LD2003', name: 'Priya Singh', email: 'priya@gmail.com', phone: '9823412451', course: 'BBA', city: 'Delhi', state: 'Delhi', region: 'North' },
        { lead_id: 'LD2004', name: 'Ankit Verma', email: 'ankit@gmail.com', phone: '9765432108', course: 'B.Tech', city: 'Jaipur', state: 'Rajasthan', region: 'North' },
        { lead_id: 'LD2005', name: 'Sourav Dutta', email: 'sourav@gmail.com', phone: '9934567810', course: 'MCA', city: 'Kolkata', state: 'West Bengal', region: 'East' },
        { lead_id: 'LD2006', name: 'Nikita Rao', email: 'nikita@gmail.com', phone: '9012345678', course: 'MBA', city: 'Hyderabad', state: 'Telangana', region: 'South' },
        { lead_id: 'LD2007', name: 'Aditya Mehta', email: 'aditya@gmail.com', phone: '9123456780', course: 'BBA', city: 'Ahmedabad', state: 'Gujarat', region: 'West / Others' },
        { lead_id: 'LD2008', name: 'Sneha Patel', email: 'sneha@gmail.com', phone: '9988776655', course: 'MCA', city: 'Pune', state: 'Maharashtra', region: 'West / Others' }
    ];

    var colleaguesByRegion = {
        'South': ['Asha Reddy', 'Kiran Kumar', 'Divya Menon'],
        'North': ['Rohit Bansal', 'Neha Kapoor', 'Varun Malhotra'],
        'East': ['Sohini Ghosh', 'Abhishek Paul', 'Riya Das'],
        'West / Others': ['Mihir Shah', 'Pooja Jain', 'Yash Kulkarni']
    };

    var stateKey = 'lead_management_mapping_state';

    function readState() {
        try {
            return JSON.parse(localStorage.getItem(stateKey) || '{}');
        } catch (error) {
            return {};
        }
    }

    function writeState(nextState) {
        localStorage.setItem(stateKey, JSON.stringify(nextState));
    }

    function groupLeadsByRegion(leads) {
        return leads.reduce(function (acc, lead) {
            var region = lead.region || 'West / Others';
            if (!acc[region]) {
                acc[region] = [];
            }
            acc[region].push(lead);
            return acc;
        }, {});
    }

    var previewRoot = document.querySelector('[data-mapping-preview]');
    if (previewRoot) {
        var previewBody = previewRoot.querySelector('[data-preview-body]');
        var nextButton = previewRoot.querySelector('[data-mapping-next]');
        var totalRecords = document.querySelector('[data-total-records]');
        var regionUrl = previewRoot.getAttribute('data-region-url');
        var state = readState();
        state.leads = mockLeads;
        state.regionGroups = groupLeadsByRegion(mockLeads);
        state.assignments = state.assignments || {};
        writeState(state);

        if (previewBody) {
            previewBody.innerHTML = mockLeads.map(function (lead) {
                return '<tr>' +
                    '<td>' + lead.lead_id + '</td>' +
                    '<td>' + lead.name + '</td>' +
                    '<td>' + lead.email + '</td>' +
                    '<td>' + lead.phone + '</td>' +
                    '<td>' + lead.course + '</td>' +
                    '<td>' + lead.region + '</td>' +
                    '<td>' + lead.city + '</td>' +
                    '<td>' + lead.state + '</td>' +
                    '</tr>';
            }).join('');
        }

        if (totalRecords) {
            totalRecords.textContent = String(mockLeads.length);
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                window.location.href = regionUrl;
            });
        }
    }

    var regionRoot = document.querySelector('[data-region-grouping]');
    if (regionRoot) {
        var regionSummaryCards = regionRoot.querySelector('[data-region-summary-cards]');
        var regionGroupsContainer = regionRoot.querySelector('[data-region-groups]');
        var confirmAssignButton = regionRoot.querySelector('[data-confirm-assign]');
        var apiUrl = regionRoot.getAttribute('data-api-url');
        var regionState = readState();
        var grouped = regionState.regionGroups || groupLeadsByRegion(mockLeads);
        regionState.regionGroups = grouped;
        regionState.leads = regionState.leads || mockLeads;
        writeState(regionState);

        if (regionSummaryCards) {
            regionSummaryCards.innerHTML = Object.keys(grouped).map(function (region) {
                return '<article class="metric-card mapping-region-card">' +
                    '<div class="metric-card__header"><span>' + region + '</span><span class="panel-chip">' + grouped[region].length + ' leads</span></div>' +
                    '<h3>' + grouped[region].length + '</h3>' +
                    '<p>Grouped automatically using mock region data.</p>' +
                    '</article>';
            }).join('');
        }

        if (regionGroupsContainer) {
            regionGroupsContainer.innerHTML = Object.keys(grouped).map(function (region) {
                var items = grouped[region].map(function (lead) {
                    return '<li><strong>' + lead.name + '</strong><span>' + lead.course + ' · ' + lead.city + ', ' + lead.state + '</span></li>';
                }).join('');

                return '<section class="region-group-card">' +
                    '<div class="panel-head"><div><p class="hero-label">' + region + '</p><h3>' + region + ' Region</h3></div><span class="panel-chip">' + grouped[region].length + ' leads</span></div>' +
                    '<ul class="region-lead-list">' + items + '</ul>' +
                    '</section>';
            }).join('');
        }

        if (confirmAssignButton) {
            confirmAssignButton.addEventListener('click', function () {
                window.location.href = apiUrl;
            });
        }
    }

    var assignmentRoot = document.querySelector('[data-colleague-assignment]');
    if (assignmentRoot) {
        var assignmentGrid = assignmentRoot.querySelector('[data-assignment-grid]');
        var submitButton = assignmentRoot.querySelector('[data-assignment-submit]');
        var successBox = assignmentRoot.querySelector('[data-assignment-success]');
        var assignmentState = readState();
        var groupedLeads = assignmentState.regionGroups || groupLeadsByRegion(mockLeads);
        assignmentState.assignments = assignmentState.assignments || {};

        if (assignmentGrid) {
            assignmentGrid.innerHTML = Object.keys(groupedLeads).map(function (region) {
                var options = ['<option value="">Select Colleague</option>'].concat(colleaguesByRegion[region].map(function (name) {
                    var selected = assignmentState.assignments[region] === name ? ' selected' : '';
                    return '<option value="' + name + '"' + selected + '>' + name + '</option>';
                })).join('');

                return '<article class="info-panel assignment-card">' +
                    '<p class="hero-label">' + region + '</p>' +
                    '<h3>' + region + ' Region</h3>' +
                    '<p>' + groupedLeads[region].length + ' leads ready for assignment.</p>' +
                    '<select class="form-select assignment-select" data-region-select="' + region + '">' + options + '</select>' +
                    '</article>';
            }).join('');

            assignmentGrid.querySelectorAll('[data-region-select]').forEach(function (select) {
                select.addEventListener('change', function () {
                    assignmentState.assignments[select.getAttribute('data-region-select')] = select.value;
                    writeState(assignmentState);
                });
            });
        }

        if (submitButton) {
            submitButton.addEventListener('click', function () {
                var regions = Object.keys(groupedLeads);
                var allSelected = regions.every(function (region) {
                    return assignmentState.assignments[region];
                });

                if (!allSelected) {
                    window.alert('Please select a colleague for each region before submitting.');
                    return;
                }

                if (successBox) {
                    successBox.classList.remove('d-none');
                }
            });
        }
    }

    document.querySelectorAll('[data-stepper]').forEach(function (stepper) {
        var currentStep = stepper.getAttribute('data-current-step');
        var order = ['preview', 'region', 'assign'];
        var currentIndex = order.indexOf(currentStep);
        stepper.querySelectorAll('.mapping-step').forEach(function (step, index) {
            if (index < currentIndex) {
                step.classList.add('is-complete');
            }
            if (index === currentIndex) {
                step.classList.add('is-current');
            }
        });
    });
});
