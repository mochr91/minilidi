Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$project = Split-Path -Parent $PSScriptRoot
$port = if ($env:MINILIDI_PORT) { [int]$env:MINILIDI_PORT } else { 8093 }
$gameUrl = "http://127.0.0.1:$port/"
$php = 'D:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe'
if (!(Test-Path -LiteralPath $php)) {
    $php = (Get-Command php.exe -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1).Source
}

$hash = [System.Security.Cryptography.SHA256]::Create()
$key = [BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($project.ToLowerInvariant()))).Replace('-','')
$hash.Dispose()
$mutex = New-Object System.Threading.Mutex($false, "Local\Minilidi-$key")
$activationName = "Local\Minilidi-Activate-$key"
$activation = New-Object System.Threading.EventWaitHandle($false, [System.Threading.EventResetMode]::AutoReset, $activationName)
try { $ownsMutex = $mutex.WaitOne(0) }
catch [System.Threading.AbandonedMutexException] { $ownsMutex = $true }
if (!$ownsMutex) {
    [void]$activation.Set()
    $activation.Dispose()
    $mutex.Dispose()
    return
}

$form = New-Object System.Windows.Forms.Form
$form.Text = 'Minilidi'
$form.StartPosition = 'CenterScreen'
$form.ClientSize = New-Object System.Drawing.Size(490, 210)
$form.FormBorderStyle = 'FixedDialog'
$form.MaximizeBox = $false
$form.Font = New-Object System.Drawing.Font('Segoe UI', 10)
$form.BackColor = [System.Drawing.Color]::FromArgb(247, 241, 226)

$heading = New-Object System.Windows.Forms.Label
$heading.Text = 'Minilidi'
$heading.Font = New-Object System.Drawing.Font('Georgia', 21, [System.Drawing.FontStyle]::Bold)
$heading.SetBounds(18, 12, 450, 40)
$form.Controls.Add($heading)

$status = New-Object System.Windows.Forms.Label
$status.SetBounds(20, 56, 450, 44)
$form.Controls.Add($status)

$useAi = New-Object System.Windows.Forms.CheckBox
$useAi.Text = 'Používat místní AI a GPU'
$useAi.Checked = $env:MINILIDI_NO_AI -ne '1'
$useAi.SetBounds(18, 106, 450, 25)
$form.Controls.Add($useAi)

function New-LauncherButton([string]$text, [int]$left, [int]$width) {
    $button = New-Object System.Windows.Forms.Button
    $button.Text = $text
    $button.SetBounds($left, 154, $width, 34)
    $form.Controls.Add($button)
    return $button
}

$startButton = New-LauncherButton 'Spustit' 18 100
$stopButton = New-LauncherButton 'Zastavit' 126 100
$restartButton = New-LauncherButton 'Restartovat' 234 110
$openButton = New-LauncherButton 'Otevřít hru' 352 120

$run = @{
    Process = $null
    StopFile = $null
    StandardError = $null
    StandardOutput = $null
    Ready = $false
    Stopping = $false
    Restart = $false
    Closing = $false
    OpenBrowser = $false
}

function Update-Buttons {
    $managed = $null -ne $run.Process
    $startButton.Enabled = !$managed
    $stopButton.Enabled = $managed -and !$run.Stopping
    $restartButton.Enabled = $run.Ready -and !$run.Stopping
    $openButton.Enabled = $run.Ready -and !$run.Stopping
    $useAi.Enabled = !$managed -or ($run.Ready -and !$run.Stopping)
}

function Get-FreeLocalPort {
    $listener = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, 0)
    try {
        $listener.Start()
        return $listener.LocalEndpoint.Port
    } finally {
        $listener.Stop()
    }
}

function Start-Game([bool]$openBrowser) {
    if ($run.Process) { return }
    if (!$php) {
        $status.Text = 'PHP nebylo nalezeno. Zkontroluj instalaci Laragonu.'
        return
    }
    try {
        $stopFile = Join-Path ([IO.Path]::GetTempPath()) ('minilidi-' + [Guid]::NewGuid().ToString('N') + '.stop')
        $extensions = Join-Path (Split-Path -Parent $php) 'ext'
        $supervisor = Join-Path $PSScriptRoot 'spustit.php'
        $info = New-Object System.Diagnostics.ProcessStartInfo
        $info.FileName = $php
        $info.Arguments = '-n -d "extension_dir={0}" -d extension=pdo_sqlite -d extension=curl "{1}"' -f $extensions, $supervisor
        $info.WorkingDirectory = $PSScriptRoot
        $info.UseShellExecute = $false
        $info.CreateNoWindow = $true
        $info.RedirectStandardError = $true
        $info.RedirectStandardOutput = $true
        $info.StandardErrorEncoding = [Text.Encoding]::UTF8
        $info.StandardOutputEncoding = [Text.Encoding]::UTF8
        $info.EnvironmentVariables['MINILIDI_NO_BROWSER'] = '1'
        $info.EnvironmentVariables['MINILIDI_STOP_FILE'] = $stopFile
        $info.EnvironmentVariables['MINILIDI_PORT'] = [string]$port
        $info.EnvironmentVariables['MINILIDI_NO_AI'] = if ($useAi.Checked) { '0' } else { '1' }
        $info.EnvironmentVariables['MINILIDI_PRIVATE_OLLAMA_PORT'] = if ($useAi.Checked) { [string](Get-FreeLocalPort) } else { '0' }

        $run.Process = [System.Diagnostics.Process]::Start($info)
        $run.StopFile = $stopFile
        $run.StandardError = $run.Process.StandardError.ReadToEndAsync()
        $run.StandardOutput = $run.Process.StandardOutput.ReadToEndAsync()
        $run.OpenBrowser = $openBrowser
        $status.Text = if ($useAi.Checked) { 'Spouštím hru a místní AI…' } else { 'Spouštím hru bez AI…' }
        Update-Buttons
    } catch {
        $status.Text = 'Spuštění selhalo. ' + $_.Exception.GetBaseException().Message
        Update-Buttons
    }
}

function Stop-Game([bool]$restart) {
    if (!$run.Process -or $run.Stopping) { return }
    try {
        [IO.File]::WriteAllText($run.StopFile, '')
        $run.Stopping = $true
        $run.Restart = $restart
        $status.Text = if ($restart) { 'Restartuji hru…' } else { 'Zastavuji hru…' }
        Update-Buttons
    } catch {
        $run.Closing = $false
        $status.Text = 'Zastavení selhalo. ' + $_.Exception.GetBaseException().Message
    }
}

$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 250
$timer.Add_Tick({
    if ($activation.WaitOne(0)) {
        if ($form.WindowState -eq 'Minimized') { $form.WindowState = 'Normal' }
        $form.Activate()
    }
    if (!$run.Process) { return }
    if ($run.Process.HasExited) {
        $exitCode = $run.Process.ExitCode
        $errorText = $run.StandardError.GetAwaiter().GetResult().Trim()
        $run.Process.Dispose()
        $run.Process = $null
        foreach ($file in @($run.StopFile, ($run.StopFile + '.ready'))) {
            if ([IO.File]::Exists($file)) { [IO.File]::Delete($file) }
        }
        $run.Ready = $false
        $run.Stopping = $false
        $restart = $run.Restart
        $run.Restart = $false
        if ($run.Closing) {
            $form.Close()
            return
        }
        if ($restart -and $exitCode -eq 0) {
            Start-Game $false
            return
        }
        $status.Text = if ($exitCode -eq 0) { 'Hra je zastavená.' } elseif ($errorText) { $errorText } else { 'Herní proces skončil s chybou.' }
        Update-Buttons
        return
    }
    if (!$run.Ready -and !$run.Stopping -and [IO.File]::Exists($run.StopFile + '.ready')) {
        $run.Ready = $true
        $status.Text = if ($useAi.Checked) { 'Hra běží s místní AI.' } else { 'Hra běží podle pravidel bez AI.' }
        Update-Buttons
        if ($run.OpenBrowser) { Start-Process $gameUrl }
    }
})

$startButton.Add_Click({ Start-Game $true })
$stopButton.Add_Click({ Stop-Game $false })
$restartButton.Add_Click({ Stop-Game $true })
$openButton.Add_Click({ Start-Process $gameUrl })
$useAi.Add_CheckedChanged({ if ($run.Ready) { Stop-Game $true } })
$form.Add_FormClosing({
    param($sender, $eventArgs)
    if ($run.Process) {
        $eventArgs.Cancel = $true
        $run.Closing = $true
        Stop-Game $false
    }
})

$status.Text = 'Hra je zastavená.'
$probe = New-Object System.Net.Sockets.TcpClient
try {
    $connect = $probe.ConnectAsync([Net.IPAddress]::Loopback, $port)
    if ($connect.Wait(200) -and $probe.Connected) {
        $status.Text = 'Hra už běží v jiném spouštěči. Nejdřív jej ukonči.'
    }
} catch {
    # Uzavřený port je očekávaný stav před spuštěním.
} finally {
    $probe.Dispose()
}
Update-Buttons
try {
    $timer.Start()
    [void]$form.ShowDialog()
} finally {
    $timer.Dispose()
    $form.Dispose()
    $activation.Dispose()
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}
