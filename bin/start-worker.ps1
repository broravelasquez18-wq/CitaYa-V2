param([string]$PhpPath = '')
$ErrorActionPreference = 'Stop'
$projectPath = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$pidPath = Join-Path $projectPath '.runtime\worker.pid'
# Prevent a manual launch and the scheduled check from starting two processes.
$mutexName = 'Local\CitaYaWorker-' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($projectPath)).Replace('/','_')
$launchMutex = [Threading.Mutex]::new($false, $mutexName)
$ownsMutex = $false
try {
    try { $ownsMutex = $launchMutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $ownsMutex = $true }
    if (-not $ownsMutex) { return }
    $runningWorker = Get-CimInstance Win32_Process -Filter "name = 'php.exe'" | Where-Object {
        $_.CommandLine -like "*$projectPath*bin*worker.php*--loop*"
    } | Select-Object -First 1
    if ($runningWorker) {
        Set-Content -LiteralPath $pidPath -Value $runningWorker.ProcessId
        Write-Output 'El procesador de correos ya está activo.'
        return
    }
    if (-not $PhpPath) { $PhpPath = (Get-Command php -ErrorAction Stop).Source }
    if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'No se encuentra el ejecutable de PHP.' }
    $workerPath = Join-Path $projectPath 'bin\worker.php'
    $workerProcess = Start-Process -FilePath $PhpPath -ArgumentList @('"' + $workerPath + '"', '--loop') -WorkingDirectory $projectPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectPath '.runtime\worker-output.log') -RedirectStandardError (Join-Path $projectPath '.runtime\worker-error.log')
    Set-Content -LiteralPath $pidPath -Value $workerProcess.Id
    Write-Output "Procesador de correos iniciado. PID: $($workerProcess.Id)"
} finally {
    if ($ownsMutex) { $launchMutex.ReleaseMutex() }
    $launchMutex.Dispose()
}
