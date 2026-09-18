<?php $pageTitle = 'Public Holidays'; $activePage = 'holidays'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="controls">
            <label>Year:</label>
            <input type="number" id="holidayYear" value="<?php echo date('Y'); ?>" min="2024" max="2030" onchange="loadHolidays()">
            <button class="btn btn-secondary" onclick="loadHolidays()">Load</button>
            <button class="btn btn-export" onclick="seedCurrentYearHolidays(event)">Seed Holidays</button>
            <button class="btn btn-outline" onclick="openHolidayModal()">+ Add Holiday</button>
        </div>
        <div class="stats-bar">
            <div class="stat-card"><span class="stat-value" id="hStatTotal">0</span><span class="stat-label">Total Holidays</span></div>
            <div class="stat-card stat-pending"><span class="stat-value" id="hStatPoya">0</span><span class="stat-label">Poya Days</span></div>
            <div class="stat-card stat-info"><span class="stat-value" id="hStatOther">0</span><span class="stat-label">Other Holidays</span></div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>#</th><th>Date</th><th>Day</th><th>Holiday Name</th><th>Type</th><th>Action</th></tr></thead>
                <tbody id="holidaysBody"><tr><td colspan="6" class="no-data">Loading...</td></tr></tbody>
            </table>
        </div>
        <div id="holidayModal" class="modal">
            <div class="modal-content">
                <h3>Add Holiday</h3>
                <div class="form-group"><label>Date</label><input type="date" id="hzDate"></div>
                <div class="form-group"><label>Name</label><input type="text" id="hzName"></div>
                <div class="form-group"><label><input type="checkbox" id="hzPoya"> Poya Day</label></div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="addHoliday()">Add</button>
                    <button class="btn btn-outline" onclick="closeHolidayModal()">Cancel</button>
                </div>
            </div>
        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() { loadHolidays(); });
</script>
