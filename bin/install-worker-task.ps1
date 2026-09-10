$ErrorActionPreference = 'Stop'
$projectPath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$starterPath = Join-Path $PSScriptRoot 'start-worker.ps1'
$phpPath = (Get-Command php -ErrorAction Stop).Source
$powershellPath = Join-Path $env:WINDIR 'System32\WindowsPowerShell\v1.0\powershell.exe'
$userName = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$taskName = 'CitaYaV2 - Procesador de correos'
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -File "' + $starterPath + '" -PhpPath "' + $phpPath + '"'
$action = New-ScheduledTaskAction -Execute $powershellPath -Argument $arguments -WorkingDirectory $projectPath
$loginTrigger = New-ScheduledTaskTrigger -AtLogOn -User $userName
$recoveryTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId $userName -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 2)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger @($loginTrigger,$recoveryTrigger) -Principal $principal -Settings $settings -Description 'Mantiene activo el procesador de CitaYaV2 durante la sesión de Windows. Comprueba cada minuto si necesita reiniciarse. Envía las solicitudes pendientes autorizadas al correo guardado en cada formulario.' -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Write-Output 'Arranque al iniciar sesión y recuperación cada minuto configurados.'
