<?php $pageTitle = 'Shift Management'; $activePage = 'shifts'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="controls">
            <button class="btn btn-secondary" onclick="openShiftModal()">+ Add Shift</button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>#</th><th>Shift Name</th><th>Start Time</th><th>End Time</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="shiftsBody"><tr><td colspan="7" class="no-data">Loading...</td></tr></tbody>
            </table>
        </div>
        <div id="shiftModal" class="modal">
            <div class="modal-content">
                <h3 id="shiftModalTitle">Add Shift</h3>
                <input type="hidden" id="shiftId">
                <div class="form-group"><label>Name</label><input type="text" id="shiftName"></div>
                <div class="form-group"><label>Start Time</label><input type="time" id="shiftStart"></div>
                <div class="form-group"><label>End Time</label><input type="time" id="shiftEnd"></div>
                <div class="form-group"><label><input type="checkbox" id="shiftRostered"> Rostered Shift</label></div>
                <div class="form-group"><label><input type="checkbox" id="shiftActive" checked> Active</label></div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="saveShift()">Save</button>
                    <button class="btn btn-outline" onclick="closeShiftModal()">Cancel</button>
                </div>
            </div>
        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() { loadShifts(); });
</script>
