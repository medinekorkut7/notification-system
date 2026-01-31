<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
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
            --shadow: 0 30px 80px rgba(31, 31, 36, 0.12);
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
            display: grid;
            place-items: center;
            padding: 40px 20px;
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 32px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow);
            max-width: 420px;
            width: 100%;
        }

        h1 {
            margin: 0 0 8px 0;
            font-family: "Fraunces", "Times New Roman", serif;
            font-size: 2rem;
        }

        p {
            margin: 0 0 24px 0;
            color: var(--muted);
        }

        label {
            font-size: 0.85rem;
            color: var(--muted);
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(31, 31, 36, 0.16);
            margin-top: 6px;
            margin-bottom: 16px;
            font-family: inherit;
            font-size: 0.95rem;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 999px;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 600;
            background: var(--accent);
            color: #fff;
            box-shadow: 0 16px 30px rgba(47, 111, 237, 0.25);
        }

        .error {
            background: rgba(255, 77, 77, 0.12);
            color: #b03737;
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Admin Login</h1>
        <p>Sign in to access the notification console.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/admin/login">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}">

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <button class="btn" type="submit">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
