<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Form</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 35%, #f8fafc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.12);
            max-width: 540px;
            width: 100%;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .card-header {
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #3b82f6 100%);
            padding: 28px 32px 24px;
            color: white;
        }

        .card-header .badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.22);
            color: white;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 6px 12px;
            border-radius: 999px;
            margin-bottom: 12px;
        }

        .card-header h1 {
            font-size: clamp(24px, 4vw, 32px);
            line-height: 1.2;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .card-header p {
            font-size: 13px;
            opacity: 0.85;
        }

        .card-body {
            padding: 28px 32px 32px;
        }

        .alert {
            display: none;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .alert.show { display: block; }
        .alert.success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #166534; }
        .alert.error   { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

        .hint-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 20px;
            padding: 10px 12px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 7px;
        }

        .form-group label .req { color: #ef4444; margin-left: 2px; }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #dfe7f1;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: #111827;
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
            appearance: auto;
        }

        .form-group input:hover,
        .form-group select:hover,
        .form-group textarea:hover {
            border-color: #cbd5e1;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.12);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 110px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 12px 14px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0f172a, #2563eb);
            color: white;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.26);
        }
        .btn-primary:hover { opacity: 0.96; transform: translateY(-1px); }
        .btn-primary:active { transform: none; }
        .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .btn-ghost {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .btn-ghost:hover { background: #e2e8f0; }

        .success-state {
            display: none;
            text-align: center;
            padding: 20px 0 8px;
        }

        .success-state .icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 30px;
            color: #15803d;
            box-shadow: inset 0 0 0 1px rgba(22, 163, 74, 0.12);
        }

        .success-state h2 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .success-state p {
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.45);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 520px) {
            body { padding: 18px; }
            .card-header, .card-body { padding-left: 20px; padding-right: 20px; }
            .form-row { grid-template-columns: 1fr; }
            .hint-row { flex-direction: column; }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="badge" id="formTypeBadge">Request</div>
        <h1 id="formTitle">Loading...</h1>
        <p id="formSubtitle">Please wait</p>
    </div>
    <div class="card-body">
        <div id="alertBox" class="alert"></div>

        <div class="hint-row" id="formHintRow">
            <span id="hintPrimary">Complete the details below</span>
            <span id="hintSecondary">Secure request form</span>
        </div>

        <div id="formArea">
            <form id="requestForm" autocomplete="off"></form>
        </div>

        <div class="success-state" id="successState">
            <div class="icon">&#10003;</div>
            <h2>Request Submitted</h2>
            <p>Your request has been received and will be reviewed shortly.</p>
        </div>
    </div>
</div>

<script>
const token = new URLSearchParams(window.location.search).get('token');
let formType = null;

async function init() {
    try {
        const res  = await fetch('api/form_links.php?action=get_url&token=' + encodeURIComponent(token || ''));
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Invalid or expired form link');
        formType = data.form_type;
        buildForm(formType);
    } catch(e) {
        showAlert(e.message, 'error');
        document.getElementById('requestForm').style.display = 'none';
    }
}

function buildForm(type) {
    const isLeave = type === 'leave';
    document.getElementById('formTypeBadge').textContent = isLeave ? 'Leave Request' : 'Overtime Request';
    document.getElementById('formTitle').textContent     = isLeave ? 'Leave Request' : 'Overtime Request';
    document.getElementById('formSubtitle').textContent  = isLeave ? 'Fill in your leave details below' : 'Fill in your overtime details below';
    document.getElementById('hintPrimary').textContent = isLeave ? 'Leave dates and reasons' : 'Overtime date and hours';
    document.getElementById('hintSecondary').textContent = isLeave ? 'Submit once, review later' : 'Track OT hours accurately';

    const form = document.getElementById('requestForm');

    if (isLeave) {
        form.innerHTML = `
            <div class="form-group">
                <label>Leave Type <span class="req">*</span></label>
                <select name="leave_type" required>
                    <option value="">Select leave type</option>
                    <option>Annual Leave</option>
                    <option>Sick Leave</option>
                    <option>Casual Leave</option>
                    <option>Maternity Leave</option>
                    <option>Paternity Leave</option>
                    <option>Unpaid Leave</option>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date <span class="req">*</span></label>
                    <input type="date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label>End Date <span class="req">*</span></label>
                    <input type="date" name="end_date" required>
                </div>
            </div>
            <div class="form-group">
                <label>Reason <span class="req">*</span></label>
                <textarea name="reason" required placeholder="Briefly describe your reason..."></textarea>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary" id="submitBtn">Submit Request</button>
                <button type="reset" class="btn btn-ghost">Clear</button>
            </div>`;
    } else {
        form.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label>Date <span class="req">*</span></label>
                    <input type="date" name="ot_date" required>
                </div>
                <div class="form-group">
                    <label>Hours <span class="req">*</span></label>
                    <input type="number" name="hours" min="0.5" max="24" step="0.5" placeholder="e.g. 2" required>
                </div>
            </div>
            <div class="form-group">
                <label>Reason <span class="req">*</span></label>
                <textarea name="reason" required placeholder="Briefly describe your overtime work..."></textarea>
            </div>
            <div class="btn-row">
                <button type="submit" class="btn btn-primary" id="submitBtn">Submit Request</button>
                <button type="reset" class="btn btn-ghost">Clear</button>
            </div>`;
    }

    form.addEventListener('submit', submitForm);
    form.addEventListener('reset', function() {
        setTimeout(function() { showAlert('', ''); }, 10);
    });
}

async function submitForm(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const form = e.target;

    if (formType === 'leave') {
        const start = form.start_date.value;
        const end = form.end_date.value;
        if (start && end && new Date(end) < new Date(start)) {
            showAlert('End date cannot be before the start date.', 'error');
            return;
        }
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Submitting...';

    const fd = new FormData(form);
    const data = {};
    fd.forEach((v, k) => data[k] = v);

    try {
        const res  = await fetch('api/form_submit.php?token=' + encodeURIComponent(token), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.success) {
            document.getElementById('formArea').style.display = 'none';
            document.getElementById('formHintRow').style.display = 'none';
            document.getElementById('successState').style.display = 'block';
        } else {
            showAlert(result.message || 'Submission failed. Please try again.', 'error');
            btn.disabled = false;
            btn.textContent = 'Submit Request';
        }
    } catch(err) {
        showAlert('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Submit Request';
    }
}

function showAlert(msg, type) {
    const el = document.getElementById('alertBox');
    el.textContent = msg;
    el.className = 'alert show ' + type;
}

init();
</script>
</body>
</html>
