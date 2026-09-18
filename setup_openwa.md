# OpenWA WhatsApp Gateway Setup Guide

## Current Status
- Port 8080 is occupied by another service (EnterpriseDB)
- OpenWA service is not running
- WhatsApp gateway is configured but not operational

## Setup Instructions

### 1. Install Node.js (if not already installed)
Download and install Node.js from: https://nodejs.org/

### 2. Install OpenWA
```bash
npm install -g @open-wa/wa-automate
```

### 3. Create OpenWA Configuration

Create a file named `openwa-config.js`:

```javascript
const wa = require('@open-wa/wa-automate');

wa.create({
  sessionId: 'default',
  multiDevice: true,
  authTimeout: 60,
  blockCrashLogs: true,
  disableSpins: true,
  headless: true,
  hostNotificationLang: 'PT_BR',
  logConsole: false,
  popup: true,
  qrTimeout: 0,
  restartOnCrash: true,
  sessionDataPath: './session-data',
  port: 8081, // Changed from 8080 since it's occupied
  cors: '*'
}).then(client => start(client));

function start(client) {
  console.log('OpenWA WhatsApp Gateway is ready!');
  console.log('API running on port 8081');
  
  // Keep the client alive
  client.onMessage(async message => {
    console.log('Received message:', message.body);
  });
}
```

### 4. Alternative: Use different port
Since port 8080 is occupied, we'll use port 8081 for OpenWA.

### 5. Update Configuration
Update the WhatsApp configuration to use the new port.