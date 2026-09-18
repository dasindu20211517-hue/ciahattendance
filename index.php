<?php $pageTitle = 'Dashboard'; $activePage = 'dashboard'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<div class="dash-hero">
    <div class="dash-hero-left">
        <h1 class="dash-greeting">Good <?php echo (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening')); ?>, <?php echo htmlspecialchars(explode(' ', $loggedInUser['name'])[0]); ?></h1>
        <p class="dash-date-display"><?php echo date('l, F j, Y'); ?></p>
    </div>
    <div class="dash-hero-right">
        <div class="dash-date-picker">
            <span class="dash-dp-icon">&#128197;</span>
            <input type="date" id="dashDatePicker" value="<?php echo date('Y-m-d'); ?>">
            <button class="btn btn-primary btn-sm" onclick="loadDashboard()">Refresh</button>
        </div>
    </div>
</div>

<div class="company-tabs-wrapper" id="companyTabsWrapper">
    <div class="company-tabs" id="companyTabs">
        <button class="company-tab active" data-company="" onclick="switchCompanyTab(this)">
            <span class="tab-dot"></span>All Companies
        </button>
    </div>
</div>

<div class="dash-stats-row" id="dashStats">
    <div class="dash-stat-card dash-stat-card-total">
        <div class="dash-stat-glow"></div>
        <div class="dash-stat-icon-wrap"><div class="dash-stat-icon dash-stat-total"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
        <div class="dash-stat-info">
            <span class="dash-stat-value" id="dashTotal">0</span>
            <span class="dash-stat-label">Total Employees</span>
        </div>
        <div class="dash-stat-trend" id="dashTotalTrend"></div>
    </div>
    <div class="dash-stat-card dash-stat-card-present">
        <div class="dash-stat-glow"></div>
        <div class="dash-stat-icon-wrap"><div class="dash-stat-icon dash-stat-present"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div></div>
        <div class="dash-stat-info">
            <span class="dash-stat-value" id="dashPresent">0</span>
            <span class="dash-stat-label">Present Today</span>
        </div>
        <div class="dash-stat-trend" id="dashPresentTrend"></div>
    </div>
    <div class="dash-stat-card dash-stat-card-ontime">
        <div class="dash-stat-glow"></div>
        <div class="dash-stat-icon-wrap"><div class="dash-stat-icon dash-stat-ontime"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
        <div class="dash-stat-info">
            <span class="dash-stat-value" id="dashOnTime">0</span>
            <span class="dash-stat-label">On Time Instances</span>
        </div>
        <div class="dash-stat-trend" id="dashOnTimeTrend"></div>
    </div>
    <div class="dash-stat-card dash-stat-card-late">
        <div class="dash-stat-glow"></div>
        <div class="dash-stat-icon-wrap"><div class="dash-stat-icon dash-stat-late"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
        <div class="dash-stat-info">
            <span class="dash-stat-value" id="dashLate">0</span>
            <span class="dash-stat-label">Late Instances</span>
        </div>
        <div class="dash-stat-trend" id="dashLateTrend"></div>
    </div>
    <div class="dash-stat-card dash-stat-card-absent">
        <div class="dash-stat-glow"></div>
        <div class="dash-stat-icon-wrap"><div class="dash-stat-icon dash-stat-absent"><svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div></div>
        <div class="dash-stat-info">
            <span class="dash-stat-value" id="dashAbsent">0</span>
            <span class="dash-stat-label">Absent</span>
        </div>
        <div class="dash-stat-trend" id="dashAbsentTrend"></div>
    </div>
</div>

<div class="dash-charts-row">
    <div class="dash-chart-card dash-donut-card">
        <div class="dash-chart-header">
            <h3 class="dash-chart-title">Attendance Overview</h3>
            <span class="dash-chart-badge" id="dashAttendanceRate">0%</span>
        </div>
        <div class="dash-donut-wrap">
            <canvas id="attendanceDonut"></canvas>
            <div class="donut-center-text">
                <span class="donut-center-value" id="donutCenterVal">0%</span>
                <span class="donut-center-label">Attendance</span>
            </div>
        </div>
        <div class="donut-legend" id="donutLegend">
            <div class="donut-legend-item"><span class="donut-legend-dot" style="background:#10b981"></span>On Time <strong id="donutOnTime">0</strong></div>
            <div class="donut-legend-item"><span class="donut-legend-dot" style="background:#f97316"></span>Late <strong id="donutLate">0</strong></div>
            <div class="donut-legend-item"><span class="donut-legend-dot" style="background:#cbd5e1"></span>Absent <strong id="donutAbsent">0</strong></div>
        </div>
    </div>
    <div class="dash-chart-card dash-donut-card">
        <div class="dash-chart-header">
            <h3 class="dash-chart-title">Gender Distribution</h3>
            <span class="dash-chart-badge" id="dashGenderTotal">0 Total</span>
        </div>
        <div class="dash-donut-wrap">
            <canvas id="genderChart" width="200" height="200"></canvas>
            <div class="donut-center-text">
                <span class="donut-center-value" id="genderCenterVal">0</span>
                <span class="donut-center-label">Employees</span>
            </div>
        </div>
        <div class="donut-legend" id="genderLegend">
            <div class="donut-legend-item"><span class="donut-legend-dot" style="background:#3B82F6"></span>Male <strong id="maleCount">0</strong></div>
            <div class="donut-legend-item"><span class="donut-legend-dot" style="background:#EC4899"></span>Female <strong id="femaleCount">0</strong></div>
        </div>
    </div>
    <div class="dash-chart-card dash-trend-card">
        <div class="dash-chart-header">
            <h3 class="dash-chart-title">7-Day Attendance Trend</h3>
        </div>
        <div class="dash-chart-wrap"><canvas id="weekTrendChart"></canvas></div>
    </div>
</div>

<div class="dash-charts-row">
    <div class="dash-chart-card">
        <div class="dash-chart-header">
            <h3 class="dash-chart-title">Company Comparison</h3>
        </div>
        <div class="dash-chart-wrap"><canvas id="companyBarChart"></canvas></div>
    </div>
    <div class="dash-chart-card">
        <div class="dash-chart-header">
            <h3 class="dash-chart-title">Department Breakdown</h3>
        </div>
        <div class="dash-chart-wrap"><canvas id="deptBarChart"></canvas></div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDashboard();
    document.getElementById('dashDatePicker').addEventListener('change', loadDashboard);
});
</script>
