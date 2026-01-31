<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin User</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f1ec;
            --ink: #1f1f24;
            --muted: #6b6b6f;
            --accent: #2f6fed;
            --card: #ffffff;
            --card-border: rgba(31, 31, 36, 0.1);
            --shadow: 0 24px 60px rgba(31, 31, 36, 0.12);
            --radius: 20px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Space Grotesk", "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background: radial-gradient(circle at top, #fdf8f1 0%, #f4f1ec 40%, #eef1f6 100%);
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 24px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--card-border);
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
        .page { padding: 48px 6vw 80px; display: grid; gap: 24px; }
        h1 { margin: 0; font-family: "Fraunces", "Times New Roman", serif; font-size: 2.2rem; }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow);
            max-width: 560px;
        }
        label { font-size: 0.85rem; color: var(--muted); }
        input, select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(31,31,36,0.16);
            margin-top: 6px;
            margin-bottom: 16px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        .btn {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 600;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
        }
        .row { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .error { color: #b03737; background: rgba(255,77,77,0.12); padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="page">
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
        <form method="POST" action="/admin/logout">
            @csrf
            <button class="btn" type="submit">Log out</button>
        </form>
    </div>
    <div>
        <div class="muted">Admin access</div>
        <h1>Edit Admin User</h1>
    </div>

    <div class="card">
        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/users/{{ $user->id }}">
            @csrf
            @method('PUT')
            <label for="name">Name</label>
            <input id="name" name="name" required value="{{ old('name', $user->name) }}">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="{{ old('email', $user->email) }}">

            <label for="password">Password (leave blank to keep)</label>
            <input id="password" name="password" type="password">

            <div class="row">
                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="viewer" {{ old('role', $user->role) === 'viewer' ? 'selected' : '' }}>Viewer</option>
                    </select>
                </div>
                <div>
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active">
                        <option value="1" {{ old('is_active', $user->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('is_active', $user->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Disabled</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <a class="btn" href="/admin/users" style="background:#fff;color:var(--ink);border:1px solid rgba(31,31,36,0.2);text-align:center;">Cancel</a>
                <button class="btn" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
