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
        var multiselects = leadsFilterForm ? Array.from(leadsFilterForm.querySelectorAll('[data-filter-multiselect]')) : [];
        var apiUrl = leadsPageRoot.getAttribute('data-leads-api-url') || '/api/leads';
        var exportUrl = leadsPageRoot.getAttribute('data-leads-export-url') || '/api/leads/export';
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

    var coursesMappingRoot = document.querySelector('[data-course-mapping-page]');
    if (coursesMappingRoot) {
        var rows = readJsonAttribute(coursesMappingRoot, 'data-region-rows');
        var colleges = readJsonAttribute(coursesMappingRoot, 'data-colleges');
        var headers = rows.length ? Object.keys(rows[0]) : defaultPreviewHeaders;
        var grouped = groupRowsByRegion(rows);
        var regionPicker = coursesMappingRoot.querySelector('[data-region-picker]');
        var selectedRegionSections = coursesMappingRoot.querySelector('[data-selected-region-sections]');
        var courseSelect = coursesMappingRoot.querySelector('[data-course-values]');
        var specializationSelect = coursesMappingRoot.querySelector('[data-specialization-select]');
        var collegeSelect = coursesMappingRoot.querySelector('[data-college-select]');
        var courseChipList = coursesMappingRoot.querySelector('[data-course-chip-list]');
        var collegeChipList = coursesMappingRoot.querySelector('[data-college-chip-list]');
        var errorNode = coursesMappingRoot.querySelector('[data-course-mapping-error]');
        var generateButton = coursesMappingRoot.querySelector('[data-generate-preview]');
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

        renderRegionPicker();
        updateDependentFields();
        updateCollegeOptions();

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

        if (generateButton) {
            generateButton.addEventListener('click', function () {
                if (!selectedSpecialization) {
                    errorNode.textContent = 'Select one specialization before confirming the mapping.';
                    errorNode.classList.remove('d-none');
                    return;
                }

                if (!selectedColleges.length) {
                    errorNode.textContent = 'Select one or more colleges before confirming the mapping.';
                    errorNode.classList.remove('d-none');
                    return;
                }

                generateButton.disabled = true;
                fetchJson(coursesMappingRoot.getAttribute('data-generate-preview-url'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: coursesMappingRoot.getAttribute('data-batch-id') || '',
                        regions: selectedRegions,
                        column: 'Course',
                        course_values: selectedCourses,
                        specialization: selectedSpecialization,
                        college_ids: selectedColleges
                    })
                }).then(function () {
                    window.location.href = coursesMappingRoot.getAttribute('data-preview-page-url');
                }).catch(function (error) {
                    errorNode.textContent = error.message || 'Unable to confirm the mapping.';
                    errorNode.classList.remove('d-none');
                }).finally(function () {
                    generateButton.disabled = false;
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

    var previewRoot = document.querySelector('[data-mapping-courses-preview]');
    if (previewRoot) {
        var confirmButton = previewRoot.querySelector('[data-confirm-mapping]');
        var durationMessage = previewRoot.querySelector('[data-duration-message]');

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
