<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session expired</title>
    <style>html{font-size:16px}body{font:1rem/1.5 system-ui,sans-serif;color:#173b27;background:#f4f7f4;margin:0;padding:2rem}h1{font-size:1.5rem;line-height:1.25}main{max-width:38rem;margin:8vh auto;padding:2rem;background:#fff;border:1px solid #dce4de;border-radius:1rem}a{color:#14532d}a:focus-visible{outline:3px solid #b38328;outline-offset:4px}</style>
</head>
<body>
<main aria-labelledby="expired-title">
    <h1 id="expired-title">Your session expired</h1>
    <p>{{ $message }}</p>
    <p>This page cannot reliably restore submitted form data, passwords, or file selections. Use your browser's Back button to check for your entries and copy any work before leaving. Sign in in a new tab, then open a fresh form and review it before submitting.</p>
    <p><a href="{{ $recovery_url }}" target="_blank" rel="noopener">Sign in again (opens a new tab)</a></p>
    <p>No action will be retried automatically.</p>
</main>
</body>
</html>
