<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Operations Console</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
            --accent-3: #13b08a;
            --card: #ffffff;
            --card-border: rgba(31, 31, 36, 0.08);
            --shadow: 0 24px 60px rgba(31, 31, 36, 0.12);
            --radius: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Space Grotesk", "Segoe UI", system-ui, sans-serif;
            color: var(--ink);
            background: radial-gradient(circle at top, #fdf8f1 0%, #f4f1ec 40%, #eef1f6 100%);
        }

        .page {
            min-height: 100vh;
            padding: 48px 6vw 80px;
            display: flex;
            flex-direction: column;
            gap: 32px;
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
            background: linear-gradient(135deg, var(--accent), var(--accent-3));
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

        header {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 12px;
            color: var(--muted);
        }

        h1 {
            margin: 0;
            font-family: "Fraunces", "Times New Roman", serif;
            font-size: clamp(2.4rem, 4vw, 3.4rem);
        }

        .subhead {
            color: var(--muted);
            max-width: 720px;
            font-size: 1rem;
        }

        .grid {
            display: grid;
            gap: 20px;
        }

        .grid.kpis {
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow);
        }

        .card h3 {
            margin: 0 0 10px 0;
            font-size: 1rem;
        }

        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
        }

        .kpi-label {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .split {
            display: grid;
            gap: 24px;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .panel-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .panel-title span {
            font-size: 0.85rem;
            color: var(--muted);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(47, 111, 237, 0.12);
            color: var(--accent);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        form {
            display: grid;
            gap: 12px;
        }

        label {
            font-size: 0.85rem;
            color: var(--muted);
        }

        input, select, textarea, button {
            font-family: inherit;
            font-size: 0.95rem;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid rgba(31, 31, 36, 0.16);
            background: #fff;
        }

        textarea {
            min-height: 90px;
        }

        .btn {
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 600;
            background: var(--accent);
            color: #fff;
            box-shadow: 0 16px 30px rgba(47, 111, 237, 0.25);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn.secondary {
            background: var(--accent-2);
            box-shadow: 0 16px 30px rgba(243, 156, 70, 0.2);
        }

        .btn.ghost {
            background: #fff;
            color: var(--ink);
            border: 1px dashed rgba(31, 31, 36, 0.2);
            box-shadow: none;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .row {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }

        .list {
            display: grid;
            gap: 12px;
        }

        .list-item {
            padding: 12px;
            border-radius: 14px;
            border: 1px solid rgba(31, 31, 36, 0.1);
            display: grid;
            gap: 6px;
        }

        .bar {
            height: 8px;
            border-radius: 999px;
            background: rgba(31, 31, 36, 0.08);
            overflow: hidden;
        }

        .bar > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent-3));
        }

        .status {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--muted);
        }

        .status.badge {
            display: inline-flex;
            width: fit-content;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(19, 176, 138, 0.12);
            color: var(--accent-3);
        }

        .status.badge.failed {
            background: rgba(255, 77, 77, 0.12);
            color: #c0392b;
        }

        .status.badge.pending {
            background: rgba(47, 111, 237, 0.12);
            color: var(--accent);
        }

        .muted {
            color: var(--muted);
        }

        .api-key-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            background: rgba(19, 176, 138, 0.12);
            color: #0f5f4b;
            font-size: 0.9rem;
        }

        .alert.error {
            background: rgba(255, 77, 77, 0.12);
            color: #b03737;
        }

        @media (max-width: 900px) {
            .page {
                padding: 36px 8vw 60px;
            }
        }
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
            <a href="/dead-letter">Dead Letter</a>
            <a href="/admin/audit">Audit Logs</a>
            <a href="/swagger">API Docs</a>
        </nav>
        <form method="POST" action="/admin/logout">
            @csrf
            <button class="btn ghost" type="submit">Log out</button>
        </form>
    </div>

    <header>
        <div class="eyebrow">Operations Console</div>
        <h1>Notification Command Center</h1>
        <p class="subhead">Manage high‑volume messaging, monitor delivery health, and act fast when a campaign spikes. This dashboard is optimized for rapid triage and confident releases.</p>
        @if (!$isAdmin)
            <div class="alert error">Viewer mode: actions are disabled. You can view metrics only.</div>
        @endif
        <div class="api-key-bar card">
            <div>
                <label for="apiKey">API Key</label>
                <input id="apiKey" placeholder="Paste X-Api-Key value" autocomplete="off" {{ $isAdmin ? '' : 'disabled' }}>
            </div>
            <button class="btn ghost" id="saveKey" {{ $isAdmin ? '' : 'disabled' }}>Save Key</button>
            <span class="muted">Required for live API actions.</span>
        </div>

        <div class="api-key-bar card">
            <form id="providerForm" style="width: 100%; display: grid; gap: 12px;">
                <div>
                    <label for="providerWebhookUrl">Primary Webhook URL</label>
                    <input id="providerWebhookUrl" type="url" required value="{{ $providerWebhookUrl ?? '' }}" {{ $isAdmin ? '' : 'disabled' }}>
                </div>
                <div>
                    <label for="providerFallbackUrl">Fallback Webhook URL (optional)</label>
                    <input id="providerFallbackUrl" type="url" value="{{ $providerFallbackWebhookUrl ?? '' }}" {{ $isAdmin ? '' : 'disabled' }}>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button class="btn secondary" type="submit" {{ $isAdmin ? '' : 'disabled' }}>Save Provider Settings</button>
                    <div id="providerAlert" class="alert" style="display:none;"></div>
                </div>
            </form>
        </div>
    </header>

    <section class="grid kpis">
        <div class="card">
            <h3>Total Notifications</h3>
            <div class="kpi-value" id="kpiTotal">{{ $metrics['total'] }}</div>
            <div class="kpi-label">All statuses combined</div>
        </div>
        <div class="card">
            <h3>Sent</h3>
            <div class="kpi-value" id="kpiSent">{{ $metrics['status_counts']['sent'] ?? 0 }}</div>
            <div class="kpi-label">Delivered to provider</div>
        </div>
        <div class="card">
            <h3>Failed</h3>
            <div class="kpi-value" id="kpiFailed">{{ $metrics['status_counts']['failed'] ?? 0 }}</div>
            <div class="kpi-label">Permanent failures</div>
        </div>
        <div class="card">
            <h3>Pending</h3>
            <div class="kpi-value" id="kpiPending">{{ $metrics['status_counts']['pending'] ?? 0 }}</div>
            <div class="kpi-label">In queue</div>
        </div>
        <div class="card">
            <h3>Dead Letter</h3>
            <div class="kpi-value" id="kpiDead">{{ $metrics['dead_letter_count'] }}</div>
            <div class="kpi-label">Needs review</div>
        </div>
        <div class="card">
            <h3>Avg Latency</h3>
            <div class="kpi-value" id="kpiLatency">{{ $metrics['avg_latency_seconds'] ?? '—' }}</div>
            <div class="kpi-label">Seconds to send</div>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Queue Lanes</h3>
                <span>Real-time depth</span>
            </div>
            <div class="grid kpis">
                @foreach ($metrics['queues'] as $lane => $depth)
                    <div class="list-item">
                        <div class="status">{{ ucfirst($lane) }} Queue</div>
                        <div class="kpi-value" id="queue-{{ $lane }}">{{ $depth }}</div>
                    </div>
                @endforeach
            </div>
            <div style="margin-top: 16px;">
                <span class="pill">Circuit Breaker</span>
                <div class="list">
                    @foreach ($metrics['circuit_breaker'] as $channel => $state)
                        <div class="list-item">
                            <div class="status">{{ strtoupper($channel) }}</div>
                            <div class="status badge {{ $state === 'open' ? 'failed' : 'pending' }}">
                                {{ $state === 'open' ? 'Open' : ($state === 'closed' ? 'Closed' : 'Unknown') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="panel-title">
                <h3>Send a Notification</h3>
                <span>Single or scheduled</span>
            </div>
            <form id="sendForm">
                <div class="row">
                    <div>
                        <label for="recipient">Recipient</label>
                        <input id="recipient" required placeholder="+905551234567 or user@example.com" {{ $isAdmin ? '' : 'disabled' }}>
                    </div>
                    <div>
                        <label for="channel">Channel</label>
                        <select id="channel" {{ $isAdmin ? '' : 'disabled' }}>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="push">Push</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label for="priority">Priority</label>
                        <select id="priority" {{ $isAdmin ? '' : 'disabled' }}>
                            <option value="high">High</option>
                            <option value="normal">Normal</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div>
                        <label for="scheduledAt">Schedule (optional)</label>
                        <input type="datetime-local" id="scheduledAt" {{ $isAdmin ? '' : 'disabled' }}>
                    </div>
                </div>
                <div>
                    <label for="content">Content</label>
                    <textarea id="content" placeholder="Your message" {{ $isAdmin ? '' : 'disabled' }}></textarea>
                </div>
                <div>
                    <label for="templateId">Template (optional)</label>
                    <select id="templateId" {{ $isAdmin ? '' : 'disabled' }}>
                        <option value="">No template</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->channel }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="variables">Template Variables (JSON)</label>
                    <textarea id="variables" placeholder='{"name":"Ada"}' {{ $isAdmin ? '' : 'disabled' }}></textarea>
                </div>
                <button class="btn" type="submit" {{ $isAdmin ? '' : 'disabled' }}>Send Notification</button>
                <div id="sendAlert" class="alert" style="display:none;"></div>
            </form>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Create Template</h3>
                <span>Reusable content blocks</span>
            </div>
            <form id="templateForm">
                <div class="row">
                    <div>
                        <label for="templateName">Name</label>
                        <input id="templateName" required placeholder="welcome_sms" {{ $isAdmin ? '' : 'disabled' }}>
                    </div>
                    <div>
                        <label for="templateChannel">Channel</label>
                        <select id="templateChannel" {{ $isAdmin ? '' : 'disabled' }}>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="push">Push</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="templateContent">Template Content</label>
                    <textarea id="templateContent" placeholder="Hello @{{name}}" {{ $isAdmin ? '' : 'disabled' }}></textarea>
                </div>
                <div>
                    <label for="templateVars">Default Variables (JSON)</label>
                    <textarea id="templateVars" placeholder='{"name":"Friend"}' {{ $isAdmin ? '' : 'disabled' }}></textarea>
                </div>
                <div class="row">
                    <button class="btn secondary" type="submit" {{ $isAdmin ? '' : 'disabled' }}>Create Template</button>
                    <button class="btn ghost" type="button" id="previewTemplate" {{ $isAdmin ? '' : 'disabled' }}>Preview Template</button>
                </div>
                <div id="templateAlert" class="alert" style="display:none;"></div>
            </form>
        </div>

        <div class="card">
            <div class="panel-title">
                <h3>Recent Activity</h3>
                <span>Last 5 notifications</span>
            </div>
            <div class="list" id="activityList">
                @forelse ($recentNotifications as $notification)
                    <div class="list-item">
                        <div class="status">{{ strtoupper($notification->channel) }} · {{ $notification->priority ?? 'normal' }}</div>
                        <div>{{ $notification->recipient }}</div>
                        <div class="muted">{{ $notification->created_at?->format('Y-m-d H:i') ?? '—' }}</div>
                        <div class="status badge {{ $notification->status === 'failed' ? 'failed' : 'pending' }}">
                            {{ $notification->status }}
                        </div>
                    </div>
                @empty
                    <div class="muted">No recent notifications yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Worker Control</h3>
                <span>Local start/stop toggle</span>
            </div>
            <div class="list">
                <div class="list-item">
                    <div class="status">Processing State</div>
                    <div class="status badge pending" id="jobStatus">Unknown</div>
                    <div class="muted">Paused means jobs will re-queue until resumed.</div>
                </div>
                <div class="list-item">
                    <div class="status">Worker Processes</div>
                    <div class="status badge pending" id="workerStatus">Unknown</div>
                    <div class="muted">Controls queue:work processes managed by Supervisor.</div>
                </div>
                <div class="list-item">
                    <div class="status">Supervisor Jobs</div>
                    <div class="muted" id="workerList">No data.</div>
                </div>
            </div>
            <div class="row" style="margin-top: 12px;">
                <button class="btn secondary" id="pauseJobs" {{ $isAdmin ? '' : 'disabled' }}>Stop Jobs</button>
                <button class="btn" id="resumeJobs" {{ $isAdmin ? '' : 'disabled' }}>Start Jobs</button>
                <button class="btn ghost" id="restartJobs" {{ $isAdmin ? '' : 'disabled' }}>Restart Workers</button>
            </div>
            <div class="row" style="margin-top: 12px;">
                <button class="btn secondary" id="stopWorkers" {{ $isAdmin ? '' : 'disabled' }}>Stop Workers</button>
                <button class="btn" id="startWorkers" {{ $isAdmin ? '' : 'disabled' }}>Start Workers</button>
            </div>
            <div id="jobAlert" class="alert" style="display:none; margin-top: 12px;"></div>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Stress Test</h3>
                <span>Generate load locally</span>
            </div>
            <form id="stressForm">
                <div class="list-item" style="margin-bottom: 12px;">
                    <div class="status">Last Run</div>
                    <div class="status badge pending" id="stressStatus">Idle</div>
                    <div class="muted" id="stressDetails">No runs yet.</div>
                </div>
                <div class="row">
                    <div>
                        <label for="stressCount">Total Count</label>
                        <input id="stressCount" type="number" min="1" max="5000" value="500" {{ $isAdmin ? '' : 'disabled' }}>
                    </div>
                    <div>
                        <label for="stressBatch">Batch Size</label>
                        <input id="stressBatch" type="number" min="1" max="1000" value="100" {{ $isAdmin ? '' : 'disabled' }}>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label for="stressChannel">Channel</label>
                        <select id="stressChannel" {{ $isAdmin ? '' : 'disabled' }}>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="push">Push</option>
                        </select>
                    </div>
                    <div>
                        <label for="stressPriority">Priority</label>
                        <select id="stressPriority" {{ $isAdmin ? '' : 'disabled' }}>
                            <option value="high">High</option>
                            <option value="normal" selected>Normal</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <button class="btn secondary" type="submit" {{ $isAdmin ? '' : 'disabled' }}>Run Stress Test</button>
                <div id="stressAlert" class="alert" style="display:none;"></div>
            </form>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Templates</h3>
                <span>Latest definitions</span>
            </div>
            <div class="list">
                @forelse ($templates as $template)
                    <div class="list-item">
                        <div class="status">{{ strtoupper($template->channel) }}</div>
                        <div>{{ $template->name }}</div>
                        <div class="muted">{{ \Illuminate\Support\Str::limit($template->content, 80) }}</div>
                    </div>
                @empty
                    <div class="muted">No templates yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="panel-title">
                <h3>Dead Letter Queue</h3>
                <span>Last 10 failures</span>
            </div>
            <div class="list">
                @forelse ($deadLetters as $dead)
                    <div class="list-item">
                        <div class="status">{{ strtoupper($dead->channel) }}</div>
                        <div>{{ $dead->recipient }}</div>
                        <div class="muted">{{ $dead->error_message }}</div>
                    </div>
                @empty
                    <div class="muted">No dead letters.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Failure Analytics</h3>
                <span>Permanent failures overview</span>
            </div>
            <div class="list">
                <div class="list-item">
                    <div class="status">Top Error Codes</div>
                    @forelse ($failureStats['codes'] as $row)
                        @php
                            $max = max(1, $failureStats['max_code']);
                            $width = (int) round(($row->total / $max) * 100);
                        @endphp
                        <div>
                            <div class="muted">{{ $row->error_code }} · {{ $row->total }}</div>
                            <div class="bar"><span style="width: {{ $width }}%"></span></div>
                        </div>
                    @empty
                        <div class="muted">No permanent failures yet.</div>
                    @endforelse
                </div>
                <div class="list-item">
                    <div class="status">Failure by Channel</div>
                    @forelse ($failureStats['channels'] as $row)
                        @php
                            $max = max(1, $failureStats['max_channel']);
                            $width = (int) round(($row->total / $max) * 100);
                        @endphp
                        <div>
                            <div class="muted">{{ strtoupper($row->channel) }} · {{ $row->total }}</div>
                            <div class="bar"><span style="width: {{ $width }}%"></span></div>
                        </div>
                    @empty
                        <div class="muted">No permanent failures yet.</div>
                    @endforelse
                </div>
                <div class="list-item">
                    <div class="status">Failure Types</div>
                    @forelse ($failureStats['types'] as $row)
                        @php
                            $max = max(1, $failureStats['max_type']);
                            $width = (int) round(($row->total / $max) * 100);
                        @endphp
                        <div>
                            <div class="muted">{{ $row->error_type }} · {{ $row->total }}</div>
                            <div class="bar"><span style="width: {{ $width }}%"></span></div>
                        </div>
                    @empty
                        <div class="muted">No failure types yet.</div>
                    @endforelse
                </div>
            </div>
            <div style="margin-top: 12px;">
                <a class="btn ghost" href="/dead-letter">Review Dead Letter Queue</a>
            </div>
        </div>
    </section>

    <section class="split">
        <div class="card">
            <div class="panel-title">
                <h3>Admin Users</h3>
                <span>Users with panel access</span>
            </div>
            <div class="list">
                @forelse ($adminUsers as $admin)
                    <div class="list-item">
                        <div class="status">{{ $admin->role }}</div>
                        <div>{{ $admin->name }}</div>
                        <div class="muted">{{ $admin->email }}</div>
                        <div class="status badge {{ $admin->is_active ? 'pending' : 'failed' }}">
                            {{ $admin->is_active ? 'Active' : 'Disabled' }}
                        </div>
                    </div>
                @empty
                    <div class="muted">No admin users yet.</div>
                @endforelse
            </div>
        </div>
    </section>
</div>

<script>
    const apiKeyInput = document.getElementById('apiKey');
    const saveKeyBtn = document.getElementById('saveKey');
    const sendForm = document.getElementById('sendForm');
    const templateForm = document.getElementById('templateForm');
    const previewTemplateBtn = document.getElementById('previewTemplate');
    const isAdmin = {{ $isAdmin ? 'true' : 'false' }};

    function getApiKey() {
        return localStorage.getItem('notification-api-key') || '';
    }

    function setApiKey(value) {
        localStorage.setItem('notification-api-key', value);
        apiKeyInput.value = value;
    }

    function showAlert(targetId, message, isError = false) {
        const target = document.getElementById(targetId);
        if (!target) return;
        target.textContent = message;
        target.classList.toggle('error', isError);
        target.style.display = 'block';
    }

    function apiFetch(path, options = {}) {
        const headers = options.headers || {};
        const key = getApiKey();
        if (key) {
            headers['X-Api-Key'] = key;
        }
        headers['Content-Type'] = 'application/json';
        headers['X-Correlation-Id'] = headers['X-Correlation-Id'] || crypto.randomUUID();
        return fetch(path, { ...options, headers });
    }

    function adminFetch(path, options = {}) {
        const headers = options.headers || {};
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }
        headers['Content-Type'] = 'application/json';
        return fetch(path, { ...options, headers });
    }

    async function refreshMetrics() {
        if (!getApiKey()) return;
        try {
            const response = await apiFetch('/api/v1/metrics');
            if (!response.ok) return;
            const data = await response.json();
            document.getElementById('kpiTotal').textContent = Object.values(data.status_counts || {}).reduce((a, b) => a + b, 0);
            document.getElementById('kpiSent').textContent = data.status_counts?.sent ?? 0;
            document.getElementById('kpiFailed').textContent = data.status_counts?.failed ?? 0;
            document.getElementById('kpiPending').textContent = data.status_counts?.pending ?? 0;
            document.getElementById('kpiDead').textContent = data.dead_letter_count ?? 0;
            document.getElementById('kpiLatency').textContent = data.avg_latency_seconds ?? '—';

            Object.entries(data.queues || {}).forEach(([queue, depth]) => {
                const el = document.getElementById(`queue-${queue}`);
                if (el) el.textContent = depth;
            });
        } catch (error) {
            console.error(error);
        }
    }

    async function refreshActivity() {
        if (!getApiKey()) return;
        try {
            const response = await apiFetch('/api/v1/notifications?per_page=10');
            if (!response.ok) return;
            const data = await response.json();
            const items = data.data || [];
            const container = document.getElementById('activityList');
            container.innerHTML = '';
            items.forEach((item) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'list-item';
                const createdAt = item.created_at ? new Date(item.created_at).toLocaleString() : '—';
                wrapper.innerHTML = `
                    <div class="status">${(item.channel || '').toUpperCase()} · ${item.priority || 'normal'}</div>
                    <div>${item.recipient || '-'}</div>
                    <div class="muted">${createdAt}</div>
                    <div class="status badge ${item.status === 'failed' ? 'failed' : 'pending'}">${item.status}</div>
                `;
                container.appendChild(wrapper);
            });
            if (!items.length) {
                container.innerHTML = '<div class="muted">No recent notifications yet.</div>';
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function refreshJobStatus() {
        try {
            const response = await adminFetch('/admin/jobs/status');
            if (!response.ok) return;
            const data = await response.json();
            const statusEl = document.getElementById('jobStatus');
            if (!statusEl) return;
            if (data.paused) {
                statusEl.textContent = 'Paused';
                statusEl.classList.remove('pending');
                statusEl.classList.add('failed');
            } else {
                statusEl.textContent = 'Running';
                statusEl.classList.remove('failed');
                statusEl.classList.add('pending');
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function refreshWorkerStatus() {
        try {
            const response = await adminFetch('/admin/workers/status');
            if (!response.ok) return;
            const data = await response.json();
            const lines = Array.isArray(data.lines) ? data.lines.join('\n') : '';
            const workerEl = document.getElementById('workerStatus');
            const listEl = document.getElementById('workerList');
            if (!workerEl) return;
            const running = lines.includes('RUNNING');
            workerEl.textContent = running ? 'Running' : 'Stopped';
            workerEl.classList.toggle('failed', !running);
            workerEl.classList.toggle('pending', running);
            if (listEl) {
                listEl.textContent = Array.isArray(data.lines) && data.lines.length ? data.lines.join(' | ') : 'No data.';
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function refreshStressStatus() {
        try {
            const response = await adminFetch('/admin/jobs/stress/status');
            if (!response.ok) return;
            const data = await response.json();
            const statusEl = document.getElementById('stressStatus');
            const detailsEl = document.getElementById('stressDetails');
            if (!statusEl || !detailsEl) return;

            const status = data.status || 'idle';
            statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusEl.classList.toggle('failed', status === 'failed');
            statusEl.classList.toggle('pending', status !== 'failed');

            const payload = data.payload ? `count=${data.payload.count}, batch=${data.payload.batch}, ${data.payload.channel}/${data.payload.priority}` : '';
            const when = data.finished_at || data.started_at || '';
            const output = data.output ? data.output.replace(/\n/g, ' | ') : '';
            detailsEl.textContent = [payload, when, output].filter(Boolean).join(' • ') || 'No runs yet.';
        } catch (error) {
            console.error(error);
        }
    }

    saveKeyBtn.addEventListener('click', () => {
        if (!isAdmin) {
            return;
        }
        setApiKey(apiKeyInput.value.trim());
        refreshMetrics();
        refreshActivity();
    });

    sendForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!isAdmin) {
            return;
        }
        const payload = {
            notifications: [{
                recipient: document.getElementById('recipient').value.trim(),
                channel: document.getElementById('channel').value,
                content: document.getElementById('content').value.trim() || null,
                priority: document.getElementById('priority').value,
            }]
        };
        const scheduledAt = document.getElementById('scheduledAt').value;
        if (scheduledAt) {
            payload.notifications[0].scheduled_at = new Date(scheduledAt).toISOString();
        }
        const templateId = document.getElementById('templateId').value;
        if (templateId) {
            payload.notifications[0].template_id = templateId;
        }
        const variables = document.getElementById('variables').value.trim();
        if (variables) {
            try {
                payload.notifications[0].variables = JSON.parse(variables);
            } catch (error) {
                showAlert('sendAlert', 'Invalid JSON for variables.', true);
                return;
            }
        }

        try {
            const response = await apiFetch('/api/v1/notifications', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                showAlert('sendAlert', data.message || 'Failed to send.', true);
                return;
            }
            showAlert('sendAlert', `Queued ${data.created} notifications in batch ${data.batch_id}.`);
            refreshActivity();
            refreshMetrics();
        } catch (error) {
            showAlert('sendAlert', 'Request failed.', true);
        }
    });

    templateForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!isAdmin) {
            return;
        }
        const payload = {
            name: document.getElementById('templateName').value.trim(),
            channel: document.getElementById('templateChannel').value,
            content: document.getElementById('templateContent').value.trim(),
        };
        const vars = document.getElementById('templateVars').value.trim();
        if (vars) {
            try {
                payload.default_variables = JSON.parse(vars);
            } catch (error) {
                showAlert('templateAlert', 'Invalid JSON for default variables.', true);
                return;
            }
        }

        try {
            const response = await apiFetch('/api/v1/templates', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                showAlert('templateAlert', data.message || 'Failed to create template.', true);
                return;
            }
            showAlert('templateAlert', `Template created with id ${data.id}.`);
        } catch (error) {
            showAlert('templateAlert', 'Request failed.', true);
        }
    });

    previewTemplateBtn.addEventListener('click', async () => {
        if (!isAdmin) {
            return;
        }
        const templateId = document.getElementById('templateId').value;
        const varsRaw = document.getElementById('variables').value.trim();
        let variables = {};
        if (varsRaw) {
            try {
                variables = JSON.parse(varsRaw);
            } catch (error) {
                showAlert('sendAlert', 'Invalid JSON for variables.', true);
                return;
            }
        }

        if (!templateId) {
            showAlert('sendAlert', 'Select a template to preview.', true);
            return;
        }

        try {
            const response = await apiFetch('/api/v1/templates/preview', {
                method: 'POST',
                body: JSON.stringify({
                    template_id: templateId,
                    variables,
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                showAlert('sendAlert', data.message || 'Preview failed.', true);
                return;
            }
            document.getElementById('content').value = data.content || '';
            showAlert('sendAlert', 'Template preview loaded into content.');
        } catch (error) {
            showAlert('sendAlert', 'Request failed.', true);
        }
    });

    apiKeyInput.value = getApiKey();
    if (apiKeyInput.value) {
        refreshMetrics();
        refreshActivity();
    }

    document.getElementById('pauseJobs')?.addEventListener('click', async () => {
        if (!isAdmin) {
            return;
        }
        try {
            const response = await adminFetch('/admin/jobs/pause', { method: 'POST' });
            const data = await response.json();
            showAlert('jobAlert', data.message || 'Processing paused.');
            refreshJobStatus();
        } catch (error) {
            showAlert('jobAlert', 'Request failed.', true);
        }
    });

    document.getElementById('resumeJobs')?.addEventListener('click', async () => {
        if (!isAdmin) {
            return;
        }
        try {
            const response = await adminFetch('/admin/jobs/resume', { method: 'POST' });
            const data = await response.json();
            showAlert('jobAlert', data.message || 'Processing resumed.');
            refreshJobStatus();
        } catch (error) {
            showAlert('jobAlert', 'Request failed.', true);
        }
    });

    document.getElementById('restartJobs')?.addEventListener('click', async () => {
        if (!isAdmin) {
            return;
        }
        try {
            const response = await adminFetch('/admin/jobs/restart', { method: 'POST' });
            const data = await response.json();
            if (!response.ok) {
                showAlert('jobAlert', data.message || 'Queue restart failed.', true);
                return;
            }
            showAlert('jobAlert', data.message || 'Queue restart signal sent.');
        } catch (error) {
            showAlert('jobAlert', 'Request failed.', true);
        }
    });

    document.getElementById('startWorkers')?.addEventListener('click', async () => {
        if (!isAdmin) {
            return;
        }
        try {
            const response = await adminFetch('/admin/workers/start', { method: 'POST' });
            const data = await response.json();
            if (!response.ok) {
                showAlert('jobAlert', data.lines?.join('\n') || 'Failed to start workers.', true);
                return;
            }
            showAlert('jobAlert', data.lines?.join('\n') || 'Workers started.');
            refreshWorkerStatus();
        } catch (error) {
            showAlert('jobAlert', 'Request failed.', true);
        }
    });

    document.getElementById('stopWorkers')?.addEventListener('click', async () => {
        if (!isAdmin) {
            return;
        }
        try {
            const response = await adminFetch('/admin/workers/stop', { method: 'POST' });
            const data = await response.json();
            if (!response.ok) {
                showAlert('jobAlert', data.lines?.join('\n') || 'Failed to stop workers.', true);
                return;
            }
            showAlert('jobAlert', data.lines?.join('\n') || 'Workers stopped.');
            refreshWorkerStatus();
        } catch (error) {
            showAlert('jobAlert', 'Request failed.', true);
        }
    });

    document.getElementById('stressForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!isAdmin) {
            return;
        }
        const payload = {
            count: parseInt(document.getElementById('stressCount').value, 10),
            batch: parseInt(document.getElementById('stressBatch').value, 10),
            channel: document.getElementById('stressChannel').value,
            priority: document.getElementById('stressPriority').value,
        };

        try {
            const response = await adminFetch('/admin/jobs/stress', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                showAlert('stressAlert', data.message || 'Stress test failed.', true);
                return;
            }
            showAlert('stressAlert', data.message || 'Stress test started.');
            refreshStressStatus();
        } catch (error) {
            showAlert('stressAlert', 'Request failed.', true);
        }
    });

    document.getElementById('providerForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!isAdmin) {
            return;
        }
        const payload = {
            provider_webhook_url: document.getElementById('providerWebhookUrl').value.trim(),
            provider_fallback_webhook_url: document.getElementById('providerFallbackUrl').value.trim() || null,
        };

        try {
            const response = await adminFetch('/admin/provider/settings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok) {
                showAlert('providerAlert', data.message || 'Failed to save provider settings.', true);
                return;
            }
            showAlert('providerAlert', data.message || 'Provider settings saved.');
        } catch (error) {
            showAlert('providerAlert', 'Request failed.', true);
        }
    });

    refreshJobStatus();
    refreshWorkerStatus();
    refreshStressStatus();
</script>
</body>
</html>
