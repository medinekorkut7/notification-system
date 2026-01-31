<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Users</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f1ec;
            --ink: #1f1f24;
            --muted: #6b6b6f;
            --accent: #2f6fed;
            --accent-2: #f39c46;
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
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
        h1 { margin: 0; font-family: "Fraunces", "Times New Roman", serif; font-size: 2.4rem; }
        .btn {
            border: none;
            border-radius: 999px;
            padding: 10px 16px;
            cursor: pointer;
            font-weight: 600;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
        }
        .btn.secondary { background: var(--accent-2); }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow);
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid rgba(31,31,36,0.08); }
        th { font-size: 0.85rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
        .badge {
            display: inline-flex;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.75rem;
            background: rgba(47, 111, 237, 0.12);
            color: var(--accent);
        }
        .badge.off { background: rgba(255, 77, 77, 0.12); color: #b03737; }
        .actions { display: flex; gap: 8px; }
        .link { background: transparent; color: var(--accent); border: none; cursor: pointer; font-weight: 600; padding: 0; }
        .muted { color: var(--muted); }
        .status { margin-top: 8px; color: #0f5f4b; background: rgba(19,176,138,0.12); padding: 8px 12px; border-radius: 10px; width: fit-content; }
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
            <button class="btn secondary" type="submit">Log out</button>
        </form>
    </div>
    <header>
        <div>
            <div class="muted">Admin access</div>
            <h1>Admin Users</h1>
        </div>
        <div class="actions">
            <a class="btn secondary" href="/admin">Back to Dashboard</a>
            @if ($isAdmin)
                <a class="btn" href="/admin/users/create">Add User</a>
            @else
                <span class="muted">Viewer mode: user management disabled.</span>
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="badge">{{ $user->role }}</span></td>
                    <td>
                        <span class="badge {{ $user->is_active ? '' : 'off' }}">
                            {{ $user->is_active ? 'Active' : 'Disabled' }}
                        </span>
                    </td>
                    <td>{{ $user->last_login_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        <div class="actions">
                            <a class="link" href="/admin/users/{{ $user->id }}/edit">Edit</a>
                            <form method="POST" action="/admin/users/{{ $user->id }}" onsubmit="return confirm('Remove this user?');">
                                @csrf
                                @method('DELETE')
                                <button class="link" type="submit">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">No admin users found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="muted">Showing {{ $users->count() }} of {{ $users->total() }} users.</div>
</div>
</body>
</html>
