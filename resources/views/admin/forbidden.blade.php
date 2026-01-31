<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Access Denied</title>
        <style>
            :root {
                color-scheme: light;
                --bg: #f4f1ea;
                --ink: #1b1b1b;
                --muted: #6e6e6e;
                --accent: #d1495b;
                --card: #fff7ed;
                --border: #e6ddcf;
            }
            body {
                margin: 0;
                font-family: "IBM Plex Serif", "Georgia", serif;
                background: radial-gradient(circle at top right, #fff1d6, var(--bg));
                color: var(--ink);
            }
            .container {
                max-width: 900px;
                margin: 40px auto;
                padding: 0 24px;
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
                text-decoration: none;
                color: var(--ink);
                font-weight: 600;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(47, 111, 237, 0.08);
            }
            .card {
                margin-top: 32px;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 18px;
                padding: 28px;
            }
            h1 {
                margin: 0 0 8px;
            }
            p {
                color: var(--muted);
            }
        </style>
    </head>
    <body>
        <div class="container">
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
                    <a href="/swagger">API Docs</a>
                </nav>
            </div>
            <div class="card">
                <h1>Access denied</h1>
                <p>Your account does not have permission to perform this action.</p>
            </div>
        </div>
    </body>
    </html>
