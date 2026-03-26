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

    var stateKey = 'lead_management_mapping_state';
    var rowsPerPage = 20;

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

    function readJsonScript(root, selector) {
        var node = root.querySelector(selector);

        if (!node) {
            return [];
        }

        try {
            return JSON.parse(node.textContent || '[]');
        } catch (error) {
            return [];
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function normalizeKey(key) {
        return String(key || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_');
    }

    function getValue(row, keys) {
        var found = '';

        Object.keys(row || {}).some(function (originalKey) {
            var normalized = normalizeKey(originalKey);
            if (keys.indexOf(normalized) !== -1) {
                found = row[originalKey];
                return true;
            }
            return false;
        });

        return found;
    }

    function regionFromState(stateValue) {
        var stateName = String(stateValue || '').trim().toLowerCase();
        var south = ['karnataka', 'tamil nadu', 'telangana', 'kerala', 'andhra pradesh'];
        var north = ['delhi', 'rajasthan', 'haryana', 'punjab', 'uttar pradesh', 'uttarakhand', 'himachal pradesh', 'jammu and kashmir'];
        var east = ['west bengal', 'odisha', 'bihar', 'jharkhand', 'assam', 'sikkim'];

        if (south.indexOf(stateName) !== -1) {
            return 'South';
        }
        if (north.indexOf(stateName) !== -1) {
            return 'North';
        }
        if (east.indexOf(stateName) !== -1) {
            return 'East';
        }
        return 'West / Others';
    }

    function prepareRows(rawRows) {
        return rawRows.filter(function (row) {
            return Object.values(row || {}).some(function (value) {
                return String(value || '').trim() !== '';
            });
        }).map(function (row, index) {
            var preparedRow = Object.assign({}, row);
            var region = getValue(preparedRow, ['region', 'zone']);
            var state = getValue(preparedRow, ['state', 'province']);

            if (!region) {
                region = regionFromState(state);
            }

            if (!getValue(preparedRow, ['lead_id', 'leadid', 'id'])) {
                preparedRow['Lead ID'] = 'LD' + String(1001 + index);
            }

            if (!getValue(preparedRow, ['region', 'zone'])) {
                preparedRow['Region'] = region;
            }

            return preparedRow;
        });
    }

    function buildPager(paginationRoot, currentPage, totalPages, onPageChange) {
        if (!paginationRoot) {
            return;
        }

        if (totalPages <= 1) {
            paginationRoot.innerHTML = '';
            return;
        }

        var buttons = [];
        buttons.push('<button type="button" class="table-page-btn" data-page="' + (currentPage - 1) + '"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>');

        for (var page = 1; page <= totalPages; page += 1) {
            buttons.push('<button type="button" class="table-page-btn' + (page === currentPage ? ' is-active' : '') + '" data-page="' + page + '">' + page + '</button>');
        }

        buttons.push('<button type="button" class="table-page-btn" data-page="' + (currentPage + 1) + '"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>');
        paginationRoot.innerHTML = buttons.join('');

        paginationRoot.querySelectorAll('[data-page]').forEach(function (button) {
            button.addEventListener('click', function () {
                var nextPage = Number(button.getAttribute('data-page'));
                if (nextPage >= 1 && nextPage <= totalPages) {
                    onPageChange(nextPage);
                }
            });
        });
    }

    function renderTable(config) {
        var headers = Array.isArray(config.headers) ? config.headers : [];
        var rows = Array.isArray(config.rows) ? config.rows : [];
        var head = config.head;
        var body = config.body;
        var countNode = config.countNode;
        var paginationRoot = config.paginationRoot;
        var emptyMessage = config.emptyMessage || 'No data available.';
        var currentPage = 1;
        var totalPages = Math.max(1, Math.ceil(rows.length / rowsPerPage));

        function update(page) {
            currentPage = page;

            if (head) {
                if (headers.length > 0) {
                    head.innerHTML = '<tr>' + headers.map(function (header) {
                        return '<th>' + escapeHtml(header) + '</th>';
                    }).join('') + '</tr>';
                } else {
                    head.innerHTML = '';
                }
            }

            if (!body) {
                return;
            }

            if (rows.length === 0 || headers.length === 0) {
                body.innerHTML = '<tr><td colspan="' + Math.max(headers.length, 1) + '" class="table-empty-state">' + escapeHtml(emptyMessage) + '</td></tr>';
                if (countNode) {
                    countNode.textContent = '0 rows';
                }
                if (paginationRoot) {
                    paginationRoot.innerHTML = '';
                }
                return;
            }

            var start = (currentPage - 1) * rowsPerPage;
            var pagedRows = rows.slice(start, start + rowsPerPage);
            body.innerHTML = pagedRows.map(function (row) {
                return '<tr>' + headers.map(function (header) {
                    return '<td>' + escapeHtml(row && row[header] != null ? row[header] : '') + '</td>';
                }).join('') + '</tr>';
            }).join('');

            if (countNode) {
                var end = Math.min(start + rowsPerPage, rows.length);
                countNode.textContent = rows.length === 0 ? '0 rows' : 'Showing ' + (start + 1) + '-' + end + ' of ' + rows.length + ' rows';
            }

            buildPager(paginationRoot, currentPage, totalPages, update);
        }

        update(1);
    }

    var uploadInput = document.querySelector('[data-upload-input]');
    var uploadForm = document.querySelector('[data-upload-form]');
    var uploadRowsField = document.querySelector('[data-upload-rows-json]');
    var uploadHeadersField = document.querySelector('[data-upload-headers-json]');

    if (uploadInput && uploadForm) {
        uploadInput.addEventListener('change', function () {
            if (!(uploadInput.files && uploadInput.files.length > 0)) {
                return;
            }

            var file = uploadInput.files[0];

            if (typeof XLSX === 'undefined') {
                uploadForm.submit();
                return;
            }

            var reader = new FileReader();
            reader.onload = function (event) {
                try {
                    var data = new Uint8Array(event.target.result);
                    var workbook = XLSX.read(data, { type: 'array' });
                    var firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    var jsonRows = XLSX.utils.sheet_to_json(firstSheet, { defval: '' });
                    var preparedRows = prepareRows(jsonRows);
                    var headers = preparedRows.length > 0 ? Object.keys(preparedRows[0]) : [];

                    if (uploadRowsField) {
                        uploadRowsField.value = JSON.stringify(preparedRows);
                    }

                    if (uploadHeadersField) {
                        uploadHeadersField.value = JSON.stringify(headers);
                    }
                } catch (error) {
                    if (uploadRowsField) {
                        uploadRowsField.value = '';
                    }
                    if (uploadHeadersField) {
                        uploadHeadersField.value = '';
                    }
                }

                uploadForm.submit();
            };
            reader.onerror = function () {
                uploadForm.submit();
            };
            reader.readAsArrayBuffer(file);
        });
    }

    var colleaguesByRegion = {
        'South': ['Asha Reddy', 'Kiran Kumar', 'Divya Menon'],
        'North': ['Rohit Bansal', 'Neha Kapoor', 'Varun Malhotra'],
        'East': ['Sohini Ghosh', 'Abhishek Paul', 'Riya Das'],
        'West / Others': ['Mihir Shah', 'Pooja Jain', 'Yash Kulkarni']
    };

    var leadsRoot = document.querySelector('[data-leads-table]');
    if (leadsRoot) {
        renderTable({
            headers: readJsonScript(leadsRoot, '[data-table-headers]'),
            rows: readJsonScript(leadsRoot, '[data-table-rows]'),
            head: leadsRoot.querySelector('[data-table-head]'),
            body: leadsRoot.querySelector('[data-table-body]'),
            countNode: leadsRoot.querySelector('[data-table-count]'),
            paginationRoot: leadsRoot.querySelector('[data-table-pagination]'),
            emptyMessage: 'Upload a lead file to see lead rows here.'
        });
    }

    var previewRoot = document.querySelector('[data-mapping-preview]');
    if (previewRoot) {
        var previewRows = readJsonScript(previewRoot, '[data-preview-rows]');
        var previewHeaders = readJsonScript(previewRoot, '[data-preview-headers]');
        var nextButton = previewRoot.querySelector('[data-mapping-next]');
        var totalRecords = previewRoot.querySelector('[data-total-records]');
        var keyFields = previewRoot.querySelector('[data-key-fields]');
        var regionUrl = previewRoot.getAttribute('data-region-url');

        renderTable({
            headers: previewHeaders,
            rows: previewRows,
            head: previewRoot.querySelector('[data-preview-head]'),
            body: previewRoot.querySelector('[data-preview-body]'),
            countNode: previewRoot.querySelector('[data-preview-count]'),
            paginationRoot: previewRoot.querySelector('[data-preview-pagination]'),
            emptyMessage: 'No uploaded lead rows were found for preview.'
        });

        if (totalRecords) {
            totalRecords.textContent = String(previewRows.length);
        }

        if (keyFields) {
            keyFields.textContent = previewHeaders.length > 0 ? previewHeaders.slice(0, 7).join(', ') : 'No columns detected';
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
        var confirmAssignButton = regionRoot.querySelector('[data-confirm-assign]');
        var apiUrl = regionRoot.getAttribute('data-api-url');
        var regionSummary = readJsonScript(regionRoot, '[data-region-summary]');

        if (regionSummaryCards) {
            if (regionSummary.length === 0) {
                regionSummaryCards.innerHTML = '<article class="metric-card mapping-region-card"><div class="metric-card__header"><span>No regions</span><span class="panel-chip">Summary</span></div><h3>0</h3><p>Upload leads to generate region grouping.</p></article>';
            } else {
                regionSummaryCards.innerHTML = regionSummary.map(function (item) {
                    return '<article class="metric-card mapping-region-card">' +
                        '<div class="metric-card__header"><span>' + escapeHtml(item.region) + '</span><span class="panel-chip">Region</span></div>' +
                        '<h3>' + escapeHtml(item.total) + '</h3>' +
                        '<p>Leads grouped under ' + escapeHtml(item.region) + '.</p>' +
                        '</article>';
                }).join('');
            }
        }

        renderTable({
            headers: readJsonScript(regionRoot, '[data-region-headers]'),
            rows: readJsonScript(regionRoot, '[data-region-rows]'),
            head: regionRoot.querySelector('[data-region-head]'),
            body: regionRoot.querySelector('[data-region-body]'),
            countNode: regionRoot.querySelector('[data-region-count]'),
            paginationRoot: regionRoot.querySelector('[data-region-pagination]'),
            emptyMessage: 'No uploaded leads are available for region grouping.'
        });

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
        var groupedLeads = {};

        readJsonScript(assignmentRoot, '[data-assignment-summary]').forEach(function (item) {
            groupedLeads[item.region] = Array(item.total).fill({});
        });

        assignmentState.assignments = assignmentState.assignments || {};

        if (assignmentGrid) {
            var regions = Object.keys(groupedLeads);

            if (regions.length === 0) {
                assignmentGrid.innerHTML = '<article class="info-panel assignment-card"><p class="hero-label">No regions</p><h3>Upload leads first</h3><p>Region assignments will appear here after a lead file is uploaded and grouped.</p></article>';
            } else {
                assignmentGrid.innerHTML = regions.map(function (region) {
                    var options = ['<option value="">Select Colleague</option>'].concat((colleaguesByRegion[region] || []).map(function (name) {
                        var selected = assignmentState.assignments[region] === name ? ' selected' : '';
                        return '<option value="' + escapeHtml(name) + '"' + selected + '>' + escapeHtml(name) + '</option>';
                    })).join('');

                    return '<article class="info-panel assignment-card">' +
                        '<p class="hero-label">' + escapeHtml(region) + '</p>' +
                        '<h3>' + escapeHtml(region) + ' Region</h3>' +
                        '<p>' + escapeHtml(groupedLeads[region].length) + ' leads ready for assignment.</p>' +
                        '<select class="form-select assignment-select" data-region-select="' + escapeHtml(region) + '">' + options + '</select>' +
                        '</article>';
                }).join('');
            }

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
                var allSelected = regions.length > 0 && regions.every(function (region) {
                    return assignmentState.assignments[region];
                });

                if (regions.length === 0) {
                    window.alert('Upload and group a lead file before assigning colleagues.');
                    return;
                }

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
