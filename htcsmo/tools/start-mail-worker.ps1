$ErrorActionPreference = 'Stop'
$workspaceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpBinary = (Get-Command php.exe).Source
$pidFile = Join-Path $workspaceRoot 'storage/framework/mail-worker.pid'

if (Test-Path -LiteralPath $pidFile) {
    $savedProcessId = 0
    if ([int]::TryParse((Get-Content -LiteralPath $pidFile -Raw).Trim(), [ref]$savedProcessId)) {
        $existingWorker = Get-Process -Id $savedProcessId -ErrorAction SilentlyContinue
        if ($existingWorker -and $existingWorker.Path -eq $phpBinary -and
            $existingWorker.StartTime.ToUniversalTime() -le (Get-Item -LiteralPath $pidFile).LastWriteTimeUtc) {
            Write-Output "Mail worker is already running (PID $savedProcessId)."
            return
        }
    }
}

$worker = Start-Process -FilePath $phpBinary -WorkingDirectory $workspaceRoot `
    -ArgumentList @('artisan', 'queue:work', '--queue=mail', '--sleep=5', '--tries=3', '--timeout=30') `
    -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $workspaceRoot 'storage/logs/mail-worker.log') `
    -RedirectStandardError (Join-Path $workspaceRoot 'storage/logs/mail-worker-error.log')
$worker.Id | Set-Content -LiteralPath $pidFile
Write-Output "Mail worker started in the background (PID $($worker.Id))."
