<?php $pageTitle = 'Import DAT'; $activePage = 'import'; ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
        <div class="controls">
            <form id="datImportForm" enctype="multipart/form-data">
                <label for="datFile">Attendance or employee Excel file:</label>
                <input type="file" id="datFile" name="dat_file" accept=".dat,.txt,.csv,.xlsx" required>
                <button class="btn btn-secondary" type="submit">Import Records</button>
            </form>
        </div>
        <div class="sync-status" id="datImportStatus"></div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Supported columns</th><th>Mapping</th></tr></thead>
                <tbody>
                    <tr><td>ENROLL NO + EMPLOYEE ID + EMPLOYEE NAME / CALLING NAME / DEPARTMENT NAME / M/F</td><td>Captures the Employee ID column; uses Enroll Number for K40 attendance matching when both are provided. M/F column for gender (M/Male/1 for Male, F/Female/2 for Female)</td></tr>
                    <tr><td>Date and Time</td><td>Saved as the attendance check time</td></tr>
                    <tr><td>State / Status / Verify</td><td>Saved as the attendance state</td></tr>
                </tbody>
            </table>
        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('datImportForm');
    if (form) form.addEventListener('submit', importDatFile);
});
</script>
