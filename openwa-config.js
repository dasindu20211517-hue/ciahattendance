const wa = require('@open-wa/wa-automate');

// OpenWA Configuration for CIAH Attendance System - Updated for reliability
wa.create({
  sessionId: 'default',
  multiDevice: true,
  useChrome: true, // Use Chrome for better compatibility
  authTimeout: 60,
  blockCrashLogs: true,
  disableSpins: true,
  headless: false, // Show browser window for QR code
  logConsole: true,
  popup: false,
  qrTimeout: 0,
  restartOnCrash: true,
  sessionDataPath: './session-data',
  port: 8081,
  cors: '*',
  executablePath: null, // Auto-detect Chrome
  chromiumSandbox: false
}).then(client => start(client)).catch(error => {
  console.error('❌ Failed to create OpenWA client:', error.message);
  console.log('\n🔧 Troubleshooting tips:');
  console.log('1. Make sure Google Chrome is installed');
  console.log('2. Close any existing Chrome/WhatsApp Web instances');
  console.log('3. Restart this script');
  console.log('4. If still failing, try running as administrator');
});

async function start(client) {
  console.log('🚀 OpenWA WhatsApp Gateway is ready!');
  console.log('📱 API running on http://localhost:8081');
  console.log('🔗 Session ID: default');
  console.log('');
  console.log('📋 Available endpoints:');
  console.log('- POST /default/sendText - Send text message');
  console.log('- GET /default/getMe - Get bot info');
  console.log('- GET /default/getChats - Get chat list');
  console.log('');
  console.log('📱 Next steps:');
  console.log('1. Scan QR code with WhatsApp on your phone');
  console.log('2. Go to WhatsApp > Settings > Linked Devices');
  console.log('3. Tap "Link a Device" and scan the QR code');
  console.log('4. Wait for connection confirmation');
  console.log('5. Test with: php test_whatsapp.php');
  console.log('');
  
  // Handle incoming messages
  client.onMessage(async message => {
    console.log('📨 Received message from', message.from, ':', message.body);
  });
  
  // Log connection status
  client.onStateChanged(state => {
    console.log('🔄 Connection state changed:', state);
  });
}