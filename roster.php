<?php $pageTitle = 'Roster Management'; $activePage = 'roster'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="controls">
            <label>Employee:</label>
            <select id="rosterUser" onchange="loadRosterCalendar()"><option value="">Select Employee</option></select>
            <label>Month:</label>
            <input type="month" id="rosterMonth" value="<?php echo date('Y-m'); ?>" onchange="loadRosterCalendar()">
            <button class="btn btn-secondary" onclick="openRosterModal()">+ New Roster</button>
            <a href="roster_import.php" class="btn btn-primary" style="margin-left: 8px;">📊 Weekly Import</a>
            <a href="monthly_roster_import.php" class="btn btn-success" style="margin-left: 8px;">📅 Monthly Import</a>
        </div>
        <div id="rosterInfo" class="roster-info" style="display:none;"></div>
        <div class="calendar-grid" id="rosterCalendar"></div>
        <div id="rosterModal" class="modal">
            <div class="modal-content">
                <h3>Create Roster</h3>
                <div class="form-group"><label>Employee</label><select id="rmUser"></select></div>
                <div class="form-group"><label>Effective From</label><input type="date" id="rmFrom" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="form-group"><label>Effective To (optional)</label><input type="date" id="rmTo"></div>
                <div id="rmDays"></div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="saveRoster()">Save</button>
                    <button class="btn btn-outline" onclick="closeRosterModal()">Cancel</button>
                </div>
            </div>
        </div>
        <div id="dayModal" class="modal">
            <div class="modal-content">
                <h3>Set Shift for <span id="dmDate"></span></h3>
                <div class="form-group"><label>Shift</label><select id="dmShift"><option value="">REST DAY</option></select></div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="saveDayOverride()">Save</button>
                    <button class="btn btn-outline" onclick="closeDayModal()">Cancel</button>
                </div>
            </div>
        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() { loadRosterUsers(); });
</script>
