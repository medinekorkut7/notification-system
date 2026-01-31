<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Notification API Docs</title>
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
        <style>
            body { margin: 0; background: #f8fafc; }
            #swagger-ui { max-width: 1100px; margin: 0 auto; }
            .topbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 24px;
                border-radius: 18px;
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid #e5e7eb;
                box-shadow: 0 16px 40px rgba(31, 31, 36, 0.08);
                position: sticky;
                top: 20px;
                z-index: 10;
                backdrop-filter: blur(8px);
                font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            }
            .topbar .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 700;
                letter-spacing: 0.02em;
                color: #111827;
            }
            .brand-badge {
                width: 36px;
                height: 36px;
                border-radius: 12px;
                background: linear-gradient(135deg, #d1495b, #13b08a);
            }
            .topbar nav {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .topbar a {
                text-decoration: none;
                color: #374151;
                font-weight: 600;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(47, 111, 237, 0.08);
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
                <a href="/dead-letter">Dead Letter</a>
                <a href="/admin/audit">Audit Logs</a>
                <a href="/ws-demo">WebSocket Demo</a>
            </nav>
        </div>
        <div id="swagger-ui"></div>
        <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
        <script>
            window.onload = () => {
                SwaggerUIBundle({
                    url: '/docs/openapi.yaml',
                    dom_id: '#swagger-ui',
                    deepLinking: true
                });
            };
        </script>
    </body>
</html>
