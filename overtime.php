<?php $pageTitle = 'Overtime Submissions'; $activePage = 'overtime'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="sub-tabs submission-bar">
    <a href="#" class="tab active" id="otTabPending" onclick="showOTTab('pending');return false;">Requests</a>
    <a href="#" class="tab" id="otTabForms" onclick="showOTTab('forms');return false;">Form Links</a>
</div>

<!-- ===== REQUESTS TAB ===== -->
<div id="otPendingTab">
    <div class="controls-container request-filters-top">
        <div class="controls-group filters-group">
            <div class="control-item">
                <label for="requestSearch">Search</label>
                <input type="search" id="requestSearch" placeholder="Employee name...">
            </div>
            <div class="control-item">
                <label for="requestEmployee">Employee</label>
                <select id="requestEmployee"><option value="">All employees</option></select>
            </div>
            <div class="control-item">
                <label for="requestCompany">Company</label>
                <select id="requestCompany"><option value="">All companies</option></select>
            </div>
            <div class="control-item">
                <label for="requestDepartment">Department</label>
                <select id="requestDepartment"><option value="">All departments</option></select>
            </div>
            <div class="control-item">
                <label for="requestStatus">Status</label>
                <select id="requestStatus">
                    <option value="all">All</option>
                    <option value="pending" selected>Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </div>
        <div class="controls-actions">
            <button class="btn btn-secondary" onclick="loadPendingOT()">&#8635; Refresh</button>
            <button class="btn btn-outline" style="color:#ef4444;border-color:#ef4444;" onclick="confirmClearOT()">Clear All</button>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="otTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Date</th>
                    <th>Hours</th>
                    <th>Reason</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="otPendingBody">
                <tr><td colspan="9" class="no-data">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== FORM LINKS TAB ===== -->
<div id="otFormsTab" style="display:none;">
    <div class="controls-container">
        <div class="controls-group">
            <div class="control-item">
                <label>Employee</label>
                <select id="formEmployeeFilterOT"><option value="">Select Employee</option></select>
            </div>
        </div>
        <div class="controls-actions">
            <button class="btn btn-primary" onclick="generateOvertimeFormLink()">Generate New Link</button>
            <button class="btn btn-outline" style="color:#ef4444;border-color:#ef4444;" onclick="clearOTFormLinks()">Clear Form Links</button>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Employee</th><th>Generated</th><th>Expires</th><th>Submissions</th><th>Form Link</th><th>Actions</th></tr></thead>
            <tbody id="otFormsBody"><tr><td colspan="7" class="no-data">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Form Link Modal -->
<div id="formLinkModalOT" class="modal">
    <div class="modal-content">
        <h3>Overtime Form Link</h3>
        <p style="color:var(--text-muted);font-size:13px;margin:6px 0 16px;">Share this link with the employee to submit their overtime request.</p>
        <div class="form-group">
            <input type="text" id="formLinkURLOT" readonly onclick="copyToClipboard(this)" style="cursor:pointer;background:var(--body-bg);">
            <small style="color:var(--text-muted);margin-top:4px;display:block;">Click to copy</small>
        </div>
        <div style="margin-top:12px;">
            <button class="btn btn-primary" style="width:100%;" onclick="shareOTFormViaWhatsApp()">Share via WhatsApp</button>
        </div>
        <div class="modal-actions" style="margin-top:16px;">
            <button class="btn btn-outline" onclick="closeFormLinkModalOT()">Close</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="otRejectModal" class="modal">
    <div class="modal-content">
        <h3>Reject Overtime Request</h3>
        <div class="form-group" style="margin-top:16px;">
            <label>Reason for rejection</label>
            <textarea id="otRejectReason" rows="3" placeholder="Optional reason..." style="width:100%;margin-top:6px;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="submitOTReject()">Confirm Reject</button>
            <button class="btn btn-outline" onclick="document.getElementById('otRejectModal').style.display='none'">Cancel</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
var _rejectOTId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadPendingOT();
    loadRequestFilters();
    loadOvertimeFormLinks();
    loadFormEmployeeFilter('#formEmployeeFilterOT');
    document.getElementById('requestSearch').addEventListener('input', debounceOT);
    document.getElementById('requestStatus').addEventListener('change', loadPendingOT);
});

function debounceOT() { clearTimeout(window._ott); window._ott = setTimeout(loadPendingOT, 300); }

function confirmClearOT() {
    if (!confirm('This will permanently delete all overtime requests and overtime form links. Are you sure?')) return;
    fetch('api/overtime.php?action=clear_all', { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast(d.message || 'All overtime records cleared', 'success'); loadPendingOT(); loadOvertimeFormLinks(); }
            else showToast(d.message || 'Failed', 'error');
        });
}

function clearOTFormLinks() {
    if (!confirm('This will permanently delete all overtime form links and their submissions. Are you sure?')) return;
    fetch('api/form_links.php?action=clear_type&type=overtime', { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast(d.message || 'Overtime form links cleared', 'success'); loadOvertimeFormLinks(); }
            else showToast(d.message || 'Failed', 'error');
        });
}

function openOTReject(id) {
    _rejectOTId = id;
    document.getElementById('otRejectReason').value = '';
    document.getElementById('otRejectModal').style.display = 'flex';
}

function submitOTReject() {
    if (!_rejectOTId) return;
    var reason = document.getElementById('otRejectReason').value;
    var fd = new FormData();
    fd.append('ot_id', _rejectOTId);
    fd.append('rejection_reason', reason);
    fetch('api/overtime.php?action=reject', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('otRejectModal').style.display = 'none';
            if (d.success) { showToast('Overtime rejected', 'success'); loadPendingOT(); }
            else showToast(d.message || 'Failed', 'error');
        });
}
</script>
