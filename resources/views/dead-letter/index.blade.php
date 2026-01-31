<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dead-Letter Notifications</title>
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
                max-width: 1100px;
                margin: 40px auto;
                padding: 0 24px 48px;
            }
            header {
                display: flex;
                flex-direction: column;
                gap: 12px;
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
            .header-row {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 16px;
                margin-top: 24px;
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
            .grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-top: 24px;
            }
            .card {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 18px;
                padding: 18px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            }
            .card h2 {
                font-size: 16px;
                margin: 0 0 8px;
                color: var(--accent);
            }
            .row {
                display: flex;
                justify-content: space-between;
                font-size: 14px;
                margin-bottom: 6px;
            }
            .row span:last-child {
                font-weight: 600;
            }
            .payload {
                font-family: "IBM Plex Mono", "Courier New", monospace;
                font-size: 12px;
                background: #fff;
                border: 1px dashed var(--border);
                padding: 8px;
                border-radius: 12px;
                overflow-x: auto;
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
            .toolbar {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 16px;
                align-items: center;
            }
            .toolbar label {
                font-size: 12px;
                color: var(--muted);
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .toolbar input,
            .toolbar select {
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
            }
            .btn.ghost {
                background: transparent;
                color: var(--accent);
                border: 1px solid var(--accent);
                box-shadow: none;
            }
            .actions {
                display: flex;
                gap: 8px;
                margin-top: 10px;
            }
            .status {
                margin-top: 12px;
                font-size: 12px;
                color: var(--muted);
            }
            .progress {
                width: 100%;
                height: 6px;
                border-radius: 999px;
                background: #f2e6d8;
                overflow: hidden;
                display: none;
                margin-top: 8px;
            }
            .progress span {
                display: block;
                height: 100%;
                width: 0%;
                background: var(--accent);
                transition: width 0.2s ease;
            }
            .progress-count {
                font-size: 12px;
                color: var(--muted);
                margin-top: 4px;
            }
            .empty {
                background: #fff;
                border-radius: 18px;
                border: 1px solid var(--border);
                padding: 32px;
                text-align: center;
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
                <form method="POST" action="/admin/logout">
                    @csrf
                    <button class="btn ghost" type="submit">Log out</button>
                </form>
            </div>

            <div class="header-row">
                <div>
                    <h1>Dead-Letter Notifications</h1>
                    <div class="meta">Latest failed deliveries captured for review.</div>
                </div>
                <div class="badge">Total: {{ $items->total() }}</div>
            </div>

            <div class="toolbar">
                <label>
                    Channel (optional)
                    <select id="channel" {{ $isAdmin ? '' : 'disabled' }}>
                        <option value="">all</option>
                        <option value="sms">sms</option>
                        <option value="email">email</option>
                        <option value="push">push</option>
                    </select>
                </label>
                <label>
                    Limit
                    <input id="limit" type="number" min="1" max="1000" value="100" {{ $isAdmin ? '' : 'disabled' }}>
                </label>
                <button class="btn" id="requeueAll" {{ $isAdmin ? '' : 'disabled' }}>Requeue All</button>
                <div class="status" id="status"></div>
                <div class="progress" id="progress"><span></span></div>
                <div class="progress-count" id="progressCount"></div>
            </div>

            @if ($items->count() === 0)
                <div class="empty">No dead-letter notifications yet.</div>
            @else
                <div class="grid">
                    @foreach ($items as $item)
                        <div class="card">
                            <h2>{{ $item->id }}</h2>
                            <div class="row"><span>Channel</span><span>{{ $item->channel }}</span></div>
                            <div class="row"><span>Recipient</span><span>{{ $item->recipient }}</span></div>
                            <div class="row"><span>Attempts</span><span>{{ $item->attempts }}</span></div>
                            <div class="row"><span>Error</span><span>{{ $item->error_code ?? 'n/a' }}</span></div>
                            <div class="row"><span>Type</span><span>{{ $item->error_type ?? 'n/a' }}</span></div>
                            <div class="row"><span>Created</span><span>{{ $item->created_at }}</span></div>
                            <div class="payload">{{ json_encode($item->payload, JSON_PRETTY_PRINT) }}</div>
                            <div class="actions">
                                <button class="btn ghost" data-requeue-id="{{ $item->id }}" {{ $isAdmin ? '' : 'disabled' }}>Requeue</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <script>
            const channelInput = document.getElementById('channel');
            const limitInput = document.getElementById('limit');
            const statusEl = document.getElementById('status');
            const progressEl = document.getElementById('progress');
            const progressBar = progressEl.querySelector('span');
            const progressCountEl = document.getElementById('progressCount');
            const requeueAllBtn = document.getElementById('requeueAll');
            const csrfToken = '{{ csrf_token() }}';

            function setStatus(text, isError = false) {
                statusEl.textContent = text;
                statusEl.style.color = isError ? '#8f2232' : '#2f6f44';
            }

            function setProgress(active, percent = 0) {
                if (!active) {
                    progressEl.style.display = 'none';
                    progressBar.style.width = '0%';
                    progressCountEl.textContent = '';
                    return;
                }
                progressEl.style.display = 'block';
                progressBar.style.width = `${percent}%`;
            }

            function setProgressCount(text) {
                progressCountEl.textContent = text;
            }

            async function postJson(url, payload = null) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json'
                    },
                    body: payload ? JSON.stringify(payload) : null
                });

                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = body.message || 'Request failed.';
                    setStatus(message, true);
                    return null;
                }

                return body;
            }

            requeueAllBtn.addEventListener('click', async () => {
                const limit = Number(limitInput.value) || 100;
                const channel = channelInput.value;
                requeueAllBtn.disabled = true;
                setStatus('Requeueing...');
                setProgress(true, 15);
                setProgressCount(`0 / ${limit}`);
                const body = await postJson(`/admin/dead-letter/requeue`, {
                    limit,
                    channel
                });
                if (body) {
                    setProgress(true, 100);
                    setProgressCount(`${body.requeued} / ${limit} requeued`);
                    setStatus(`Requeued ${body.requeued}, skipped ${body.skipped}.`);
                }
                requeueAllBtn.disabled = false;
                setTimeout(() => setProgress(false), 600);
                setTimeout(() => setProgressCount(''), 600);
            });

            document.querySelectorAll('[data-requeue-id]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const id = button.getAttribute('data-requeue-id');
                    if (!id) {
                        return;
                    }

                    button.disabled = true;
                    setStatus(`Requeueing ${id}...`);
                    setProgress(true, 40);
                    setProgressCount('0 / 1');
                    const body = await postJson(`/admin/dead-letter/${id}/requeue`);
                    if (body) {
                        setProgress(true, 100);
                        setProgressCount('1 / 1');
                        setStatus(`Requeued ${body.notification_id}.`);
                    }
                    button.disabled = false;
                    setTimeout(() => setProgress(false), 600);
                    setTimeout(() => setProgressCount(''), 600);
                });
            });
        </script>
    </body>
    </html>
