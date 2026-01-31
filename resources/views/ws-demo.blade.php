<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Notification WebSocket Demo</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f7f7fb;
                --ink: #16161d;
                --accent: #2563eb;
                --card: #ffffff;
                --border: #e5e7eb;
            }
            body {
                margin: 0;
                font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
                background: linear-gradient(135deg, #eef2ff, #f7f7fb);
                color: var(--ink);
            }
            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 24px;
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid var(--border);
                box-shadow: 0 16px 40px rgba(31, 31, 36, 0.08);
                position: sticky;
                top: 20px;
                z-index: 10;
                backdrop-filter: blur(8px);
            }
            .topbar .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 700;
                letter-spacing: 0.02em;
            }
            .brand-badge {
                width: 36px;
                height: 36px;
                border-radius: 12px;
                background: linear-gradient(135deg, var(--accent), #13b08a);
            }
            .topbar nav {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .topbar a {
                color: var(--ink);
                text-decoration: none;
                font-weight: 600;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(47, 111, 237, 0.08);
            }
            .container {
                max-width: 960px;
                margin: 40px auto;
                padding: 0 24px;
            }
            h1 {
                margin: 0 0 12px;
                font-size: 28px;
            }
            .card {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            }
            .log {
                font-family: "IBM Plex Mono", "Courier New", monospace;
                font-size: 13px;
                background: #0f172a;
                color: #e2e8f0;
                border-radius: 12px;
                padding: 12px;
                height: 300px;
                overflow-y: auto;
            }
            .pill {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 999px;
                background: #dbeafe;
                color: #1e40af;
                font-size: 12px;
                margin-right: 8px;
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <div class="brand">
                <span class="brand-badge"></span>
                Notification Console
            </div>
            <nav>
                <a href="/admin">Dashboard</a>
                <a href="/admin/users">Users</a>
                <a href="/admin/audit">Audit Logs</a>
                <a href="/dead-letter">Dead Letter</a>
                <a href="/swagger">API Docs</a>
            </nav>
        </div>
        <div class="container">
            <h1>Notification WebSocket Demo</h1>
            <div class="card">
                <div>
                    <span class="pill">Channel: notifications</span>
                    <span class="pill">Event: notification.status.updated</span>
                </div>
                <p>Open this page, then send a notification. Status updates will stream below.</p>
                <div style="margin-bottom: 16px;">
                    <button id="send" style="background:#2563eb;color:#fff;border:none;padding:10px 16px;border-radius:10px;cursor:pointer;">
                        Send Test Notification
                    </button>
                </div>
                <div class="log" id="log"></div>
            </div>
        </div>

        <script src="https://unpkg.com/pusher-js@8.2.0/dist/web/pusher.min.js"
                integrity="sha256-+ds/9n0yh0+RQciTuOSBWSGYYwdN8LrNVR93R76EkWM="
                crossorigin="anonymous"></script>
        <script src="https://unpkg.com/laravel-echo@1.16.0/dist/echo.iife.js"
                integrity="sha256-/NeRh3MNrcNNNYC5LJFUkSUCmmcnfCdq5SCVmpDbl0A="
                crossorigin="anonymous"></script>
        <script>
            const log = document.getElementById('log');
            const echo = new Echo({
                broadcaster: 'reverb',
                key: '{{ env('REVERB_APP_KEY', 'local') }}',
                wsHost: window.location.hostname,
                wsPort: 8080,
                wssPort: 8080,
                forceTLS: false,
                enabledTransports: ['ws', 'wss'],
            });

            echo.channel('notifications')
                .listen('.notification.status.updated', (event) => {
                    const line = `[${new Date().toISOString()}] ${event.notificationId} -> ${event.status}`;
                    const div = document.createElement('div');
                    div.textContent = line;
                    log.appendChild(div);
                    log.scrollTop = log.scrollHeight;
                });

            document.getElementById('send').addEventListener('click', async () => {
                const payload = {
                    notifications: [
                        {
                            recipient: '+905551234567',
                            channel: 'sms',
                            content: 'WebSocket demo ' + new Date().toISOString(),
                            priority: 'normal'
                        }
                    ]
                };

                const res = await fetch('/api/v1/notifications', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const text = await res.text();
                const line = `[${new Date().toISOString()}] API ${res.status}: ${text.slice(0, 200)}`;
                const div = document.createElement('div');
                div.textContent = line;
                log.appendChild(div);
                log.scrollTop = log.scrollHeight;
            });
        </script>
    </body>
    </html>
