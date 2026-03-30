document.addEventListener('DOMContentLoaded', function () {
    var regionOrder = ['North', 'South', 'East', 'West / Others'];
    var defaultPreviewHeaders = ['Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region'];

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

    function groupRowsByRegion(rows) {
        var grouped = { 'North': [], 'South': [], 'East': [], 'West / Others': [] };

        (Array.isArray(rows) ? rows : []).forEach(function (row) {
            var region = row.Region || row.region || 'West / Others';
            if (!grouped[region]) {
                grouped[region] = [];
            }
            grouped[region].push(row);
        });

        return grouped;
    }

    function buildPager(root, currentPage, totalPages, onChange) {
        if (!root) {
            return;
        }

        if (totalPages <= 1) {
            root.innerHTML = '';
            return;
        }

        var html = ['<button type="button" class="table-page-btn" data-page="' + (currentPage - 1) + '"' + (currentPage === 1 ? ' disabled' : '') + '>Prev</button>'];
        for (var page = 1; page <= totalPages; page += 1) {
            html.push('<button type="button" class="table-page-btn' + (page === currentPage ? ' is-active' : '') + '" data-page="' + page + '">' + page + '</button>');
        }
        html.push('<button type="button" class="table-page-btn" data-page="' + (currentPage + 1) + '"' + (currentPage === totalPages ? ' disabled' : '') + '>Next</button>');
        root.innerHTML = html.join('');

        root.querySelectorAll('[data-page]').forEach(function (button) {
            button.addEventListener('click', function () {
                var page = Number(button.getAttribute('data-page'));
                if (page >= 1 && page <= totalPages) {
                    onChange(page);
                }
            });
        });
    }

    function renderTable(config) {
        var headers = Array.isArray(config.headers) ? config.headers : [];
        var rows = Array.isArray(config.rows) ? config.rows : [];
        var rowsPerPage = Math.max(1, Number(config.rowsPerPage || 20));
        var emptyMessage = config.emptyMessage || 'No data available.';
        var totalPages = Math.max(1, Math.ceil(rows.length / rowsPerPage));

        function draw(page) {
            var currentPage = Math.min(Math.max(page, 1), totalPages);
            var start = (currentPage - 1) * rowsPerPage;
            var slice = rows.slice(start, start + rowsPerPage);

            if (config.head) {
                config.head.innerHTML = headers.length
                    ? '<tr>' + headers.map(function (header) {
                        return '<th>' + escapeHtml(header) + '</th>';
                    }).join('') + '</tr>'
                    : '';
            }

            if (!config.body) {
                return;
            }

            if (!rows.length || !headers.length) {
                config.body.innerHTML = '<tr><td colspan="' + Math.max(1, headers.length) + '" class="table-empty-state">' + escapeHtml(emptyMessage) + '</td></tr>';
                if (config.countNode) {
                    config.countNode.textContent = '0 rows';
                }
                if (config.paginationRoot) {
                    config.paginationRoot.innerHTML = '';
                }
                return;
            }

            config.body.innerHTML = slice.map(function (row) {
                return '<tr>' + headers.map(function (header) {
                    return '<td>' + escapeHtml(row && row[header] != null ? row[header] : '') + '</td>';
                }).join('') + '</tr>';
            }).join('');

            if (config.countNode) {
                var end = Math.min(start + rowsPerPage, rows.length);
                config.countNode.textContent = 'Showing ' + (start + 1) + '-' + end + ' of ' + rows.length + ' rows';
            }

            buildPager(config.paginationRoot, currentPage, totalPages, draw);
        }

        draw(1);
    }

    function renderCompactTable(rows, headers, emptyMessage) {
        var safeHeaders = Array.isArray(headers) && headers.length ? headers : defaultPreviewHeaders;
        if (!rows.length) {
            return '<div class="table-responsive"><table class="table admin-table align-middle mb-0"><tbody><tr><td class="table-empty-state">' + escapeHtml(emptyMessage || 'No rows available.') + '</td></tr></tbody></table></div>';
        }

        return '<div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr>' + safeHeaders.map(function (header) {
            return '<th>' + escapeHtml(header) + '</th>';
        }).join('') + '</tr></thead><tbody>' + rows.map(function (row) {
            return '<tr>' + safeHeaders.map(function (header) {
                return '<td>' + escapeHtml(row && row[header] != null ? row[header] : '') + '</td>';
            }).join('') + '</tr>';
        }).join('') + '</tbody></table></div>';
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function (response) {
            return response.text().then(function (text) {
                var payload;
                try {
                    payload = JSON.parse(text);
                } catch (error) {
                    throw new Error('Server returned an invalid JSON response.');
                }

                if (!response.ok || payload.status === 'error') {
                    throw new Error(payload.message || 'Request failed.');
                }

                return payload;
            });
        });
    }

    function selectValues(select) {
        return Array.from(select.selectedOptions || []).map(function (option) {
            return option.value;
        }).filter(function (value) {
            return value !== '';
        });
    }

    function renderChipList(root, values, emptyMessage) {
        if (!root) {
            return;
        }

        if (!values.length) {
            root.innerHTML = '<span class="mapping-chip mapping-chip--muted">' + escapeHtml(emptyMessage) + '</span>';
            return;
        }

        root.innerHTML = values.map(function (value) {
            return '<span class="mapping-chip">' + escapeHtml(value) + '</span>';
        }).join('');
    }

    function toggleNode(node, shouldShow) {
        if (!node) {
            return;
        }

        node.classList.toggle('d-none', !shouldShow);
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

            if (typeof XLSX === 'undefined') {
                uploadForm.submit();
                return;
            }

            var reader = new FileReader();
            reader.onload = function (event) {
                try {
                    var workbook = XLSX.read(new Uint8Array(event.target.result), { type: 'array' });
                    var firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    var jsonRows = XLSX.utils.sheet_to_json(firstSheet, { defval: '' });
                    if (uploadRowsField) {
                        uploadRowsField.value = JSON.stringify(jsonRows);
                    }
                    if (uploadHeadersField) {
                        uploadHeadersField.value = JSON.stringify(jsonRows.length ? Object.keys(jsonRows[0]) : []);
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
            reader.readAsArrayBuffer(uploadInput.files[0]);
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
            rowsPerPage: 12,
            emptyMessage: 'No lead push logs are available yet.'
        });
    }

    var uploadPreviewRoot = document.querySelector('[data-mapping-preview]');
    if (uploadPreviewRoot) {
        var uploadPreviewRows = readJsonScript(uploadPreviewRoot, '[data-preview-rows]');
        var uploadPreviewHeaders = readJsonScript(uploadPreviewRoot, '[data-preview-headers]');
        renderTable({
            headers: uploadPreviewHeaders,
            rows: uploadPreviewRows,
            head: uploadPreviewRoot.querySelector('[data-preview-head]'),
            body: uploadPreviewRoot.querySelector('[data-preview-body]'),
            countNode: uploadPreviewRoot.querySelector('[data-preview-count]'),
            paginationRoot: uploadPreviewRoot.querySelector('[data-preview-pagination]'),
            emptyMessage: 'No uploaded lead rows were found for preview.'
        });

        var nextButton = uploadPreviewRoot.querySelector('[data-mapping-next]');
        if (nextButton) {
            nextButton.addEventListener('click', function () {
                window.location.href = uploadPreviewRoot.getAttribute('data-region-url');
            });
        }
    }

    var regionRoot = document.querySelector('[data-course-mapping-page]');
    if (regionRoot) {
        var rows = readJsonAttribute(regionRoot, 'data-region-rows');
        var summary = readJsonAttribute(regionRoot, 'data-region-summary');
        var openCourseMappingButton = regionRoot.querySelector('[data-open-course-mapping]');
        var confirmRegionRedirectButton = regionRoot.querySelector('[data-confirm-region-redirect]');
        var columns = readJsonAttribute(regionRoot, 'data-columns');
        var colleges = readJsonAttribute(regionRoot, 'data-colleges');
        var headers = rows.length ? Object.keys(rows[0]) : defaultPreviewHeaders;
        var grouped = groupRowsByRegion(rows);
        var modal = document.querySelector('[data-course-mapping-modal]');
        var closeModalButtons = document.querySelectorAll('[data-close-course-mapping]');
        var regionPicker = modal ? modal.querySelector('[data-region-picker]') : null;
        var selectedRegionSections = modal ? modal.querySelector('[data-selected-region-sections]') : null;
        var columnSelect = modal ? modal.querySelector('[data-mapping-column]') : null;
        var courseSelect = modal ? modal.querySelector('[data-course-values]') : null;
        var specializationSelect = modal ? modal.querySelector('[data-specialization-select]') : null;
        var collegeSelect = modal ? modal.querySelector('[data-college-select]') : null;
        var courseChipList = modal ? modal.querySelector('[data-course-chip-list]') : null;
        var collegeChipList = modal ? modal.querySelector('[data-college-chip-list]') : null;
        var errorNode = modal ? modal.querySelector('[data-course-mapping-error]') : null;
        var generateButton = modal ? modal.querySelector('[data-generate-preview]') : null;
        var selectedRegions = regionOrder.slice();
        var selectedCourses = [];
        var selectedSpecialization = '';
        var selectedColleges = [];

        function selectedRows() {
            return rows.filter(function (row) {
                return selectedRegions.indexOf(row.Region) !== -1;
            });
        }

        function selectedCourseRows() {
            return selectedRows().filter(function (row) {
                return !selectedCourses.length || selectedCourses.indexOf(row.Course) !== -1;
            });
        }

        function renderSummaryCards() {
            var root = regionRoot.querySelector('[data-region-summary-cards]');
            if (!root) {
                return;
            }

            root.innerHTML = regionOrder.map(function (region) {
                var item = summary.find(function (entry) {
                    return entry.region === region;
                }) || { total: 0 };

                return '<article class="metric-card mapping-region-card"><div class="metric-card__header"><span>' + escapeHtml(region) + '</span><span class="panel-chip">Leads</span></div><h3>' + escapeHtml(item.total) + '</h3><p>Region-wise grouping of leads is ready for mapping.</p></article>';
            }).join('');
        }

        function renderRegionGroups() {
            var root = regionRoot.querySelector('[data-region-groups]');
            if (!root) {
                return;
            }

            root.innerHTML = regionOrder.map(function (region) {
                var previewRows = grouped[region].slice(0, 8);
                return '<article class="region-group-card"><div class="panel-head panel-head--table"><div><h3>' + escapeHtml(region) + '</h3><p class="table-subtext">First 8 leads shown for quick review on all devices.</p></div><span class="panel-chip">' + escapeHtml(grouped[region].length) + ' leads</span></div>' + renderCompactTable(previewRows, headers, 'No leads in this region.') + '</article>';
            }).join('');
        }

        function renderRegionPicker() {
            if (!regionPicker) {
                return;
            }

            regionPicker.innerHTML = regionOrder.map(function (region) {
                var checked = selectedRegions.indexOf(region) !== -1;
                return '<label class="mapping-region-option"><input type="checkbox" value="' + escapeHtml(region) + '"' + (checked ? ' checked' : '') + '> <span>' + escapeHtml(region) + '</span></label>';
            }).join('');

            regionPicker.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    selectedRegions = Array.from(regionPicker.querySelectorAll('input:checked')).map(function (node) {
                        return node.value;
                    });
                    if (!selectedRegions.length) {
                        selectedRegions = regionOrder.slice();
                        renderRegionPicker();
                    }
                    updateDependentFields();
                });
            });
        }

        function renderSelectedRegionSections() {
            if (!selectedRegionSections) {
                return;
            }

            selectedRegionSections.innerHTML = selectedRegions.map(function (region) {
                var previewRows = grouped[region].slice(0, 8);
                return '<article class="mapping-selected-region-card"><div class="panel-head panel-head--table"><div><h3>' + escapeHtml(region) + ' Section</h3><p class="table-subtext">First 8 filtered rows for the selected region.</p></div><span class="panel-chip">' + escapeHtml(grouped[region].length) + ' leads</span></div>' + renderCompactTable(previewRows, headers, 'No leads in this region.') + '</article>';
            }).join('');
        }

        function renderColumnOptions() {
            if (!columnSelect) {
                return;
            }

            columnSelect.innerHTML = (Array.isArray(columns) ? columns : []).map(function (column) {
                return '<option value="' + escapeHtml(column) + '"' + (column === 'Course' ? ' selected' : '') + '>' + escapeHtml(column) + '</option>';
            }).join('');
        }

        function updateCourseOptions() {
            if (!courseSelect) {
                return;
            }

            var values = [];
            selectedRows().forEach(function (row) {
                if (row.Course && values.indexOf(row.Course) === -1) {
                    values.push(row.Course);
                }
            });
            values.sort();

            if (!selectedCourses.length) {
                selectedCourses = values.slice();
            } else {
                selectedCourses = selectedCourses.filter(function (value) {
                    return values.indexOf(value) !== -1;
                });
                if (!selectedCourses.length) {
                    selectedCourses = values.slice();
                }
            }

            courseSelect.innerHTML = values.map(function (value) {
                return '<option value="' + escapeHtml(value) + '"' + (selectedCourses.indexOf(value) !== -1 ? ' selected' : '') + '>' + escapeHtml(value) + '</option>';
            }).join('');
            renderChipList(courseChipList, selectedCourses, 'All available course values are selected.');
        }

        function updateSpecializationOptions() {
            if (!specializationSelect) {
                return;
            }

            var values = [];
            selectedCourseRows().forEach(function (row) {
                if (row.Specialization && values.indexOf(row.Specialization) === -1) {
                    values.push(row.Specialization);
                }
            });
            values.sort();

            if (values.indexOf(selectedSpecialization) === -1) {
                selectedSpecialization = values[0] || '';
            }

            specializationSelect.innerHTML = values.length
                ? values.map(function (value) {
                    return '<option value="' + escapeHtml(value) + '"' + (value === selectedSpecialization ? ' selected' : '') + '>' + escapeHtml(value) + '</option>';
                }).join('')
                : '<option value="">No specialization found</option>';
        }

        function updateCollegeOptions() {
            if (!collegeSelect) {
                return;
            }

            collegeSelect.innerHTML = (Array.isArray(colleges) ? colleges : []).map(function (college) {
                var value = college.id || '';
                var label = college.name || value;
                return '<option value="' + escapeHtml(value) + '"' + (selectedColleges.indexOf(value) !== -1 ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
            }).join('');
            renderChipList(collegeChipList, selectedColleges.map(function (id) {
                var match = colleges.find(function (college) {
                    return college.id === id;
                });
                return match ? match.name : id;
            }), 'No colleges selected yet.');
        }

        function updateDependentFields() {
            renderSelectedRegionSections();
            updateCourseOptions();
            updateSpecializationOptions();
        }

        function setModalOpen(isOpen) {
            if (!modal) {
                return;
            }

            modal.classList.toggle('d-none', !isOpen);
            document.body.classList.toggle('modal-is-open', isOpen);
            if (errorNode) {
                errorNode.classList.add('d-none');
                errorNode.textContent = '';
            }
        }

        renderSummaryCards();
        renderRegionGroups();

        if (modal) {
            renderRegionPicker();
            renderColumnOptions();
            updateDependentFields();
            updateCollegeOptions();
        }

        if (courseSelect) {
            courseSelect.addEventListener('change', function () {
                selectedCourses = selectValues(courseSelect);
                if (!selectedCourses.length) {
                    selectedCourses = Array.from(courseSelect.options).map(function (option) {
                        return option.value;
                    });
                }
                renderChipList(courseChipList, selectedCourses, 'All available course values are selected.');
                updateSpecializationOptions();
            });
        }

        if (specializationSelect) {
            specializationSelect.addEventListener('change', function () {
                selectedSpecialization = specializationSelect.value || '';
            });
        }

        if (collegeSelect) {
            collegeSelect.addEventListener('change', function () {
                selectedColleges = selectValues(collegeSelect);
                updateCollegeOptions();
            });
        }

        if (openCourseMappingButton) {
            openCourseMappingButton.addEventListener('click', function () {
                setModalOpen(true);
            });
        }

        if (confirmRegionRedirectButton) {
            confirmRegionRedirectButton.addEventListener('click', function () {
                window.location.href = regionRoot.getAttribute('data-api-colleagues-url') || '/leads/mapping/region/api-colleagues';
            });
        }

        closeModalButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setModalOpen(false);
            });
        });

        if (generateButton) {
            generateButton.addEventListener('click', function () {
                if (!selectedSpecialization) {
                    errorNode.textContent = 'Select one specialization before generating preview.';
                    errorNode.classList.remove('d-none');
                    return;
                }

                if (!selectedColleges.length) {
                    errorNode.textContent = 'Select one or more colleges before generating preview.';
                    errorNode.classList.remove('d-none');
                    return;
                }

                generateButton.disabled = true;
                fetchJson(regionRoot.getAttribute('data-generate-preview-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: regionRoot.getAttribute('data-batch-id') || '',
                        regions: selectedRegions,
                        column: columnSelect ? columnSelect.value : 'Course',
                        course_values: selectedCourses,
                        specialization: selectedSpecialization,
                        college_ids: selectedColleges
                    })
                }).then(function () {
                    window.location.href = regionRoot.getAttribute('data-preview-page-url');
                }).catch(function (error) {
                    errorNode.textContent = error.message || 'Unable to generate preview.';
                    errorNode.classList.remove('d-none');
                }).finally(function () {
                    generateButton.disabled = false;
                });
            });
        }
    }

    var previewRoot = document.querySelector('[data-mapping-courses-preview]');
    if (previewRoot) {
        var previewRows = readJsonScript(previewRoot, '[data-preview-rows]');
        var previewHeaders = readJsonScript(previewRoot, '[data-preview-headers]');
        var previewGrouped = readJsonAttribute(previewRoot, 'data-preview-grouped');
        var confirmButton = previewRoot.querySelector('[data-confirm-mapping]');
        var durationMessage = previewRoot.querySelector('[data-duration-message]');

        renderTable({
            headers: previewHeaders.length ? previewHeaders : defaultPreviewHeaders,
            rows: previewRows,
            rowsPerPage: 10,
            head: previewRoot.querySelector('[data-preview-head]'),
            body: previewRoot.querySelector('[data-preview-body]'),
            countNode: previewRoot.querySelector('[data-preview-count]'),
            paginationRoot: previewRoot.querySelector('[data-preview-pagination]'),
            emptyMessage: 'No leads available in preview.'
        });

        var regionGroupsRoot = previewRoot.querySelector('[data-preview-region-groups]');
        if (regionGroupsRoot) {
            var groupedRows = previewGrouped && typeof previewGrouped === 'object' ? previewGrouped : {};
            regionGroupsRoot.innerHTML = regionOrder.map(function (region) {
                var regionRows = Array.isArray(groupedRows[region]) ? groupedRows[region] : [];
                return '<article class="region-group-card"><div class="panel-head panel-head--table"><div><h3>' + escapeHtml(region) + '</h3><p class="table-subtext">Preview rows grouped region-wise.</p></div><span class="panel-chip">' + escapeHtml(regionRows.length) + ' leads</span></div>' + renderCompactTable(regionRows.slice(0, 10), defaultPreviewHeaders, 'No preview rows in this region.') + '</article>';
            }).join('');
        }

        function setMessage(message, isError) {
            if (!durationMessage) {
                return;
            }

            durationMessage.textContent = message;
            durationMessage.classList.remove('d-none');
            durationMessage.classList.toggle('mapping-preview-message--error', !!isError);
            durationMessage.classList.toggle('mapping-preview-message--success', !isError);
        }

        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                confirmButton.disabled = true;
                fetchJson(previewRoot.getAttribute('data-confirm-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        mapping_configuration_id: previewRoot.getAttribute('data-mapping-configuration-id')
                    })
                }).then(function (payload) {
                    setMessage('Mapping confirmed. Redirecting to Assign Region Colleagues.', false);
                    setTimeout(function () {
                        window.location.href = payload.data && payload.data.redirect ? payload.data.redirect : '/leads/mapping/region/api-colleagues';
                    }, 300);
                }).catch(function (error) {
                    confirmButton.disabled = false;
                    setMessage(error.message || 'Unable to confirm mapping.', true);
                });
            });
        }
    }

    var regionColleaguesRoot = document.querySelector('[data-region-colleagues-page]');
    if (regionColleaguesRoot) {
        var regionSummary = readJsonAttribute(regionColleaguesRoot, 'data-region-summary');
        var regionRows = readJsonAttribute(regionColleaguesRoot, 'data-region-rows');
        var regionGrouped = groupRowsByRegion(regionRows);
        var colleagueCatalog = readJsonAttribute(regionColleaguesRoot, 'data-colleague-catalog');
        var existingAssignments = readJsonAttribute(regionColleaguesRoot, 'data-region-assignments');
        var assignmentGrid = regionColleaguesRoot.querySelector('[data-assignment-grid]');
        var summaryBody = regionColleaguesRoot.querySelector('[data-region-summary-body]');
        var confirmAssignButton = regionColleaguesRoot.querySelector('[data-confirm-assign]');
        var assignMessage = regionColleaguesRoot.querySelector('[data-assign-message]');
        var assignments = {};

        regionOrder.forEach(function (region) {
            assignments[region] = existingAssignments && existingAssignments[region] ? existingAssignments[region] : [];
        });

        function catalogForRegion(region) {
            var catalog = colleagueCatalog && typeof colleagueCatalog === 'object' ? colleagueCatalog[region] : [];
            if (Array.isArray(catalog)) {
                return catalog;
            }

            return [];
        }

        function renderSummaryTable() {
            if (!summaryBody) {
                return;
            }

            summaryBody.innerHTML = regionOrder.map(function (region) {
                var entry = regionSummary.find(function (item) {
                    return item.region === region;
                }) || { total: (regionGrouped[region] || []).length };

                return '<tr><td>' + escapeHtml(region) + '</td><td>' + escapeHtml(entry.total) + '</td></tr>';
            }).join('');
        }

        function renderRegionChips(region) {
            var root = assignmentGrid.querySelector('[data-region-chip-list="' + region + '"]');
            if (!root) {
                return;
            }

            renderChipList(root, assignments[region].map(function (id) {
                var match = catalogForRegion(region).find(function (college) {
                    return college.id === id;
                });
                return match ? match.name : id;
            }), 'No colleagues selected.');
        }

        function renderAssignments() {
            if (!assignmentGrid) {
                return;
            }

            assignmentGrid.innerHTML = regionOrder.map(function (region) {
                var options = catalogForRegion(region);
                var leadCount = (regionGrouped[region] || []).length;
                var copy = leadCount > 0
                    ? String(leadCount) + ' leads ready for assignment'
                    : 'No leads ready for assignment';

                return '<article class="info-panel assignment-card"><p class="hero-label">' + escapeHtml(region) + '</p><h3>' + escapeHtml(region) + ' Region</h3><p>' + escapeHtml(copy) + '</p><label class="form-label">Select Colleagues<select class="assignment-select" data-region-assignment="' + escapeHtml(region) + '" multiple>' + options.map(function (college) {
                    var value = college.id || '';
                    var label = college.name || value;
                    var selected = assignments[region].indexOf(value) !== -1 ? ' selected' : '';
                    return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
                }).join('') + '</select></label><div class="mapping-chip-list" data-region-chip-list="' + escapeHtml(region) + '"></div></article>';
            }).join('');

            assignmentGrid.querySelectorAll('[data-region-assignment]').forEach(function (select) {
                select.addEventListener('change', function () {
                    var region = select.getAttribute('data-region-assignment') || '';
                    assignments[region] = selectValues(select);
                    renderRegionChips(region);
                });
            });
        }

        function setAssignMessage(message, isError) {
            if (!assignMessage) {
                return;
            }

            assignMessage.textContent = message;
            assignMessage.classList.remove('d-none');
            assignMessage.classList.toggle('mapping-preview-message--error', !!isError);
            assignMessage.classList.toggle('mapping-preview-message--success', !isError);
        }

        renderSummaryTable();
        renderAssignments();
        regionOrder.forEach(renderRegionChips);

        if (confirmAssignButton) {
            confirmAssignButton.addEventListener('click', function () {
                confirmAssignButton.disabled = true;
                fetchJson(regionColleaguesRoot.getAttribute('data-confirm-assign-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        assignments: assignments
                    })
                }).then(function (payload) {
                    setAssignMessage('Assignments confirmed. Opening API Duration page.', false);
                    setTimeout(function () {
                        window.location.href = payload.data && payload.data.redirect
                            ? payload.data.redirect
                            : (regionColleaguesRoot.getAttribute('data-api-duration-page-url') || '/leads/mapping/api-duration');
                    }, 300);
                }).catch(function (error) {
                    confirmAssignButton.disabled = false;
                    setAssignMessage(error.message || 'Unable to confirm assignments.', true);
                });
            });
        }
    }

    var apiDurationRoot = document.querySelector('[data-api-duration-page]');
    if (apiDurationRoot) {
        var apiRows = readJsonAttribute(apiDurationRoot, 'data-region-rows');
        var durationDefaults = readJsonAttribute(apiDurationRoot, 'data-duration-defaults');
        var selectedCollegeNames = readJsonAttribute(apiDurationRoot, 'data-selected-colleges');
        var selectedAssignments = readJsonAttribute(apiDurationRoot, 'data-region-assignments');
        var batchSelect = apiDurationRoot.querySelector('[data-batch-size]');
        var delaySelect = apiDurationRoot.querySelector('[data-delay-size]');
        var customBatchWrap = apiDurationRoot.querySelector('[data-custom-batch-wrap]');
        var customDelayWrap = apiDurationRoot.querySelector('[data-custom-delay-wrap]');
        var customBatchInput = apiDurationRoot.querySelector('[data-custom-batch-size]');
        var customDelayInput = apiDurationRoot.querySelector('[data-custom-delay-size]');
        var saveDurationButton = apiDurationRoot.querySelector('[data-save-duration]');
        var apiDurationMessage = apiDurationRoot.querySelector('[data-api-duration-message]');
        var totalLeadsNode = apiDurationRoot.querySelector('[data-duration-total-leads]');
        var selectedColleaguesNode = apiDurationRoot.querySelector('[data-duration-selected-colleagues]');
        var selectedCollegesNode = apiDurationRoot.querySelector('[data-duration-selected-colleges]');
        var batchSizeNode = apiDurationRoot.querySelector('[data-duration-batch-size]');
        var delayNode = apiDurationRoot.querySelector('[data-duration-delay]');
        var estimateNode = apiDurationRoot.querySelector('[data-duration-estimate]');
        var selectedCollegeList = apiDurationRoot.querySelector('[data-selected-college-list]');

        function selectedAssignmentCount() {
            var total = 0;

            regionOrder.forEach(function (region) {
                total += Array.isArray(selectedAssignments && selectedAssignments[region]) ? selectedAssignments[region].length : 0;
            });

            return total;
        }

        function setApiMessage(message, isError) {
            if (!apiDurationMessage) {
                return;
            }

            apiDurationMessage.textContent = message;
            apiDurationMessage.classList.remove('d-none');
            apiDurationMessage.classList.toggle('mapping-preview-message--error', !!isError);
            apiDurationMessage.classList.toggle('mapping-preview-message--success', !isError);
        }

        function updateDurationSummary() {
            var totalLeads = apiRows.length;
            var batchSize = batchSelect && batchSelect.value === 'custom' ? Number(customBatchInput && customBatchInput.value ? customBatchInput.value : 0) : Number(batchSelect ? batchSelect.value : 50);
            var delay = delaySelect && delaySelect.value === 'custom' ? Number(customDelayInput && customDelayInput.value ? customDelayInput.value : 0) : Number(delaySelect ? delaySelect.value : 0.2);
            var batches = batchSize > 0 ? Math.ceil(totalLeads / batchSize) : 0;
            var estimated = Math.max(0, batches - 1) * Math.max(0, delay);

            if (totalLeadsNode) {
                totalLeadsNode.textContent = String(totalLeads);
            }
            if (selectedColleaguesNode) {
                selectedColleaguesNode.textContent = String(selectedAssignmentCount());
            }
            if (selectedCollegesNode) {
                selectedCollegesNode.textContent = String(Array.isArray(selectedCollegeNames) ? selectedCollegeNames.length : 0);
            }
            if (batchSizeNode) {
                batchSizeNode.textContent = batchSize > 0 ? String(batchSize) : '0';
            }
            if (delayNode) {
                delayNode.textContent = delay.toFixed(2) + ' seconds';
            }
            if (estimateNode) {
                estimateNode.textContent = estimated.toFixed(2) + ' seconds';
            }
        }

        function syncCustomFields() {
            toggleNode(customBatchWrap, batchSelect && batchSelect.value === 'custom');
            toggleNode(customDelayWrap, delaySelect && delaySelect.value === 'custom');
            updateDurationSummary();
        }

        if (durationDefaults && typeof durationDefaults === 'object') {
            if (batchSelect && durationDefaults.batch_size) {
                batchSelect.value = String(durationDefaults.batch_size);
            }
            if (delaySelect && durationDefaults.delay !== undefined) {
                delaySelect.value = String(durationDefaults.delay);
            }
        }

        renderChipList(selectedCollegeList, Array.isArray(selectedCollegeNames) ? selectedCollegeNames : [], 'No colleges selected yet.');
        updateDurationSummary();
        syncCustomFields();

        if (batchSelect) {
            batchSelect.addEventListener('change', syncCustomFields);
        }
        if (delaySelect) {
            delaySelect.addEventListener('change', syncCustomFields);
        }
        if (customBatchInput) {
            customBatchInput.addEventListener('input', updateDurationSummary);
        }
        if (customDelayInput) {
            customDelayInput.addEventListener('input', updateDurationSummary);
        }

        if (saveDurationButton) {
            saveDurationButton.addEventListener('click', function () {
                saveDurationButton.disabled = true;
                setApiMessage('Saving duration settings and preparing background sending...', false);

                fetchJson(apiDurationRoot.getAttribute('data-save-duration-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_size: batchSelect ? batchSelect.value : '50',
                        custom_batch_size: customBatchInput ? customBatchInput.value : '',
                        delay: delaySelect ? delaySelect.value : '0.2',
                        custom_delay: customDelayInput ? customDelayInput.value : ''
                    })
                }).then(function (payload) {
                    var successMessage = payload.data && payload.data.confirmation ? payload.data.confirmation : 'Duration settings saved successfully.';
                    var redirectMessage = payload.data && payload.data.message ? payload.data.message : 'Sending leads in background. Redirecting to leads page.';
                    setApiMessage(successMessage + ' ' + redirectMessage, false);
                    setTimeout(function () {
                        window.location.href = payload.data && payload.data.redirect ? payload.data.redirect : '/leads';
                    }, 400);
                }).catch(function (error) {
                    saveDurationButton.disabled = false;
                    setApiMessage(error.message || 'Unable to save duration settings.', true);
                });
            });
        }
    }

    document.querySelectorAll('[data-stepper]').forEach(function (stepper) {
        var currentStep = stepper.getAttribute('data-current-step');
        var order = ['preview', 'region', 'assign', 'api-duration'];
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
