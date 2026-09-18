<?php $pageTitle = 'Employee Contacts'; $activePage = 'contacts'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="controls-container">
        <div class="controls-group filters-group">
            <div class="control-item">
                <label for="contactSearch">🔎 Search</label>
                <input type="search" id="contactSearch" placeholder="Search by name or ID">
            </div>
            <div class="control-item">
                <label for="contactDeptFilter">🏬 Department</label>
                <select id="contactDeptFilter"><option value="">All departments</option></select>
            </div>
        </div>
        <div class="controls-actions">
            <button class="btn btn-primary" onclick="saveAllContacts()">💾 Save All</button>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>WhatsApp Number</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="contactTableBody">
                <tr><td colspan="6" class="no-data">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <input type="hidden" id="contactPageUserId" value="<?php echo (int)$loggedInUser['id']; ?>">
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
var allContactRows = [];

function loadEmployeeContacts() {
    fetch('api/employee_contacts.php?action=list')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { showToast(data.message || 'Failed to load', 'error'); return; }
            allContactRows = data.data;
            renderContactTable(allContactRows);
            var depts = {};
            allContactRows.forEach(function(r) { if (r.department_name) depts[r.department_name] = true; });
            var sel = document.getElementById('contactDeptFilter');
            sel.innerHTML = '<option value="">All departments</option>';
            Object.keys(depts).sort().forEach(function(d) { sel.appendChild(new Option(d, d)); });
        })
        .catch(function() { showToast('Error loading employees', 'error'); });
}

function renderContactTable(rows) {
    var tbody = document.getElementById('contactTableBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="no-data">No employees found</td></tr>'; return; }
    tbody.innerHTML = rows.map(function(r, i) {
        var whatsappBtn = '';
        if (r.whatsapp_number && r.whatsapp_number.trim() !== '') {
            var cleanNumber = r.whatsapp_number.replace(/[^0-9+]/g, '');
            whatsappBtn = '<a href="https://wa.me/' + cleanNumber + '" target="_blank" class="btn btn-success" style="padding:6px 12px;font-size:13px;text-decoration:none;">📱 WhatsApp</a>';
        } else {
            whatsappBtn = '<span style="color:#999;font-size:12px;">No number</span>';
        }
        return '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + escapeHtml(r.badge_id || '-') + '</td>' +
            '<td><strong>' + escapeHtml(r.name) + '</strong></td>' +
            '<td>' + escapeHtml(r.department_name || '-') + '</td>' +
            '<td><input type="tel" class="contact-input" data-uid="' + r.id + '" value="' + escapeHtml(r.whatsapp_number || '') + '" placeholder="+94771234567" style="width:100%;padding:6px 10px;border:1.5px solid var(--border);border-radius:var(--radius);font:13px Inter,sans-serif;"></td>' +
            '<td>' + whatsappBtn + '</td>' +
            '</tr>';
    }).join('');
}

function filterContactTable() {
    var search = (document.getElementById('contactSearch').value || '').toLowerCase();
    var dept = document.getElementById('contactDeptFilter').value;
    var filtered = allContactRows.filter(function(r) {
        if (dept && r.department_name !== dept) return false;
        if (search) {
            var text = (r.name + ' ' + r.badge_id + ' ' + r.employee_id + ' ' + (r.whatsapp_number || '')).toLowerCase();
            if (text.indexOf(search) === -1) return false;
        }
        return true;
    });
    renderContactTable(filtered);
}

function saveAllContacts() {
    var inputs = document.querySelectorAll('.contact-input');
    var entries = [];
    inputs.forEach(function(inp) {
        entries.push({ user_id: parseInt(inp.dataset.uid), whatsapp_number: inp.value.trim() });
    });
    fetch('api/employee_contacts.php?action=save_bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entries: entries })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message || 'Saved', 'success');
            loadEmployeeContacts(); // Reload to refresh WhatsApp buttons
        }
        else showToast(data.message || 'Failed to save', 'error');
    })
    .catch(function() { showToast('Error saving', 'error'); });
}

document.addEventListener('DOMContentLoaded', function() {
    loadEmployeeContacts();
    document.getElementById('contactSearch').addEventListener('input', filterContactTable);
    document.getElementById('contactDeptFilter').addEventListener('change', filterContactTable);
});
</script>
