-- UNPAM HOSPITAL: invarian setelah seeding (13 section 11.1; 15 SEED-01).
-- ASCII murni, kompatibel MariaDB 10.4. Hasil lulus: 17 baris dengan pelanggaran = 0.
-- Jalankan (PowerShell; password dari .env DB_PASSWORD, jangan diketik di riwayat perintah):
--   $env:MYSQL_PWD = "<DB_PASSWORD>"
--   Get-Content -Raw "D:\Project Web RS\scripts\sql\seed-invariants.sql" | & "C:\xampp3\mysql\bin\mysql.exe" -u unpam_app --default-character-set=utf8mb4 --table unpam_hospital
--   Remove-Item Env:\MYSQL_PWD
SELECT 'I1 booked_count = appointment non-batal' AS invarian, COUNT(*) AS pelanggaran FROM (
  SELECT ps.id FROM practice_sessions ps
  LEFT JOIN appointments a ON a.practice_session_id = ps.id AND a.status <> 'cancelled'
  GROUP BY ps.id, ps.booked_count HAVING ps.booked_count <> COUNT(a.id)) x
UNION ALL
SELECT 'I2 last_queue_number >= MAX(queue_number)', COUNT(*) FROM (
  SELECT ps.id FROM practice_sessions ps JOIN appointments a ON a.practice_session_id = ps.id
  GROUP BY ps.id, ps.last_queue_number HAVING ps.last_queue_number < MAX(a.queue_number)) x
UNION ALL
SELECT 'I3 last_queue_number = jumlah seluruh appointment', COUNT(*) FROM (
  SELECT ps.id FROM practice_sessions ps LEFT JOIN appointments a ON a.practice_session_id = ps.id
  GROUP BY ps.id, ps.last_queue_number HAVING ps.last_queue_number <> COUNT(a.id)) x
UNION ALL
SELECT 'I4 tidak ada duplikat aktif pasien+sesi', COUNT(*) FROM (
  SELECT patient_profile_id, practice_session_id FROM appointments
  WHERE status IN ('pending','confirmed','checked_in')
  GROUP BY patient_profile_id, practice_session_id HAVING COUNT(*) > 1) x
UNION ALL
SELECT 'I5 tidak ada benturan jam aktif', COUNT(*) FROM appointments a1
  JOIN appointments a2 ON a2.patient_profile_id = a1.patient_profile_id
   AND a2.session_date = a1.session_date AND a2.id > a1.id
  JOIN practice_sessions s1 ON s1.id = a1.practice_session_id
  JOIN practice_sessions s2 ON s2.id = a2.practice_session_id
  WHERE a1.status IN ('pending','confirmed','checked_in')
    AND a2.status IN ('pending','confirmed','checked_in')
    AND s1.start_time < s2.end_time AND s2.start_time < s1.end_time
UNION ALL
SELECT 'I6 denormalisasi appointment = sesi', COUNT(*) FROM appointments a
  JOIN practice_sessions ps ON ps.id = a.practice_session_id
  WHERE a.doctor_id <> ps.doctor_id OR a.clinic_id <> ps.clinic_id OR a.session_date <> ps.session_date
UNION ALL
SELECT 'I7 beds_available dalam 0..bed_capacity', COUNT(*) FROM room_categories
  WHERE beds_available < 0 OR beds_available > bed_capacity
UNION ALL
SELECT 'I8 booked_count dalam 0..quota', COUNT(*) FROM practice_sessions
  WHERE booked_count < 0 OR booked_count > quota
UNION ALL
SELECT 'I9 status = riwayat terakhir', COUNT(*) FROM appointments a
  WHERE a.status <> COALESCE((SELECT h.to_status FROM appointment_status_histories h
                              WHERE h.appointment_id = a.id ORDER BY h.id DESC LIMIT 1), '')
UNION ALL
SELECT 'I10 cap waktu status terisi', COUNT(*) FROM appointments
  WHERE (status = 'checked_in' AND checked_in_at IS NULL)
     OR (status = 'completed' AND completed_at IS NULL)
     OR (status = 'no_show' AND no_show_at IS NULL)
     OR (status = 'cancelled' AND (cancelled_at IS NULL OR cancelled_by_type IS NULL))
UNION ALL
SELECT 'I11 konsistensi pembayaran', COUNT(*) FROM appointments
  WHERE (payment_type = 'bpjs' AND (payment_reference IS NULL OR payment_reference_last4 IS NULL))
     OR (payment_type = 'asuransi' AND insurance_partner_id IS NULL)
     OR (payment_type = 'umum' AND (payment_reference IS NOT NULL OR insurance_partner_id IS NOT NULL))
UNION ALL
SELECT 'I12 format booking_code', COUNT(*) FROM appointments
  WHERE booking_code NOT REGEXP '^UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$'
UNION ALL
SELECT 'I13 needs_review punya pending_change', COUNT(*) FROM practice_sessions
  WHERE status = 'needs_review' AND pending_change IS NULL
UNION ALL
SELECT 'I14 audit_logs tanpa deret 13 digit', COUNT(*) FROM audit_logs
  WHERE CONCAT_WS(' ', description, old_values, new_values) REGEXP '[0-9]{13}'
UNION ALL
SELECT 'I15 format patient_number', COUNT(*) FROM patient_profiles
  WHERE patient_number IS NULL OR patient_number <> CONCAT('P', LPAD(id, 6, '0'))
UNION ALL
SELECT 'I16 email profil = email akun', COUNT(*) FROM patient_profiles p
  JOIN users u ON u.id = p.user_id
  WHERE p.email IS NULL OR p.email <> u.email
UNION ALL
SELECT 'I17 is_active profil = is_active akun', COUNT(*) FROM patient_profiles p
  JOIN users u ON u.id = p.user_id
  WHERE p.is_active <> u.is_active;
