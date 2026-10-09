<#
.SYNOPSIS
    Menyalakan MariaDB XAMPP di latar belakang dengan log ke berkas (pengganti tombol Start MySQL di Control Panel).

.DESCRIPTION
    MariaDB 10.4.32 yang dinyalakan tanpa stderr yang sah (dari XAMPP Control Panel, atau
    Start-Process tersembunyi tanpa pengalihan) menulis setiap baris log sesudah
    "Server socket created" ke dalam berkas tabel sistem Aria (mysql.db dan lainnya),
    sehingga GRANT hilang (insiden 2026-10-02 dan dua kali 2026-10-09, blueprint 16
    bagian 13). Skrip ini menjalankan mysqld dengan --console dan mengalihkan stderr ke
    data\mysql_console.log, sehingga log tidak pernah tertulis ke tabel. Log start
    sebelumnya disimpan sebagai data\mysql_console.prev.log. Matikan selalu dengan
    scripts\mysql-stop.ps1.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\mysql-start.ps1
#>
param(
    [string]$Xampp = 'C:\xampp3',
    [int]$TimeoutSeconds = 30
)

$ErrorActionPreference = 'Stop'
$mysqld = Join-Path $Xampp 'mysql\bin\mysqld.exe'
$mysqladmin = Join-Path $Xampp 'mysql\bin\mysqladmin.exe'
$ini = Join-Path $Xampp 'mysql\bin\my.ini'
$dataDir = Join-Path $Xampp 'mysql\data'
$consoleLog = Join-Path $dataDir 'mysql_console.log'
$consoleOut = Join-Path $dataDir 'mysql_console.out.log'
$previousLog = Join-Path $dataDir 'mysql_console.prev.log'

if (-not (Test-Path $mysqld)) {
    Write-Error "mysqld.exe tidak ditemukan di $mysqld"
    exit 1
}

$running = Get-Process -Name mysqld -ErrorAction SilentlyContinue
if ($running) {
    Write-Output "MySQL sudah berjalan (PID $($running.Id -join ', ')). Tidak ada yang diubah."
    Write-Output 'Bila MySQL dinyalakan dari Control Panel, matikan dengan scripts\mysql-stop.ps1 lalu jalankan skrip ini lagi.'
    exit 0
}

if (Test-Path $consoleLog) {
    Move-Item -Path $consoleLog -Destination $previousLog -Force
}

$process = Start-Process -FilePath $mysqld `
    -ArgumentList "--defaults-file=$ini", '--standalone', '--console' `
    -WorkingDirectory $Xampp -WindowStyle Hidden -PassThru `
    -RedirectStandardError $consoleLog -RedirectStandardOutput $consoleOut

$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
while ((Get-Date) -lt $deadline) {
    if ($process.HasExited) {
        break
    }
    & $mysqladmin -u root --silent ping 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Output "MySQL berjalan (PID $($process.Id)), siap menerima koneksi di port 3306. Log: $consoleLog"
        exit 0
    }
    Start-Sleep -Milliseconds 500
}

Write-Output "MySQL gagal menyala. Baris terakhir $consoleLog :"
if (Test-Path $consoleLog) {
    Get-Content -Path $consoleLog -Tail 15 | ForEach-Object { "  $_" }
}
Write-Output 'Bila ada "mysql.db is marked as crashed", lihat blueprint 16 bagian 13 baris "GRANT tidak tersimpan".'
exit 1
