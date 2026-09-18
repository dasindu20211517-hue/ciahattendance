<?php $pageTitle = 'Weekly Report'; $activePage = 'weekly'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="controls-container">
            <div class="controls-group filters-group">
                <div class="control-item">
                    <label for="weekPicker">Week of</label>
                    <input type="date" id="weekPicker" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="control-item">
                    <label for="employeeFilter">Employee</label>
                    <select id="employeeFilter"><option value="">All employees</option></select>
                </div>
                <div class="control-item">
                    <label for="companyFilter">Company</label>
                    <select id="companyFilter"><option value="">All companies</option></select>
                </div>
                <div class="control-item">
                    <label for="departmentFilter">Department</label>
                    <select id="departmentFilter"><option value="">All departments</option></select>
                </div>
                <div class="control-item">
                    <label for="nameMode">Name Display</label>
                    <select id="nameMode"><option value="calling">Calling Name</option><option value="official">Official Name</option></select>
                </div>
            </div>
            <div class="controls-actions">
                <button class="btn btn-secondary" onclick="loadWeeklyReport()">&#8635; Load Report</button>
                <div class="export-btns">
                    <button class="btn btn-export excel" onclick="exportExcel('weekly')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18"/></svg>Excel
                    </button>
                    <button class="btn btn-export pdf" onclick="exportPDF('weekly')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>PDF
                    </button>
                </div>
            </div>
        </div>
        <div class="stats-bar">
            <div class="stat-card"><span class="stat-value" id="wStatTotal">0</span><span class="stat-label">Employees</span></div>
            <div class="stat-card stat-late"><span class="stat-value" id="wStatLate">0</span><span class="stat-label">Late Days</span></div>
            <div class="stat-card stat-ontime"><span class="stat-value" id="wStatOnTime">0</span><span class="stat-label">On Time Days</span></div>
        </div>
        <div class="table-wrapper" style="overflow-x:auto;">
            <table id="weeklyTable">
                <thead>
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">Employee ID</th>
                        <th rowspan="2">Name</th>
                        <th rowspan="2">Department</th>
                        <th colspan="7" style="text-align:center; border-bottom: 2px solid var(--border);">Week Days</th>
                        <th rowspan="2">Total</th>
                    </tr>
                    <tr>
                        <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
                    </tr>
                </thead>
                <tbody id="weeklyBody"><tr><td colspan="12" class="no-data">Loading...</td></tr></tbody>
            </table>
        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() { loadWeeklyReport(); });
</script>
