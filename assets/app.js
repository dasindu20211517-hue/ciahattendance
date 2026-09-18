document.addEventListener('DOMContentLoaded', function() {
    initializeReportsTabs();
    
    var dp = document.getElementById('datePicker');
    if (dp) {
        loadDailyData();
        dp.addEventListener('change', loadDailyData);
        setInterval(function() {
            if (document.getElementById('dailyBody') && dp.value === todayStr()) loadDailyData();
        }, 60000);
    }
});

// ===================== REPORTS TABS =====================

function initializeReportsTabs() {
    var tabItems = document.querySelectorAll('.tab-item');
    if (tabItems.length === 0) return;
    
    // Keyboard navigation
    tabItems.forEach(function(tab, index) {
        tab.addEventListener('keydown', function(e) {
            var nextIndex, prevIndex;
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextIndex = (index + 1) % tabItems.length;
                tabItems[nextIndex].focus();
                tabItems[nextIndex].click();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevIndex = (index - 1 + tabItems.length) % tabItems.length;
                tabItems[prevIndex].focus();
                tabItems[prevIndex].click();
            } else if (e.key === 'Home') {
                e.preventDefault();
                tabItems[0].focus();
                tabItems[0].click();
            } else if (e.key === 'End') {
                e.preventDefault();
                tabItems[tabItems.length - 1].focus();
                tabItems[tabItems.length - 1].click();
            }
        });
    });
    
    // Smooth scroll active tab into view
    var activeTab = document.querySelector('.tab-item.active');
    if (activeTab) {
        var container = document.querySelector('.reports-tabs');
        if (container) {
            setTimeout(function() {
                activeTab.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }, 100);
        }
    }
}


function todayStr() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function getAttendanceTableBody() {
    return document.getElementById('dailyBody') || document.getElementById('attendanceBody');
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function calculateHours(checkin, checkout) {
    if (!checkin || !checkout) return '-';
    var diff = (new Date(checkout) - new Date(checkin)) / 1000;
    if (diff < 0) return '-';
    return Math.floor(diff / 3600) + 'h ' + String(Math.floor((diff % 3600) / 60)).padStart(2, '0') + 'm';
}

function updateEl(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
}

function employeeQuery() {
    var filter = document.getElementById('employeeFilter');
    var company = document.getElementById('companyFilter');
    var department = document.getElementById('departmentFilter');
    var query = filter && filter.value ? '&employee=' + encodeURIComponent(filter.value) : '';
    query += company && company.value ? '&company=' + encodeURIComponent(company.value) : '';
    return query + (department && department.value ? '&department=' + encodeURIComponent(department.value) : '');
}

function reportName(row) {
    var mode = document.getElementById('nameMode');
    return mode && mode.value === 'official' ? (row.official_name || row.name) : (row.calling_name || row.name);
}

function loadEmployeeFilter() {
    var filter = document.getElementById('employeeFilter');
    if (!filter) return;
    fetch('api/attendance.php?action=users')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            data.users.forEach(function(user) {
                var option = document.createElement('option');
                option.value = user.badge_id;
                var displayName = user.calling_name || user.name;
                var department = user.department_name ? ' [' + user.department_name + ']' : '';
                option.textContent = displayName + ' | Employee ID: ' + (user.employee_id || '-') + ' | Enroll: ' + user.badge_id + department;
                filter.appendChild(option);
            });
            filter.addEventListener('change', refreshActiveReport);
            var companies = {};
            data.users.forEach(function(user) { if (user.company_name) companies[user.company_name] = true; });
            var companyFilter = document.getElementById('companyFilter');
            if (companyFilter) {
                Object.keys(companies).sort().forEach(function(company) {
                    var option = document.createElement('option');
                    option.value = company;
                    option.textContent = company;
                    companyFilter.appendChild(option);
                });
                companyFilter.addEventListener('change', refreshActiveReport);
            }
                var departments = {};
                data.users.forEach(function(user) { if (user.department_name) departments[user.department_name] = true; });
                var departmentFilter = document.getElementById('departmentFilter');
                if (departmentFilter) {
                    Object.keys(departments).sort().forEach(function(department) {
                        var option = document.createElement('option');
                        option.value = department;
                        option.textContent = department;
                        departmentFilter.appendChild(option);
                    });
                    departmentFilter.addEventListener('change', refreshActiveReport);
                }
        });
    var nameMode = document.getElementById('nameMode');
    if (nameMode) nameMode.addEventListener('change', refreshActiveReport);
}

document.addEventListener('DOMContentLoaded', loadEmployeeFilter);

function daysBetween(d1, d2) {
    var a = new Date(d1), b = new Date(d2);
    return Math.round((b - a) / 86400000) + 1;
}

// ===================== SYNC =====================

var syncInProgress = false;

function refreshActiveReport() {
    if (typeof loadDailyData === 'function' && document.getElementById('dailyBody')) loadDailyData();
    if (typeof loadWeeklyReport === 'function' && document.getElementById('weeklyBody')) loadWeeklyReport();
    if (typeof loadMonthlyReport === 'function' && document.getElementById('monthlyBody')) loadMonthlyReport();
    if (typeof loadCustomReport === 'function' && document.getElementById('customBody')) loadCustomReport();
}

function syncDevice() {
    var btn = document.getElementById('syncBtn');
    var status = document.getElementById('syncStatus');
    if (!btn || !status || syncInProgress) return;
    syncInProgress = true;
    btn.disabled = true;
    btn.classList.add('loading');
    status.className = 'sync-status show loading';
    status.textContent = 'Connecting to device...';

    var controller = new AbortController();
    var timeout = setTimeout(function() { controller.abort(); }, 120000);
    fetch('sync.php', { signal: controller.signal })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            status.className = 'sync-status show ' + (d.success ? 'success' : 'error');
            status.textContent = d.message;
            if (d.success) {
                showToast('Synced ' + d.stats.users + ' users and ' + d.stats.attendance + ' records', 'success');
                refreshActiveReport();
            } else {
                showToast(d.message || 'Sync failed', 'error');
            }
        })
        .catch(function(e) {
            status.className = 'sync-status show error';
            var message = e.name === 'AbortError' ? 'Sync timed out after 2 minutes. Check device connectivity.' : 'Connection failed';
            status.textContent = message;
            showToast(message, 'error');
        })
        .finally(function() {
            clearTimeout(timeout);
            syncInProgress = false;
            btn.disabled = false;
            btn.classList.remove('loading');
        });
}

function importDatFile(event) {
    event.preventDefault();
    var form = document.getElementById('datImportForm');
    var status = document.getElementById('datImportStatus');
    var file = document.getElementById('datFile');
    if (!form || !status || !file.files.length) return;
    var button = form.querySelector('button[type="submit"]');
    var data = new FormData(form);
    if (button) button.disabled = true;
    status.className = 'sync-status show loading';
    status.textContent = 'Importing records...';
    fetch('api/import_dat.php', { method: 'POST', body: data })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            status.className = 'sync-status show ' + (result.success ? 'success' : 'error');
            var skipped = result.skipped_names && result.skipped_names.length ? ' Missing IDs: ' + result.skipped_names.join(', ') : '';
            status.textContent = (result.message || 'Import failed') + skipped;
            if (result.success) refreshActiveReport();
        })
        .catch(function() {
            status.className = 'sync-status show error';
            status.textContent = 'Import failed. Check the DAT file format.';
        })
        .finally(function() {
            if (button) button.disabled = false;
        });
}

// ===================== DAILY REPORT =====================

function loadDailyData() {
    var date = document.getElementById('datePicker');
    var tbody = getAttendanceTableBody();
    if (!date || !tbody) return;
    var val = date.value;
    tbody.innerHTML = '<tr><td colspan="11" class="no-data">Loading...</td></tr>';

    fetch('api/attendance.php?action=daily&date=' + encodeURIComponent(val) + employeeQuery())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">' + escapeHtml(data.message || 'Attendance data request failed') + '</td></tr>';
                updateEl('statPresent', 0);
                updateEl('statAbsent', 0);
                updateEl('statTotal', 0);
                updateEl('statLate', 0);
                updateEl('statOnTime', 0);
                return;
            }
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">No attendance records for this date</td></tr>';
                updateEl('statPresent', 0);
                updateEl('statAbsent', data.stats ? data.stats.total_employees : 0);
                updateEl('statTotal', data.stats ? data.stats.total_employees : 0);
                updateEl('statLate', 0);
                updateEl('statOnTime', 0);
                return;
            }
            var late = 0, ontime = 0, totalPresent = 0, html = '';
            console.log('Daily report data:', data.data);
            data.data.forEach(function(r, i) {
                console.log('Employee:', r.name, 'WhatsApp:', r.whatsapp_number);
                var workedToday = (r.check_in || r.check_out) && !r.is_absent;
                if (workedToday) {
                    totalPresent++;
                    if (r.status === 'Late' || r.status === 'Missing Check In' || r.status === 'Missing Check Out') late++;
                    else ontime++;
                }
                // Row styling based on status
                var isLate = (r.status === 'Late' || r.status === 'Missing Check In' || r.status === 'Missing Check Out');
                var isOnTime = (r.status === 'On Time');
                var isIncomplete = r.missing_checkout;
                var rowClass = r.is_holiday ? (r.worked_on_holiday ? (isLate ? 'late-row' : 'ontime-row') : 'holiday-row') : (r.is_absent ? 'absent-row' : (isLate ? 'late-row' : 'ontime-row'));
                html += '<tr class="' + rowClass + '">';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + escapeHtml(r.employee_id || r.badge_id) + '</td>';
                html += '<td>' + escapeHtml(r.badge_id) + '</td>';
                html += '<td style="min-width:150px;"><span data-badge="' + escapeHtml(r.badge_id) + '">' + escapeHtml(reportName(r)) + '</span>';
                if (r.whatsapp_number) {
                    var cleanNumber = String(r.whatsapp_number).replace(/[^0-9+]/g, '');
                    if (cleanNumber) {
                        html += '<br><button onclick="shareEmployeePDFViaWhatsApp(\'' + escapeHtml(r.badge_id) + '\', \'' + cleanNumber + '\', \'' + val + '\', \'daily\')" style="border:none;cursor:pointer;text-decoration:none;display:inline-block;margin-top:4px;padding:4px 8px;background:#25d366;color:white;border-radius:3px;font-size:10px;font-weight:600;white-space:nowrap;">📱 Share PDF</button>';
                    }
                }
                html += '</td>';
                html += '<td>' + escapeHtml(r.gender === 'M' || r.gender === 'Male' ? 'M' : (r.gender === 'F' || r.gender === 'Female' ? 'F' : '-')) + '</td>';
                html += '<td>' + escapeHtml(r.company_name || '') + '</td>';
                html += '<td>' + escapeHtml(r.department_name || '') + '</td>';
                if (r.is_holiday && !r.worked_on_holiday) {
                    html += '<td colspan="2" style="text-align:center"><span class="status-badge status-holiday">' + escapeHtml(r.holiday_name || 'Public Holiday') + '</span></td>';
                } else {
                    html += '<td>' + (r.check_in ? escapeHtml(r.check_in) : '<span style="color:#dc2626;font-weight:600">Not Marked In</span>') + '</td>';
                    html += '<td>' + (r.check_out ? escapeHtml(r.check_out) : '<span style="color:#dc2626;font-weight:600">Not Marked Out</span>') + '</td>';
                }
                html += '<td><strong>' + escapeHtml(r.total_hours) + '</strong></td>';
                if (r.is_holiday) {
                    html += '<td><span class="status-badge status-holiday">' + (r.worked_on_holiday ? 'Worked on Holiday' : 'Holiday') + '</span></td>';
                } else {
                    var st = r.status || (r.is_absent ? 'Absent' : (r.is_late ? 'Late' : 'On Time'));
                    var stCls = 'status-ontime';
                    if (st === 'Absent') stCls = 'status-absent';
                    else if (st === 'Missing Check In' || st === 'Not Marked In') stCls = 'status-missing-in';
                    else if (st === 'Missing Check Out' || st === 'Not Marked Out') stCls = 'status-missing-out';
                    else if (st === 'Late') stCls = 'status-late';
                    html += '<td><span class="status-badge ' + stCls + '">' + escapeHtml(st) + '</span></td>';
                }
                html += '</tr>';
            });
            tbody.innerHTML = html;
            updateEl('statPresent', data.stats ? data.stats.present : totalPresent);
            updateEl('statAbsent', data.stats ? data.stats.absent : 0);
            updateEl('statTotal', data.stats ? data.stats.total_employees : 0);
            updateEl('statLate', data.stats ? data.stats.late : late);
            updateEl('statOnTime', data.stats ? data.stats.on_time : ontime);
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="11" class="no-data">Error loading data</td></tr>';
        });
}

// ===================== WEEKLY REPORT =====================

function loadWeeklyReport() {
    var picker = document.getElementById('weekPicker');
    var tbody = document.getElementById('weeklyBody');
    if (!picker || !tbody) return;
    tbody.innerHTML = '<tr><td colspan="14" class="no-data">Loading...</td></tr>';

    fetch('api/attendance.php?action=weekly&date=' + encodeURIComponent(picker.value) + employeeQuery())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="12" class="no-data">' + escapeHtml(data.message || 'Weekly attendance request failed') + '</td></tr>';
                return;
            }
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" class="no-data">No data for this week</td></tr>';
                return;
            }
            var totalLateDays = 0, totalOnTimeDays = 0, html = '';
            data.data.forEach(function(emp, i) {
                var daysPresent = emp.days.filter(function(d) { return d.check_in || d.check_out; }).length;
                var holidaysCount = emp.days.filter(function(d) { return d.is_holiday; }).length;
                var workingDays = daysPresent - holidaysCount;
                var rowClass = workingDays === 0 && holidaysCount === 0 ? 'absent-row' : '';
                html += '<tr class="' + rowClass + '">';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + escapeHtml(emp.employee_id || emp.badge_id) + '</td>';
                html += '<td>' + escapeHtml(reportName(emp)) + '</td>';
                html += '<td>' + escapeHtml(emp.department_name || '-') + '</td>';
                emp.days.forEach(function(day) {
                    var isLateDay = day.check_in && day.check_in > '09:15';
                    if (day.is_holiday && !day.worked_on_holiday) {
                        html += '<td class="weekly-holiday-cell" style="background:#fef9e7"><div class="status-badge status-holiday" style="font-size:10px;padding:2px 6px">' + escapeHtml(day.holiday_name || 'Holiday') + '</div></td>';
                    } else if (day.is_holiday && day.worked_on_holiday) {
                        if (isLateDay) { totalLateDays++; } else { totalOnTimeDays++; }
                        var timeIn = day.check_in ? '<div class="weekly-time-in">' + day.check_in + '</div>' : '<div class="weekly-time-missing" style="color:#dc2626;font-weight:600">Not Marked In</div>';
                        var timeOut = day.check_out ? '<div class="weekly-time-out">' + day.check_out + '</div>' : '<div class="weekly-time-missing" style="color:#dc2626;font-weight:600">Not Marked Out</div>';
                        html += '<td class="weekly-holiday-cell" style="background:#fef9e7;border-left:3px solid #92400e">' + timeIn + timeOut + '<div class="status-badge status-holiday" style="font-size:9px;padding:1px 4px;margin-top:2px">Holiday</div></td>';
                    } else if (day.check_in || day.check_out) {
                        if (isLateDay) { totalLateDays++; } else { totalOnTimeDays++; }
                        var cellClass = isLateDay ? 'weekly-late-cell' : 'weekly-present-cell';
                        var timeIn = day.check_in ? '<div class="weekly-time-in">' + day.check_in + '</div>' : '<div class="weekly-time-missing" style="color:#dc2626;font-weight:600">Not Marked In</div>';
                        var timeOut = day.check_out ? '<div class="weekly-time-out">' + day.check_out + '</div>' : '<div class="weekly-time-missing" style="color:#dc2626;font-weight:600">Not Marked Out</div>';
                        html += '<td class="' + cellClass + '">' + timeIn + timeOut + '</td>';
                    } else {
                        html += '<td class="weekly-absent-cell"><span class="status-badge status-absent">—</span></td>';
                    }
                });
                html += '<td class="weekly-total-cell"><strong>' + emp.total_hours + '</strong></td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
            updateEl('wStatTotal', data.data.length);
            updateEl('wStatLate', totalLateDays);
            updateEl('wStatOnTime', totalOnTimeDays);
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="12" class="no-data">Error loading data</td></tr>';
        });
}

// ===================== MONTHLY REPORT =====================

function loadMonthlyReport() {
    var picker = document.getElementById('monthPicker');
    var tbody = document.getElementById('monthlyBody');
    if (!picker || !tbody) return;
    var parts = picker.value.split('-');
    tbody.innerHTML = '<tr><td colspan="11" class="no-data">Loading...</td></tr>';

    fetch('api/attendance.php?action=monthly_detail&year=' + parts[0] + '&month=' + parts[1] + employeeQuery())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">' + escapeHtml(data.message || 'Monthly attendance request failed') + '</td></tr>';
                return;
            }
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">No data for this month</td></tr>';
                return;
            }
            var late = 0, html = '';
            data.data.forEach(function(r, i) {
                var isAbsent = !r.check_in && !r.check_out && !r.is_holiday;
                if (r.is_late && !isAbsent) late++;
                // Missing checkout or late = red row
                var isIncomplete = r.is_late || r.missing_checkout;
                var rowClass = r.is_holiday ? (r.worked_on_holiday ? (isIncomplete ? 'late-row' : 'ontime-row') : 'holiday-row') : (isAbsent ? 'absent-row' : (isIncomplete ? 'late-row' : 'ontime-row'));
                html += '<tr class="' + rowClass + '">';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + escapeHtml(r.employee_id || r.badge_id) + '</td>';
                html += '<td>' + escapeHtml(r.badge_id) + '</td>';
                html += '<td style="min-width:150px;"><span data-badge="' + escapeHtml(r.badge_id) + '">' + escapeHtml(reportName(r)) + '</span>';
                if (r.whatsapp_number) {
                    var cleanNumber = String(r.whatsapp_number).replace(/[^0-9+]/g, '');
                    if (cleanNumber) {
                        var monthValue = picker.value;
                        html += '<br><button onclick="shareEmployeePDFViaWhatsApp(\'' + escapeHtml(r.badge_id) + '\', \'' + cleanNumber + '\', \'' + monthValue + '\', \'monthly\')" style="border:none;cursor:pointer;text-decoration:none;display:inline-block;margin-top:4px;padding:4px 8px;background:#25d366;color:white;border-radius:3px;font-size:10px;font-weight:600;white-space:nowrap;">📱 Share PDF</button>';
                    }
                }
                html += '</td>';
                html += '<td>' + escapeHtml(r.company_name || '') + '</td>';
                html += '<td>' + escapeHtml(r.department_name || '') + '</td>';
                html += '<td>' + escapeHtml(r.date) + '</td>';
                if (r.is_holiday && !r.worked_on_holiday) {
                    html += '<td colspan="2" style="text-align:center"><span class="status-badge status-holiday">' + escapeHtml(r.holiday_name || 'Public Holiday') + '</span></td>';
                } else {
                    html += '<td>' + (r.check_in ? escapeHtml(r.check_in) : '<span style="color:#dc2626;font-weight:600">Not Marked In</span>') + '</td>';
                    html += '<td>' + (r.check_out ? escapeHtml(r.check_out) : '<span style="color:#dc2626;font-weight:600">Not Marked Out</span>') + '</td>';
                }
                html += '<td><strong>' + escapeHtml(r.total_hours) + '</strong></td>';
                if (r.is_holiday) {
                    html += '<td><span class="status-badge status-holiday">' + (r.worked_on_holiday ? 'Worked on Holiday' : 'Holiday') + '</span></td>';
                } else {
                    var st = r.status || (isAbsent ? 'Absent' : (r.is_late ? 'Late' : 'On Time'));
                    var stCls = 'status-ontime';
                    if (st === 'Absent') stCls = 'status-absent';
                    else if (st === 'Missing Check In' || st === 'Not Marked In') stCls = 'status-missing-in';
                    else if (st === 'Missing Check Out' || st === 'Not Marked Out') stCls = 'status-missing-out';
                    else if (st === 'Late') stCls = 'status-late';
                    html += '<td><span class="status-badge ' + stCls + '">' + escapeHtml(st) + '</span></td>';
                }
                html += '</tr>';
            });
            tbody.innerHTML = html;
            updateEl('mStatTotalDays', data.working_days || 0);
            updateEl('mStatLate', late);
            updateEl('mStatOnTime', data.data.filter(function(r) { return (r.check_in || r.check_out) && !r.is_late; }).length);
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="11" class="no-data">Error loading data</td></tr>';
        });
}

// ===================== CUSTOM REPORT =====================

function loadCustomReport() {
    var from = document.getElementById('customFrom');
    var to = document.getElementById('customTo');
    var tbody = document.getElementById('customBody');
    if (!from || !to || !tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" class="no-data">Loading...</td></tr>';

    fetch('api/attendance.php?action=custom&from=' + from.value + '&to=' + to.value + employeeQuery())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="10" class="no-data">' + escapeHtml(data.message || 'Custom attendance request failed') + '</td></tr>';
                return;
            }
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="no-data">No data for this range</td></tr>';
                return;
            }
            var late = 0, html = '';
            data.data.forEach(function(r, i) {
                var isAbsent = !r.check_in && !r.check_out && !r.is_holiday;
                if (r.is_late && !isAbsent) late++;
                // If worked on holiday AND late, show as late-row (red)
                var rowClass = r.is_holiday ? (r.worked_on_holiday ? (r.is_late ? 'late-row' : 'ontime-row') : 'holiday-row') : (isAbsent ? 'absent-row' : (r.is_late ? 'late-row' : 'ontime-row'));
                html += '<tr class="' + rowClass + '">';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + escapeHtml(r.employee_id || r.badge_id) + '</td>';
                html += '<td>' + escapeHtml(r.badge_id) + '</td>';
                html += '<td style="min-width:150px;"><span data-badge="' + escapeHtml(r.badge_id) + '">' + escapeHtml(reportName(r)) + '</span>';
                if (r.whatsapp_number) {
                    var cleanNumber = String(r.whatsapp_number).replace(/[^0-9+]/g, '');
                    if (cleanNumber) {
                        html += '<br><button onclick="shareEmployeePDFViaWhatsApp(\'' + escapeHtml(r.badge_id) + '\', \'' + cleanNumber + '\', \'' + from.value + '\', \'custom\')" style="border:none;cursor:pointer;text-decoration:none;display:inline-block;margin-top:4px;padding:4px 8px;background:#25d366;color:white;border-radius:3px;font-size:10px;font-weight:600;white-space:nowrap;">📱 Share PDF</button>';
                    }
                }
                html += '</td>';
                html += '<td>' + escapeHtml(r.company_name || '') + '</td>';
                html += '<td>' + escapeHtml(r.department_name || '') + '</td>';
                html += '<td>' + escapeHtml(r.date) + '</td>';
                if (r.is_holiday && !r.worked_on_holiday) {
                    html += '<td colspan="2" style="text-align:center"><span class="status-badge status-holiday">' + escapeHtml(r.holiday_name || 'Public Holiday') + '</span></td>';
                } else {
                    html += '<td>' + (r.check_in ? escapeHtml(r.check_in) : '<span style="color:#dc2626;font-weight:600">Not Marked In</span>') + '</td>';
                    html += '<td>' + (r.check_out ? escapeHtml(r.check_out) : '<span style="color:#dc2626;font-weight:600">Not Marked Out</span>') + '</td>';
                }
                html += '<td><strong>' + escapeHtml(r.total_hours) + '</strong></td>';
                if (r.is_holiday) {
                    html += '<td><span class="status-badge status-holiday">' + (r.worked_on_holiday ? 'Worked on Holiday' : 'Holiday') + '</span></td>';
                } else {
                    var st = r.status || (isAbsent ? 'Absent' : (r.is_late ? 'Late' : 'On Time'));
                    var stCls = 'status-ontime';
                    if (st === 'Absent') stCls = 'status-absent';
                    else if (st === 'Missing Check In' || st === 'Not Marked In') stCls = 'status-missing-in';
                    else if (st === 'Missing Check Out' || st === 'Not Marked Out') stCls = 'status-missing-out';
                    else if (st === 'Late') stCls = 'status-late';
                    html += '<td><span class="status-badge ' + stCls + '">' + escapeHtml(st) + '</span></td>';
                }
                html += '</tr>';
            });
            tbody.innerHTML = html;
            updateEl('cStatTotal', data.data.filter(function(r) { return r.check_in || r.check_out; }).length);
            updateEl('cStatLate', late);
            updateEl('cStatOnTime', data.data.filter(function(r) { return (r.check_in || r.check_out) && !r.is_late; }).length);
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="10" class="no-data">Error loading data</td></tr>';
        });
}

// ===================== WHATSAPP PDF SHARE =====================

function shareEmployeePDFViaWhatsApp(badgeId, whatsappNumber, date, reportType) {
    if (!badgeId || !whatsappNumber) {
        showToast('Missing employee information', 'error');
        return;
    }
    
    showToast('Generating PDF report...', 'info');
    
    // Generate the PDF
    var url = 'api/employee_pdf.php?action=generate&badge_id=' + encodeURIComponent(badgeId) + 
              '&date=' + encodeURIComponent(date) + '&type=' + encodeURIComponent(reportType);
    
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                // Construct WhatsApp share URL with message
                var employeeName = document.querySelector('[data-badge="' + badgeId + '"]')?.textContent || 'Employee';
                var message = encodeURIComponent('Hi! Here is your attendance report for ' + date + '. Please download the PDF file.');
                var cleanNumber = String(whatsappNumber).replace(/[^0-9+]/g, '');
                
                // Create download link for the PDF
                var downloadUrl = window.location.origin + window.location.pathname.replace(/[^\/]+$/, '') + data.download_url;
                
                // Open WhatsApp with message (PDF will need to be shared manually after download)
                var whatsappUrl = 'https://wa.me/' + cleanNumber + '?text=' + message + '%0A%0APlease download your report from: ' + encodeURIComponent(downloadUrl);
                window.open(whatsappUrl, '_blank');
                
                // Also trigger download for admin
                var link = document.createElement('a');
                link.href = data.download_url;
                link.download = data.filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                setTimeout(function() { link.remove(); }, 1000);
                
                showToast('PDF generated! WhatsApp opened for sharing.', 'success');
            } else {
                showToast(data.message || 'Failed to generate PDF', 'error');
            }
        })
        .catch(function(e) {
            showToast('Error generating PDF: ' + e.message, 'error');
        });
}

// ===================== EXPORT =====================

function exportCSV(tableId, type) {
    // Use enhanced export with dashboard
    var url = 'enhanced_export.php?format=csv&type=' + type;
    
    // Add date parameter
    var datePicker = document.getElementById('datePicker') || 
                     document.getElementById('weekPicker') || 
                     document.getElementById('monthPicker') ||
                     document.getElementById('dashDatePicker');
    if (datePicker && datePicker.value) {
        url += '&date=' + encodeURIComponent(datePicker.value);
    }
    
    // Add filters
    var employee = document.getElementById('employeeFilter');
    if (employee && employee.value) url += '&employee=' + encodeURIComponent(employee.value);
    
    var company = document.getElementById('companyFilter');
    if (company && company.value) url += '&company=' + encodeURIComponent(company.value);
    
    var department = document.getElementById('departmentFilter');
    if (department && department.value) url += '&department=' + encodeURIComponent(department.value);
    
    var nameMode = document.getElementById('nameMode');
    if (nameMode && nameMode.value) url += '&name_mode=' + encodeURIComponent(nameMode.value);
    
    // Add month/year for monthly reports
    if (type === 'monthly' && datePicker && datePicker.value) {
        var parts = datePicker.value.split('-');
        if (parts.length === 2) {
            url += '&year=' + parts[0] + '&month=' + parts[1];
        }
    }
    
    // Add custom date range
    if (type === 'custom') {
        var fromDate = document.getElementById('customFrom');
        var toDate = document.getElementById('customTo');
        if (fromDate && fromDate.value) url += '&from=' + encodeURIComponent(fromDate.value);
        if (toDate && toDate.value) url += '&to=' + encodeURIComponent(toDate.value);
    }
    
    var link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    setTimeout(function() { link.remove(); }, 1000);
    showToast('Enhanced CSV with Dashboard exported successfully', 'success');
}

function exportExcel(type) {
    // Use Excel export with dashboard
    var url = 'excel_export.php?type=' + type;
    
    // Add date parameter
    var datePicker = document.getElementById('datePicker') || 
                     document.getElementById('weekPicker') || 
                     document.getElementById('monthPicker') ||
                     document.getElementById('dashDatePicker');
    if (datePicker && datePicker.value) {
        url += '&date=' + encodeURIComponent(datePicker.value);
    }
    
    // Add filters
    var employee = document.getElementById('employeeFilter');
    if (employee && employee.value) url += '&employee=' + encodeURIComponent(employee.value);
    
    var company = document.getElementById('companyFilter');
    if (company && company.value) url += '&company=' + encodeURIComponent(company.value);
    
    var department = document.getElementById('departmentFilter');
    if (department && department.value) url += '&department=' + encodeURIComponent(department.value);
    
    var nameMode = document.getElementById('nameMode');
    if (nameMode && nameMode.value) url += '&name_mode=' + encodeURIComponent(nameMode.value);
    
    // Add month/year for monthly reports
    if (type === 'monthly' && datePicker && datePicker.value) {
        var parts = datePicker.value.split('-');
        if (parts.length === 2) {
            url += '&year=' + parts[0] + '&month=' + parts[1];
        }
    }
    
    // Add custom date range
    if (type === 'custom') {
        var fromDate = document.getElementById('customFrom');
        var toDate = document.getElementById('customTo');
        if (fromDate && fromDate.value) url += '&from=' + encodeURIComponent(fromDate.value);
        if (toDate && toDate.value) url += '&to=' + encodeURIComponent(toDate.value);
    }
    
    var link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    setTimeout(function() { link.remove(); }, 1000);
    showToast('Enhanced Excel with Dashboard exported successfully', 'success');
}

function exportPDF(type) {
    // Use enhanced export with dashboard
    var url = 'enhanced_export.php?format=pdf&type=' + type;
    
    // Add filters
    var employee = document.getElementById('employeeFilter');
    if (employee && employee.value) url += '&employee=' + encodeURIComponent(employee.value);
    var company = document.getElementById('companyFilter');
    if (company && company.value) url += '&company=' + encodeURIComponent(company.value);
    var department = document.getElementById('departmentFilter');
    if (department && department.value) url += '&department=' + encodeURIComponent(department.value);
    var nameMode = document.getElementById('nameMode');
    if (nameMode) url += '&name_mode=' + encodeURIComponent(nameMode.value);
    
    // Add date parameters
    if (type === 'daily') url += '&date=' + document.getElementById('datePicker').value;
    else if (type === 'weekly') url += '&date=' + document.getElementById('weekPicker').value;
    else if (type === 'monthly') { var p = document.getElementById('monthPicker').value.split('-'); url += '&year=' + p[0] + '&month=' + p[1]; }
    else if (type === 'custom') url += '&from=' + document.getElementById('customFrom').value + '&to=' + document.getElementById('customTo').value;
    
    var link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    setTimeout(function() { link.remove(); }, 1000);
}

// ===================== SHIFTS =====================

function loadShifts() {
    fetch('api/shifts.php?action=list')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('shiftsBody');
            if (!data.success || !data.shifts.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No shifts configured</td></tr>';
                return;
            }
            var html = '';
            data.shifts.forEach(function(s, i) {
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td><strong>' + escapeHtml(s.name) + '</strong></td>';
                html += '<td>' + s.start_time + '</td>';
                html += '<td>' + s.end_time + '</td>';
                html += '<td><span class="status-badge ' + (s.is_rostered ? 'status-pending' : 'status-ontime') + '">' + (s.is_rostered ? 'Rostered' : 'General') + '</span></td>';
                html += '<td><span class="status-badge ' + (s.is_active ? 'status-ontime' : 'status-absent') + '">' + (s.is_active ? 'Active' : 'Inactive') + '</span></td>';
                html += '<td><button class="btn btn-sm btn-outline" onclick="editShift(' + s.id + ',\'' + escapeHtml(s.name) + '\',\'' + s.start_time + '\',\'' + s.end_time + '\',' + s.is_rostered + ',' + s.is_active + ')">Edit</button> ';
                html += '<button class="btn btn-sm btn-outline" onclick="deleteShift(' + s.id + ')">Delete</button></td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        });
}

    // ===================== FORM MANAGEMENT =====================

    function loadLeaveFormLinks() {
        var selectedUser = document.getElementById('formEmployeeFilter');
        var userId = selectedUser ? selectedUser.value : '';
        var tbody = document.getElementById('leaveFormsBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="no-data">Loading...</td></tr>';
    
        var url = 'api/form_links.php?action=list&type=leave';
        if (userId) url += '&user_id=' + userId;
    
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || data.links.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="no-data">No form links generated yet</td></tr>';
                    return;
                }
                var html = '';
                data.links.forEach(function(link, i) {
                    var expiresDate = link.expires_at ? new Date(link.expires_at).toLocaleDateString() : 'Never';
                    var isActive = link.is_active ? '<span class="status-badge status-ontime">Active</span>' : '<span class="status-badge status-absent">Inactive</span>';
                    var copyBtn = '<button class="btn btn-sm btn-outline" onclick="copyToClipboard(document.getElementById(\'url_' + link.id + '\'))">📋 Copy</button>';
                    var shareBtn = '<button class="btn btn-sm btn-outline" onclick="shareFormLink(\'' + link.token + '\', \'leave\')">📤 Share</button>';
                    var deactivateBtn = link.is_active ? '<button class="btn btn-sm btn-outline" onclick="deactivateFormLink(\'' + link.token + '\')">🔒 Deactivate</button>' : '';
                    html += '<tr>';
                    html += '<td>' + (i+1) + '</td>';
                    html += '<td>' + escapeHtml(link.employee_name || 'All Employees') + '</td>';
                    html += '<td>' + new Date(link.created_at).toLocaleDateString() + '</td>';
                    html += '<td>' + expiresDate + '</td>';
                    html += '<td><strong>' + (link.submission_count || 0) + '</strong></td>';
                    html += '<td><input type="text" id="url_' + link.id + '" value="' + escapeHtml(link.url) + '" readonly style="width:100%; padding:5px; font-size:12px;"></td>';
                    html += '<td>' + copyBtn + ' ' + shareBtn + ' ' + deactivateBtn + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            });
    }

    function loadOvertimeFormLinks() {
        var selectedUser = document.getElementById('formEmployeeFilterOT');
        var userId = selectedUser ? selectedUser.value : '';
        var tbody = document.getElementById('otFormsBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="no-data">Loading...</td></tr>';
    
        var url = 'api/form_links.php?action=list&type=overtime';
        if (userId) url += '&user_id=' + userId;
    
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || data.links.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="no-data">No form links generated yet</td></tr>';
                    return;
                }
                var html = '';
                data.links.forEach(function(link, i) {
                    var expiresDate = link.expires_at ? new Date(link.expires_at).toLocaleDateString() : 'Never';
                    var isActive = link.is_active ? '<span class="status-badge status-ontime">Active</span>' : '<span class="status-badge status-absent">Inactive</span>';
                    var copyBtn = '<button class="btn btn-sm btn-outline" onclick="copyToClipboard(document.getElementById(\'url_' + link.id + '\'))">📋 Copy</button>';
                    var shareBtn = '<button class="btn btn-sm btn-outline" onclick="shareFormLink(\'' + link.token + '\', \'overtime\')">📤 Share</button>';
                    var deactivateBtn = link.is_active ? '<button class="btn btn-sm btn-outline" onclick="deactivateFormLink(\'' + link.token + '\')">🔒 Deactivate</button>' : '';
                    html += '<tr>';
                    html += '<td>' + (i+1) + '</td>';
                    html += '<td>' + escapeHtml(link.employee_name || 'All Employees') + '</td>';
                    html += '<td>' + new Date(link.created_at).toLocaleDateString() + '</td>';
                    html += '<td>' + expiresDate + '</td>';
                    html += '<td><strong>' + (link.submission_count || 0) + '</strong></td>';
                    html += '<td><input type="text" id="url_' + link.id + '" value="' + escapeHtml(link.url) + '" readonly style="width:100%; padding:5px; font-size:12px;"></td>';
                    html += '<td>' + copyBtn + ' ' + shareBtn + ' ' + deactivateBtn + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            });
    }

    function generateLeaveFormLink() {
        var selectedUser = document.getElementById('formEmployeeFilter');
        var userId = selectedUser ? selectedUser.value : null;
            if (!userId) {
                showToast('Select an employee before generating a form link', 'error');
                return;
            }
    
        var url = 'api/form_links.php?action=generate&type=leave';
        if (userId) url += '&user_id=' + userId;
    
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                      document.getElementById('formLinkURL').value = data.url;
                      document.getElementById('formLinkModal').classList.add('show');
                    loadLeaveFormLinks();
                    showToast(data.email_sent ? 'Form link generated and emailed to employee' : 'Form link generated successfully', 'success');
                } else {
                    showToast(data.message || 'Failed to generate link', 'error');
                }
            })
            .catch(function() { showToast('Error generating link', 'error'); });
    }

    function generateOvertimeFormLink() {
        var selectedUser = document.getElementById('formEmployeeFilterOT');
        var userId = selectedUser ? selectedUser.value : null;
            if (!userId) {
                showToast('Select an employee before generating a form link', 'error');
                return;
            }
    
        var url = 'api/form_links.php?action=generate&type=overtime';
        if (userId) url += '&user_id=' + userId;
    
        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('formLinkURLOT').value = data.url;
                    document.getElementById('formLinkModalOT').classList.add('show');
                    loadOvertimeFormLinks();
                    showToast(data.email_sent ? 'Form link generated and emailed to employee' : 'Form link generated successfully', 'success');
                } else {
                    showToast(data.message || 'Failed to generate link', 'error');
                }
            })
            .catch(function() { showToast('Error generating link', 'error'); });
    }

    function copyToClipboard(element) {
        if (!element) return;
        element.select();
        document.execCommand('copy');
        showToast('Copied to clipboard!', 'success');
    }

    function shareFormLink(token, type) {
        var basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        var url = window.location.origin + basePath + 'form.php?token=' + encodeURIComponent(token);
        var isOvertime = type === 'overtime';
        var modal = document.getElementById(isOvertime ? 'formLinkModalOT' : 'formLinkModal');
        if (modal) {
            document.getElementById(isOvertime ? 'formLinkURLOT' : 'formLinkURL').value = url;
            modal.classList.add('show');
        }
    }

    function shareLeaveFormViaWhatsApp() {
        var url = document.getElementById('formLinkURL').value;
        var text = encodeURIComponent('[HRIS ROWELMARK GROUP] Hi! Please fill out your leave request using this link: ' + url);
        window.open('https://wa.me/?text=' + text, '_blank');
    }

    function shareOTFormViaWhatsApp() {
        var url = document.getElementById('formLinkURLOT').value;
        var text = encodeURIComponent('[HRIS ROWELMARK GROUP] Hi! Please fill out your overtime request using this link: ' + url);
        window.open('https://wa.me/?text=' + text, '_blank');
    }

    // Email sharing functions removed - WhatsApp only system

    function deactivateFormLink(token) {
        if (!confirm('Deactivate this form link? Employees won\'t be able to submit through it anymore.')) return;
        fetch('api/form_links.php?action=deactivate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token })
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    loadLeaveFormLinks();
                    loadOvertimeFormLinks();
                    showToast('Form link deactivated', 'info');
                } else {
                    showToast(data.message || 'Failed to deactivate link', 'error');
                }
            });
    }

    function clearFormLinksByType(type) {
        var label = type === 'leave' ? 'Leave' : 'Overtime';
        if (!confirm('This will permanently delete all ' + label.toLowerCase() + ' form links and their submissions. Are you sure?')) return;
        fetch('api/form_links.php?action=clear_type&type=' + type, { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (type === 'leave') loadLeaveFormLinks();
                    else loadOvertimeFormLinks();
                    showToast(data.message || label + ' form links cleared', 'success');
                } else {
                    showToast(data.message || 'Failed to clear form links', 'error');
                }
            });
    }

    function closeFormLinkModal() {
        document.getElementById('formLinkModal').classList.remove('show');
    }

        function closeFormLinkModalOT() {
            var modal = document.getElementById('formLinkModalOT');
            if (modal) modal.classList.remove('show');
        }

    function loadFormEmployeeFilter(selector) {
        var filter = document.querySelector(selector);
        if (!filter) return;
        fetch('api/attendance.php?action=users')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                data.users.forEach(function(user) {
                    var option = document.createElement('option');
                    option.value = user.id;
                    var display = user.calling_name || user.name;
                    display += ' | Employee ID: ' + (user.employee_id || '-') + ' | Enroll: ' + user.badge_id;
                    if (user.email) display += ' | Email: ' + user.email;
                    option.textContent = display;
                    filter.appendChild(option);
                });
            });
    }

function openShiftModal() {
    document.getElementById('shiftModalTitle').textContent = 'Add Shift';
    document.getElementById('shiftId').value = '';
    document.getElementById('shiftName').value = '';
    document.getElementById('shiftStart').value = '';
    document.getElementById('shiftEnd').value = '';
    document.getElementById('shiftRostered').checked = false;
    document.getElementById('shiftActive').checked = true;
    document.getElementById('shiftModal').classList.add('show');
}

function editShift(id, name, start, end, rostered, active) {
    document.getElementById('shiftModalTitle').textContent = 'Edit Shift';
    document.getElementById('shiftId').value = id;
    document.getElementById('shiftName').value = name;
    document.getElementById('shiftStart').value = start;
    document.getElementById('shiftEnd').value = end;
    document.getElementById('shiftRostered').checked = rostered;
    document.getElementById('shiftActive').checked = active;
    document.getElementById('shiftModal').classList.add('show');
}

function closeShiftModal() {
    document.getElementById('shiftModal').classList.remove('show');
}

function saveShift() {
    var id = document.getElementById('shiftId').value;
    var fd = new FormData();
    fd.append('name', document.getElementById('shiftName').value);
    fd.append('start_time', document.getElementById('shiftStart').value);
    fd.append('end_time', document.getElementById('shiftEnd').value);
    fd.append('is_rostered', document.getElementById('shiftRostered').checked ? 1 : 0);
    fd.append('is_active', document.getElementById('shiftActive').checked ? 1 : 0);
    var action = id ? 'update' : 'create';
    if (id) fd.append('id', id);
    fetch('api/shifts.php?action=' + action, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            closeShiftModal();
            loadShifts();
            showToast(id ? 'Shift updated' : 'Shift created', 'success');
        });
}

function deleteShift(id) {
    if (!confirm('Delete this shift? Rosters using it must be removed first.')) return;
    var fd = new FormData();
    fd.append('id', id);
    fetch('api/shifts.php?action=delete', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { showToast(data.message || 'Shift could not be deleted', 'error'); return; }
            loadShifts();
            showToast('Shift deleted', 'info');
        });
}

// ===================== ROSTER =====================

var rosterShifts = [];

function loadRosterUsers() {
    fetch('api/attendance.php?action=users')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var sel = document.getElementById('rosterUser');
            var rmSel = document.getElementById('rmUser');
            if (data.success) {
                data.users.forEach(function(u) {
                    var displayName = u.calling_name || u.name;
                    sel.innerHTML += '<option value="' + u.id + '">' + escapeHtml(displayName) + '</option>';
                    if (rmSel) rmSel.innerHTML += '<option value="' + u.id + '">' + escapeHtml(displayName) + '</option>';
                });
            }
        });
    fetch('api/shifts.php?action=active')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) rosterShifts = data.shifts;
            buildRosterDaySelectors();
        });
}

function buildRosterDaySelectors() {
    var container = document.getElementById('rmDays');
    if (!container) return;
    var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    var html = '';
    days.forEach(function(d, i) {
        html += '<div class="form-group"><label>' + d + '</label>';
        html += '<select id="rmDay' + i + '"><option value="rest">REST DAY</option>';
        rosterShifts.forEach(function(s) {
            html += '<option value="' + s.id + '">' + escapeHtml(s.name) + ' (' + s.start_time + '-' + s.end_time + ')</option>';
        });
        html += '</select></div>';
    });
    container.innerHTML = html;
}

function loadRosterCalendar() {
    var userId = document.getElementById('rosterUser').value;
    var monthVal = document.getElementById('rosterMonth').value;
    var cal = document.getElementById('rosterCalendar');
    var info = document.getElementById('rosterInfo');
    if (!userId || !monthVal) { cal.innerHTML = '<p class="no-data">Select an employee and month</p>'; return; }
    var parts = monthVal.split('-');
    var year = parseInt(parts[0]), month = parseInt(parts[1]);
    fetch('api/roster.php?action=list&year=' + year + '&month=' + month)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var userRoster = data.data.find(function(r) { return r.user_id == userId; });
            var daysInMonth = new Date(year, month, 0).getDate();
            var firstDay = new Date(year, month - 1, 1).getDay();
            var startCol = firstDay === 0 ? 6 : firstDay - 1;
            var dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            var html = '<div class="calendar-header">';
            dayNames.forEach(function(d) { html += '<div class="cal-header-cell">' + d + '</div>'; });
            html += '</div><div class="calendar-body">';
            for (var i = 0; i < startCol; i++) html += '<div class="cal-cell empty"></div>';
            for (var d = 1; d <= daysInMonth; d++) {
                var dateStr = year + '-' + String(month).padStart(2,'0') + '-' + String(d).padStart(2,'0');
                var shiftLabel = 'No roster', cls = 'cal-cell';
                if (userRoster) {
                    var ov = userRoster.overrides.find(function(o) { return o.override_date === dateStr; });
                    if (ov) {
                        if (ov.is_rest_day) { shiftLabel = 'OFF'; cls += ' rest-day'; }
                        else { var sh = rosterShifts.find(function(s) { return s.id == ov.shift_id; }); shiftLabel = sh ? sh.name : 'Set'; cls += ' has-shift'; }
                    } else {
                        var dow = new Date(year, month-1, d).getDay();
                        var dowIdx = dow === 0 ? 6 : dow - 1;
                        var rd = userRoster.days.find(function(r) { return r.day_of_week == dowIdx; });
                        if (rd) {
                            if (rd.is_rest_day) { shiftLabel = 'OFF'; cls += ' rest-day'; }
                            else { shiftLabel = rd.shift_name || 'Set'; cls += ' has-shift'; }
                        }
                    }
                }
                html += '<div class="' + cls + '" onclick="openDayOverride(\'' + dateStr + '\')" title="' + dateStr + '">';
                html += '<span class="cal-date">' + d + '</span>';
                html += '<span class="cal-shift">' + shiftLabel + '</span></div>';
            }
            html += '</div>';
            cal.innerHTML = html;
            if (userRoster) {
                info.style.display = 'block';
                info.innerHTML = '<strong>Roster:</strong> From ' + userRoster.effective_from + (userRoster.effective_to ? ' to ' + userRoster.effective_to : ' (ongoing)') + ' &nbsp;|&nbsp; <a href="#" onclick="deleteRoster(' + userRoster.roster_id + ');return false;" style="color:var(--danger);font-weight:600;">Delete Roster</a>';
            } else {
                info.style.display = 'block';
                info.innerHTML = '<em>No roster assigned for this employee in this period.</em>';
            }
        });
}

var currentOverrideDate = null;

function openDayOverride(dateStr) {
    currentOverrideDate = dateStr;
    document.getElementById('dmDate').textContent = dateStr;
    var sel = document.getElementById('dmShift');
    sel.innerHTML = '<option value="">REST DAY</option>';
    rosterShifts.forEach(function(s) { sel.innerHTML += '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>'; });
    document.getElementById('dayModal').classList.add('show');
}

function closeDayModal() { document.getElementById('dayModal').classList.remove('show'); }

function saveDayOverride() {
    var userId = document.getElementById('rosterUser').value;
    var monthVal = document.getElementById('rosterMonth').value;
    var parts = monthVal.split('-');
    fetch('api/roster.php?action=list&year=' + parts[0] + '&month=' + parts[1])
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var userRoster = data.data.find(function(r) { return r.user_id == userId; });
            if (!userRoster) { alert('No roster for this employee'); return; }
            var shiftId = document.getElementById('dmShift').value;
            var fd = new FormData();
            fd.append('roster_id', userRoster.roster_id);
            fd.append('date', currentOverrideDate);
            fd.append('shift_id', shiftId);
            fd.append('is_rest_day', shiftId ? 0 : 1);
            fetch('api/roster.php?action=override', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function() { closeDayModal(); loadRosterCalendar(); showToast('Day override saved', 'success'); });
        });
}

function openRosterModal() {
    buildRosterDaySelectors();
    document.getElementById('rosterModal').classList.add('show');
}

function closeRosterModal() { document.getElementById('rosterModal').classList.remove('show'); }

function saveRoster() {
    var days = [];
    for (var i = 0; i < 7; i++) {
        var val = document.getElementById('rmDay' + i).value;
        days.push({ day_of_week: i, shift_id: val === 'rest' ? null : parseInt(val), is_rest_day: val === 'rest' ? 1 : 0 });
    }
    var data = {
        user_id: parseInt(document.getElementById('rmUser').value),
        effective_from: document.getElementById('rmFrom').value,
        effective_to: document.getElementById('rmTo').value || null,
        created_by: 1,
        days: days
    };
    var fd = new FormData();
    fd.append('action', 'create');
    fd.append('data', JSON.stringify(data));
    fetch('api/roster.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (result.success) {
                closeRosterModal();
                loadRosterCalendar();
                showToast('Roster created', 'success');
            } else {
                showToast(result.message || 'Error creating roster', 'error');
            }
        })
        .catch(function() {
            showToast('Error creating roster', 'error');
        });
}

function deleteRoster(rosterId) {
    if (!confirm('Delete this roster?')) return;
    var fd = new FormData(); fd.append('roster_id', rosterId);
    fetch('api/roster.php?action=delete', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadRosterCalendar(); showToast('Roster deleted', 'info'); });
}

// ===================== LEAVE =====================

function requestFilterQuery() {
    var fields = {
        search: document.getElementById('requestSearch'),
        employee: document.getElementById('requestEmployee'),
        company: document.getElementById('requestCompany'),
        department: document.getElementById('requestDepartment'),
        status: document.getElementById('requestStatus'),
        type: document.getElementById('requestType')
    };
    var query = '';
    Object.keys(fields).forEach(function(key) {
        if (fields[key] && fields[key].value) query += '&' + key + '=' + encodeURIComponent(fields[key].value);
    });
    return query;
}

function loadRequestFilters() {
    var filterIds = ['requestSearch', 'requestEmployee', 'requestCompany', 'requestDepartment', 'requestStatus', 'requestType'];
    filterIds.forEach(function(id) {
        var field = document.getElementById(id);
        if (!field) return;
        field.addEventListener('input', refreshRequestTable);
        field.addEventListener('change', refreshRequestTable);
    });
    var endpoint = 'api/leave.php?action=request_employees';
    if (document.getElementById('otPendingBody') || document.getElementById('otSummaryBody')) {
        endpoint = 'api/overtime.php?action=request_employees';
    }
    fetch(endpoint)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) return;
            var users = document.getElementById('requestEmployee');
            var companies = document.getElementById('requestCompany');
            var departments = document.getElementById('requestDepartment');
            var companyValues = {}, departmentValues = {};
            data.users.forEach(function(user) {
                var displayName = user.calling_name || user.name;
                if (users) users.appendChild(new Option(displayName + ' | Employee ID: ' + (user.employee_id || '-') + ' | Enroll: ' + user.badge_id, user.badge_id));
                if (user.company_name) companyValues[user.company_name] = true;
                if (user.department_name) departmentValues[user.department_name] = true;
            });
            Object.keys(companyValues).sort().forEach(function(value) { if (companies) companies.appendChild(new Option(value, value)); });
            Object.keys(departmentValues).sort().forEach(function(value) { if (departments) departments.appendChild(new Option(value, value)); });
        });
}

function refreshRequestTable() {
    if (document.getElementById('leavePendingBody')) loadPendingLeaves();
    if (document.getElementById('otPendingBody')) loadPendingOT();
}

function showLeaveTab(tab) {
    var myTab = document.getElementById('leaveMyTab');
    if (myTab) myTab.style.display = tab === 'my' ? 'block' : 'none';
    var pendingTab = document.getElementById('leavePendingTab');
    if (pendingTab) pendingTab.style.display = tab === 'pending' ? 'block' : 'none';
    var trackerTab = document.getElementById('leaveTrackerTab');
    if (trackerTab) trackerTab.style.display = tab === 'tracker' ? 'block' : 'none';
    var formsTab = document.getElementById('leaveFormsTab');
    if (formsTab) formsTab.style.display = tab === 'forms' ? 'block' : 'none';
    var pendingBtn = document.getElementById('leaveTabPending');
    if (pendingBtn) pendingBtn.className = 'tab' + (tab === 'pending' ? ' active' : '');
    var trackerBtn = document.getElementById('leaveTabTracker');
    if (trackerBtn) trackerBtn.className = 'tab' + (tab === 'tracker' ? ' active' : '');
    var formsBtn = document.getElementById('leaveTabForms');
    if (formsBtn) formsBtn.className = 'tab' + (tab === 'forms' ? ' active' : '');
    if (tab === 'my' && typeof loadMyLeaves === 'function') loadMyLeaves(); else if (tab === 'pending') loadPendingLeaves(); else if (tab === 'tracker') loadLeaveTracker(); else if (tab === 'forms') loadLeaveFormLinks();
}

function loadMyLeaves() {
    var year = new Date().getFullYear();
    fetch('api/leave.php?action=my&year=' + year)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.balances) {
                var bh = '';
                var names = {annual:'Annual',sick:'Sick',casual:'Casual',maternity:'Maternity',paternity:'Paternity',nopay:'No Pay'};
                for (var k in data.balances) {
                    var v = data.balances[k];
                    var remain = v.entitled - v.used;
                    bh += '<div class="stat-card"><span class="stat-value">' + remain + '/' + v.entitled + '</span><span class="stat-label">' + names[k] + '</span></div>';
                }
                var balanceEl = document.getElementById('leaveBalances');
                if (balanceEl) balanceEl.innerHTML = bh;
            }
            var tbody = document.getElementById('leaveMyBody');
            if (!tbody) return;
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No leave requests</td></tr>';
                return;
            }
            var html = '';
            data.data.forEach(function(l, i) {
                var stCls = l.status === 'approved' ? 'status-ontime' : l.status === 'rejected' ? 'status-late' : 'status-pending';
                var canCancel = l.status === 'pending' ? '<button class="btn btn-sm btn-danger" onclick="cancelLeave(' + l.id + ')">Cancel</button>' : '';
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + escapeHtml(l.leave_type) + '</td>';
                html += '<td>' + l.start_date + '</td>';
                html += '<td>' + l.end_date + '</td>';
                html += '<td>' + daysBetween(l.start_date, l.end_date) + '</td>';
                html += '<td>' + escapeHtml(l.reason || '-') + '</td>';
                html += '<td><span class="status-badge ' + stCls + '">' + l.status + '</span></td>';
                html += '<td>' + canCancel + '</td></tr>';
            });
            tbody.innerHTML = html;
        });
}

function loadLeaveTracker() {
    var employeeSelect = document.getElementById('trackerEmployee');
    var yearSelect = document.getElementById('trackerYear');
    var tbody = document.getElementById('leaveTrackerBody');
    var summary = document.getElementById('leaveTrackerSummary');
    if (!tbody || !summary) return;

    var employeeId = employeeSelect ? employeeSelect.value : '';
    var year = yearSelect ? yearSelect.value : new Date().getFullYear();
    if (!employeeId) {
        var currentUser = document.body && document.body.dataset && document.body.dataset.userId ? document.body.dataset.userId : '';
        employeeId = currentUser;
    }

    tbody.innerHTML = '<tr><td colspan="7" class="no-data">Loading...</td></tr>';
    var url = 'api/leave.php?action=tracker&year=' + encodeURIComponent(year);
    if (employeeId) url += '&user_id=' + encodeURIComponent(employeeId);

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                summary.innerHTML = '<div class="stat-card"><span class="stat-value">--</span><span class="stat-label">No access</span></div>';
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">' + (data.message || 'Unable to load tracker') + '</td></tr>';
                return;
            }

            var names = {annual:'Annual',sick:'Sick',casual:'Casual',maternity:'Maternity',paternity:'Paternity',nopay:'No Pay'};
            var cards = '';
            Object.keys(data.balances || {}).forEach(function(key) {
                var detail = data.balances[key] || {entitled: 0, used: 0};
                var remaining = Math.max(0, (detail.entitled || 0) - (detail.used || 0));
                cards += '<div class="stat-card"><span class="stat-value">' + remaining + '/' + (detail.entitled || 0) + '</span><span class="stat-label">' + (names[key] || key) + '</span></div>';
            });
            summary.innerHTML = cards || '<div class="stat-card"><span class="stat-value">0/0</span><span class="stat-label">No data</span></div>';

            var rows = data.records || [];
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No leave records for this employee</td></tr>';
                return;
            }

            var html = '';
            rows.forEach(function(row, index) {
                var days = daysBetween(row.start_date, row.end_date);
                html += '<tr>';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td>' + escapeHtml(row.leave_type || '-') + '</td>';
                html += '<td>' + (row.start_date || '-') + '</td>';
                html += '<td>' + (row.end_date || '-') + '</td>';
                html += '<td>' + days + '</td>';
                html += '<td><span class="status-badge ' + (row.status === 'approved' ? 'status-ontime' : row.status === 'rejected' ? 'status-late' : 'status-pending') + '">' + escapeHtml(row.status || 'pending') + '</span></td>';
                html += '<td>' + escapeHtml(row.reason || '-') + '</td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        });
}

function loadPendingLeaves() {
    fetch('api/leave.php?action=pending' + requestFilterQuery())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('leavePendingBody');
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No pending requests</td></tr>';
                return;
            }
            var html = '';
            data.data.forEach(function(l, i) {
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td><strong>' + escapeHtml(l.employee_name) + '</strong></td>';
                html += '<td>' + escapeHtml(l.leave_type) + '</td>';
                html += '<td>' + l.start_date + '</td>';
                html += '<td>' + l.end_date + '</td>';
                html += '<td>' + daysBetween(l.start_date, l.end_date) + '</td>';
                html += '<td>' + escapeHtml(l.reason || '-') + '</td>';
                var actions = l.status === 'pending' ? '<button class="btn btn-sm btn-success" onclick="approveLeave(' + l.id + ')">Approve</button> <button class="btn btn-sm btn-danger" onclick="rejectLeave(' + l.id + ')">Reject</button>' : '<span class="status-badge status-' + (l.status === 'approved' ? 'ontime' : 'late') + '">' + escapeHtml(l.status) + '</span>';
                html += '<td>' + actions + '</td></tr>';
            });
            tbody.innerHTML = html;
        });
}

function loadLeaveSummary() {
    var year = new Date().getFullYear();
    fetch('api/leave.php?action=summary&year=' + year)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('leaveSummaryBody');
            if (!tbody) return;
            var filters = requestFilterQuery();
            var filterValues = {};
            filters.replace(/[&?]([^=]+)=([^&]*)/g, function(_, key, value) { filterValues[key] = decodeURIComponent(value); });
            var rows = data.data.filter(function(row) { return (!filterValues.employee || row.badge_id === filterValues.employee) && (!filterValues.department || row.department_name === filterValues.department) && (!filterValues.company || row.company_name === filterValues.company) && (!filterValues.search || (row.name + ' ' + row.badge_id + ' ' + (row.department_name || '')).toLowerCase().indexOf(filterValues.search.toLowerCase()) >= 0); });
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="no-data">No employee leave data</td></tr>'; return; }
            document.getElementById('leaveSummaryPeriod').textContent = year;
            tbody.innerHTML = rows.map(function(row) {
                return '<tr><td><strong>' + escapeHtml(row.name) + '</strong><br><small>' + escapeHtml(row.badge_id) + '</small></td><td>' + escapeHtml(row.department_name || '-') + '</td><td>' + Math.round(row.approved_days) + '</td><td>' + Math.round(row.pending_days) + '</td><td>' + Math.round(row.rejected_days) + '</td><td>' + row.total_requests + '</td></tr>';
            }).join('');
        });
}

function openLeaveModal() { document.getElementById('leaveModal').classList.add('show'); }
function closeLeaveModal() { document.getElementById('leaveModal').classList.remove('show'); }

function submitLeave() {
    var fd = new FormData();
    fd.append('leave_type', document.getElementById('lvType').value);
    fd.append('start_date', document.getElementById('lvFrom').value);
    fd.append('end_date', document.getElementById('lvTo').value);
    fd.append('reason', document.getElementById('lvReason').value);
    fetch('api/leave.php?action=submit', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                closeLeaveModal();
                loadMyLeaves();
                showToast('Leave request submitted!', 'success');
            } else {
                showToast(d.message || 'Error submitting leave', 'error');
            }
        });
}

function approveLeave(id) {
    var fd = new FormData(); fd.append('leave_id', id);
    fetch('api/leave.php?action=approve', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadPendingLeaves(); loadMyLeaves(); showToast('Leave approved', 'success'); });
}

function rejectLeave(id) {
    var reason = prompt('Rejection reason (optional):');
    var fd = new FormData(); fd.append('leave_id', id); fd.append('rejection_reason', reason || '');
    fetch('api/leave.php?action=reject', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadPendingLeaves(); loadMyLeaves(); showToast('Leave rejected', 'info'); });
}

function cancelLeave(id) {
    if (!confirm('Cancel this leave request?')) return;
    var fd = new FormData(); fd.append('leave_id', id);
    fetch('api/leave.php?action=cancel', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadMyLeaves(); showToast('Leave cancelled', 'info'); });
}

// ===================== OVERTIME =====================

function showOTTab(tab) {
    var myTab = document.getElementById('otMyTab');
    if (myTab) myTab.style.display = tab === 'my' ? 'block' : 'none';
    var pendingTab = document.getElementById('otPendingTab');
    if (pendingTab) pendingTab.style.display = tab === 'pending' ? 'block' : 'none';
    var formsTab = document.getElementById('otFormsTab');
    if (formsTab) formsTab.style.display = tab === 'forms' ? 'block' : 'none';
    document.getElementById('otTabPending').className = 'tab' + (tab === 'pending' ? ' active' : '');
    document.getElementById('otTabForms').className = 'tab' + (tab === 'forms' ? ' active' : '');
    if (tab === 'my' && typeof loadMyOT === 'function') loadMyOT(); else if (tab === 'pending') loadPendingOT(); else if (tab === 'forms') loadOvertimeFormLinks();
}

function loadMyOT() {
    var mv = document.getElementById('otMonth').value.split('-');
    fetch('api/overtime.php?action=my&year=' + mv[0] + '&month=' + mv[1])
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('otMyBody');
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No OT records</td></tr>';
                return;
            }
            var html = '';
            data.data.forEach(function(o, i) {
                var stCls = o.status === 'approved' ? 'status-ontime' : o.status === 'rejected' ? 'status-late' : 'status-pending';
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + o.ot_date + '</td>';
                html += '<td><strong>' + o.hours + 'h</strong></td>';
                html += '<td>' + escapeHtml(o.reason || '-') + '</td>';
                html += '<td><span class="status-badge ' + stCls + '">' + o.status + '</span></td>';
                html += '<td>' + escapeHtml(o.approved_by_name || '-') + '</td></tr>';
            });
            tbody.innerHTML = html;
        });
}

function loadPendingOT() {
    fetch('api/overtime.php?action=pending' + requestFilterQuery())
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('otPendingBody');
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No pending OT</td></tr>';
                return;
            }
            var html = '';
            data.data.forEach(function(o, i) {
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td><strong>' + escapeHtml(o.employee_name) + '</strong></td>';
                html += '<td>' + o.ot_date + '</td>';
                html += '<td><strong>' + o.hours + 'h</strong></td>';
                html += '<td>' + escapeHtml(o.reason || '-') + '</td>';
                var actions = o.status === 'pending' ? '<button class="btn btn-sm btn-success" onclick="approveOT(' + o.id + ')">Approve</button> <button class="btn btn-sm btn-danger" onclick="rejectOT(' + o.id + ')">Reject</button>' : '<span class="status-badge status-' + (o.status === 'approved' ? 'ontime' : 'late') + '">' + escapeHtml(o.status) + '</span>';
                html += '<td>' + actions + '</td></tr>';
            });
            tbody.innerHTML = html;
        });
}

function loadOTSummary() {
    var now = new Date();
    fetch('api/overtime.php?action=summary&year=' + now.getFullYear() + '&month=' + String(now.getMonth() + 1).padStart(2, '0'))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('otSummaryBody');
            if (!tbody) return;
            var filters = requestFilterQuery();
            var filterValues = {};
            filters.replace(/[&?]([^=]+)=([^&]*)/g, function(_, key, value) { filterValues[key] = decodeURIComponent(value); });
            var rows = data.data.filter(function(row) { return (!filterValues.employee || row.badge_id === filterValues.employee) && (!filterValues.department || row.department_name === filterValues.department) && (!filterValues.company || row.company_name === filterValues.company) && (!filterValues.search || (row.name + ' ' + row.badge_id + ' ' + (row.department_name || '')).toLowerCase().indexOf(filterValues.search.toLowerCase()) >= 0); });
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="no-data">No employee overtime data</td></tr>'; return; }
            document.getElementById('otSummaryPeriod').textContent = now.toLocaleString(undefined, { month: 'long', year: 'numeric' });
            tbody.innerHTML = rows.map(function(row) {
                return '<tr><td><strong>' + escapeHtml(row.name) + '</strong><br><small>' + escapeHtml(row.badge_id) + '</small></td><td>' + escapeHtml(row.department_name || '-') + '</td><td>' + Number(row.approved_hours).toFixed(2) + 'h</td><td>' + Number(row.pending_hours).toFixed(2) + 'h</td><td>' + Number(row.rejected_hours).toFixed(2) + 'h</td><td>' + row.total_requests + '</td></tr>';
            }).join('');
        });
}

function openOTModal() { document.getElementById('otModal').classList.add('show'); }
function closeOTModal() { document.getElementById('otModal').classList.remove('show'); }

function submitOT() {
    var fd = new FormData();
    fd.append('ot_date', document.getElementById('otDate').value);
    fd.append('hours', document.getElementById('otHours').value);
    fd.append('reason', document.getElementById('otReason').value);
    fetch('api/overtime.php?action=submit', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                closeOTModal();
                loadMyOT();
                showToast('OT submitted!', 'success');
            } else {
                showToast(d.message || 'Error', 'error');
            }
        });
}

function approveOT(id) {
    var fd = new FormData(); fd.append('ot_id', id);
    fetch('api/overtime.php?action=approve', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadPendingOT(); loadMyOT(); showToast('OT approved', 'success'); });
}

function rejectOT(id) {
    var reason = prompt('Rejection reason (optional):');
    var fd = new FormData(); fd.append('ot_id', id); fd.append('rejection_reason', reason || '');
    fetch('api/overtime.php?action=reject', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadPendingOT(); loadMyOT(); showToast('OT rejected', 'info'); });
}

// ===================== HOLIDAYS =====================

function loadHolidays() {
    var year = document.getElementById('holidayYear').value;
    if (!year) year = new Date().getFullYear();
    fetch('api/holidays.php?action=list&year=' + encodeURIComponent(year))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('holidaysBody');
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">Error loading holidays: ' + escapeHtml(data.message || 'Unknown') + '</td></tr>';
                updateEl('hStatTotal', 0); updateEl('hStatPoya', 0); updateEl('hStatOther', 0);
                return;
            }
            if (!data.holidays || data.holidays.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No holidays. Click "Seed Holidays" to add Sri Lankan holidays.</td></tr>';
                updateEl('hStatTotal', 0); updateEl('hStatPoya', 0); updateEl('hStatOther', 0);
                return;
            }
            var html = '', poya = 0;
            data.holidays.forEach(function(h, i) {
                if (h.is_poya) poya++;
                var d = new Date(h.holiday_date + 'T00:00:00');
                var dayName = d.toLocaleDateString('en-US', { weekday: 'long' });
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + h.holiday_date + '</td>';
                html += '<td>' + dayName + '</td>';
                html += '<td><strong>' + escapeHtml(h.name) + '</strong></td>';
                html += '<td><span class="status-badge ' + (h.is_poya ? 'status-pending' : 'status-info') + '">' + (h.is_poya ? 'Poya' : 'Public Holiday') + '</span></td>';
                html += '<td><button class="btn btn-sm btn-danger" onclick="removeHoliday(' + h.id + ')">Remove</button></td></tr>';
            });
            tbody.innerHTML = html;
            updateEl('hStatTotal', data.holidays.length);
            updateEl('hStatPoya', poya);
            updateEl('hStatOther', data.holidays.length - poya);
        })
        .catch(function(err) {
            console.error('loadHolidays error:', err);
            var tbody = document.getElementById('holidaysBody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="no-data">Failed to load holidays. Check console.</td></tr>';
        });
}

function seedCurrentYearHolidays(evt) {
    var year = document.getElementById('holidayYear').value;
    if (!year) {
        showToast('Please select a year', 'error');
        return;
    }
    var fd = new FormData(); 
    fd.append('action', 'seed');
    fd.append('year', year);
    
    // Show loading state
    var btn = evt && evt.target ? evt.target.closest('button') : document.querySelector('.btn-export');
    var originalText = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span>&#9203;</span> Seeding...'; }
    
    fetch('api/holidays.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
            
            if (result.success) {
                var msg = result.count > 0 ? 'Added ' + result.count + ' holidays for ' + year : 'All holidays already exist for ' + year;
                showToast(msg, 'success');
                loadHolidays();
            } else {
                showToast(result.message || 'Error seeding holidays', 'error');
            }
        })
        .catch(function(err) {
            if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
            showToast('Network error while seeding holidays', 'error');
            console.error('Seed error:', err);
        });
}

function openHolidayModal() { document.getElementById('holidayModal').classList.add('show'); }
function closeHolidayModal() { document.getElementById('holidayModal').classList.remove('show'); }

function addHoliday() {
    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('date', document.getElementById('hzDate').value);
    fd.append('name', document.getElementById('hzName').value);
    fd.append('is_poya', document.getElementById('hzPoya').checked ? 1 : 0);
    fetch('api/holidays.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            if (result.success) {
                closeHolidayModal();
                loadHolidays();
                showToast('Holiday added', 'success');
            } else {
                showToast(result.message || 'Error adding holiday', 'error');
            }
        })
        .catch(function() {
            showToast('Error adding holiday', 'error');
        });
}

function removeHoliday(id) {
    if (!confirm('Remove this holiday?')) return;
    var fd = new FormData(); 
    fd.append('action', 'remove');
    fd.append('id', id);
    fetch('api/holidays.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function() { loadHolidays(); showToast('Holiday removed', 'info'); });
}

// ===================== NOTIFICATIONS =====================

function loadNotifications() {
    var filter = document.getElementById('notifFilter') ? document.getElementById('notifFilter').value : '';
    fetch('api/notifications.php?action=list' + (filter ? '&type=' + filter : ''))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tbody = document.getElementById('notifBody');
            if (!data.success || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="no-data">No notifications</td></tr>';
                return;
            }
            var html = '';
            data.data.forEach(function(n, i) {
                var chCls = n.channel === 'sms' ? 'status-ontime' : 'status-info';
                html += '<tr>';
                html += '<td>' + (i+1) + '</td>';
                html += '<td>' + (n.created_at || '-') + '</td>';
                html += '<td>' + escapeHtml(n.user_name || '-') + '</td>';
                html += '<td>' + escapeHtml(n.type) + '</td>';
                html += '<td><span class="status-badge ' + chCls + '">' + n.channel.toUpperCase() + '</span></td>';
                html += '<td>' + escapeHtml(n.subject || '-') + '</td>';
                html += '<td>' + escapeHtml(n.message) + '</td>';
                html += '<td><span class="status-badge status-ontime">' + escapeHtml(n.status) + '</span></td>';
                html += '</tr>';
            });
            tbody.innerHTML = html;
        });
}

// ===================== DASHBOARD =====================

var dashDonutChart = null, dashTrendChart = null, dashCompanyChart = null, dashDeptChart = null;
var dashFullData = null;
var dashActiveCompany = '';
var dashAnimFrames = {};

var donutCenterPlugin = {
    id: 'donutCenter',
    afterDraw: function(chart) {}
};

function switchCompanyTab(btn) {
    document.querySelectorAll('.company-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    dashActiveCompany = btn.getAttribute('data-company');
    loadDashboardData();
}

function loadDashboard() {
    var tabs = document.getElementById('companyTabs');
    if (!tabs) return;
    var dp = document.getElementById('dashDatePicker');
    var url = 'api/attendance.php?action=dashboard&date=' + encodeURIComponent(dp ? dp.value : todayStr());
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                console.error('Dashboard API error:', data.message || 'Unknown error');
                var cards = document.querySelectorAll('.dash-stat-value');
                cards.forEach(function(c) { c.textContent = '-'; });
                return;
            }
            dashFullData = data;
            buildCompanyTabs(data.company_names || []);
            var dashBanner = document.getElementById('dashHolidayBanner');
            if (data.is_holiday) {
                if (!dashBanner) {
                    var banner = document.createElement('div');
                    banner.id = 'dashHolidayBanner';
                    banner.style.cssText = 'text-align:center;background:#fef9e7;border:1px solid #f59e0b;border-radius:12px;padding:12px 20px;margin-bottom:16px';
                    banner.innerHTML = '<span class="status-badge status-holiday" style="font-size:14px;padding:6px 18px">Holiday: ' + escapeHtml(data.holiday_name || 'Public Holiday') + '</span><div style="color:#92400e;font-size:13px;margin-top:6px">Showing attendance for employees who worked on this holiday</div>';
                    var statsRow = document.getElementById('dashStats');
                    if (statsRow) statsRow.parentNode.insertBefore(banner, statsRow);
                }
            } else {
                if (dashBanner) dashBanner.remove();
            }
            renderDashboardStats(data.stats);
            renderDonutChart(data.stats);
            renderWeekTrend(data.week_trend);
            renderCompanyChart(data.companies);
            renderDeptChart(data.departments);
            loadGenderChart();
        })
        .catch(function(err) {
            console.error('Dashboard fetch failed:', err);
            var cards = document.querySelectorAll('.dash-stat-value');
            cards.forEach(function(c) { c.textContent = '-'; });
        });
}

function loadDashboardData() {
    if (!dashFullData) { loadDashboard(); return; }
    var filtered = filterDashboardData(dashFullData);
    renderDashboardStats(filtered.stats);
    renderDonutChart(filtered.stats);
    renderWeekTrend(filtered.week_trend);
    renderCompanyChart(filtered.companies);
    renderDeptChart(filtered.departments);
}

function filterDashboardData(data) {
    if (!dashActiveCompany) return data;
    var fData = JSON.parse(JSON.stringify(data));
    fData.data = fData.data.filter(function(r) { return (r.company_name || '') === dashActiveCompany; });
    var present = 0, late = 0, onTime = 0, absent = 0;
    fData.data.forEach(function(r) {
        if (r.is_holiday) {
            if (r.worked_on_holiday) { present++; if (r.is_late) late++; else onTime++; }
        } else {
            if (!r.is_absent) { present++; if (r.is_late) late++; else onTime++; }
            else absent++;
        }
    });
    fData.stats = { total: fData.data.length, present: present, late: late, on_time: onTime, absent: absent };
    fData.week_trend = fData.week_trend.map(function(d) {
        var compUsers = data.data.filter(function(u) { return (u.company_name || '') === dashActiveCompany; });
        return { date: d.date, day: d.day, present: Math.min(d.present, compUsers.length), absent: Math.max(0, compUsers.length - d.present), total: compUsers.length };
    });
    return fData;
}

function buildCompanyTabs(names) {
    var tabs = document.getElementById('companyTabs');
    if (!tabs) return;
    var html = '<button class="company-tab' + (!dashActiveCompany ? ' active' : '') + '" data-company="" onclick="switchCompanyTab(this)"><span class="tab-dot"></span>All Companies</button>';
    names.forEach(function(name) {
        html += '<button class="company-tab' + (dashActiveCompany === name ? ' active' : '') + '" data-company="' + escapeHtml(name) + '" onclick="switchCompanyTab(this)"><span class="tab-dot"></span>' + escapeHtml(name) + '</button>';
    });
    tabs.innerHTML = html;
}

function animateValue(el, start, end, duration) {
    var elId = el.id || Math.random().toString(36);
    if (dashAnimFrames[elId]) cancelAnimationFrame(dashAnimFrames[elId]);
    var startTime = null;
    var diff = end - start;
    function step(ts) {
        if (!startTime) startTime = ts;
        var progress = Math.min((ts - startTime) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(start + diff * eased);
        if (progress < 1) dashAnimFrames[elId] = requestAnimationFrame(step);
    }
    dashAnimFrames[elId] = requestAnimationFrame(step);
}

function renderDashboardStats(stats) {
    var prev = { total: parseInt(document.getElementById('dashTotal').textContent) || 0, present: parseInt(document.getElementById('dashPresent').textContent) || 0, on_time: parseInt(document.getElementById('dashOnTime').textContent) || 0, late: parseInt(document.getElementById('dashLate').textContent) || 0, absent: parseInt(document.getElementById('dashAbsent').textContent) || 0 };

    animateValue(document.getElementById('dashTotal'), prev.total, stats.total, 600);
    animateValue(document.getElementById('dashPresent'), prev.present, stats.present, 600);
    animateValue(document.getElementById('dashOnTime'), prev.on_time, stats.on_time, 600);
    animateValue(document.getElementById('dashLate'), prev.late, stats.late, 600);
    animateValue(document.getElementById('dashAbsent'), prev.absent, stats.absent, 600);

    var rate = stats.total > 0 ? Math.round((stats.present / stats.total) * 100) : 0;
    var badge = document.getElementById('dashAttendanceRate');
    if (badge) badge.textContent = rate + '% present';

    var center = document.getElementById('donutCenterVal');
    if (center) center.textContent = rate + '%';

    var dlOnTime = document.getElementById('donutOnTime');
    var dlLate = document.getElementById('donutLate');
    var dlAbsent = document.getElementById('donutAbsent');
    if (dlOnTime) dlOnTime.textContent = stats.on_time;
    if (dlLate) dlLate.textContent = stats.late;
    if (dlAbsent) dlAbsent.textContent = stats.absent;

    var trends = [
        { id: 'dashPresentTrend', val: stats.present, total: stats.total, good: true },
        { id: 'dashOnTimeTrend', val: stats.on_time, total: stats.present || 1, good: true },
        { id: 'dashLateTrend', val: stats.late, total: stats.present || 1, good: false },
        { id: 'dashAbsentTrend', val: stats.absent, total: stats.total || 1, good: false },
    ];
    trends.forEach(function(t) {
        var el = document.getElementById(t.id);
        if (el) {
            var pct = t.total > 0 ? Math.round((t.val / t.total) * 100) : 0;
            el.textContent = pct + '% of total';
            el.className = 'dash-stat-trend ' + (t.good ? 'positive' : 'negative');
        }
    });
}

function renderDonutChart(stats) {
    var ctx = document.getElementById('attendanceDonut');
    if (!ctx) return;
    if (dashDonutChart) dashDonutChart.destroy();
    var total = stats.on_time + stats.late + stats.absent;
    var hasData = total > 0;
    var onTime = hasData ? stats.on_time : 1;
    var late = hasData ? stats.late : 0;
    var absent = hasData ? stats.absent : 0;
    dashDonutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['On Time', 'Late', 'Absent'],
            datasets: [{
                data: hasData ? [onTime, late, absent] : [1],
                backgroundColor: hasData ? [
                    '#10b981',
                    '#f97316',
                    '#cbd5e1'
                ] : ['#e2e8f0'],
                borderWidth: 0,
                hoverOffset: 8,
                borderRadius: 3,
                spacing: hasData ? 2 : 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            cutout: '70%',
            radius: '92%',
            animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
            layout: { padding: 6 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.92)',
                    titleFont: { family: 'Inter', weight: '700', size: 13 },
                    bodyFont: { family: 'Inter', weight: '500', size: 12 },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true,
                    boxPadding: 4,
                    callbacks: {
                        label: function(ctx) {
                            if (!hasData) return ' No data for this date';
                            var pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

function renderWeekTrend(trend) {
    var ctx = document.getElementById('weekTrendChart');
    if (!ctx) return;
    if (dashTrendChart) dashTrendChart.destroy();

    var gradientPresent = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
    gradientPresent.addColorStop(0, 'rgba(16,185,129,0.2)');
    gradientPresent.addColorStop(1, 'rgba(16,185,129,0.01)');

    var gradientAbsent = ctx.getContext('2d').createLinearGradient(0, 0, 0, 280);
    gradientAbsent.addColorStop(0, 'rgba(239,68,68,0.12)');
    gradientAbsent.addColorStop(1, 'rgba(239,68,68,0.01)');

    dashTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trend.map(function(d) { return d.day + ' ' + d.date.slice(5); }),
            datasets: [
                {
                    label: 'Present', data: trend.map(function(d) { return d.present; }),
                    borderColor: '#10b981', backgroundColor: gradientPresent,
                    fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 7,
                    pointBackgroundColor: '#10b981', pointBorderColor: '#fff', pointBorderWidth: 2,
                    borderWidth: 2.5
                },
                {
                    label: 'Absent', data: trend.map(function(d) { return d.absent; }),
                    borderColor: '#ef4444', backgroundColor: gradientAbsent,
                    fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 7,
                    pointBackgroundColor: '#ef4444', pointBorderColor: '#fff', pointBorderWidth: 2,
                    borderWidth: 2.5, borderDash: [5, 3]
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeOutQuart' },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#94a3b8', padding: 8 }, border: { display: false } },
                x: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#94a3b8', padding: 8 }, border: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 8, font: { size: 12, family: 'Inter', weight: '600' }, color: '#475569' } },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.9)', titleFont: { family: 'Inter', weight: '700', size: 13 },
                    bodyFont: { family: 'Inter', weight: '500', size: 12 }, padding: 12, cornerRadius: 8, boxPadding: 4
                }
            },
            interaction: { intersect: false, mode: 'index' }
        }
    });
}

function renderCompanyChart(companies) {
    var ctx = document.getElementById('companyBarChart');
    if (!ctx) return;
    if (dashCompanyChart) dashCompanyChart.destroy();
    dashCompanyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: companies.map(function(c) { return c.name; }),
            datasets: [
                { label: 'On Time', data: companies.map(function(c) { return c.on_time; }), backgroundColor: '#10b981', borderRadius: { topLeft: 6, topRight: 6 }, borderSkipped: false },
                { label: 'Late', data: companies.map(function(c) { return c.late; }), backgroundColor: '#f59e0b', borderRadius: 0, borderSkipped: false },
                { label: 'Absent', data: companies.map(function(c) { return c.absent; }), backgroundColor: '#e2e8f0', borderRadius: { bottomLeft: 6, bottomRight: 6 }, borderSkipped: false }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeOutQuart' },
            scales: {
                y: { beginAtZero: true, stacked: true, grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#94a3b8', padding: 8 }, border: { display: false } },
                x: { stacked: true, grid: { display: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#64748b', maxRotation: 45, minRotation: 0 }, border: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 8, font: { size: 12, family: 'Inter', weight: '600' }, color: '#475569' } },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.9)', titleFont: { family: 'Inter', weight: '700', size: 13 },
                    bodyFont: { family: 'Inter', weight: '500', size: 12 }, padding: 12, cornerRadius: 8, boxPadding: 4
                }
            }
        }
    });
}

function renderDeptChart(depts) {
    var ctx = document.getElementById('deptBarChart');
    if (!ctx) return;
    if (dashDeptChart) dashDeptChart.destroy();
    var sorted = depts.slice().sort(function(a, b) { return b.total - a.total; }).slice(0, 12);
    dashDeptChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: sorted.map(function(d) { return d.name; }),
            datasets: [
                { label: 'Present', data: sorted.map(function(d) { return d.present; }), backgroundColor: '#6366f1', borderRadius: { topRight: 6, bottomRight: 6 }, borderSkipped: false },
                { label: 'Late', data: sorted.map(function(d) { return d.late; }), backgroundColor: '#f59e0b', borderRadius: 0, borderSkipped: false }
            ]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            animation: { duration: 800, easing: 'easeOutQuart' },
            scales: {
                x: { beginAtZero: true, stacked: true, grid: { color: 'rgba(0,0,0,0.04)', drawBorder: false }, ticks: { font: { size: 11, family: 'Inter' }, color: '#94a3b8', padding: 8 }, border: { display: false } },
                y: { stacked: true, grid: { display: false }, ticks: { font: { size: 11, family: 'Inter', weight: '500' }, color: '#475569' }, border: { display: false } }
            },
            plugins: {
                legend: { position: 'top', align: 'end', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 8, font: { size: 12, family: 'Inter', weight: '600' }, color: '#475569' } },
                tooltip: {
                    backgroundColor: 'rgba(15,23,42,0.9)', titleFont: { family: 'Inter', weight: '700', size: 13 },
                    bodyFont: { family: 'Inter', weight: '500', size: 12 }, padding: 12, cornerRadius: 8, boxPadding: 4
                }
            }
        }
    });
}

// ===================== GENDER CHART =====================

let dashGenderChart = null;

function initGenderChart() {
    const canvas = document.getElementById('genderChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    dashGenderChart = {
        canvas: canvas,
        ctx: ctx,
        data: { male: 0, female: 0 }
    };
    
    drawGenderDonut();
}

function drawGenderDonut() {
    if (!dashGenderChart) return;
    
    const { ctx, canvas, data } = dashGenderChart;
    const total = data.male + data.female;
    
    // Clear canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    if (total === 0) {
        ctx.font = '14px Inter, sans-serif';
        ctx.fillStyle = '#94a3b8';
        ctx.textAlign = 'center';
        ctx.fillText('No data', canvas.width / 2, canvas.height / 2);
        return;
    }
    
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const outerRadius = Math.min(canvas.width, canvas.height) / 2 - 10;
    const innerRadius = outerRadius * 0.6; // Donut hole
    
    // Calculate angles
    const maleAngle = (data.male / total) * 2 * Math.PI;
    const femaleAngle = 2 * Math.PI - maleAngle;
    
    // Draw male segment
    if (data.male > 0) {
        ctx.beginPath();
        ctx.arc(centerX, centerY, outerRadius, -Math.PI / 2, -Math.PI / 2 + maleAngle);
        ctx.arc(centerX, centerY, innerRadius, -Math.PI / 2 + maleAngle, -Math.PI / 2, true);
        ctx.closePath();
        ctx.fillStyle = '#3B82F6';
        ctx.fill();
    }
    
    // Draw female segment
    if (data.female > 0) {
        ctx.beginPath();
        ctx.arc(centerX, centerY, outerRadius, -Math.PI / 2 + maleAngle, -Math.PI / 2 + maleAngle + femaleAngle);
        ctx.arc(centerX, centerY, innerRadius, -Math.PI / 2 + maleAngle + femaleAngle, -Math.PI / 2 + maleAngle, true);
        ctx.closePath();
        ctx.fillStyle = '#EC4899';
        ctx.fill();
    }
    
    // Update center text
    const centerValueEl = document.getElementById('genderCenterVal');
    if (centerValueEl) {
        centerValueEl.textContent = total;
    }
    
    // Update badge
    const badgeEl = document.getElementById('dashGenderTotal');
    if (badgeEl) {
        badgeEl.textContent = `${total} Total`;
    }
}

function updateGenderChart(maleCount, femaleCount) {
    if (!dashGenderChart) {
        initGenderChart();
    }
    
    if (dashGenderChart) {
        dashGenderChart.data.male = parseInt(maleCount) || 0;
        dashGenderChart.data.female = parseInt(femaleCount) || 0;
        
        const maleEl = document.getElementById('maleCount');
        const femaleEl = document.getElementById('femaleCount');
        
        if (maleEl) maleEl.textContent = maleCount;
        if (femaleEl) femaleEl.textContent = femaleCount;
        
        drawGenderDonut();
    }
}

function loadGenderChart() {
    fetch('api/attendance.php?action=gender_stats')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                updateGenderChart(
                    parseInt(data.gender_stats.male_count) || 0, 
                    parseInt(data.gender_stats.female_count) || 0
                );
            }
        })
        .catch(function(e) {
            console.error('Failed to load gender stats:', e);
            // Show empty state
            updateGenderChart(0, 0);
        });
}

// Initialize gender chart when dashboard loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('genderChart')) {
        initGenderChart();
    }
});