<#
.SYNOPSIS
    Menyalakan MariaDB XAMPP lewat konsol tersembunyi (pengganti tombol Start MySQL di Control Panel).

.DESCRIPTION
    MariaDB 10.4.32 yang dinyalakan dari XAMPP Control Panel menulis log galat ke dalam
    berkas tabel sistem Aria (mysql.db dan lainnya), sehingga GRANT hilang (insiden
    2026-10-02 dan 2026-10-09, blueprint 16 bagian 13). Dengan konsol tersembunyi, log
    tidak tertulis ke tabel. Matikan selalu dengan scripts\mysql-stop.ps1.

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

if (-not (Test-Path $mysqld)) {
    Write-Error "mysqld.exe tidak ditemukan di $mysqld"
    exit 1
}

$running = Get-Process -Name mysqld -ErrorAction SilentlyContinue
if ($running) {
    Write-Output "MySQL sudah berjalan (PID $($running.Id -join ', ')). Tidak ada yang diubah."
    exit 0
}

$process = Start-Process -FilePath $mysqld `
    -ArgumentList "--defaults-file=$ini", '--standalone' `
    -WorkingDirectory $Xampp -WindowStyle Hidden -PassThru

$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
while ((Get-Date) -lt $deadline) {
    if ($process.HasExited) {
        break
    }
    & $mysqladmin -u root --silent ping 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Output "MySQL berjalan (PID $($process.Id)), siap menerima koneksi di port 3306."
        exit 0
    }
    Start-Sleep -Milliseconds 500
}

Write-Output 'MySQL gagal menyala. Galat terakhir dari Windows Event Log (sumber MariaDB):'
Get-WinEvent -FilterHashtable @{ LogName = 'Application'; ProviderName = 'MariaDB'; StartTime = (Get-Date).AddMinutes(-2) } -ErrorAction SilentlyContinue |
    Where-Object { $_.LevelDisplayName -eq 'Error' } |
    Sort-Object TimeCreated |
    ForEach-Object { '  {0:HH:mm:ss} {1}' -f $_.TimeCreated, (($_.Message -replace '\s+', ' ').Trim()) }
Write-Output 'Bila ada "mysql.db is marked as crashed", lihat blueprint 16 bagian 13 baris "GRANT tidak tersimpan".'
exit 1
