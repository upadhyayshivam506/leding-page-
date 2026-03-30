document.addEventListener('DOMContentLoaded', function () {
    var rowsPerPage = 20;
    var regionOrder = ['North', 'South', 'East', 'West / Others'];

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

    function readJsonAttribute(node, name) {
        if (!node) {
            return [];
        }

        try {
            return JSON.parse(node.getAttribute(name) || '[]');
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
                head.innerHTML = headers.length > 0
                    ? '<tr>' + headers.map(function (header) {
                        return '<th>' + escapeHtml(header) + '</th>';
                    }).join('') + '</tr>'
                    : '';
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

    function renderCompactTable(rows, headers) {
        if (!rows.length) {
            return '<div class="table-responsive"><table class="table admin-table align-middle mb-0"><tbody><tr><td class="table-empty-state">No leads in this region.</td></tr></tbody></table></div>';
        }

        return '<div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr>' + headers.map(function (header) {
            return '<th>' + escapeHtml(header) + '</th>';
        }).join('') + '</tr></thead><tbody>' + rows.map(function (row) {
            return '<tr>' + headers.map(function (header) {
                return '<td>' + escapeHtml(row && row[header] != null ? row[header] : '') + '</td>';
            }).join('') + '</tr>';
        }).join('') + '</tbody></table></div>';
    }

    function groupRowsByRegion(rows) {
        var grouped = {
            'North': [],
            'South': [],
            'East': [],
            'West / Others': []
        };

        rows.forEach(function (row) {
            var region = row.Region || row.region || 'West / Others';
            if (!grouped[region]) {
                grouped[region] = [];
            }
            grouped[region].push(row);
        });

        return grouped;
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function (response) {
            return response.text().then(function (text) {
                var data;

                try {
                    data = JSON.parse(text);
                } catch (error) {
                    throw new Error('Server returned an invalid JSON response.');
                }

                if (!response.ok) {
                    throw new Error(data.message || 'Request failed.');
                }

                return data;
            });
        });
    }

    function populateDropdown(selectElement, selectedValues, options) {
        var values = Array.isArray(selectedValues) ? selectedValues : [];
        var availableOptions = Array.isArray(options) ? options : [];

        selectElement.innerHTML = availableOptions.map(function (option) {
            var isSelected = values.indexOf(option.value) !== -1;
            return '<option value="' + escapeHtml(option.value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>';
        }).join('');
    }

    function readSelectValues(select) {
        var values = [];

        if (typeof window.jQuery !== 'undefined') {
            var jqueryValues = window.jQuery(select).val();
            if (Array.isArray(jqueryValues)) {
                values = jqueryValues.slice();
            } else if (jqueryValues) {
                values = [jqueryValues];
            }
        }

        if (values.length === 0) {
            values = Array.from(select.selectedOptions).map(function (option) {
                return option.value;
            });
        }

        return values.filter(function (value) {
            return value !== '' && value !== 'NONE';
        });
    }

    function initializeRegionColleagueSelects(root, onChange) {
        if (!root) {
            return;
        }

        root.querySelectorAll('.region-colleague-select').forEach(function (selectElement) {
            selectElement.addEventListener('change', function () {
                onChange((selectElement.getAttribute('data-region-select') || ''), readSelectValues(selectElement), selectElement);
            });
        });

        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
            return;
        }

        window.jQuery(root).find('.region-colleague-select').each(function () {
            var select = window.jQuery(this);

            if (select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }

            select.select2({
                placeholder: 'Select colleagues',
                width: '100%',
                closeOnSelect: false,
                allowClear: true
            });

            select.off('change.regionColleagues').on('change.regionColleagues', function () {
                onChange((this.getAttribute('data-region-select') || ''), readSelectValues(this), this);
            });
        });
    }

    function regionFieldId(region) {
        return 'colleagues_' + String(region || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

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
                    if (uploadRowsField) {
                        uploadRowsField.value = JSON.stringify(jsonRows);
                    }
                    if (uploadHeadersField) {
                        uploadHeadersField.value = JSON.stringify(jsonRows.length > 0 ? Object.keys(jsonRows[0]) : []);
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

    var logsRoot = document.querySelector('[data-lead-push-logs-table]');
    if (logsRoot) {
        renderTable({
            headers: readJsonScript(logsRoot, '[data-table-headers]'),
            rows: readJsonScript(logsRoot, '[data-table-rows]'),
            head: logsRoot.querySelector('[data-table-head]'),
            body: logsRoot.querySelector('[data-table-body]'),
            countNode: logsRoot.querySelector('[data-table-count]'),
            paginationRoot: logsRoot.querySelector('[data-table-pagination]'),
            emptyMessage: 'No lead push logs are available yet.'
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
            keyFields.textContent = previewHeaders.length > 0 ? previewHeaders.join(', ') : 'No columns detected';
        }

        if (nextButton) {
            nextButton.addEventListener('click', function () {
                window.location.href = regionUrl;
            });
        }
    }

    var regionRoot = document.querySelector('[data-region-grouping]');
    if (regionRoot) {
        var regionRows = readJsonAttribute(regionRoot, 'data-region-rows');
        var regionSummary = readJsonAttribute(regionRoot, 'data-region-summary');
        var summaryCards = regionRoot.querySelector('[data-region-summary-cards]');
        var regionGroups = regionRoot.querySelector('[data-region-groups]');
        var confirmAssignButton = regionRoot.querySelector('[data-confirm-assign]');
        var apiUrl = regionRoot.getAttribute('data-api-url');
        var groupedRows = groupRowsByRegion(regionRows);
        var tableHeaders = regionRows.length > 0 ? Object.keys(regionRows[0]) : ['Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region'];

        if (summaryCards) {
            summaryCards.innerHTML = regionOrder.map(function (region) {
                var item = regionSummary.find(function (entry) {
                    return entry.region === region;
                }) || { region: region, total: 0 };

                return '<article class="metric-card mapping-region-card">' +
                    '<div class="metric-card__header"><span>' + escapeHtml(region) + ' Region</span><span class="panel-chip">Leads</span></div>' +
                    '<h3>' + escapeHtml(item.total) + '</h3>' +
                    '<p>Mapped leads ready for assignment.</p>' +
                    '</article>';
            }).join('');
        }

        if (regionGroups) {
            regionGroups.innerHTML = regionOrder.map(function (region) {
                return '<article class="table-panel region-group-card">' +
                    '<div class="panel-head panel-head--table"><div><h3>' + escapeHtml(region) + ' Region</h3><p class="table-subtext">Region name, lead count, and grouped leads.</p></div><span class="panel-chip">' + escapeHtml(groupedRows[region].length) + ' leads</span></div>' +
                    renderCompactTable(groupedRows[region], tableHeaders) +
                    '</article>';
            }).join('');
        }

        if (confirmAssignButton) {
            confirmAssignButton.addEventListener('click', function () {
                var nextUrl = apiUrl + '?region_summary_json=' + encodeURIComponent(JSON.stringify(regionSummary));
                window.location.href = nextUrl;
            });
        }
    }

    var assignmentRoot = document.querySelector('[data-colleague-assignment]');
    if (assignmentRoot) {
        var assignmentGrid = assignmentRoot.querySelector('[data-assignment-grid]');
        var submitButton = assignmentRoot.querySelector('[data-assignment-submit]');
        var successBox = assignmentRoot.querySelector('[data-assignment-success]');
        var errorBox = assignmentRoot.querySelector('[data-assignment-error]');
        var successMessage = assignmentRoot.querySelector('[data-assignment-success-message]');
        var errorMessage = assignmentRoot.querySelector('[data-assignment-error-message]');
        var loader = document.getElementById('upload-loader');
        var summary = readJsonAttribute(assignmentRoot, 'data-region-summary');
        var colleagueCatalog = readJsonAttribute(assignmentRoot, 'data-colleague-catalog');
        var batchId = assignmentRoot.getAttribute('data-batch-id') || '';
        var submitUrl = assignmentRoot.getAttribute('data-submit-url');
        var successRedirect = assignmentRoot.getAttribute('data-success-redirect') || '/leads';
        var regionLeadCounts = {};
        var selectedColleaguesByRegion = {};

        regionOrder.forEach(function (region) {
            var summaryItem = summary.find(function (entry) {
                return entry.region === region;
            }) || { total: 0 };

            regionLeadCounts[region] = Number(summaryItem.total) || 0;
            selectedColleaguesByRegion[region] = [];
        });

        function setLoadingState(isLoading) {
            if (loader) {
                loader.style.display = isLoading ? 'block' : 'none';
            }
            if (!submitButton) {
                return;
            }
            submitButton.disabled = isLoading;
            submitButton.textContent = isLoading ? 'Submitting...' : 'Submit';
        }

        function hideMessages() {
            if (successBox) {
                successBox.classList.add('d-none');
            }
            if (errorBox) {
                errorBox.classList.add('d-none');
            }
        }

        function getRegionCatalogItems(region) {
            var catalog = colleagueCatalog && typeof colleagueCatalog === 'object' ? colleagueCatalog[region] : [];
            var items = Array.isArray(catalog)
                ? catalog
                : (catalog && typeof catalog === 'object' ? Object.keys(catalog).map(function (key) {
                    return catalog[key];
                }) : []);

            return items.filter(function (item) {
                return item && (item.id != null || item.name != null);
            });
        }

        function regionHasConfiguredColleagues(region) {
            return getRegionCatalogItems(region).some(function (item) {
                return String(item && item.id != null ? item.id : '') !== '';
            });
        }

        function getRegionOptions(region) {
            var options = getRegionCatalogItems(region).map(function (item) {
                return {
                    value: String(item && item.id != null ? item.id : ''),
                    label: String(item && item.name != null ? item.name : item && item.id != null ? item.id : '')
                };
            });

            options = options.filter(function (option) {
                return option.value !== '';
            });

            if (options.length === 0 && regionLeadCounts[region] > 0) {
                return [];
            }

            return options;
        }

        function clearRegionErrors() {
            assignmentGrid.querySelectorAll('[data-region-card]').forEach(function (card) {
                card.classList.remove('assignment-card--error');
            });

            assignmentGrid.querySelectorAll('[data-region-error]').forEach(function (message) {
                message.textContent = '';
                message.classList.add('d-none');
            });
        }

        function showRegionError(region, message) {
            var card = assignmentGrid.querySelector('[data-region-card][data-region="' + region + '"]');
            if (!card) {
                return;
            }

            var errorNode = card.querySelector('[data-region-error]');
            card.classList.add('assignment-card--error');

            if (errorNode) {
                errorNode.textContent = message;
                errorNode.classList.remove('d-none');
            }
        }

        function validateAssignments() {
            clearRegionErrors();

            for (var index = 0; index < regionOrder.length; index += 1) {
                var region = regionOrder[index];
                if ((regionLeadCounts[region] || 0) === 0) {
                    continue;
                }

                if (!regionHasConfiguredColleagues(region)) {
                    continue;
                }

                if (!Array.isArray(selectedColleaguesByRegion[region]) || selectedColleaguesByRegion[region].length === 0) {
                    showRegionError(region, 'Please select at least one colleague for regions that have leads.');
                    return false;
                }
            }

            return true;
        }

        function buildAssignmentsPayload() {
            var assignments = {};

            regionOrder.forEach(function (region) {
                assignments[region] = Array.isArray(selectedColleaguesByRegion[region]) ? selectedColleaguesByRegion[region].slice() : [];
            });

            return assignments;
        }

        function renderAssignmentCards() {
            assignmentGrid.innerHTML = regionOrder.map(function (region) {
                var leadsCount = regionLeadCounts[region] || 0;
                var fieldId = regionFieldId(region);
                var hasConfiguredColleagues = regionHasConfiguredColleagues(region);
                var isDisabled = leadsCount === 0 || !hasConfiguredColleagues;
                var helperText = 'Select at least one colleague for this region.';
                var statusText = leadsCount > 0
                    ? escapeHtml(leadsCount) + ' leads ready for assignment.'
                    : 'No leads ready for assignment.';

                if (leadsCount === 0) {
                    helperText = 'No leads available. Selection not required.';
                } else if (!hasConfiguredColleagues) {
                    helperText = 'No colleagues configured for this region. Leads will not be transferred.';
                    statusText = escapeHtml(leadsCount) + ' leads ready for assignment. No colleagues configured for this region.';
                    selectedColleaguesByRegion[region] = [];
                }

                return '<article class="info-panel assignment-card" data-region-card data-region="' + escapeHtml(region) + '">' +
                    '<p class="hero-label">' + escapeHtml(region) + '</p>' +
                    '<h3>' + escapeHtml(region) + ' Region</h3>' +
                    '<p>' + statusText + '</p>' +
                    '<label class="form-label" for="' + escapeHtml(fieldId) + '">Select Colleagues</label>' +
                    '<select class="form-select assignment-select region-colleague-select" name="colleagues[' + escapeHtml(region) + '][]" id="' + escapeHtml(fieldId) + '" data-region-select="' + escapeHtml(region) + '"' + (isDisabled ? ' disabled' : '') + ' multiple></select>' +
                    '<p class="assignment-card__hint' + (isDisabled ? ' assignment-card__hint--muted' : '') + '">' + escapeHtml(helperText) + '</p>' +
                    '<p class="assignment-card__error d-none" data-region-error></p>' +
                    '</article>';
            }).join('');

            assignmentGrid.querySelectorAll('[data-region-select]').forEach(function (select) {
                var region = select.getAttribute('data-region-select') || '';
                populateDropdown(select, selectedColleaguesByRegion[region], getRegionOptions(region));
            });

            initializeRegionColleagueSelects(assignmentGrid, function (region, values) {
                selectedColleaguesByRegion[region] = values.slice();

                var card = assignmentGrid.querySelector('[data-region-card][data-region="' + region + '"]');
                if (card && values.length > 0) {
                    card.classList.remove('assignment-card--error');

                    var errorNode = card.querySelector('[data-region-error]');
                    if (errorNode) {
                        errorNode.textContent = '';
                        errorNode.classList.add('d-none');
                    }
                }
            });
        }
        renderAssignmentCards();

        if (submitButton) {
            submitButton.addEventListener('click', function () {
                hideMessages();
                clearRegionErrors();

                if (!validateAssignments()) {
                    if (errorBox && errorMessage) {
                        errorMessage.textContent = 'Please select at least one colleague for every region that has leads.';
                        errorBox.classList.remove('d-none');
                    }
                    return;
                }

                setLoadingState(true);
                fetchJson(submitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: batchId,
                        assignments: buildAssignmentsPayload()
                    })
                }).then(function (response) {
                    if (response.status === 'success') {
                        window.location.href = successRedirect || '/leads';
                        return;
                    }

                    throw new Error(response.message || 'Upload failed');
                }).catch(function (error) {
                    if (errorBox && errorMessage) {
                        errorMessage.textContent = error.message || 'Upload failed';
                        errorBox.classList.remove('d-none');
                    }
                }).finally(function () {
                    setLoadingState(false);
                });
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












