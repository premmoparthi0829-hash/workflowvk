/**
 * VK Logistics Hub - Handwritten Form OCR & Auto-Fill Controller
 * Uploads physical/handwritten form scans, calls the extraction API,
 * auto-populates fields across all 5 tabs in joining_form.php,
 * highlights filled values, and provides side-by-side scan viewing.
 */

(function(window, document) {
    'use strict';

    let currentScanFileUrl = '';

    // Initialize Auto-Fill System
    function init() {
        bindEvents();
        setupMessageListeners();
    }

    function setupMessageListeners() {
        window.addEventListener('message', function(e) {
            if (e.data && e.data.type === 'vk_ocr_autofill') {
                window.receiveExtractedData(
                    e.data.extracted_data,
                    e.data.scan_file_path,
                    e.data.mode,
                    e.data.message
                );
            } else if (e.data && e.data.type === 'vk_open_ocr_modal') {
                if (typeof window.openOcrModal === 'function') {
                    window.openOcrModal();
                }
            }
        });
    }

    function bindEvents() {
        const dropZone = document.getElementById('ocrDropZone');
        const fileInput = document.getElementById('ocrFileInput');

        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('border-primary', 'bg-light');
            });

            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-primary', 'bg-light');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('border-primary', 'bg-light');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileSelected(fileInput.files[0]);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files.length > 0) {
                    handleFileSelected(fileInput.files[0]);
                }
            });
        }
    }

    function handleFileSelected(file) {
        if (!file) return;

        const fileNameEl = document.getElementById('ocrSelectedFileName');
        const fileSizeEl = document.getElementById('ocrSelectedFileSize');
        const fileInfoEl = document.getElementById('ocrFileInfo');
        const btnUpload = document.getElementById('btnStartOcrUpload');

        if (fileNameEl) fileNameEl.textContent = file.name;
        if (fileSizeEl) fileSizeEl.textContent = '(' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
        if (fileInfoEl) fileInfoEl.style.display = 'block';
        if (btnUpload) btnUpload.disabled = false;
    }

    // Modal Control
    window.openOcrModal = function() {
        // If running inside iframe, request parent window to open its centered viewport modal
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({ type: 'vk_open_ocr_modal' }, '*');
                return;
            } catch (e) {
                console.warn('Could not postMessage to parent, opening locally', e);
            }
        }

        const modalEl = document.getElementById('ocrUploadModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        } else if (modalEl) {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
        }
    };

    window.closeOcrModal = function() {
        const modalEl = document.getElementById('ocrUploadModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        } else if (modalEl) {
            modalEl.style.display = 'none';
            modalEl.classList.remove('show');
        }
    };

    // Calculate dynamic API URL depending on current folder location
    function getApiUrl() {
        const path = window.location.pathname;
        if (path.includes('/dashboard/management/')) {
            return '../../api/extract_handwritten_form.php';
        } else if (path.includes('/dashboard/')) {
            return '../api/extract_handwritten_form.php';
        }
        return 'api/extract_handwritten_form.php';
    }

    // Upload & Extract Execution
    window.startOcrProcessing = function() {
        const fileInput = document.getElementById('ocrFileInput');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert('Please select or drop a physical form document (PDF or image).');
            return;
        }

        const file = fileInput.files[0];
        const apiKeyInput = document.getElementById('ocrCustomApiKey');
        const customApiKey = apiKeyInput ? apiKeyInput.value.trim() : '';

        // UI Progress State
        const progressContainer = document.getElementById('ocrProgressContainer');
        const uploadFormArea = document.getElementById('ocrUploadFormArea');
        const statusText = document.getElementById('ocrStatusText');
        const progressBar = document.getElementById('ocrProgressBar');
        const btnUpload = document.getElementById('btnStartOcrUpload');

        if (uploadFormArea) uploadFormArea.style.display = 'none';
        if (progressContainer) progressContainer.style.display = 'block';
        if (btnUpload) btnUpload.disabled = true;

        const updateProgress = (pct, text) => {
            if (progressBar) progressBar.style.width = pct + '%';
            if (statusText) statusText.textContent = text;
        };

        updateProgress(25, 'Uploading document to secure server...');

        const formData = new FormData();
        formData.append('form_document', file);
        if (customApiKey) {
            formData.append('gemini_api_key', customApiKey);
        }

        // Get application token or ID if present
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        if (token) {
            formData.append('token', token);
        }

        setTimeout(() => {
            updateProgress(60, 'Analyzing handwritten ink & printed fields with AI Vision...');
        }, 800);

        const targetApi = getApiUrl();

        // Call backend API
        fetch(targetApi, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                    throw new Error(errData.message || 'Server returned status ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to extract document data.');
            }

            updateProgress(90, 'Mapping fields across all 5 form tabs...');

            // If we are in the management parent page, deliver to the embedded iframe
            const iframe = document.getElementById('inlineJoiningIframe');
            if (iframe) {
                if (typeof window.switchView === 'function') {
                    window.switchView('form');
                }

                setTimeout(() => {
                    updateProgress(100, 'Populating digital form fields!');
                    window.closeOcrModal();
                    resetOcrModalState();

                    // Send data to iframe
                    const deliverToIframe = () => {
                        try {
                            if (iframe.contentWindow && typeof iframe.contentWindow.receiveExtractedData === 'function') {
                                iframe.contentWindow.receiveExtractedData(data.extracted_data, data.scan_file_path, data.mode, data.message);
                            } else {
                                iframe.contentWindow.postMessage({
                                    type: 'vk_ocr_autofill',
                                    extracted_data: data.extracted_data,
                                    scan_file_path: data.scan_file_path,
                                    mode: data.mode,
                                    message: data.message
                                }, '*');
                            }
                        } catch(err) {
                            iframe.contentWindow.postMessage({
                                type: 'vk_ocr_autofill',
                                extracted_data: data.extracted_data,
                                scan_file_path: data.scan_file_path,
                                mode: data.mode,
                                message: data.message
                            }, '*');
                        }
                    };

                    deliverToIframe();
                }, 500);
            } else {
                // We are directly in joining_form.php
                if (data.scan_file_path) {
                    currentScanFileUrl = data.scan_file_path;
                    const btnToggle = document.getElementById('btnToggleScanView');
                    if (btnToggle) btnToggle.style.display = 'inline-flex';
                }

                setTimeout(() => {
                    updateProgress(100, 'Populating digital form fields!');
                    window.populateFormWithData(data.extracted_data);
                    window.closeOcrModal();
                    resetOcrModalState();
                    window.showAutofillSuccessBanner(data.mode, data.message);
                }, 600);
            }
        })
        .catch(err => {
            console.error('OCR Extraction Error:', err);
            alert('Error processing document: ' + err.message);
            resetOcrModalState();
        });
    };

    function resetOcrModalState() {
        const progressContainer = document.getElementById('ocrProgressContainer');
        const uploadFormArea = document.getElementById('ocrUploadFormArea');
        const btnUpload = document.getElementById('btnStartOcrUpload');
        const fileInfoEl = document.getElementById('ocrFileInfo');
        const fileInput = document.getElementById('ocrFileInput');

        if (progressContainer) progressContainer.style.display = 'none';
        if (uploadFormArea) uploadFormArea.style.display = 'block';
        if (btnUpload) btnUpload.disabled = false;
        if (fileInfoEl) fileInfoEl.style.display = 'none';
        if (fileInput) fileInput.value = '';
    }

    // Global Receiver for Data
    window.receiveExtractedData = function(data, scanFilePath, mode, message) {
        if (scanFilePath) {
            currentScanFileUrl = scanFilePath;
            const btnToggle = document.getElementById('btnToggleScanView');
            if (btnToggle) btnToggle.style.display = 'inline-flex';
        }
        window.populateFormWithData(data);
        window.showAutofillSuccessBanner(mode, message);
    };

    // Populate Fields Across All 5 Tabs
    window.populateFormWithData = function(data) {
        if (!data) return;

        let filledCount = 0;

        const setVal = (selector, val) => {
            if (val === null || val === undefined || val === '') return;
            const el = document.querySelector(selector);
            if (el) {
                el.value = val;
                el.classList.add('ocr-autofilled');
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                filledCount++;

                // Clear highlight on focus
                el.addEventListener('focus', function handler() {
                    el.classList.remove('ocr-autofilled');
                    el.removeEventListener('focus', handler);
                });
            }
        };

        const setSelectClosest = (selector, val) => {
            if (!val) return;
            const el = document.querySelector(selector);
            if (!el) return;

            const searchVal = val.toLowerCase().trim();
            for (let i = 0; i < el.options.length; i++) {
                const optText = el.options[i].text.toLowerCase();
                const optVal = el.options[i].value.toLowerCase();
                if (optText.includes(searchVal) || optVal.includes(searchVal) || searchVal.includes(optVal)) {
                    el.selectedIndex = i;
                    el.classList.add('ocr-autofilled');
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                    filledCount++;
                    break;
                }
            }
        };

        const setCheck = (selector, checked) => {
            const el = document.querySelector(selector);
            if (el && checked !== undefined) {
                el.checked = !!checked;
                el.classList.add('ocr-autofilled');
                el.dispatchEvent(new Event('change', { bubbles: true }));
                filledCount++;
            }
        };

        // 1. Personal Details (Tab 1)
        if (data.personal) {
            const p = data.personal;
            setVal('input[name="full_name"]', p.full_name);
            setVal('input[name="father_name"]', p.father_name);
            setVal('input[name="dob"]', p.dob);
            setSelectClosest('select[name="gender"]', p.gender);
            setSelectClosest('select[name="blood_group"]', p.blood_group);
            setVal('input[name="previous_work_experience"]', p.previous_work_experience);
            setVal('input[name="joining_date"]', p.joining_date);
            setVal('input[name="employee_id"]', p.employee_id);

            if (p.designation) {
                setVal('input[name="designation"]', p.designation);
                setVal('#designationSelect', p.designation);
                if (typeof window.checkRoleRequirements === 'function') {
                    const desigEl = document.getElementById('designationSelect');
                    if (desigEl) window.checkRoleRequirements(desigEl.value);
                }
            }

            if (p.employee_type === 'existing_employee') {
                const r = document.getElementById('type_existing');
                if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
            } else {
                const r = document.getElementById('type_new');
                if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
            }

            setVal('input[name="mobile"]', p.mobile);
            setVal('input[name="alternate_mobile"]', p.alternate_mobile);
            setVal('input[name="email"]', p.email);
        }

        // 2. Identity & Bank Details (Tab 1)
        if (data.identity) {
            const id = data.identity;
            setVal('input[name="aadhaar_number"]', id.aadhaar_number);
            setSelectClosest('select[name="other_id_type"]', id.other_id_type);
            setVal('input[name="other_id_number"]', id.other_id_number);

            if (Array.isArray(id.docs_submitted)) {
                id.docs_submitted.forEach(docName => {
                    const checkboxes = document.querySelectorAll('input[name="docs_submitted[]"]');
                    checkboxes.forEach(cb => {
                        if (docName.toLowerCase().includes(cb.value.toLowerCase()) || cb.value.toLowerCase().includes(docName.toLowerCase())) {
                            cb.checked = true;
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                            filledCount++;
                        }
                    });
                });
            }
        }

        // 3. Addresses & Communication Details (Tab 2)
        if (data.present_address) {
            const a = data.present_address;
            setVal('#p_house', a.house_flat_no);
            setVal('#p_street', a.street_area);
            setVal('#p_village', a.village_town);
            setVal('#p_mandal', a.mandal);
            setVal('#p_district', a.district);
            setVal('#p_state', a.state);
            setVal('#p_pincode', a.pincode);
        }

        if (data.permanent_address) {
            const pa = data.permanent_address;
            if (pa.same_as_present) {
                setCheck('#sameAsPresentToggle', true);
                if (typeof window.toggleSameAsPresent === 'function') {
                    window.toggleSameAsPresent();
                }
            } else {
                setCheck('#sameAsPresentToggle', false);
                setVal('#perm_house', pa.house_flat_no);
                setVal('#perm_street', pa.street_area);
                setVal('#perm_village', pa.village_town);
                setVal('#perm_mandal', pa.mandal);
                setVal('#perm_district', pa.district);
                setVal('#perm_state', pa.state);
                setVal('#perm_pincode', pa.pincode);
            }
        }

        // 4. Previous Employment & References (Tab 3)
        if (data.previous_employment) {
            const pe = data.previous_employment;
            setVal('#prev_employer', pe.employer_name);
            setVal('#prev_desig', pe.designation);
            setVal('#prev_phone', pe.employer_phone);
            setVal('#prev_reason', pe.reason_for_leaving);
        }

        if (Array.isArray(data.references)) {
            if (data.references[0]) {
                const r1 = data.references[0];
                setVal('input[name="ref1_name"]', r1.name);
                setVal('input[name="ref1_rel"]', r1.relationship);
                setVal('input[name="ref1_mobile"]', r1.mobile);
                setVal('textarea[name="ref1_address"]', r1.address);
            }
            if (data.references[1]) {
                const r2 = data.references[1];
                setVal('input[name="ref2_name"]', r2.name);
                setVal('input[name="ref2_rel"]', r2.relationship);
                setVal('input[name="ref2_mobile"]', r2.mobile);
                setVal('textarea[name="ref2_address"]', r2.address);
            }
        }

        // 5. Terms & Declarations (Tab 4 & Tab 5)
        if (data.declarations) {
            const d = data.declarations;
            if (d.terms_acknowledged) setCheck('#termsAck', true);
            if (d.pickup_policy_acknowledged) setCheck('#pickupPolicyAck', true);
            if (d.data_consent_acknowledged) setCheck('#dataConsentAck', true);
        }

        // 6. Emergency Contact (Tab 5)
        if (data.emergency_contact) {
            const ec = data.emergency_contact;
            setVal('input[name="emergency_name"]', ec.name);
            setVal('input[name="emergency_rel"]', ec.relationship);
            setVal('input[name="emergency_mobile"]', ec.mobile);
            setVal('input[name="emergency_alt_mobile"]', ec.alternate_mobile);
            setVal('input[name="emergency_address"]', ec.address);
        }

        // Switch to Tab 1 for initial review
        if (typeof window.goToTab === 'function') {
            window.goToTab(1);
        }

        // Notify parent iframe for auto-resize
        if (typeof window.notifyParentOfResize === 'function') {
            window.notifyParentOfResize();
        }
    };

    window.showAutofillSuccessBanner = function(mode, message) {
        let banner = document.getElementById('ocrAutofillBanner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'ocrAutofillBanner';
            banner.className = 'alert alert-success alert-dismissible fade show shadow-sm mb-3 mt-2';
            banner.style.borderLeft = '5px solid #28a745';
            
            const targetContainer = document.querySelector('.bel-content-wrapper') || document.body;
            targetContainer.insertBefore(banner, targetContainer.firstChild);
        }

        const isLive = mode === 'gemini_live';
        const badgeHtml = isLive 
            ? '<span class="badge bg-success ms-2"><i class="fas fa-check-circle me-1"></i> Document Processed</span>'
            : '<span class="badge bg-info text-dark ms-2"><i class="fas fa-check-circle me-1"></i> Document Processed</span>';

        banner.innerHTML = `
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle fs-3 text-success me-3"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">
                            Form Loaded from Uploaded Document! ${badgeHtml}
                        </h6>
                        <p class="mb-0 small text-dark">
                            Handwritten values have been populated into the fields below. Highlighted fields can be inspected and edited as needed before saving.
                        </p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill fw-bold" onclick="toggleScanViewer()">
                        <i class="fas fa-eye me-1"></i> Compare with Scan
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        `;
    };

    // Side-by-side Scan Viewer Toggle
    window.toggleScanViewer = function() {
        if (!currentScanFileUrl) {
            alert('No scanned document is currently active.');
            return;
        }

        let drawer = document.getElementById('scanPreviewDrawer');
        if (!drawer) {
            drawer = document.createElement('div');
            drawer.id = 'scanPreviewDrawer';
            drawer.className = 'shadow-lg border-start bg-white';
            drawer.style.position = 'fixed';
            drawer.style.top = '0';
            drawer.style.right = '0';
            drawer.style.width = '48%';
            drawer.style.height = '100vh';
            drawer.style.zIndex = '1060';
            drawer.style.transition = 'transform 0.3s ease-in-out';
            drawer.style.transform = 'translateX(100%)';
            drawer.style.display = 'flex';
            drawer.style.flexDirection = 'column';

            drawer.innerHTML = `
                <div class="p-3 bg-dark text-white d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-file-alt me-2 text-primary"></i> Uploaded Physical Form Scan</h6>
                    <div class="d-flex align-items-center gap-2">
                        <a href="${currentScanFileUrl}" target="_blank" class="btn btn-sm btn-outline-light rounded-pill px-2" title="Open in new window">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-light rounded-circle" onclick="toggleScanViewer()" style="width: 30px; height: 30px; line-height: 1;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-grow-1 p-2 bg-light text-center" style="overflow: auto;">
                    <iframe id="scanDrawerIframe" src="${currentScanFileUrl}" style="width: 100%; height: 100%; border: none; background: #fff;"></iframe>
                </div>
            `;
            document.body.appendChild(drawer);
        }

        const isHidden = drawer.style.transform === 'translateX(100%)';
        if (isHidden) {
            drawer.style.transform = 'translateX(0)';
            const frame = document.getElementById('scanDrawerIframe');
            if (frame && frame.src !== currentScanFileUrl) {
                frame.src = currentScanFileUrl;
            }
        } else {
            drawer.style.transform = 'translateX(100%)';
        }
    };

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
