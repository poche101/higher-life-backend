<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $state === 'done' ? 'Password updated' : 'Choose a new password' }} — Higher Life</title>
<style>
    :root { --navy:#0B2A5B; --blue:#1F6FEB; --gold:#FFC233; --ink:#14264A; --muted:#5F7394; --line:#D6E6F6; --danger:#C93B3B; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; color: var(--ink);
        background:
            radial-gradient(circle at 100% 0%, rgba(255,224,138,.9), transparent 52%),
            radial-gradient(ellipse 340px 140px at 12% 14%, rgba(255,255,255,.95), transparent 70%),
            radial-gradient(ellipse 420px 160px at 90% 82%, rgba(255,255,255,.95), transparent 70%),
            linear-gradient(180deg, #7CC8F8 0%, #BDE5FC 42%, #EAF6FF 72%, #FFF3CF 100%);
    }
    .card { width: 100%; max-width: 440px; background: rgba(255,255,255,.95); border-radius: 28px; padding: 40px 32px 32px; text-align: center; box-shadow: 0 30px 60px rgba(11,42,91,.18); }
    .brand { font-weight: 700; letter-spacing: .28em; font-size: 11px; color: #B07A00; margin-bottom: 26px; }
    .icon { width: 76px; height: 76px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 36px; color: #fff; }
    .ok { background: linear-gradient(135deg, #2DD4A0, #12A57A); }
    .warn { background: linear-gradient(135deg, #FFD166, #FFB020); }
    h1 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 34px; line-height: 1.1; color: var(--navy); margin-bottom: 12px; }
    p { color: var(--muted); font-size: 16px; line-height: 1.55; }
    form { margin-top: 26px; text-align: left; }
    label { display: block; font-size: 13px; font-weight: 600; color: var(--navy); margin: 14px 0 6px; }
    .field { position: relative; }
    input[type=password], input[type=text], input[type=email] {
        width: 100%; padding: 15px 48px 15px 16px; border-radius: 14px; border: 1.6px solid var(--line); background: #fff;
        font-size: 16px; color: var(--ink); outline: none;
    }
    input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(31,111,235,.15); }
    input[readonly] { background: #F3F8FD; color: var(--muted); padding-right: 16px; }
    .eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); border: 0; background: none; padding: 10px; cursor: pointer; color: var(--muted); font-size: 14px; font-weight: 600; }
    .hint { font-size: 12.5px; color: var(--muted); margin-top: 6px; }
    .err { background: #FDECEC; color: var(--danger); border-radius: 12px; padding: 11px 14px; font-size: 14px; margin-top: 16px; text-align: left; }
    .btn { display: block; width: 100%; margin-top: 24px; padding: 17px; border: 0; border-radius: 14px; font-weight: 700; font-size: 16px; text-align: center; text-decoration: none; background: var(--gold); color: var(--navy); cursor: pointer; }
    .btn:disabled { opacity: .6; cursor: default; }
    .note { margin-top: 20px; font-size: 13px; }
</style>
</head>
<body>
<main class="card">
    <div class="brand">HIGHER LIFE</div>

    @if ($state === 'done')
        <div class="icon ok">✓</div>
        <h1>Password updated</h1>
        <p>Your password has been changed and you have been signed out on all devices. Open the Higher Life app and sign in with your new password.</p>
        <p class="note">You can close this page.</p>
    @elseif ($state === 'expired')
        <div class="icon warn">!</div>
        <h1>This link has expired</h1>
        <p>For your security, a reset link works for a short time and only once. Open the Higher Life app, tap <strong>Forgot password?</strong> and we'll send you a new one.</p>
    @else
        <h1>Choose a new password</h1>
        <p>Pick something you'll remember and others can't guess.</p>

        <form method="POST" action="{{ route('password.update') }}" id="reset-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" readonly>

            <label for="password">New password</label>
            <div class="field">
                <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password" autofocus>
                <button type="button" class="eye" data-target="password" aria-label="Show or hide password">Show</button>
            </div>
            <div class="hint">At least 8 characters.</div>

            <label for="password_confirmation">Confirm new password</label>
            <div class="field">
                <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
                <button type="button" class="eye" data-target="password_confirmation" aria-label="Show or hide password">Show</button>
            </div>

            @if ($errors->any())
                <div class="err">{{ $errors->first() }}</div>
            @endif

            <button class="btn" type="submit" id="submit-btn">Update password</button>
        </form>
    @endif
</main>
<script>
    document.querySelectorAll('.eye').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.getElementById(b.dataset.target);
            var show = i.type === 'password';
            i.type = show ? 'text' : 'password';
            b.textContent = show ? 'Hide' : 'Show';
        });
    });
    var f = document.getElementById('reset-form');
    if (f) f.addEventListener('submit', function () {
        var s = document.getElementById('submit-btn'); s.disabled = true; s.textContent = 'Updating…';
    });
</script>
</body>
</html>
