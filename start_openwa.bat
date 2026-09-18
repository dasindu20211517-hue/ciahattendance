@echo off
echo Starting OpenWA WhatsApp Gateway...
echo.
echo IMPORTANT: 
echo 1. Make sure you have Node.js installed
echo 2. Install OpenWA: npm install -g @open-wa/wa-automate
echo 3. The first time you run this, scan the QR code with WhatsApp
echo.
pause
echo.
echo Starting OpenWA on port 8081...
node openwa-config.js