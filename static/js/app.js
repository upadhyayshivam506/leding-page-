document.addEventListener('DOMContentLoaded', function () {
    var regionOrder = ['North', 'South', 'East', 'West / Others'];
    var defaultPreviewHeaders = ['Lead ID', 'Name', 'Email', 'Phone', 'Course', 'Specialization', 'Campus', 'College Name', 'City', 'State', 'Region'];
    var globalToast = null;
    var globalToastTimer = 0;
    var pendingToastStorageKey = 'lead_upload_pending_toast';

    function ensureGlobalToast() {
        if (globalToast) {
            return globalToast;
        }

        globalToast = document.createElement('div');
        globalToast.setAttribute('role', 'status');
        globalToast.setAttribute('aria-live', 'polite');
        globalToast.style.position = 'fixed';
        globalToast.style.right = '28px';
        globalToast.style.bottom = '28px';
        globalToast.style.zIndex = '1600';
        globalToast.style.minWidth = '240px';
        globalToast.style.maxWidth = 'min(360px, calc(100vw - 32px))';
        globalToast.style.padding = '14px 18px';
        globalToast.style.borderRadius = '16px';
        globalToast.style.background = 'rgba(30, 160, 110, 0.96)';
        globalToast.style.color = '#f8fffc';
        globalToast.style.boxShadow = '0 18px 42px rgba(0, 0, 0, 0.32)';
        globalToast.style.opacity = '0';
        globalToast.style.pointerEvents = 'none';
        globalToast.style.transform = 'translateY(12px)';
        globalToast.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        globalToast.style.fontWeight = '600';
        document.body.appendChild(globalToast);

        return globalToast;
    }

    function showGlobalToast(message) {
        var toast = ensureGlobalToast();
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        if (globalToastTimer) {
            window.clearTimeout(globalToastTimer);
        }

        globalToastTimer = window.setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
        }, 2600);
    }

    function queueToastForNextPage(message) {
        try {
            window.sessionStorage.setItem(pendingToastStorageKey, message);
        } catch (error) {
            return;
        }
    }

    function flushQueuedToast() {
        try {
            var message = window.sessionStorage.getItem(pendingToastStorageKey);
            if (!message) {
                return;
            }

            window.sessionStorage.removeItem(pendingToastStorageKey);
            window.setTimeout(function () {
                showGlobalToast(message);
            }, 150);
        } catch (error) {
            return;
        }
    }

    function flushToastFromUrl() {
        try {
            var pageUrl = new URL(window.location.href);
            var message = pageUrl.searchParams.get('upload_notice');
            if (!message) {
                return;
            }

            pageUrl.searchParams.delete('upload_notice');
            window.history.replaceState({}, '', pageUrl.toString());
            window.setTimeout(function () {
                showGlobalToast(message);
            }, 150);
        } catch (error) {
            return;
        }
    }

    flushQueuedToast();
    flushToastFromUrl();

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
        var requestOptions = options || {};
        var method = String(requestOptions.method || 'GET').toUpperCase();
        var headers = new Headers(requestOptions.headers || {});
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';

        headers.set('Accept', 'application/json');
        if (csrfToken && method !== 'GET' && method !== 'HEAD') {
            headers.set('X-CSRF-Token', csrfToken);
        }

        requestOptions.headers = headers;

        return fetch(url, requestOptions).then(function (response) {
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

    function debounce(callback, wait) {
        var timeoutId = 0;

        return function () {
            var args = arguments;
            clearTimeout(timeoutId);
            timeoutId = window.setTimeout(function () {
                callback.apply(null, args);
            }, wait);
        };
    }

    function parseDisplayDate(value) {
        var trimmed = String(value || '').trim();
        if (!trimmed) {
            return { valid: true, normalized: '', raw: '' };
        }

        var match = trimmed.match(/^(\d{2})-(\d{2})-(\d{4})$/);
        if (!match) {
            return { valid: false, message: 'Use dd-mm-yyyy for date filters.' };
        }

        var day = Number(match[1]);
        var month = Number(match[2]);
        var year = Number(match[3]);
        var date = new Date(year, month - 1, day);

        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return { valid: false, message: 'Enter a valid calendar date in dd-mm-yyyy format.' };
        }

        return {
            valid: true,
            normalized: String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0'),
            raw: trimmed
        };
    }

    var leadsPageRoot = document.querySelector('[data-leads-page]');
    if (leadsPageRoot) {
        var leadsFilterForm = document.querySelector('[data-leads-filter-form]');
        var leadsTableRoot = document.querySelector('[data-leads-table-root]');
        var leadsTableHead = document.querySelector('[data-leads-table-head]');
        var leadsTableBody = document.querySelector('[data-leads-table-body]');
        var leadsPagination = document.querySelector('[data-leads-pagination]');
        var leadsCount = document.querySelector('[data-leads-count]');
        var leadsLoading = document.querySelector('[data-leads-loading]');
        var leadsFilterMessage = document.querySelector('[data-leads-filter-message]');
        var leadsSearchInput = leadsFilterForm ? leadsFilterForm.querySelector('[data-filter-search]') : null;
        var leadsDateFromInput = leadsFilterForm ? leadsFilterForm.querySelector('[data-filter-date-from]') : null;
        var leadsDateToInput = leadsFilterForm ? leadsFilterForm.querySelector('[data-filter-date-to]') : null;
        var leadsResetButton = leadsFilterForm ? leadsFilterForm.querySelector('[data-leads-reset]') : null;
        var exportButton = document.querySelector('[data-export-leads]');
        var sendToCollegeButton = document.querySelector('[data-open-send-college-modal]');
        var sendToCollegeMessage = document.querySelector('[data-leads-send-message]');
        var sendToCollegeModal = document.querySelector('[data-leads-send-college-modal]');
        var closeSendCollegeButtons = sendToCollegeModal ? Array.from(sendToCollegeModal.querySelectorAll('[data-close-send-college-modal]')) : [];
        var sendCollegeSelectionSummary = sendToCollegeModal ? sendToCollegeModal.querySelector('[data-send-college-selection-summary]') : null;
        var sendCollegeSearchInput = sendToCollegeModal ? sendToCollegeModal.querySelector('[data-college-search-input]') : null;
        var sendCollegeSelect = sendToCollegeModal ? sendToCollegeModal.querySelector('[data-college-single-select]') : null;
        var sendCollegeModalMessage = sendToCollegeModal ? sendToCollegeModal.querySelector('[data-send-college-modal-message]') : null;
        var confirmSendCollegeButton = sendToCollegeModal ? sendToCollegeModal.querySelector('[data-confirm-send-college]') : null;
        var multiselects = leadsFilterForm ? Array.from(leadsFilterForm.querySelectorAll('[data-filter-multiselect]')) : [];
        var collegesCatalog = readJsonAttribute(leadsPageRoot, 'data-colleges');
        var apiUrl = leadsPageRoot.getAttribute('data-leads-api-url') || '/api/leads';
        var exportUrl = leadsPageRoot.getAttribute('data-leads-export-url') || '/api/leads/export';
        var sendSelectedLeadsUrl = leadsPageRoot.getAttribute('data-send-selected-leads-url') || '';
        var leadPushStatusUrl = leadsPageRoot.getAttribute('data-lead-push-status-url') || '';
        var currentPage = Number(new URL(window.location.href).searchParams.get('page') || '1');
        var latestRequestId = 0;
        var isResetting = false;
        var leadUploadProgressPanel = document.querySelector('[data-lead-upload-progress-panel]');
        var leadUploadProgressTitle = document.querySelector('[data-lead-upload-progress-title]');
        var leadUploadProgressText = document.querySelector('[data-lead-upload-progress-text]');
        var leadUploadProgressChip = document.querySelector('[data-lead-upload-progress-chip]');
        var leadUploadProgressBar = document.querySelector('[data-lead-upload-progress-bar]');
        var activeLeadPushTimer = 0;
        var selectedLeadIds = new Set();

        function setSendToCollegeMessage(message, isError) {
            if (!sendToCollegeMessage) {
                return;
            }

            if (!message) {
                sendToCollegeMessage.textContent = '';
                sendToCollegeMessage.classList.add('d-none');
                sendToCollegeMessage.classList.remove('leads-filter-message--error', 'leads-filter-message--success');
                return;
            }

            sendToCollegeMessage.textContent = message;
            sendToCollegeMessage.classList.remove('d-none');
            sendToCollegeMessage.classList.toggle('leads-filter-message--error', !!isError);
            sendToCollegeMessage.classList.toggle('leads-filter-message--success', !isError);
        }

        function setSendCollegeModalMessage(message, isError) {
            if (!sendCollegeModalMessage) {
                return;
            }

            if (!message) {
                sendCollegeModalMessage.textContent = '';
                sendCollegeModalMessage.classList.add('d-none');
                sendCollegeModalMessage.classList.remove('mapping-preview-message--error', 'mapping-preview-message--success');
                return;
            }

            sendCollegeModalMessage.textContent = message;
            sendCollegeModalMessage.classList.remove('d-none');
            sendCollegeModalMessage.classList.toggle('mapping-preview-message--error', !!isError);
            sendCollegeModalMessage.classList.toggle('mapping-preview-message--success', !isError);
        }

        function selectedLeadIdList() {
            return Array.from(selectedLeadIds).map(function (value) {
                return Number(value || 0);
            }).filter(function (value) {
                return value > 0;
            });
        }

        function visibleLeadCheckboxes() {
            return leadsTableBody ? Array.from(leadsTableBody.querySelectorAll('[data-lead-select-row]')) : [];
        }

        function syncLeadSelectionUi() {
            var selectedIds = selectedLeadIdList();
            var allToggle = leadsTableHead ? leadsTableHead.querySelector('[data-lead-select-all]') : null;
            var visibleCheckboxes = visibleLeadCheckboxes();
            var visibleChecked = visibleCheckboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (allToggle) {
                allToggle.checked = visibleCheckboxes.length > 0 && visibleChecked === visibleCheckboxes.length;
                allToggle.indeterminate = visibleChecked > 0 && visibleChecked < visibleCheckboxes.length;
            }

            if (sendToCollegeButton) {
                sendToCollegeButton.disabled = selectedIds.length === 0;
            }

            if (sendCollegeSelectionSummary) {
                sendCollegeSelectionSummary.textContent = selectedIds.length
                    ? (String(selectedIds.length) + ' lead' + (selectedIds.length === 1 ? '' : 's') + ' selected for direct API sending.')
                    : 'No leads selected.';
            }
        }

        function restoreVisibleLeadSelection() {
            visibleLeadCheckboxes().forEach(function (checkbox) {
                checkbox.checked = selectedLeadIds.has(String(checkbox.value || ''));
            });
            syncLeadSelectionUi();
        }

        function renderCollegeOptions(searchTerm) {
            if (!sendCollegeSelect) {
                return;
            }

            var query = String(searchTerm || '').trim().toLowerCase();
            var currentValue = sendCollegeSelect.value || '';
            var filtered = (Array.isArray(collegesCatalog) ? collegesCatalog : []).filter(function (college) {
                var label = String(college && college.name || college && college.id || '').toLowerCase();
                return !query || label.indexOf(query) !== -1;
            });

            sendCollegeSelect.innerHTML = filtered.length
                ? filtered.map(function (college) {
                    var value = college.id || '';
                    var label = college.name || value;
                    var selected = value === currentValue ? ' selected' : '';
                    return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
                }).join('')
                : '<option value="">No colleges found</option>';

            if (filtered.length && !sendCollegeSelect.value) {
                sendCollegeSelect.selectedIndex = 0;
            }

            if (confirmSendCollegeButton) {
                confirmSendCollegeButton.disabled = filtered.length === 0;
            }
        }

        function openSendCollegeModal() {
            if (!sendToCollegeModal) {
                return;
            }

            renderCollegeOptions(sendCollegeSearchInput ? sendCollegeSearchInput.value : '');
            setSendCollegeModalMessage('', false);
            syncLeadSelectionUi();
            sendToCollegeModal.classList.remove('d-none');
            sendToCollegeModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-is-open');
            if (sendCollegeSearchInput) {
                sendCollegeSearchInput.focus();
            }
        }

        function closeSendCollegeModal() {
            if (!sendToCollegeModal) {
                return;
            }

            sendToCollegeModal.classList.add('d-none');
            sendToCollegeModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-is-open');
            setSendCollegeModalMessage('', false);
        }

        function setLeadMessage(message, isError) {
            if (!leadsFilterMessage) {
                return;
            }

            if (!message) {
                leadsFilterMessage.textContent = '';
                leadsFilterMessage.classList.add('d-none');
                leadsFilterMessage.classList.remove('leads-filter-message--error', 'leads-filter-message--success');
                return;
            }

            leadsFilterMessage.textContent = message;
            leadsFilterMessage.classList.remove('d-none');
            leadsFilterMessage.classList.toggle('leads-filter-message--error', !!isError);
            leadsFilterMessage.classList.toggle('leads-filter-message--success', !isError);
        }

        function setLeadsLoading(isLoading) {
            if (leadsLoading) {
                leadsLoading.classList.toggle('d-none', !isLoading);
            }
            if (leadsTableRoot) {
                leadsTableRoot.classList.toggle('is-loading', !!isLoading);
            }
        }

        function setLeadUploadPanelState(title, text, chip, progressPercent, stateClass) {
            if (!leadUploadProgressPanel) {
                return;
            }

            leadUploadProgressPanel.classList.remove('d-none', 'is-processing', 'is-completed', 'is-partial', 'is-failed');
            leadUploadProgressPanel.classList.add(stateClass || 'is-processing');

            if (leadUploadProgressTitle) {
                leadUploadProgressTitle.textContent = title;
            }
            if (leadUploadProgressText) {
                leadUploadProgressText.textContent = text;
            }
            if (leadUploadProgressChip) {
                leadUploadProgressChip.textContent = chip;
            }
            if (leadUploadProgressBar) {
                leadUploadProgressBar.style.width = String(Math.max(0, Math.min(100, Number(progressPercent || 0)))) + '%';
            }
        }

        function clearLeadPushQueryParams() {
            try {
                var pageUrl = new URL(window.location.href);
                pageUrl.searchParams.delete('lead_push_job_token');
                pageUrl.searchParams.delete('lead_push_total');
                window.history.replaceState({}, '', pageUrl.toString());
            } catch (error) {
                return;
            }
        }

        function stopLeadPushPolling() {
            if (activeLeadPushTimer) {
                window.clearTimeout(activeLeadPushTimer);
                activeLeadPushTimer = 0;
            }
        }

        function pollLeadPushProgress(jobToken, fallbackTotal) {
            if (!leadPushStatusUrl || !jobToken) {
                return;
            }

            fetchJson(leadPushStatusUrl + '?job_token=' + encodeURIComponent(jobToken)).then(function (payload) {
                var jobData = payload && payload.data ? payload.data : {};
                var fileStatus = String(jobData.file_status || jobData.status || 'Processing');
                var totalLeads = Number(jobData.total_leads || fallbackTotal || 0);
                var processedLeads = Number(jobData.processed_leads || 0);
                var progressPercent = totalLeads > 0 ? (processedLeads / totalLeads) * 100 : 0;
                var progressText = 'Uploading leads... ' + String(processedLeads) + ' of ' + String(totalLeads) + ' leads completed.';
                var stateClass = 'is-processing';
                var title = 'Uploading leads...';
                var chip = 'Processing';

                if (fileStatus === 'Completed') {
                    progressPercent = 100;
                    title = 'Lead upload completed';
                    chip = 'Completed';
                    progressText = String(processedLeads) + ' of ' + String(totalLeads) + ' leads completed successfully.';
                    stateClass = 'is-completed';
                } else if (fileStatus === 'Partial') {
                    progressPercent = 100;
                    title = 'Lead upload completed with partial failures';
                    chip = 'Partial';
                    progressText = String(processedLeads) + ' of ' + String(totalLeads) + ' leads processed. Some API requests failed.';
                    stateClass = 'is-partial';
                } else if (fileStatus === 'Failed') {
                    progressPercent = totalLeads > 0 ? progressPercent : 100;
                    title = 'Lead upload failed';
                    chip = 'Failed';
                    progressText = 'The API push failed for this upload batch.';
                    stateClass = 'is-failed';
                }

                setLeadUploadPanelState(title, progressText, chip, progressPercent, stateClass);

                if (fileStatus === 'Completed' || fileStatus === 'Partial' || fileStatus === 'Failed') {
                    stopLeadPushPolling();
                    window.setTimeout(clearLeadPushQueryParams, 300);
                    return;
                }

                activeLeadPushTimer = window.setTimeout(function () {
                    pollLeadPushProgress(jobToken, totalLeads || fallbackTotal);
                }, 1000);
            }).catch(function () {
                setLeadUploadPanelState(
                    'Uploading leads...',
                    'Waiting for live progress updates from the background job...',
                    'Processing',
                    4,
                    'is-processing'
                );

                activeLeadPushTimer = window.setTimeout(function () {
                    pollLeadPushProgress(jobToken, fallbackTotal);
                }, 2000);
            });
        }

        function updateSelectionSummary(select) {
            if (!select) {
                return;
            }

            var container = select.nextElementSibling;
            var rendered = container ? container.querySelector('.select2-selection__rendered') : null;
            if (!rendered) {
                return;
            }

            var count = selectValues(select).length;
            rendered.setAttribute('data-selection-summary', String(count) + ' selected');
            rendered.classList.add('leads-select2-rendered');
        }

        function initLeadMultiselect(select) {
            if (!select) {
                return;
            }

            if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function') {
                window.jQuery(select).select2({
                    width: '100%',
                    placeholder: select.getAttribute('data-placeholder') || 'Select options',
                    allowClear: true,
                    closeOnSelect: false
                });

                window.jQuery(select).on('change', function () {
                    updateSelectionSummary(select);
                    if (!isResetting) {
                        currentPage = 1;
                        debouncedLeadsFetch();
                    }
                });
            } else {
                select.addEventListener('change', function () {
                    if (!isResetting) {
                        currentPage = 1;
                        debouncedLeadsFetch();
                    }
                });
            }

            updateSelectionSummary(select);
        }

        function readLeadState(page) {
            return {
                search: leadsSearchInput ? leadsSearchInput.value.trim() : '',
                course: multiselects[0] ? selectValues(multiselects[0]) : [],
                state: multiselects[1] ? selectValues(multiselects[1]) : [],
                city: multiselects[2] ? selectValues(multiselects[2]) : [],
                lead_origin: multiselects[3] ? selectValues(multiselects[3]) : [],
                campaign: multiselects[4] ? selectValues(multiselects[4]) : [],
                lead_stage: multiselects[5] ? selectValues(multiselects[5]) : [],
                lead_status: multiselects[6] ? selectValues(multiselects[6]) : [],
                form_initiated: multiselects[7] ? selectValues(multiselects[7]) : [],
                paid_apps: multiselects[8] ? selectValues(multiselects[8]) : [],
                date_from: leadsDateFromInput ? leadsDateFromInput.value.trim() : '',
                date_to: leadsDateToInput ? leadsDateToInput.value.trim() : '',
                page: Math.max(1, Number(page || currentPage || 1)),
                limit: 20
            };
        }

        function validateLeadState(state) {
            var from = parseDisplayDate(state.date_from);
            var to = parseDisplayDate(state.date_to);
            if (!from.valid) {
                return from;
            }
            if (!to.valid) {
                return to;
            }
            if (from.normalized && to.normalized && from.normalized > to.normalized) {
                return { valid: false, message: 'The From date cannot be later than the To date.' };
            }

            return { valid: true };
        }

        function buildLeadQuery(state, includePaging) {
            var params = new URLSearchParams();

            if (state.search) {
                params.set('search', state.search);
            }

            ['course', 'state', 'city', 'lead_origin', 'campaign', 'lead_stage', 'lead_status', 'form_initiated', 'paid_apps'].forEach(function (key) {
                if (Array.isArray(state[key]) && state[key].length) {
                    params.set(key, state[key].join(','));
                }
            });

            if (state.date_from) {
                params.set('date_from', state.date_from);
            }
            if (state.date_to) {
                params.set('date_to', state.date_to);
            }

            if (includePaging !== false) {
                params.set('page', String(Math.max(1, Number(state.page || 1))));
                params.set('limit', String(state.limit || 20));
            }

            return params;
        }

        function syncLeadUrl(state) {
            var pageUrl = new URL(window.location.href);
            pageUrl.search = buildLeadQuery(state, true).toString();
            window.history.replaceState({}, '', pageUrl.toString());
        }

        function requestLeads(page) {
            var state = readLeadState(page);
            var validation = validateLeadState(state);
            if (!validation.valid) {
                setLeadMessage(validation.message || 'Please correct the selected filters.', true);
                return Promise.resolve();
            }

            setLeadMessage('', false);
            currentPage = state.page;
            latestRequestId += 1;
            var requestId = latestRequestId;
            setLeadsLoading(true);

            return fetchJson(apiUrl + '?' + buildLeadQuery(state, true).toString()).then(function (payload) {
                if (requestId !== latestRequestId) {
                    return;
                }

                if (leadsTableHead && payload.data && payload.data.table_head_html) {
                    leadsTableHead.innerHTML = payload.data.table_head_html;
                }
                if (leadsTableBody) {
                    leadsTableBody.innerHTML = payload.data && payload.data.table_body_html ? payload.data.table_body_html : '';
                }
                if (leadsPagination) {
                    leadsPagination.innerHTML = payload.data && payload.data.pagination_html ? payload.data.pagination_html : '';
                }
                if (leadsCount) {
                    leadsCount.textContent = payload.data && payload.data.count_label ? payload.data.count_label : '0 leads';
                }

                restoreVisibleLeadSelection();
                syncLeadUrl(state);
            }).catch(function (error) {
                setLeadMessage(error.message || 'Unable to load leads right now.', true);
            }).finally(function () {
                if (requestId === latestRequestId) {
                    setLeadsLoading(false);
                }
            });
        }

        var debouncedLeadsFetch = debounce(function () {
            requestLeads(1);
        }, 400);

        if (leadsFilterForm) {
            leadsFilterForm.addEventListener('submit', function (event) {
                event.preventDefault();
                requestLeads(1);
            });
        }

        if (leadsSearchInput) {
            leadsSearchInput.addEventListener('input', function () {
                currentPage = 1;
                debouncedLeadsFetch();
            });
        }

        [leadsDateFromInput, leadsDateToInput].forEach(function (input) {
            if (!input) {
                return;
            }

            input.addEventListener('change', function () {
                currentPage = 1;
                requestLeads(1);
            });
            input.addEventListener('blur', function () {
                currentPage = 1;
                requestLeads(1);
            });
        });

        if (leadsResetButton) {
            leadsResetButton.addEventListener('click', function () {
                isResetting = true;

                if (leadsSearchInput) {
                    leadsSearchInput.value = '';
                }
                if (leadsDateFromInput) {
                    leadsDateFromInput.value = '';
                }
                if (leadsDateToInput) {
                    leadsDateToInput.value = '';
                }

                multiselects.forEach(function (select) {
                    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function') {
                        window.jQuery(select).val(null).trigger('change');
                    } else {
                        Array.from(select.options).forEach(function (option) {
                            option.selected = false;
                        });
                    }
                    updateSelectionSummary(select);
                });

                isResetting = false;
                currentPage = 1;
                requestLeads(1);
            });
        }

        if (leadsPagination) {
            leadsPagination.addEventListener('click', function (event) {
                var target = event.target.closest('a.table-page-btn');
                if (!target) {
                    return;
                }

                event.preventDefault();
                var url = new URL(target.href, window.location.origin);
                var page = Number(url.searchParams.get('page') || '1');
                requestLeads(page);
            });
        }

        if (leadsTableRoot) {
            leadsTableRoot.addEventListener('change', function (event) {
                var rowCheckbox = event.target.closest('[data-lead-select-row]');
                if (rowCheckbox) {
                    if (rowCheckbox.checked) {
                        selectedLeadIds.add(String(rowCheckbox.value || ''));
                    } else {
                        selectedLeadIds.delete(String(rowCheckbox.value || ''));
                    }
                    setSendToCollegeMessage('', false);
                    syncLeadSelectionUi();
                    return;
                }

                var selectAllCheckbox = event.target.closest('[data-lead-select-all]');
                if (selectAllCheckbox) {
                    visibleLeadCheckboxes().forEach(function (checkbox) {
                        checkbox.checked = !!selectAllCheckbox.checked;
                        if (checkbox.checked) {
                            selectedLeadIds.add(String(checkbox.value || ''));
                        } else {
                            selectedLeadIds.delete(String(checkbox.value || ''));
                        }
                    });
                    setSendToCollegeMessage('', false);
                    syncLeadSelectionUi();
                }
            });
        }

        if (exportButton) {
            exportButton.addEventListener('click', function () {
                var state = readLeadState(1);
                var validation = validateLeadState(state);
                if (!validation.valid) {
                    setLeadMessage(validation.message || 'Please correct the selected filters before exporting.', true);
                    return;
                }

                setLeadMessage('', false);
                exportButton.disabled = true;

                fetch(exportUrl + '?' + buildLeadQuery(state, false).toString(), {
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) {
                        return response.text().then(function (text) {
                            throw new Error(text || 'Unable to export leads.');
                        });
                    }

                    var disposition = response.headers.get('Content-Disposition') || '';
                    var match = disposition.match(/filename=\"?([^\";]+)\"?/i);
                    var filename = match && match[1] ? match[1] : 'leads_export.csv';

                    return response.blob().then(function (blob) {
                        var blobUrl = window.URL.createObjectURL(blob);
                        var link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        window.URL.revokeObjectURL(blobUrl);
                    });
                }).catch(function (error) {
                    setLeadMessage(error.message || 'Unable to export leads right now.', true);
                }).finally(function () {
                    exportButton.disabled = false;
                });
            });
        }

        if (sendToCollegeButton) {
            sendToCollegeButton.addEventListener('click', function () {
                if (!selectedLeadIdList().length) {
                    setSendToCollegeMessage('Select one or more leads before sending.', true);
                    return;
                }

                setSendToCollegeMessage('', false);
                openSendCollegeModal();
            });
        }

        closeSendCollegeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                closeSendCollegeModal();
            });
        });

        if (sendCollegeSearchInput) {
            sendCollegeSearchInput.addEventListener('input', function () {
                renderCollegeOptions(sendCollegeSearchInput.value);
            });
        }

        if (confirmSendCollegeButton) {
            confirmSendCollegeButton.addEventListener('click', function () {
                var selectedIds = selectedLeadIdList();
                var selectedCollegeId = sendCollegeSelect ? String(sendCollegeSelect.value || '').trim() : '';

                if (!selectedIds.length) {
                    setSendCollegeModalMessage('Select one or more leads before sending.', true);
                    return;
                }

                if (!selectedCollegeId) {
                    setSendCollegeModalMessage('Select one college before sending.', true);
                    return;
                }

                confirmSendCollegeButton.disabled = true;
                setSendCollegeModalMessage('Sending selected leads to the chosen college API...', false);

                fetchJson(sendSelectedLeadsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        selected_ids: selectedIds,
                        college_id: selectedCollegeId
                    })
                }).then(function (payload) {
                    closeSendCollegeModal();
                    setSendToCollegeMessage(payload.message || 'Selected leads successfully sent to the college API.', false);
                    selectedLeadIds.clear();
                    restoreVisibleLeadSelection();
                }).catch(function (error) {
                    setSendCollegeModalMessage(error.message || 'Failed to send selected leads. Please try again.', true);
                    setSendToCollegeMessage(error.message || 'Failed to send selected leads. Please try again.', true);
                }).finally(function () {
                    confirmSendCollegeButton.disabled = false;
                });
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sendToCollegeModal && !sendToCollegeModal.classList.contains('d-none')) {
                closeSendCollegeModal();
            }
        });

        restoreVisibleLeadSelection();

        try {
            var leadPageUrl = new URL(window.location.href);
            var activeJobToken = leadPageUrl.searchParams.get('lead_push_job_token') || '';
            var activeJobTotal = Number(leadPageUrl.searchParams.get('lead_push_total') || '0');

            if (activeJobToken) {
                setLeadUploadPanelState(
                    'Uploading leads...',
                    'Uploading leads... 0 of ' + String(activeJobTotal) + ' leads completed.',
                    'Processing',
                    0,
                    'is-processing'
                );
                pollLeadPushProgress(activeJobToken, activeJobTotal);
            }
        } catch (error) {
            // Ignore URL parsing issues and keep the page working.
        }

        multiselects.forEach(initLeadMultiselect);
    }

    var regionMappingRoot = document.querySelector('[data-region-mapping-page]');
    if (regionMappingRoot) {
        var regionRows = readJsonAttribute(regionMappingRoot, 'data-region-rows');
        var regionSummary = readJsonAttribute(regionMappingRoot, 'data-region-summary');
        var confirmRegionRedirectButton = regionMappingRoot.querySelector('[data-confirm-region-redirect]');
        var regionHeaders = regionRows.length ? Object.keys(regionRows[0]) : defaultPreviewHeaders;
        var regionGrouped = groupRowsByRegion(regionRows);

        function renderRegionSummaryCards() {
            var root = regionMappingRoot.querySelector('[data-region-summary-cards]');
            if (!root) {
                return;
            }

            root.innerHTML = regionOrder.map(function (region) {
                var item = regionSummary.find(function (entry) {
                    return entry.region === region;
                }) || { total: 0 };

                return '<article class="metric-card mapping-region-card"><div class="metric-card__header"><span>' + escapeHtml(region) + '</span><span class="panel-chip">Leads</span></div><h3>' + escapeHtml(item.total) + '</h3><p>Region-wise grouping of leads is ready for mapping.</p></article>';
            }).join('');
        }

        function renderRegionPreviewGroups() {
            var root = regionMappingRoot.querySelector('[data-region-groups]');
            if (!root) {
                return;
            }

            root.innerHTML = regionOrder.map(function (region) {
                var previewRows = regionGrouped[region].slice(0, 8);
                return '<article class="region-group-card"><div class="panel-head panel-head--table"><div><h3>' + escapeHtml(region) + '</h3><p class="table-subtext">First 8 leads shown for quick review on all devices.</p></div><span class="panel-chip">' + escapeHtml(regionGrouped[region].length) + ' leads</span></div>' + renderCompactTable(previewRows, regionHeaders, 'No leads in this region.') + '</article>';
            }).join('');
        }

        renderRegionSummaryCards();
        renderRegionPreviewGroups();

        if (confirmRegionRedirectButton) {
            confirmRegionRedirectButton.addEventListener('click', function () {
                var target = regionMappingRoot.getAttribute('data-api-colleagues-url') || '/leads/mapping/region/api-colleagues';
                var nextUrl = new URL(target, window.location.origin);
                nextUrl.searchParams.set('region_summary_json', JSON.stringify(regionSummary));
                window.location.href = nextUrl.toString();
            });
        }
    }

    var coursesConvertRoot = document.querySelector('[data-courses-convert-page]');
    if (coursesConvertRoot) {
        var convertRows = readJsonAttribute(coursesConvertRoot, 'data-region-rows');
        var convertSummary = readJsonAttribute(coursesConvertRoot, 'data-region-summary');
        var convertColleges = readJsonAttribute(coursesConvertRoot, 'data-colleges');
        var convertRegionPicker = coursesConvertRoot.querySelector('[data-convert-region-picker]');
        var courseConversionBody = coursesConvertRoot.querySelector('[data-course-conversion-body]');
        var specializationConversionBody = coursesConvertRoot.querySelector('[data-specialization-conversion-body]');
        var convertCollegeSelect = coursesConvertRoot.querySelector('[data-convert-colleges]');
        var convertCollegeChipList = coursesConvertRoot.querySelector('[data-convert-college-chip-list]');
        var convertPreviewHead = coursesConvertRoot.querySelector('[data-convert-preview-head]');
        var convertPreviewBody = coursesConvertRoot.querySelector('[data-convert-preview-body]');
        var convertPreviewCount = coursesConvertRoot.querySelector('[data-convert-preview-count]');
        var convertPagination = coursesConvertRoot.querySelector('[data-convert-pagination]');
        var convertSearchInput = coursesConvertRoot.querySelector('[data-convert-search]');
        var convertPageSizeSelect = coursesConvertRoot.querySelector('[data-convert-page-size]');
        var convertMessage = coursesConvertRoot.querySelector('[data-convert-message]');
        var confirmCoursesConvertButton = coursesConvertRoot.querySelector('[data-confirm-courses-convert]');
        var convertPreviewHeaders = ['Batch ID', 'Lead ID', 'Name', 'Email', 'Phone', 'Region', 'Original Course', 'Converted Course', 'Specialization', 'Selected Colleges', 'Lead Status'];
        var selectedConvertRegions = regionOrder.filter(function (region) {
            return convertRows.some(function (row) {
                return row.Region === region;
            });
        });
        var courseConversions = {};
        var specializationOverrides = {};
        var selectedConvertColleges = [];
        var convertCurrentPage = 1;

        if (!selectedConvertRegions.length) {
            selectedConvertRegions = regionOrder.slice();
        }

        function setConvertMessage(message, isError) {
            if (!convertMessage) {
                return;
            }

            if (!message) {
                convertMessage.textContent = '';
                convertMessage.classList.add('d-none');
                convertMessage.classList.remove('mapping-preview-message--error', 'mapping-preview-message--success');
                return;
            }

            convertMessage.textContent = message;
            convertMessage.classList.remove('d-none');
            convertMessage.classList.toggle('mapping-preview-message--error', !!isError);
            convertMessage.classList.toggle('mapping-preview-message--success', !isError);
        }

        function detectedCoursesForSelectedRegions() {
            var values = [];

            convertRows.forEach(function (row) {
                var region = row.Region || '';
                var course = row.Course || '';
                if (selectedConvertRegions.indexOf(region) !== -1 && course && values.indexOf(course) === -1) {
                    values.push(course);
                }
            });

            return values.sort();
        }

        function selectedCollegeNames() {
            return selectedConvertColleges.map(function (collegeId) {
                var match = (Array.isArray(convertColleges) ? convertColleges : []).find(function (college) {
                    return college.id === collegeId;
                });

                return match ? match.name : collegeId;
            });
        }

        function buildConvertedPreviewRows() {
            var selectedCollegeLabel = selectedCollegeNames().join(', ');

            return convertRows.filter(function (row) {
                return selectedConvertRegions.indexOf(row.Region || '') !== -1;
            }).map(function (row) {
                var originalCourse = String(row.Course || '').trim();
                var convertedCourse = String(courseConversions[originalCourse] || '').trim() || originalCourse;
                var specialization = String(specializationOverrides[originalCourse] || '').trim() || String(row.Specialization || '').trim();

                return {
                    'Batch ID': String(row['Batch ID'] || ''),
                    'Lead ID': String(row.__lead_id || row['Lead ID'] || ''),
                    'Name': String(row.Name || ''),
                    'Email': String(row.Email || ''),
                    'Phone': String(row.Mobile || row.Phone || ''),
                    'Region': String(row.Region || ''),
                    'Original Course': originalCourse,
                    'Converted Course': convertedCourse,
                    'Specialization': specialization,
                    'Selected Colleges': selectedCollegeLabel,
                    'Lead Status': String(row.Status || '')
                };
            });
        }

        function filteredConvertedPreviewRows() {
            var search = String(convertSearchInput && convertSearchInput.value || '').trim().toLowerCase();
            var previewRows = buildConvertedPreviewRows();

            if (!search) {
                return previewRows;
            }

            return previewRows.filter(function (row) {
                return convertPreviewHeaders.some(function (header) {
                    return String(row[header] || '').toLowerCase().indexOf(search) !== -1;
                });
            });
        }

        function renderConvertPagination(totalPages) {
            if (!convertPagination) {
                return;
            }

            if (totalPages <= 1) {
                convertPagination.innerHTML = '';
                return;
            }

            var buttons = [];

            function button(label, page, disabled, active) {
                var classes = 'table-page-btn';
                if (active) {
                    classes += ' is-active';
                }
                if (disabled && !active) {
                    classes += ' is-disabled';
                }

                if (active || disabled) {
                    return '<span class="' + classes + '">' + escapeHtml(label) + '</span>';
                }

                return '<button type="button" class="' + classes + '" data-convert-page="' + escapeHtml(String(page)) + '">' + escapeHtml(label) + '</button>';
            }

            buttons.push(button('Prev', convertCurrentPage - 1, convertCurrentPage <= 1, false));
            for (var page = 1; page <= totalPages; page += 1) {
                buttons.push(button(String(page), page, false, page === convertCurrentPage));
            }
            buttons.push(button('Last', totalPages, convertCurrentPage >= totalPages, false));

            convertPagination.innerHTML = '<div class="table-pagination"><div class="table-pagination__inner">' + buttons.join('') + '</div></div>';
            convertPagination.querySelectorAll('[data-convert-page]').forEach(function (buttonNode) {
                buttonNode.addEventListener('click', function () {
                    convertCurrentPage = Number(buttonNode.getAttribute('data-convert-page') || '1');
                    renderConvertedPreview();
                });
            });
        }

        function renderConvertedPreview() {
            var previewRows = filteredConvertedPreviewRows();
            var pageSize = Number(convertPageSizeSelect && convertPageSizeSelect.value || '20');
            var totalRows = previewRows.length;
            var totalPages = Math.max(1, Math.ceil(totalRows / Math.max(1, pageSize)));

            if (convertCurrentPage > totalPages) {
                convertCurrentPage = totalPages;
            }

            var startIndex = (convertCurrentPage - 1) * pageSize;
            var visibleRows = previewRows.slice(startIndex, startIndex + pageSize);

            if (convertPreviewHead) {
                convertPreviewHead.innerHTML = '<tr>' + convertPreviewHeaders.map(function (header) {
                    return '<th>' + escapeHtml(header) + '</th>';
                }).join('') + '</tr>';
            }

            if (convertPreviewBody) {
                convertPreviewBody.innerHTML = visibleRows.length ? visibleRows.map(function (row) {
                    return '<tr>' + convertPreviewHeaders.map(function (header) {
                        return '<td>' + escapeHtml(row[header] || '') + '</td>';
                    }).join('') + '</tr>';
                }).join('') : '<tr><td colspan="' + String(convertPreviewHeaders.length) + '" class="table-empty-state">No converted leads matched the current filters.</td></tr>';
            }

            if (convertPreviewCount) {
                var fromRow = totalRows ? startIndex + 1 : 0;
                var toRow = totalRows ? Math.min(startIndex + pageSize, totalRows) : 0;
                convertPreviewCount.textContent = totalRows ? ('Showing ' + String(fromRow) + '-' + String(toRow) + ' of ' + String(totalRows) + ' leads') : '0 leads';
            }

            renderConvertPagination(totalPages);
        }

        function renderConvertRegionPicker() {
            if (!convertRegionPicker) {
                return;
            }

            convertRegionPicker.innerHTML = regionOrder.map(function (region) {
                var summaryRow = (Array.isArray(convertSummary) ? convertSummary : []).find(function (entry) {
                    return entry.region === region;
                }) || { total: 0 };
                var checked = selectedConvertRegions.indexOf(region) !== -1;

                return '<label class="mapping-region-option"><input type="checkbox" value="' + escapeHtml(region) + '"' + (checked ? ' checked' : '') + '> <span>' + escapeHtml(region) + ' (' + String(summaryRow.total || 0) + ')</span></label>';
            }).join('');

            convertRegionPicker.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    selectedConvertRegions = Array.from(convertRegionPicker.querySelectorAll('input:checked')).map(function (node) {
                        return node.value;
                    });
                    if (!selectedConvertRegions.length) {
                        selectedConvertRegions = regionOrder.slice();
                        renderConvertRegionPicker();
                    }
                    convertCurrentPage = 1;
                    renderCourseConversionRows();
                    renderSpecializationRows();
                    renderConvertedPreview();
                });
            });
        }

        function renderCourseConversionRows() {
            if (!courseConversionBody) {
                return;
            }

            var detectedCourses = detectedCoursesForSelectedRegions();
            courseConversionBody.innerHTML = detectedCourses.length ? detectedCourses.map(function (course) {
                return '<tr><td>' + escapeHtml(course) + '</td><td><input type="text" class="assignment-select mapping-inline-input" data-course-convert-input="' + escapeHtml(course) + '" value="' + escapeHtml(courseConversions[course] || '') + '" placeholder="Keep original course"></td></tr>';
            }).join('') : '<tr><td colspan="2" class="table-empty-state">No detected courses found for the selected regions.</td></tr>';

            courseConversionBody.querySelectorAll('[data-course-convert-input]').forEach(function (input) {
                input.addEventListener('input', function () {
                    courseConversions[input.getAttribute('data-course-convert-input') || ''] = input.value || '';
                    convertCurrentPage = 1;
                    renderConvertedPreview();
                });
            });
        }

        function renderSpecializationRows() {
            if (!specializationConversionBody) {
                return;
            }

            var detectedCourses = detectedCoursesForSelectedRegions();
            specializationConversionBody.innerHTML = detectedCourses.length ? detectedCourses.map(function (course) {
                return '<tr><td>' + escapeHtml(course) + '</td><td><input type="text" class="assignment-select mapping-inline-input" data-specialization-convert-input="' + escapeHtml(course) + '" value="' + escapeHtml(specializationOverrides[course] || '') + '" placeholder="Keep original specialization"></td></tr>';
            }).join('') : '<tr><td colspan="2" class="table-empty-state">No specialization suggestions found for the selected regions.</td></tr>';

            specializationConversionBody.querySelectorAll('[data-specialization-convert-input]').forEach(function (input) {
                input.addEventListener('input', function () {
                    specializationOverrides[input.getAttribute('data-specialization-convert-input') || ''] = input.value || '';
                    convertCurrentPage = 1;
                    renderConvertedPreview();
                });
            });
        }

        function renderConvertCollegeOptions() {
            if (!convertCollegeSelect) {
                return;
            }

            convertCollegeSelect.innerHTML = (Array.isArray(convertColleges) ? convertColleges : []).map(function (college) {
                var value = college.id || '';
                var label = college.name || value;
                var selected = selectedConvertColleges.indexOf(value) !== -1 ? ' selected' : '';

                return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
            }).join('');

            renderChipList(convertCollegeChipList, selectedCollegeNames(), 'No colleges selected yet.');
        }

        renderConvertRegionPicker();
        renderCourseConversionRows();
        renderSpecializationRows();
        renderConvertCollegeOptions();
        renderConvertedPreview();

        if (convertCollegeSelect) {
            convertCollegeSelect.addEventListener('change', function () {
                selectedConvertColleges = selectValues(convertCollegeSelect);
                convertCurrentPage = 1;
                renderConvertCollegeOptions();
                renderConvertedPreview();
            });
        }

        if (convertSearchInput) {
            convertSearchInput.addEventListener('input', function () {
                convertCurrentPage = 1;
                renderConvertedPreview();
            });
        }

        if (convertPageSizeSelect) {
            convertPageSizeSelect.addEventListener('change', function () {
                convertCurrentPage = 1;
                renderConvertedPreview();
            });
        }

        if (confirmCoursesConvertButton) {
            confirmCoursesConvertButton.addEventListener('click', function () {
                if (!selectedConvertColleges.length) {
                    setConvertMessage('Select one or more colleges before confirming the mapping.', true);
                    return;
                }

                confirmCoursesConvertButton.disabled = true;
                setConvertMessage('Saving converted mapping and preparing the API Duration step...', false);

                fetchJson(coursesConvertRoot.getAttribute('data-confirm-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: coursesConvertRoot.getAttribute('data-batch-id') || '',
                        regions: selectedConvertRegions,
                        course_conversions: courseConversions,
                        specialization_overrides: specializationOverrides,
                        college_ids: selectedConvertColleges
                    })
                }).then(function (payload) {
                    window.location.href = payload.data && payload.data.redirect
                        ? payload.data.redirect
                        : (coursesConvertRoot.getAttribute('data-api-duration-url') || '/leads/mapping/region/courses-convert/api-duration');
                }).catch(function (error) {
                    confirmCoursesConvertButton.disabled = false;
                    setConvertMessage(error.message || 'Unable to confirm the Courses Convert mapping.', true);
                });
            });
        }
    }

    var coursesConvertDurationRoot = document.querySelector('[data-courses-convert-duration-page]');
    if (coursesConvertDurationRoot) {
        var convertedApiRows = readJsonAttribute(coursesConvertDurationRoot, 'data-api-rows');
        var convertStartTime = coursesConvertDurationRoot.querySelector('[data-convert-start-time]');
        var convertEndTime = coursesConvertDurationRoot.querySelector('[data-convert-end-time]');
        var convertBatchSize = coursesConvertDurationRoot.querySelector('[data-convert-batch-size]');
        var convertDelay = coursesConvertDurationRoot.querySelector('[data-convert-delay]');
        var convertDurationMessage = coursesConvertDurationRoot.querySelector('[data-convert-duration-message]');
        var sendConvertedLeadsButton = coursesConvertDurationRoot.querySelector('[data-send-converted-leads]');

        function setConvertDurationMessage(message, isError) {
            if (!convertDurationMessage) {
                return;
            }

            if (!message) {
                convertDurationMessage.textContent = '';
                convertDurationMessage.classList.add('d-none');
                convertDurationMessage.classList.remove('mapping-preview-message--error', 'mapping-preview-message--success');
                return;
            }

            convertDurationMessage.textContent = message;
            convertDurationMessage.classList.remove('d-none');
            convertDurationMessage.classList.toggle('mapping-preview-message--error', !!isError);
            convertDurationMessage.classList.toggle('mapping-preview-message--success', !isError);
        }

        if (sendConvertedLeadsButton) {
            sendConvertedLeadsButton.addEventListener('click', function () {
                var startTime = String(convertStartTime && convertStartTime.value || '').trim();
                var endTime = String(convertEndTime && convertEndTime.value || '').trim();
                var batchSize = Number(convertBatchSize && convertBatchSize.value || '50');
                var delaySeconds = Number(convertDelay && convertDelay.value || '0.35');

                if (startTime && endTime && startTime > endTime) {
                    setConvertDurationMessage('End Time must be later than or equal to Start Time.', true);
                    return;
                }

                if (!batchSize || batchSize <= 0) {
                    setConvertDurationMessage('Batch Size must be greater than zero.', true);
                    return;
                }

                if (delaySeconds < 0) {
                    setConvertDurationMessage('Delay Between Requests must be zero or greater.', true);
                    return;
                }

                sendConvertedLeadsButton.disabled = true;
                setConvertDurationMessage('Queueing converted leads for the colleges API...', false);
                queueToastForNextPage(String(convertedApiRows.length || 0) + ' converted leads selected for API sending.');

                fetchJson(coursesConvertDurationRoot.getAttribute('data-send-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        start_time: startTime,
                        end_time: endTime,
                        batch_size: batchSize,
                        delay_between_requests: delaySeconds,
                        leads_data: Array.isArray(convertedApiRows) ? convertedApiRows : []
                    })
                }).then(function (payload) {
                    setConvertDurationMessage(payload.data && payload.data.confirmation ? payload.data.confirmation : 'Converted leads queued successfully. Redirecting to Leads...', false);
                    window.setTimeout(function () {
                        window.location.href = payload.data && payload.data.redirect ? payload.data.redirect : '/leads';
                    }, 250);
                }).catch(function (error) {
                    sendConvertedLeadsButton.disabled = false;
                    setConvertDurationMessage(error.message || 'Unable to send converted leads to the colleges API.', true);
                });
            });
        }
    }

    function normalizeAssignmentMap(assignments) {
        var normalized = {};

        regionOrder.forEach(function (region) {
            normalized[region] = Array.isArray(assignments && assignments[region]) ? assignments[region].slice() : [];
        });

        return normalized;
    }

    function initApiDurationFlow(apiDurationRoot, options) {
        if (!apiDurationRoot) {
            return null;
        }

        if (apiDurationRoot._apiDurationFlow) {
            return apiDurationRoot._apiDurationFlow;
        }

        options = options || {};

        var durationDefaults = readJsonAttribute(apiDurationRoot, 'data-duration-defaults');
        var selectedCollegeNames = readJsonAttribute(apiDurationRoot, 'data-selected-colleges');
        var selectedAssignments = normalizeAssignmentMap(readJsonAttribute(apiDurationRoot, 'data-region-assignments'));
        var regionSummaryData = readJsonAttribute(apiDurationRoot, 'data-region-summary');
        var leadRows = readJsonAttribute(apiDurationRoot, 'data-leads-data');
        var totalLeadCount = Number(apiDurationRoot.getAttribute('data-total-leads') || '0');
        var batchInput = apiDurationRoot.querySelector('[data-batch-size-input]') || apiDurationRoot.querySelector('[data-batch-size]');
        var apiDurationSelect = apiDurationRoot.querySelector('[data-api-duration-selection]');
        var delayInput = apiDurationRoot.querySelector('[data-delay-seconds-input]') || apiDurationRoot.querySelector('[data-delay-size]') || apiDurationSelect;
        var sendButton = apiDurationRoot.querySelector('[data-send-api-requests]') || apiDurationRoot.querySelector('[data-save-duration]');
        var apiDurationMessage = apiDurationRoot.querySelector('[data-api-duration-message]');
        var totalLeadsNode = apiDurationRoot.querySelector('[data-duration-total-leads]');
        var selectedColleaguesNode = apiDurationRoot.querySelector('[data-duration-selected-colleagues]');
        var selectedCollegesNode = apiDurationRoot.querySelector('[data-duration-selected-colleges]');
        var batchSizeNode = apiDurationRoot.querySelector('[data-duration-batch-size]');
        var delayNode = apiDurationRoot.querySelector('[data-duration-delay]');
        var estimateNode = apiDurationRoot.querySelector('[data-duration-estimate]');
        var selectedCollegeList = apiDurationRoot.querySelector('[data-selected-college-list]');
        var colleagueCatalog = options.colleagueCatalog && typeof options.colleagueCatalog === 'object' ? options.colleagueCatalog : {};
        var selectedDurationValue = apiDurationRoot.getAttribute('data-api-duration-value') || '';
        var jobStatusUrl = apiDurationRoot.getAttribute('data-job-status-url') || '';
        var leadsPageUrl = apiDurationRoot.getAttribute('data-leads-page-url') || '/leads';
        var activePollTimer = 0;
        var nextLeadMilestone = 0;
        var activeJobToken = '';
        var completionAlertShown = false;
        var lastAlertedLeadCount = 0;

        function deriveSelectedCollegeNames(assignments) {
            var names = [];

            regionOrder.forEach(function (region) {
                var regionCatalog = Array.isArray(colleagueCatalog[region]) ? colleagueCatalog[region] : [];
                (Array.isArray(assignments[region]) ? assignments[region] : []).forEach(function (id) {
                    var match = regionCatalog.find(function (college) {
                        return college.id === id;
                    });
                    var label = match ? match.name : id;
                    if (label && names.indexOf(label) === -1) {
                        names.push(label);
                    }
                });
            });

            return names;
        }

        function selectedAssignmentCount() {
            var unique = [];

            regionOrder.forEach(function (region) {
                (Array.isArray(selectedAssignments[region]) ? selectedAssignments[region] : []).forEach(function (id) {
                    if (unique.indexOf(id) === -1) {
                        unique.push(id);
                    }
                });
            });

            return unique.length;
        }

        function parseDurationValue(rawValue) {
            var normalized = String(rawValue == null ? '' : rawValue).trim().toLowerCase().replace('sec', '').trim();
            var value = Number(normalized);

            return value >= 0 ? value : 0;
        }

        function readBatchSize() {
            var value = Number(batchInput ? batchInput.value : 0);
            if (!value && durationDefaults && durationDefaults.batch_size) {
                value = Number(durationDefaults.batch_size);
            }

            return value > 0 ? value : 0;
        }

        function readDelaySeconds() {
            var value = parseDurationValue(delayInput ? delayInput.value : 0);
            if (!value && value !== 0 && durationDefaults && durationDefaults.delay !== undefined) {
                value = parseDurationValue(durationDefaults.delay);
            }

            return value >= 0 ? value : 0;
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

        function stopProgressPolling() {
            if (activePollTimer) {
                window.clearTimeout(activePollTimer);
                activePollTimer = 0;
            }
        }

        function showLeadUploadAlert(message) {
            showGlobalToast(message);
        }

        function totalLeadCountForUpload() {
            return Array.isArray(leadRows) && leadRows.length ? leadRows.length : totalLeadCount;
        }

        function buildLeadsRedirectUrl(message) {
            var separator = leadsPageUrl.indexOf('?') === -1 ? '?' : '&';
            return leadsPageUrl + separator + 'upload_notice=' + encodeURIComponent(message);
        }

        function scheduleNextMilestone(processedLeads, batchSize, totalLeads) {
            if (batchSize <= 0 || totalLeads <= 0) {
                nextLeadMilestone = 0;
                return;
            }

            nextLeadMilestone = Math.min(totalLeads, Math.floor(processedLeads / batchSize + 1) * batchSize);
            if (nextLeadMilestone <= 0) {
                nextLeadMilestone = Math.min(batchSize, totalLeads);
            }
        }

        function triggerMilestoneAlerts(processedLeads, batchSize, totalLeads) {
            if (batchSize <= 0 || totalLeads <= 0) {
                return;
            }

            if (nextLeadMilestone <= 0) {
                nextLeadMilestone = Math.min(batchSize, totalLeads);
            }

            while (nextLeadMilestone > 0 && nextLeadMilestone < totalLeads && processedLeads >= nextLeadMilestone) {
                showLeadUploadAlert(String(nextLeadMilestone) + ' leads uploaded.');
                lastAlertedLeadCount = nextLeadMilestone;
                nextLeadMilestone = Math.min(totalLeads, nextLeadMilestone + batchSize);
            }
        }

        function finalizeLeadUpload(jobData) {
            stopProgressPolling();
            sendButton.disabled = false;
            var processedLeadCount = Number(jobData && jobData.processed_leads != null ? jobData.processed_leads : 0);
            var totalLeadCountValue = Number(jobData && jobData.total_leads != null ? jobData.total_leads : 0);

            if (processedLeadCount > 0 && processedLeadCount !== lastAlertedLeadCount) {
                showLeadUploadAlert(String(processedLeadCount) + ' leads uploaded.');
                lastAlertedLeadCount = processedLeadCount;
            }

            if (!completionAlertShown) {
                completionAlertShown = true;
                showLeadUploadAlert('Successfully uploaded all leads.');
            }

            setApiMessage(
                'Successfully uploaded all leads. ' +
                String(processedLeadCount) +
                ' of ' +
                String(totalLeadCountValue) +
                ' leads processed.',
                false
            );

            window.setTimeout(function () {
                queueToastForNextPage('Successfully uploaded all leads.');
                window.location.href = buildLeadsRedirectUrl('Successfully uploaded all leads.');
            }, 300);
        }

        function pollLeadUploadProgress() {
            if (!jobStatusUrl || !activeJobToken) {
                sendButton.disabled = false;
                setApiMessage('Lead push started, but live progress is unavailable right now.', true);
                return;
            }

            fetchJson(jobStatusUrl + '?job_token=' + encodeURIComponent(activeJobToken)).then(function (payload) {
                var jobData = payload && payload.data ? payload.data : {};
                var processedLeads = Number(jobData.processed_leads || 0);
                var totalLeads = Number(jobData.total_leads || 0);
                var batchSize = Number(jobData.batch_size || readBatchSize() || 0);
                var status = String(jobData.status || 'queued');

                triggerMilestoneAlerts(processedLeads, batchSize, totalLeads);
                setApiMessage('Uploading leads... ' + String(processedLeads) + ' of ' + String(totalLeads) + ' leads completed.', false);

                if (status === 'completed' || (totalLeads > 0 && processedLeads >= totalLeads)) {
                    finalizeLeadUpload(jobData);
                    return;
                }

                activePollTimer = window.setTimeout(pollLeadUploadProgress, 1000);
            }).catch(function (error) {
                stopProgressPolling();
                sendButton.disabled = false;
                setApiMessage(error.message || 'Unable to fetch upload progress.', true);
            });
        }

        function updateDurationSummary() {
            var totalLeads = Array.isArray(leadRows) && leadRows.length ? leadRows.length : totalLeadCount;
            var batchSize = readBatchSize();
            var delay = readDelaySeconds();
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

            renderChipList(selectedCollegeList, Array.isArray(selectedCollegeNames) ? selectedCollegeNames : [], 'No colleges selected yet.');
        }

        function setState(nextState) {
            nextState = nextState || {};

            if (nextState.assignments) {
                selectedAssignments = normalizeAssignmentMap(nextState.assignments);
            }
            if (Array.isArray(nextState.regionSummary)) {
                regionSummaryData = nextState.regionSummary.slice();
            }
            if (Array.isArray(nextState.leadRows)) {
                leadRows = nextState.leadRows.slice();
                totalLeadCount = leadRows.length;
            }
            if (Array.isArray(nextState.selectedCollegeNames)) {
                selectedCollegeNames = nextState.selectedCollegeNames.slice();
            } else if (!Array.isArray(selectedCollegeNames) || !selectedCollegeNames.length) {
                selectedCollegeNames = deriveSelectedCollegeNames(selectedAssignments);
            }

            updateDurationSummary();
        }

        function show() {
            toggleNode(apiDurationRoot, true);
            updateDurationSummary();
        }

        function hide() {
            toggleNode(apiDurationRoot, false);
        }

        if ((!Array.isArray(selectedCollegeNames) || !selectedCollegeNames.length) && Object.keys(colleagueCatalog).length) {
            selectedCollegeNames = deriveSelectedCollegeNames(selectedAssignments);
        }

        if (durationDefaults && typeof durationDefaults === 'object') {
            if (batchInput && durationDefaults.batch_size) {
                batchInput.value = batchInput.value || String(durationDefaults.batch_size);
            }
            if (delayInput && delayInput !== apiDurationSelect && durationDefaults.delay !== undefined) {
                delayInput.value = delayInput.value || String(durationDefaults.delay);
            }
        }

        if (apiDurationSelect) {
            apiDurationSelect.value = selectedDurationValue || (durationDefaults && durationDefaults.api_duration ? String(durationDefaults.api_duration) : '0.35');
        }

        updateDurationSummary();

        if (batchInput) {
            batchInput.addEventListener('input', updateDurationSummary);
        }
        if (delayInput) {
            delayInput.addEventListener('input', updateDurationSummary);
        }

        if (sendButton) {
            sendButton.addEventListener('click', function () {
                var batchSize = readBatchSize();
                var delay = readDelaySeconds();
                var apiDurationValue = apiDurationSelect ? apiDurationSelect.value : '0.35';

                if (batchSize <= 0) {
                    setApiMessage('Batch size must be greater than zero.', true);
                    return;
                }

                if (delay < 0) {
                    setApiMessage('Delay between batches cannot be negative.', true);
                    return;
                }

                sendButton.disabled = true;
                showLeadUploadAlert(String(totalLeadCountForUpload()) + ' leads selected for upload.');
                queueToastForNextPage(String(totalLeadCountForUpload()) + ' leads selected for upload.');
                setApiMessage('Sending API requests in the configured batch flow...', false);
                stopProgressPolling();
                completionAlertShown = false;
                activeJobToken = '';
                lastAlertedLeadCount = 0;
                scheduleNextMilestone(0, batchSize, totalLeadCountForUpload());

                fetchJson(apiDurationRoot.getAttribute('data-save-duration-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_size: batchSize,
                        delay: delay,
                        delay_duration: delay,
                        api_duration_selection: apiDurationValue,
                        assignments: selectedAssignments,
                        assigned_colleagues: selectedAssignments,
                        leads_data: Array.isArray(leadRows) ? leadRows : [],
                        region_data: Array.isArray(regionSummaryData) ? regionSummaryData : []
                    })
                }).then(function (payload) {
                    var successMessage = payload.data && payload.data.confirmation ? payload.data.confirmation : 'Duration settings saved successfully.';
                    var redirectMessage = payload.data && payload.data.message ? payload.data.message : 'API requests are being processed in the background.';
                    activeJobToken = payload.data && payload.data.job_token ? String(payload.data.job_token) : '';
                    setApiMessage(successMessage + ' ' + redirectMessage + ' Redirecting to Leads...', false);
                    window.setTimeout(function () {
                        window.location.href = payload.data && payload.data.redirect
                            ? payload.data.redirect
                            : leadsPageUrl;
                    }, 250);
                }).catch(function (error) {
                    stopProgressPolling();
                    sendButton.disabled = false;
                    setApiMessage(error.message || 'Unable to save duration settings.', true);
                });
            });
        }

        apiDurationRoot._apiDurationFlow = {
            root: apiDurationRoot,
            show: show,
            hide: hide,
            setState: setState,
            setMessage: setApiMessage
        };

        return apiDurationRoot._apiDurationFlow;
    }

    var regionColleaguesRoot = document.querySelector('[data-region-colleagues-page]');
    if (regionColleaguesRoot) {
        var regionSummary = readJsonAttribute(regionColleaguesRoot, 'data-region-summary');
        var colleagueCatalog = readJsonAttribute(regionColleaguesRoot, 'data-colleague-catalog');
        var existingAssignments = normalizeAssignmentMap(readJsonAttribute(regionColleaguesRoot, 'data-region-assignments'));
        var assignmentGrid = regionColleaguesRoot.querySelector('[data-assignment-grid]');
        var summaryBody = regionColleaguesRoot.querySelector('[data-region-summary-body]');
        var confirmAssignButton = regionColleaguesRoot.querySelector('[data-confirm-assign]');
        var assignMessage = regionColleaguesRoot.querySelector('[data-assign-message]');
        var assignments = normalizeAssignmentMap(existingAssignments);
        var summaryLookup = {};
        var apiDurationFlow = initApiDurationFlow(regionColleaguesRoot.querySelector('[data-api-duration-page]'), {
            colleagueCatalog: colleagueCatalog
        });

        (Array.isArray(regionSummary) ? regionSummary : []).forEach(function (entry) {
            if (entry && entry.region) {
                summaryLookup[entry.region] = Number(entry.total || 0);
            }
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
                }) || { total: Number(summaryLookup[region] || 0) };

                return '<tr><td>' + escapeHtml(region) + '</td><td>' + escapeHtml(entry.total) + '</td></tr>';
            }).join('');
        }

        function selectedCollegeNamesForAssignments() {
            var names = [];

            regionOrder.forEach(function (region) {
                (Array.isArray(assignments[region]) ? assignments[region] : []).forEach(function (id) {
                    var match = catalogForRegion(region).find(function (college) {
                        return college.id === id;
                    });
                    var label = match ? match.name : id;
                    if (label && names.indexOf(label) === -1) {
                        names.push(label);
                    }
                });
            });

            return names;
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
            }), catalogForRegion(region).length ? 'No colleagues selected.' : 'None');
        }

        function renderAssignments() {
            if (!assignmentGrid) {
                return;
            }

            assignmentGrid.innerHTML = regionOrder.map(function (region) {
                var options = catalogForRegion(region);
                var leadCount = Number(summaryLookup[region] || 0);
                var copy = leadCount > 0
                    ? String(leadCount) + ' leads ready for assignment'
                    : 'No leads ready for assignment';
                var hasOptions = options.length > 0;
                var hint = hasOptions
                    ? String(options.length) + ' colleagues available for this region.'
                    : 'No colleagues are configured for this region. Leads from this region will not be passed.';

                return '<article class="info-panel assignment-card" data-region-card="' + escapeHtml(region) + '"><p class="hero-label">' + escapeHtml(region) + '</p><h3>' + escapeHtml(region) + ' Region</h3><p>' + escapeHtml(copy) + '</p><p class="assignment-card__hint">' + escapeHtml(hint) + '</p><label class="form-label">Select Colleagues<select class="assignment-select" data-region-assignment="' + escapeHtml(region) + '" multiple' + (hasOptions ? '' : ' disabled') + '>' + (hasOptions ? options.map(function (college) {
                    var value = college.id || '';
                    var label = college.name || value;
                    var selected = assignments[region].indexOf(value) !== -1 ? ' selected' : '';
                    return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
                }).join('') : '<option value=\"\" disabled selected>None</option>') + '</select></label><p class="assignment-card__error d-none" data-region-error="' + escapeHtml(region) + '">Select at least one colleague for this region before continuing.</p><div class="mapping-chip-list" data-region-chip-list="' + escapeHtml(region) + '"></div></article>';
            }).join('');

            assignmentGrid.querySelectorAll('[data-region-assignment]').forEach(function (select) {
                select.addEventListener('change', function () {
                    var region = select.getAttribute('data-region-assignment') || '';
                    assignments[region] = selectValues(select);
                    renderRegionChips(region);
                    refreshRegionValidation();
                    if (apiDurationFlow && !apiDurationFlow.root.classList.contains('d-none')) {
                        apiDurationFlow.hide();
                        apiDurationFlow.setState({
                            assignments: assignments,
                            selectedCollegeNames: selectedCollegeNamesForAssignments()
                        });
                        setAssignMessage('Assignment selections changed. Confirm & Assign again to continue with API requests.', false);
                    }
                });
            });
        }

        function refreshRegionValidation() {
            if (!assignmentGrid) {
                return;
            }

            regionOrder.forEach(function (region) {
                var card = assignmentGrid.querySelector('[data-region-card="' + region + '"]');
                var error = assignmentGrid.querySelector('[data-region-error="' + region + '"]');
                var shouldShowError = Number(summaryLookup[region] || 0) > 0
                    && catalogForRegion(region).length > 0
                    && (!Array.isArray(assignments[region]) || !assignments[region].length);

                if (card) {
                    card.classList.toggle('assignment-card--error', shouldShowError);
                }
                if (error) {
                    error.classList.toggle('d-none', !shouldShowError);
                }
            });
        }

        function firstInvalidRegion() {
            var invalidRegion = '';

            regionOrder.some(function (region) {
                if (Number(summaryLookup[region] || 0) > 0
                    && catalogForRegion(region).length > 0
                    && (!Array.isArray(assignments[region]) || !assignments[region].length)) {
                    invalidRegion = region;
                    return true;
                }

                return false;
            });

            return invalidRegion;
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
        refreshRegionValidation();

        if (apiDurationFlow) {
            apiDurationFlow.setState({
                assignments: assignments,
                selectedCollegeNames: selectedCollegeNamesForAssignments()
            });
        }

        if (confirmAssignButton) {
            confirmAssignButton.addEventListener('click', function () {
                var invalidRegion = firstInvalidRegion();
                if (invalidRegion) {
                    refreshRegionValidation();
                    setAssignMessage('Select at least one colleague for the ' + invalidRegion + ' region before continuing.', true);
                    return;
                }

                confirmAssignButton.disabled = true;
                setAssignMessage('Saving region-wise colleague assignments...', false);
                fetchJson(regionColleaguesRoot.getAttribute('data-confirm-assign-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        assignments: assignments
                    })
                }).then(function (payload) {
                    confirmAssignButton.disabled = false;
                    setAssignMessage(payload.data && payload.data.message ? payload.data.message : 'Assignments confirmed. API Duration configuration is ready below.', false);

                    if (apiDurationFlow) {
                        apiDurationFlow.setState({
                            assignments: assignments,
                            selectedCollegeNames: selectedCollegeNamesForAssignments()
                        });
                        apiDurationFlow.show();
                        apiDurationFlow.root.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }).catch(function (error) {
                    confirmAssignButton.disabled = false;
                    setAssignMessage(error.message || 'Unable to confirm assignments.', true);
                });
            });
        }
    }

    var dashboardUploadHistoryRoot = document.querySelector('[data-dashboard-upload-history]');
    if (dashboardUploadHistoryRoot) {
        var dashboardHistoryUrl = dashboardUploadHistoryRoot.getAttribute('data-dashboard-upload-history-url') || '';
        var dashboardPollTimer = 0;

        function updateDashboardUploadHistory(payload) {
            var data = payload && payload.data ? payload.data : {};
            var totalUploadedFiles = dashboardUploadHistoryRoot.querySelector('[data-dashboard-total-uploaded-files]');
            var totalLeads = dashboardUploadHistoryRoot.querySelector('[data-dashboard-total-leads]');
            var leadsSent = dashboardUploadHistoryRoot.querySelector('[data-dashboard-leads-sent]');
            var failedLeads = dashboardUploadHistoryRoot.querySelector('[data-dashboard-failed-leads]');
            var successRate = document.querySelector('[data-dashboard-success-rate]');
            var uploadActivity = document.querySelector('[data-dashboard-upload-activity]');
            var processingStatus = document.querySelector('[data-dashboard-processing-status]');
            var recentUploads = document.querySelector('[data-dashboard-recent-uploads]');

            if (totalUploadedFiles && data.total_uploaded_files != null) {
                totalUploadedFiles.textContent = String(data.total_uploaded_files);
            }
            if (totalLeads && data.total_leads != null) {
                totalLeads.textContent = String(data.total_leads);
            }
            if (leadsSent && data.leads_sent != null) {
                leadsSent.textContent = String(data.leads_sent);
            }
            if (failedLeads && data.failed_leads != null) {
                failedLeads.textContent = String(data.failed_leads);
            }
            if (successRate && data.processing_success_rate != null) {
                successRate.textContent = String(data.processing_success_rate);
            }
            if (uploadActivity && data.upload_activity_bars_html != null) {
                uploadActivity.innerHTML = String(data.upload_activity_bars_html);
            }
            if (processingStatus && data.processing_status_rows_html != null) {
                processingStatus.innerHTML = String(data.processing_status_rows_html);
            }
            if (recentUploads && data.recent_uploaded_files_rows_html != null) {
                recentUploads.innerHTML = String(data.recent_uploaded_files_rows_html);
            }
        }

        function pollDashboardUploadHistory() {
            if (!dashboardHistoryUrl) {
                return;
            }

            fetchJson(dashboardHistoryUrl).then(function (payload) {
                updateDashboardUploadHistory(payload);
                dashboardPollTimer = window.setTimeout(pollDashboardUploadHistory, 5000);
            }).catch(function () {
                dashboardPollTimer = window.setTimeout(pollDashboardUploadHistory, 10000);
            });
        }

        pollDashboardUploadHistory();

        window.addEventListener('beforeunload', function () {
            if (dashboardPollTimer) {
                window.clearTimeout(dashboardPollTimer);
            }
        });
    }

    document.querySelectorAll('[data-api-duration-page]').forEach(function (root) {
        initApiDurationFlow(root);
    });

    document.querySelectorAll('[data-stepper]').forEach(function (stepper) {
        var currentStep = stepper.getAttribute('data-current-step');
        var steps = Array.from(stepper.querySelectorAll('.mapping-step'));
        var currentIndex = steps.findIndex(function (step) {
            return step.getAttribute('data-step-id') === currentStep;
        });

        steps.forEach(function (step, index) {
            if (index < currentIndex) {
                step.classList.add('is-complete');
            }
            if (index === currentIndex) {
                step.classList.add('is-current');
            }
        });
    });
});
