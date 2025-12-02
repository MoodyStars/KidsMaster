# register_worker_task.ps1
# Register a Windows scheduled task to run the PHP worker at startup.
param(
  [string]$PhpExe = "C:\xampp\php\php.exe",
  [string]$WorkerScript = "C:\xampp\htdocs\kidsmaster\workers\worker.php",
  [string]$TaskName = "KidsMasterWorker"
)

$action = New-ScheduledTaskAction -Execute $PhpExe -Argument $WorkerScript
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Description "KidsMaster worker (encoding_jobs processor)"
Write-Host "Scheduled task $TaskName registered."