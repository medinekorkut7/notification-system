<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Audit Logs</title>
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
            .container {
                max-width: 1200px;
                margin: 40px auto;
                padding: 0 24px 48px;
            }
            header {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 16px;
                flex-wrap: wrap;
            }
            h1 {
                font-size: 32px;
                letter-spacing: -0.02em;
                margin: 0;
            }
            .meta {
                font-size: 14px;
                color: var(--muted);
            }
            .badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 12px;
                background: #fbe3e6;
                color: #8f2232;
                padding: 4px 8px;
                border-radius: 999px;
            }
            .filters {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 20px;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 16px;
            }
            .filters label {
                font-size: 12px;
                color: var(--muted);
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .filters input {
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 8px 10px;
                font-size: 14px;
                background: #fff;
            }
            .btn {
                border: 0;
                border-radius: 999px;
                padding: 8px 14px;
                font-size: 13px;
                cursor: pointer;
                background: var(--accent);
                color: #fff;
                box-shadow: 0 6px 16px rgba(209, 73, 91, 0.25);
                align-self: flex-end;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 24px;
                background: #fff;
                border-radius: 16px;
                overflow: hidden;
                border: 1px solid var(--border);
            }
            th, td {
                text-align: left;
                padding: 12px 14px;
                font-size: 13px;
                border-bottom: 1px solid var(--border);
                vertical-align: top;
            }
            th {
                background: #fff1d6;
                color: #5e4a3e;
                font-weight: 600;
            }
            .payload {
                font-family: "IBM Plex Mono", "Courier New", monospace;
                font-size: 12px;
                white-space: pre-wrap;
                max-width: 320px;
            }
            .empty {
                background: #fff;
                border-radius: 18px;
                border: 1px solid var(--border);
                padding: 32px;
                text-align: center;
                color: var(--muted);
                margin-top: 24px;
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
                <form method="POST" action="/admin/logout">
                    @csrf
                    <button class="btn" type="submit">Log out</button>
                </form>
            </div>
            <header>
                <div>
                    <h1>Admin Audit Logs</h1>
                    <div class="meta">Security events and admin actions.</div>
                </div>
                <div class="badge">Total: {{ $logs->total() }}</div>
            </header>

            <form class="filters" method="GET" action="/admin/audit">
                <label>
                    Action
                    <input name="action" value="{{ $filters['action'] }}" placeholder="admin.login">
                </label>
                <label>
                    Admin User ID
                    <input name="admin_user_id" value="{{ $filters['admin_user_id'] }}" placeholder="uuid">
                </label>
                <label>
                    From
                    <input name="from" value="{{ $filters['from'] }}" placeholder="2026-01-31T00:00:00Z">
                </label>
                <label>
                    To
                    <input name="to" value="{{ $filters['to'] }}" placeholder="2026-01-31T23:59:59Z">
                </label>
                <label>
                    Per Page
                    <input name="per_page" value="{{ $filters['per_page'] }}" placeholder="25">
                </label>
                <button class="btn" type="submit">Filter</button>
            </form>

            @if ($logs->count() === 0)
                <div class="empty">No audit logs found.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>Admin User</th>
                            <th>IP</th>
                            <th>User Agent</th>
                            <th>Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at }}</td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->admin_user_id ?? 'n/a' }}</td>
                                <td>{{ $log->ip_address ?? 'n/a' }}</td>
                                <td>{{ $log->user_agent ?? 'n/a' }}</td>
                                <td class="payload">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </body>
    </html>
