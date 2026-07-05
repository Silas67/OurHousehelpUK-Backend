<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — OurHouseHelp UK</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 36px 32px; width: 100%; max-width: 380px; box-shadow: 0 4px 24px rgba(0,0,0,0.07); }
        .brand { text-align: center; margin-bottom: 28px; }
        .brand-name { font-size: 20px; font-weight: 800; color: #1E3A5F; }
        .brand-sub { font-size: 12px; color: #64748b; margin-top: 4px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
        input { width: 100%; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 14px; color: #1a1a2e; }
        input:focus { outline: none; border-color: #1E3A5F; }
        .error { font-size: 12px; color: #dc2626; margin-top: 4px; }
        button[type=submit] { width: 100%; padding: 12px; background: #1E3A5F; color: #fff; border: none; border-radius: 9px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 4px; }
        button[type=submit]:hover { background: #162d4a; }
    </style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-name">OurHouseHelp UK</div>
        <div class="brand-sub">Admin Panel</div>
    </div>

    <form method="POST" action="{{ route('admin.login.post') }}">
        @csrf
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="admin@ourhousehelp.co.uk" autofocus required>
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit">Sign In</button>
    </form>
</div>
</body>
</html>
