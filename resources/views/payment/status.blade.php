<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
@if ($refreshUrl)<meta http-equiv="refresh" content="6;url={{ $refreshUrl }}">@endif
<title>
    @switch($state)
        @case('success') Payment confirmed — Higher Life @break
        @case('failed') Payment not completed — Higher Life @break
        @case('review') Payment received — Higher Life @break
        @case('pending') Confirming your payment — Higher Life @break
        @case('unmatched') Payment received — Higher Life @break
        @default Higher Life payment
    @endswitch
</title>
<style>
    :root { --navy:#0B2A5B; --blue:#1F6FEB; --gold:#FFC233; --ink:#14264A; --muted:#5F7394; }
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
    .card { width: 100%; max-width: 440px; background: rgba(255,255,255,.94); border-radius: 28px; padding: 40px 32px 32px; text-align: center; box-shadow: 0 30px 60px rgba(11,42,91,.18); }
    .brand { font-weight: 700; letter-spacing: .28em; font-size: 11px; color: #B07A00; margin-bottom: 28px; }
    .icon { width: 84px; height: 84px; border-radius: 50%; margin: 0 auto 22px; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #fff; }
    .success .icon { background: linear-gradient(135deg, #2DD4A0, #12A57A); }
    .failed .icon { background: linear-gradient(135deg, #FF8A7A, #E5484D); }
    .pending .icon, .review .icon, .unknown .icon, .unmatched .icon { background: linear-gradient(135deg, #FFD166, #FFB020); }
    .pending .icon span { display: inline-block; width: 34px; height: 34px; border: 4px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    h1 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 34px; line-height: 1.1; color: var(--navy); margin-bottom: 12px; }
    p { color: var(--muted); font-size: 16px; line-height: 1.55; }
    .btn { display: block; margin-top: 28px; padding: 17px; border-radius: 14px; font-weight: 700; font-size: 16px; text-decoration: none; background: var(--gold); color: var(--navy); }
    .btn.alt { background: #fff; border: 1.6px solid #D6E6F6; margin-top: 12px; }
    .ref { margin-top: 22px; font-size: 12px; color: var(--muted); letter-spacing: .04em; }
    .ref code { background: #EEF5FC; padding: 3px 8px; border-radius: 8px; color: var(--navy); }
    .note { margin-top: 18px; font-size: 13px; }
</style>
</head>
<body>
<main class="card {{ $state }}">
    <div class="brand">HIGHER LIFE</div>

    @if ($state === 'success')
        <div class="icon">✓</div>
        <h1>Payment confirmed</h1>
        <p>Thank you! Your Higher Life Premium subscription is active. Return to the app and enjoy your affirmations.</p>
        <a class="btn" href="{{ $appLink }}">Open Higher Life</a>
    @elseif ($state === 'failed')
        <div class="icon">!</div>
        <h1>Payment not completed</h1>
        <p>Your payment didn't go through, and you haven't been charged for this attempt. You can go back to the app and try again.</p>
        <a class="btn" href="{{ $appLink }}">Back to Higher Life</a>
    @elseif ($state === 'review')
        <div class="icon">⏳</div>
        <h1>We're checking your payment</h1>
        <p>We received your payment details and are verifying them. Your access opens as soon as it's confirmed — usually very quickly.</p>
        <a class="btn" href="{{ $appLink }}">Back to Higher Life</a>
    @elseif ($state === 'pending')
        <div class="icon"><span></span></div>
        <h1>Confirming your payment</h1>
        <p>Espees is still processing your payment. This page refreshes by itself — you don't need to do anything.</p>
        <a class="btn alt" href="{{ $appLink }}">Back to Higher Life</a>
    @elseif ($state === 'unmatched')
        <div class="icon">↩</div>
        <h1>Back from Espees</h1>
        <p>If you completed the payment, open Higher Life: your subscription opens as soon as Espees confirms it. If it doesn't open within a few minutes, tap <strong>"Paid but still waiting?"</strong> in the app and we'll confirm it for you.</p>
        <a class="btn" href="{{ $appLink }}">Open Higher Life</a>
    @else
        <div class="icon">?</div>
        <h1>Payment status unavailable</h1>
        <p>We couldn't match this page to a payment. Open Higher Life — your subscription status shows there.</p>
        <a class="btn" href="{{ $appLink }}">Open Higher Life</a>
    @endif

    @if ($reference)<div class="ref">Reference <code>{{ $reference }}</code></div>@endif
    <p class="note">If the app doesn't open, switch back to it — it updates on its own.</p>
</main>
</body>
</html>
