<#
.SYNOPSIS
    Mematikan MariaDB XAMPP secara bersih (pengganti tombol Stop MySQL di Control Panel).

.DESCRIPTION
    Tombol Stop di XAMPP Control Panel dan mysql_stop.bat membunuh proses mysqld, sehingga
    tabel sistem Aria bisa rusak. Skrip ini memakai "mysqladmin shutdown" lalu menunggu
    proses selesai. Jalankan juga sebelum mematikan atau me-restart Windows.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\mysql-stop.ps1
#>
param(
    [string]$Xampp = 'C:\xampp3',
    [int]$TimeoutSeconds = 60
)

$ErrorActionPreference = 'Stop'
$mysqladmin = Join-Path $Xampp 'mysql\bin\mysqladmin.exe'
$pidFile = Join-Path $Xampp 'mysql\data\mysql.pid'

$running = Get-Process -Name mysqld -ErrorAction SilentlyContinue
if (-not $running) {
    Write-Output 'MySQL tidak sedang berjalan. Tidak ada yang diubah.'
    exit 0
}

& $mysqladmin -u root shutdown
if ($LASTEXITCODE -ne 0) {
    Write-Output 'mysqladmin shutdown gagal. MySQL TIDAK dihentikan; jangan memakai tombol Stop Control Panel.'
    exit 1
}

$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
while ((Get-Date) -lt $deadline) {
    if (-not (Get-Process -Name mysqld -ErrorAction SilentlyContinue)) {
        if (Test-Path $pidFile) {
            Write-Output "MySQL berhenti, tetapi $pidFile masih ada (penghentian mungkin tidak bersih)."
            exit 1
        }
        Write-Output 'MySQL berhenti dengan bersih.'
        exit 0
    }
    Start-Sleep -Milliseconds 500
}

Write-Output "MySQL belum berhenti setelah $TimeoutSeconds detik. Tunggu sebentar lalu jalankan skrip ini lagi."
exit 1
