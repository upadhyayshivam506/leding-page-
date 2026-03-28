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

    var colleagueOptions = [
        { value: '', label: 'Select Colleague' },
        { value: 'NONE', label: 'None' },
        { value: 'IBI', label: 'Indore Business Institute' },
        { value: 'Sunstone', label: 'Sunstone Education' },
        { value: 'IILM', label: 'Institute for Integrated Learning in Management' },
        { value: 'NITTE', label: 'Nitte University' },
        { value: 'KKMU', label: 'K K Modi University' },
        { value: 'KCM', label: 'KCM Bangalore' },
        { value: 'PPSU', label: 'P P Savani University' },
        { value: 'GNOIT', label: 'Greater Noida Institute of Technology' },
        { value: 'PBS', label: 'Pune Business School' },
        { value: 'DBUU', label: 'Dev Bhoomi Uttarakhand University' },
        { value: 'PCU', label: 'Pimpri Chinchwad University' },
        { value: 'JKBS', label: 'JK Business School' },
        { value: 'GIBS', label: 'Global Institute of Business Studies' },
        { value: 'Alliance', label: 'Alliance University' },
        { value: 'Lexicon', label: 'Lexicon Management Institute' }
    ];

    function populateDropdown(selectElement, selectedValues) {
        var values = Array.isArray(selectedValues) ? selectedValues : [];

        selectElement.innerHTML = colleagueOptions.map(function (option) {
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

    function initializeGlobalColleagueSelects(root, onChange) {
        if (!root) {
            return;
        }

        root.querySelectorAll('.region-colleague-select').forEach(function (selectElement) {
            selectElement.addEventListener('change', function () {
                onChange(readSelectValues(selectElement), selectElement);
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

            select.off('change.globalColleagues').on('change.globalColleagues', function () {
                onChange(readSelectValues(this), this);
            });
        });
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
        var submitUrl = assignmentRoot.getAttribute('data-submit-url');
        var successRedirect = assignmentRoot.getAttribute('data-success-redirect') || '/leads';
        var selectedColleagues = [];

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

        function getSelectedColleagues() {
            var selected = [];

            assignmentGrid.querySelectorAll('[data-region-select]').forEach(function (select) {
                readSelectValues(select).forEach(function (value) {
                    if (selected.indexOf(value) === -1) {
                        selected.push(value);
                    }
                });
            });

            return selected;
        }

        function syncSelections(values, sourceSelect) {
            if (values.indexOf('NONE') !== -1) {
                values = [];
            }

            selectedColleagues = values.slice();

            assignmentGrid.querySelectorAll('[data-region-select]').forEach(function (select) {
                Array.from(select.options).forEach(function (option) {
                    option.selected = values.indexOf(option.value) !== -1;
                });

                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(select).trigger('change.select2');
                }
            });
        }

        function renderAssignmentCards() {
            assignmentGrid.innerHTML = regionOrder.map(function (region) {
                var summaryItem = summary.find(function (entry) {
                    return entry.region === region;
                }) || { region: region, total: 0 };

                return '<article class="info-panel assignment-card" data-region-card data-region="' + escapeHtml(region) + '">' +
                    '<p class="hero-label">' + escapeHtml(region) + '</p>' +
                    '<h3>' + escapeHtml(region) + ' Region</h3>' +
                    '<p>' + (Number(summaryItem.total) > 0 ? escapeHtml(summaryItem.total) + ' leads ready for assignment.' : 'No leads ready for assignment.') + '</p>' +
                    '<label class="form-label">Select Colleagues</label>' +
                    '<select class="form-select assignment-select region-colleague-select" data-region-select="' + escapeHtml(region) + '" multiple></select>' +
                    '</article>';
            }).join('');

            assignmentGrid.querySelectorAll('[data-region-select]').forEach(function (select) {
                populateDropdown(select, selectedColleagues);
            });

            initializeGlobalColleagueSelects(assignmentGrid, function (values, sourceSelect) {
                syncSelections(values, sourceSelect);
            });
        }
        renderAssignmentCards();

        if (submitButton) {
            submitButton.addEventListener('click', function () {
                hideMessages();

                selectedColleagues = getSelectedColleagues();

                if (selectedColleagues.length === 0) {
                    if (errorBox && errorMessage) {
                        errorMessage.textContent = 'Please select at least one colleague.';
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
                        colleagues: selectedColleagues
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












