<?php $pageTitle = 'Leave Requests'; $activePage = 'leave'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="sub-tabs submission-bar">
    <a href="#" class="tab active" id="leaveTabPending" onclick="showLeaveTab('pending');return false;">Requests</a>
    <a href="#" class="tab" id="leaveTabTracker" onclick="showLeaveTab('tracker');return false;">Tracker</a>
    <a href="#" class="tab" id="leaveTabForms" onclick="showLeaveTab('forms');return false;">Form Links</a>
</div>

<!-- ===== REQUESTS TAB ===== -->
<div id="leavePendingTab">
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
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
        <div class="controls-actions">
            <button class="btn btn-secondary" onclick="loadPendingLeaves()">&#8635; Refresh</button>
            <button class="btn btn-outline" style="color:#ef4444;border-color:#ef4444;" onclick="confirmClearLeaves()">Clear All</button>
        </div>
    </div>

    <div class="table-wrapper">
        <table id="leaveTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="leavePendingBody">
                <tr><td colspan="11" class="no-data">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== LEAVE TRACKER TAB ===== -->
<div id="leaveTrackerTab" style="display:none;">
    <div class="controls-container request-filters-top">
        <div class="controls-group filters-group">
            <div class="control-item">
                <label for="trackerEmployee">Employee</label>
                <select id="trackerEmployee"><option value="">Select Employee</option></select>
            </div>
            <div class="control-item">
                <label for="trackerYear">Year</label>
                <select id="trackerYear"></select>
            </div>
        </div>
        <div class="controls-actions">
            <button class="btn btn-secondary" onclick="loadLeaveTracker()">&#8635; Refresh</button>
        </div>
    </div>

    <div id="leaveTrackerSummary" class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0;"></div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Leave Type</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody id="leaveTrackerBody">
                <tr><td colspan="7" class="no-data">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== FORM LINKS TAB ===== -->
<div id="leaveFormsTab" style="display:none;">
    <div class="controls-container">
        <div class="controls-group">
            <div class="control-item">
                <label>Employee</label>
                <select id="formEmployeeFilter"><option value="">Select Employee</option></select>
            </div>
        </div>
        <div class="controls-actions">
            <button class="btn btn-primary" onclick="generateLeaveFormLink()">Generate New Link</button>
            <button class="btn btn-outline" style="color:#ef4444;border-color:#ef4444;" onclick="clearLeaveFormLinks()">Clear Form Links</button>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>#</th><th>Employee</th><th>Generated</th><th>Expires</th><th>Submissions</th><th>Form Link</th><th>Actions</th></tr></thead>
            <tbody id="leaveFormsBody"><tr><td colspan="7" class="no-data">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Form Link Modal -->
<div id="formLinkModal" class="modal">
    <div class="modal-content">
        <h3>Leave Form Link</h3>
        <p style="color:var(--text-muted);font-size:13px;margin:6px 0 16px;">Share this link with the employee to submit their leave request.</p>
        <div class="form-group">
            <input type="text" id="formLinkURL" readonly onclick="copyToClipboard(this)" style="cursor:pointer;background:var(--body-bg);">
            <small style="color:var(--text-muted);margin-top:4px;display:block;">Click to copy</small>
        </div>
        <div style="margin-top:12px;">
            <button class="btn btn-primary" style="width:100%;" onclick="shareLeaveFormViaWhatsApp()">Share via WhatsApp</button>
        </div>
        <div class="modal-actions" style="margin-top:16px;">
            <button class="btn btn-outline" onclick="closeFormLinkModal()">Close</button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="leaveRejectModal" class="modal">
    <div class="modal-content">
        <h3>Reject Leave Request</h3>
        <div class="form-group" style="margin-top:16px;">
            <label>Reason for rejection</label>
            <textarea id="leaveRejectReason" rows="3" placeholder="Optional reason..." style="width:100%;margin-top:6px;padding:10px;border:1px solid var(--border);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="submitLeaveReject()">Confirm Reject</button>
            <button class="btn btn-outline" onclick="document.getElementById('leaveRejectModal').style.display='none'">Cancel</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
var _rejectLeaveId = null;

document.addEventListener('DOMContentLoaded', function() {
    populateLeaveTrackerYear();
    loadPendingLeaves();
    loadRequestFilters();
    loadLeaveFormLinks();
    loadLeaveTracker();
    loadFormEmployeeFilter('#formEmployeeFilter');
    loadFormEmployeeFilter('#trackerEmployee');
    document.getElementById('requestSearch').addEventListener('input', debounceLeave);
    document.getElementById('requestStatus').addEventListener('change', loadPendingLeaves);
    document.getElementById('trackerEmployee').addEventListener('change', loadLeaveTracker);
    document.getElementById('trackerYear').addEventListener('change', loadLeaveTracker);
});

function populateLeaveTrackerYear() {
    var yearSelect = document.getElementById('trackerYear');
    if (!yearSelect) return;
    var currentYear = new Date().getFullYear();
    yearSelect.innerHTML = '';
    for (var y = currentYear - 2; y <= currentYear + 2; y++) {
        var option = document.createElement('option');
        option.value = y;
        option.textContent = y;
        if (y === currentYear) option.selected = true;
        yearSelect.appendChild(option);
    }
}

function debounceLeave() { clearTimeout(window._lvt); window._lvt = setTimeout(loadPendingLeaves, 300); }

function confirmClearLeaves() {
    if (!confirm('This will permanently delete all leave requests, balances, and leave form links. Are you sure?')) return;
    fetch('api/leave.php?action=clear_all', { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast(d.message || 'All leave records cleared', 'success'); loadPendingLeaves(); loadLeaveFormLinks(); }
            else showToast(d.message || 'Failed', 'error');
        });
}

function clearLeaveFormLinks() {
    if (!confirm('This will permanently delete all leave form links and their submissions. Are you sure?')) return;
    fetch('api/form_links.php?action=clear_type&type=leave', { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) { showToast(d.message || 'Leave form links cleared', 'success'); loadLeaveFormLinks(); }
            else showToast(d.message || 'Failed', 'error');
        });
}

function openLeaveReject(id) {
    _rejectLeaveId = id;
    document.getElementById('leaveRejectReason').value = '';
    document.getElementById('leaveRejectModal').style.display = 'flex';
}

function submitLeaveReject() {
    if (!_rejectLeaveId) return;
    var reason = document.getElementById('leaveRejectReason').value;
    var fd = new FormData();
    fd.append('leave_id', _rejectLeaveId);
    fd.append('rejection_reason', reason);
    fetch('api/leave.php?action=reject', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            document.getElementById('leaveRejectModal').style.display = 'none';
            if (d.success) { showToast('Leave rejected', 'success'); loadPendingLeaves(); }
            else showToast(d.message || 'Failed', 'error');
        });
}
</script>
