import WebSocket, { WebSocketServer } from 'ws';
import http from 'http';

// إنشاء خادم HTTP لاستقبال الطلبات من Laravel
const server = http.createServer((req, res) => {
    // استقبال التنبيهات من Laravel على مسار /broadcast
    if (req.method === 'POST' && req.url === '/broadcast') {
        let body = '';
        req.on('data', chunk => body += chunk.toString());
        req.on('end', () => {
            console.log(`[HTTP] Received broadcast request: ${body}`);
            // إرسال البيانات فوراً لجميع الأجهزة المتصلة
            wss.clients.forEach(client => {
                if (client.readyState === WebSocket.OPEN) {
                    client.send(body);
                }
            });
            res.writeHead(200);
            res.end('OK');
        });
    } else {
        res.writeHead(404);
        res.end();
    }
});

// إنشاء خادم WebSocket فوق خادم HTTP
const wss = new WebSocketServer({ noServer: true });

server.on('upgrade', (request, socket, head) => {
    wss.handleUpgrade(request, socket, head, ws => {
        wss.emit('connection', ws, request);
    });
});

wss.on('connection', ws => {
    console.log('[WS] Client connected!');
    ws.on('close', () => {
        console.log('[WS] Client disconnected.');
    });
});

const PORT = 6001;
server.listen(PORT, () => {
    console.log(`✅ Custom Local WebSockets Server running on port ${PORT}`);
    console.log(`🚀 Ready to sync between Laravel & Android App`);
});
