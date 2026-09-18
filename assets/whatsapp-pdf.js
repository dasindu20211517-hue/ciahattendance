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
                var cleanNumber = String(whatsappNumber).replace(/[^0-9+]/g, '');
                
                // Download PDF first
                var link = document.createElement('a');
                link.href = data.download_url;
                link.download = data.filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                setTimeout(function() { link.remove(); }, 1000);
                
                // Then open WhatsApp to share from +94753788180 
                setTimeout(function() {
                    var message = encodeURIComponent('[HRIS ROWELMARK GROUP] Hi! Please find your attendance report for ' + date + ' attached. Download from: ' + window.location.origin + '/' + data.download_url);
                    var whatsappUrl = 'https://wa.me/' + cleanNumber + '?text=' + message;
                    window.open(whatsappUrl, '_blank');
                    showToast('PDF downloaded! WhatsApp opened for sharing via +94753788180', 'success');
                }, 1500);
            } else {
                showToast(data.message || 'Failed to generate PDF', 'error');
            }
        })
        .catch(function(e) {
            showToast('Error generating PDF: ' + e.message, 'error');
        });
}
