<?php $pageTitle = 'Notifications'; $activePage = 'notifications'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="controls">
            <label>Filter:</label>
            <select id="notifFilter" onchange="loadNotifications()">
                <option value="">All</option>
                <option value="leave_request">Leave Requests</option>
                <option value="leave_approved">Leave Approved</option>
                <option value="leave_rejected">Leave Rejected</option>
                <option value="ot_request">OT Requests</option>
                <option value="ot_approved">OT Approved</option>
                <option value="ot_rejected">OT Rejected</option>
            </select>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>#</th><th>Date</th><th>Employee</th><th>Type</th><th>Channel</th><th>Subject</th><th>Message</th><th>Status</th></tr></thead>
                <tbody id="notifBody"><tr><td colspan="8" class="no-data">Loading...</td></tr></tbody>
            </table>
        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() { loadNotifications(); });
</script>
