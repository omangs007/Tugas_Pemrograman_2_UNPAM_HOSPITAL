<?php

namespace Tests\Feature\Database;

use App\Enums\AppointmentStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Skema fase 3: 09 §2.2, §3.5-3.7, §4.1-4.2, §6.7 (insurance_partners), §10, §11.
 * Seluruh insert memakai DB::table() agar tes tidak bergantung pada model fase 3 (Task 2).
 */
class ScheduleSchemaTest extends TestCase
{
    use RefreshDatabase;

    private ?int $doctorId = null;

    private ?int $clinicId = null;

    private ?int $sessionId = null;

    private int $counter = 0;

    // --- Data kamus ---

    /**
     * Kolom: tabel, nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    public static function expectedColumns(): array
    {
        $rows = [
            // 09 §2.2 patient_profiles
            ['patient_profiles', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['patient_profiles', 'user_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['patient_profiles', 'patient_number', 'varchar', 'varchar(10)', true, null],
            ['patient_profiles', 'full_name', 'varchar', 'varchar(150)', false, null],
            ['patient_profiles', 'birth_date', 'date', 'date', false, null],
            ['patient_profiles', 'gender', 'char', 'char(1)', false, null],
            ['patient_profiles', 'phone', 'varchar', 'varchar(20)', false, null],
            ['patient_profiles', 'email', 'varchar', 'varchar(255)', true, null],
            ['patient_profiles', 'address', 'varchar', 'varchar(500)', false, null],
            ['patient_profiles', 'bpjs_number', 'text', 'text', true, null],
            ['patient_profiles', 'bpjs_number_last4', 'char', 'char(4)', true, null],
            ['patient_profiles', 'registration_channel', 'varchar', 'varchar(10)', false, null],
            ['patient_profiles', 'created_by_user_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['patient_profiles', 'consent_at', 'datetime', 'datetime', false, null],
            ['patient_profiles', 'consent_version', 'varchar', 'varchar(20)', false, null],
            ['patient_profiles', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['patient_profiles', 'created_at', 'timestamp', 'timestamp', true, null],
            ['patient_profiles', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.5 doctor_schedules
            ['doctor_schedules', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_schedules', 'doctor_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_schedules', 'clinic_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_schedules', 'day_of_week', 'tinyint', 'tinyint(3) unsigned', false, null],
            ['doctor_schedules', 'start_time', 'time', 'time', false, null],
            ['doctor_schedules', 'end_time', 'time', 'time', false, null],
            ['doctor_schedules', 'quota', 'smallint', 'smallint(5) unsigned', false, null],
            ['doctor_schedules', 'valid_from', 'date', 'date', false, null],
            ['doctor_schedules', 'valid_until', 'date', 'date', true, null],
            ['doctor_schedules', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['doctor_schedules', 'notes', 'varchar', 'varchar(255)', true, null],
            ['doctor_schedules', 'created_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['doctor_schedules', 'updated_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['doctor_schedules', 'created_at', 'timestamp', 'timestamp', true, null],
            ['doctor_schedules', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.6 schedule_exceptions
            ['schedule_exceptions', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['schedule_exceptions', 'doctor_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['schedule_exceptions', 'type', 'varchar', 'varchar(20)', false, null],
            ['schedule_exceptions', 'start_date', 'date', 'date', false, null],
            ['schedule_exceptions', 'end_date', 'date', 'date', false, null],
            ['schedule_exceptions', 'reason', 'varchar', 'varchar(255)', false, null],
            ['schedule_exceptions', 'created_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['schedule_exceptions', 'created_at', 'timestamp', 'timestamp', true, null],
            ['schedule_exceptions', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.7 practice_sessions
            ['practice_sessions', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['practice_sessions', 'doctor_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['practice_sessions', 'clinic_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['practice_sessions', 'doctor_schedule_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['practice_sessions', 'session_date', 'date', 'date', false, null],
            ['practice_sessions', 'start_time', 'time', 'time', false, null],
            ['practice_sessions', 'end_time', 'time', 'time', false, null],
            ['practice_sessions', 'quota', 'smallint', 'smallint(5) unsigned', false, null],
            ['practice_sessions', 'booked_count', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['practice_sessions', 'last_queue_number', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['practice_sessions', 'status', 'varchar', 'varchar(20)', false, 'open'],
            ['practice_sessions', 'status_reason', 'varchar', 'varchar(255)', true, null],
            ['practice_sessions', 'schedule_exception_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['practice_sessions', 'pending_change', 'longtext', 'longtext', true, null],
            ['practice_sessions', 'is_manually_modified', 'tinyint', 'tinyint(1)', false, '0'],
            ['practice_sessions', 'reviewed_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['practice_sessions', 'reviewed_at', 'datetime', 'datetime', true, null],
            ['practice_sessions', 'created_at', 'timestamp', 'timestamp', true, null],
            ['practice_sessions', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.7 insurance_partners
            ['insurance_partners', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['insurance_partners', 'name', 'varchar', 'varchar(150)', false, null],
            ['insurance_partners', 'slug', 'varchar', 'varchar(170)', false, null],
            ['insurance_partners', 'type', 'varchar', 'varchar(20)', false, null],
            ['insurance_partners', 'description', 'varchar', 'varchar(500)', true, null],
            ['insurance_partners', 'website_url', 'varchar', 'varchar(255)', true, null],
            ['insurance_partners', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['insurance_partners', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['insurance_partners', 'created_at', 'timestamp', 'timestamp', true, null],
            ['insurance_partners', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §4.1 appointments
            ['appointments', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointments', 'booking_code', 'varchar', 'varchar(13)', false, null],
            ['appointments', 'patient_profile_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointments', 'practice_session_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointments', 'doctor_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointments', 'clinic_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointments', 'session_date', 'date', 'date', false, null],
            ['appointments', 'queue_number', 'smallint', 'smallint(5) unsigned', false, null],
            ['appointments', 'status', 'varchar', 'varchar(20)', false, null],
            ['appointments', 'channel', 'varchar', 'varchar(10)', false, null],
            ['appointments', 'payment_type', 'varchar', 'varchar(10)', false, null],
            ['appointments', 'insurance_partner_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['appointments', 'payment_reference', 'text', 'text', true, null],
            ['appointments', 'payment_reference_last4', 'char', 'char(4)', true, null],
            ['appointments', 'complaint', 'varchar', 'varchar(500)', true, null],
            ['appointments', 'created_by_user_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['appointments', 'confirmed_at', 'datetime', 'datetime', true, null],
            ['appointments', 'checked_in_at', 'datetime', 'datetime', true, null],
            ['appointments', 'completed_at', 'datetime', 'datetime', true, null],
            ['appointments', 'cancelled_at', 'datetime', 'datetime', true, null],
            ['appointments', 'no_show_at', 'datetime', 'datetime', true, null],
            ['appointments', 'cancelled_by_type', 'varchar', 'varchar(10)', true, null],
            ['appointments', 'cancelled_by_user_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['appointments', 'cancel_reason', 'varchar', 'varchar(255)', true, null],
            ['appointments', 'schedule_changed_at', 'datetime', 'datetime', true, null],
            ['appointments', 'active_flag', 'tinyint', 'tinyint(4)', true, null],
            ['appointments', 'created_at', 'timestamp', 'timestamp', true, null],
            ['appointments', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §4.2 appointment_status_histories (created_at diuji terpisah: NOT NULL DEFAULT CURRENT_TIMESTAMP)
            ['appointment_status_histories', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointment_status_histories', 'appointment_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['appointment_status_histories', 'from_status', 'varchar', 'varchar(20)', true, null],
            ['appointment_status_histories', 'to_status', 'varchar', 'varchar(20)', false, null],
            ['appointment_status_histories', 'actor_type', 'varchar', 'varchar(10)', false, null],
            ['appointment_status_histories', 'actor_user_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['appointment_status_histories', 'reason', 'varchar', 'varchar(255)', true, null],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases["{$row[0]}.{$row[1]}"] = $row;
        }

        return $cases;
    }

    /**
     * Urutan kolom persis kamus data (kolom generated `active_flag` berada sebelum timestamp).
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function expectedColumnOrder(): array
    {
        return [
            'patient_profiles' => ['patient_profiles', ['id', 'user_id', 'patient_number', 'full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'bpjs_number', 'bpjs_number_last4', 'registration_channel', 'created_by_user_id', 'consent_at', 'consent_version', 'is_active', 'created_at', 'updated_at']],
            'doctor_schedules' => ['doctor_schedules', ['id', 'doctor_id', 'clinic_id', 'day_of_week', 'start_time', 'end_time', 'quota', 'valid_from', 'valid_until', 'is_active', 'notes', 'created_by', 'updated_by', 'created_at', 'updated_at']],
            'schedule_exceptions' => ['schedule_exceptions', ['id', 'doctor_id', 'type', 'start_date', 'end_date', 'reason', 'created_by', 'created_at', 'updated_at']],
            'practice_sessions' => ['practice_sessions', ['id', 'doctor_id', 'clinic_id', 'doctor_schedule_id', 'session_date', 'start_time', 'end_time', 'quota', 'booked_count', 'last_queue_number', 'status', 'status_reason', 'schedule_exception_id', 'pending_change', 'is_manually_modified', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at']],
            'insurance_partners' => ['insurance_partners', ['id', 'name', 'slug', 'type', 'description', 'website_url', 'is_active', 'sort_order', 'created_at', 'updated_at']],
            'appointments' => ['appointments', ['id', 'booking_code', 'patient_profile_id', 'practice_session_id', 'doctor_id', 'clinic_id', 'session_date', 'queue_number', 'status', 'channel', 'payment_type', 'insurance_partner_id', 'payment_reference', 'payment_reference_last4', 'complaint', 'created_by_user_id', 'confirmed_at', 'checked_in_at', 'completed_at', 'cancelled_at', 'no_show_at', 'cancelled_by_type', 'cancelled_by_user_id', 'cancel_reason', 'schedule_changed_at', 'active_flag', 'created_at', 'updated_at']],
            'appointment_status_histories' => ['appointment_status_histories', ['id', 'appointment_id', 'from_status', 'to_status', 'actor_type', 'actor_user_id', 'reason', 'created_at']],
        ];
    }

    /**
     * Index: tabel, nama index, kolom, unik, primer. Index FK otomatis yang tertutup index lain
     * (mis. doctor_id pada doctor_schedules) sengaja tidak dicantumkan: MariaDB membuangnya.
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: bool, 4: bool}>
     */
    public static function expectedIndexes(): array
    {
        $rows = [
            ['patient_profiles', 'primary', ['id'], true, true],
            ['patient_profiles', 'patient_profiles_user_id_unique', ['user_id'], true, false],
            ['patient_profiles', 'patient_profiles_patient_number_unique', ['patient_number'], true, false],
            ['patient_profiles', 'patient_profiles_created_by_user_id_foreign', ['created_by_user_id'], false, false],
            ['patient_profiles', 'patient_profiles_full_name_index', ['full_name'], false, false],
            ['patient_profiles', 'patient_profiles_phone_index', ['phone'], false, false],
            ['doctor_schedules', 'primary', ['id'], true, true],
            ['doctor_schedules', 'doctor_schedules_doctor_id_day_of_week_is_active_index', ['doctor_id', 'day_of_week', 'is_active'], false, false],
            ['doctor_schedules', 'doctor_schedules_is_active_valid_from_valid_until_index', ['is_active', 'valid_from', 'valid_until'], false, false],
            ['doctor_schedules', 'doctor_schedules_clinic_id_foreign', ['clinic_id'], false, false],
            ['doctor_schedules', 'doctor_schedules_created_by_foreign', ['created_by'], false, false],
            ['doctor_schedules', 'doctor_schedules_updated_by_foreign', ['updated_by'], false, false],
            ['schedule_exceptions', 'primary', ['id'], true, true],
            ['schedule_exceptions', 'schedule_exceptions_start_date_end_date_index', ['start_date', 'end_date'], false, false],
            ['schedule_exceptions', 'schedule_exceptions_doctor_id_start_date_index', ['doctor_id', 'start_date'], false, false],
            ['schedule_exceptions', 'schedule_exceptions_created_by_foreign', ['created_by'], false, false],
            ['practice_sessions', 'primary', ['id'], true, true],
            ['practice_sessions', 'practice_sessions_doctor_schedule_id_session_date_unique', ['doctor_schedule_id', 'session_date'], true, false],
            ['practice_sessions', 'practice_sessions_session_date_status_index', ['session_date', 'status'], false, false],
            ['practice_sessions', 'practice_sessions_doctor_id_session_date_index', ['doctor_id', 'session_date'], false, false],
            ['practice_sessions', 'practice_sessions_clinic_id_session_date_index', ['clinic_id', 'session_date'], false, false],
            ['practice_sessions', 'practice_sessions_schedule_exception_id_foreign', ['schedule_exception_id'], false, false],
            ['practice_sessions', 'practice_sessions_reviewed_by_foreign', ['reviewed_by'], false, false],
            ['insurance_partners', 'primary', ['id'], true, true],
            ['insurance_partners', 'insurance_partners_slug_unique', ['slug'], true, false],
            ['insurance_partners', 'insurance_partners_is_active_type_sort_order_index', ['is_active', 'type', 'sort_order'], false, false],
            ['appointments', 'primary', ['id'], true, true],
            ['appointments', 'appointments_booking_code_unique', ['booking_code'], true, false],
            ['appointments', 'appointments_practice_session_id_queue_number_unique', ['practice_session_id', 'queue_number'], true, false],
            ['appointments', 'appointments_patient_session_active_unique', ['patient_profile_id', 'practice_session_id', 'active_flag'], true, false],
            ['appointments', 'appointments_session_date_status_index', ['session_date', 'status'], false, false],
            ['appointments', 'appointments_patient_profile_id_session_date_index', ['patient_profile_id', 'session_date'], false, false],
            ['appointments', 'appointments_doctor_id_session_date_index', ['doctor_id', 'session_date'], false, false],
            ['appointments', 'appointments_status_index', ['status'], false, false],
            ['appointments', 'appointments_created_at_index', ['created_at'], false, false],
            ['appointments', 'appointments_clinic_id_foreign', ['clinic_id'], false, false],
            ['appointments', 'appointments_insurance_partner_id_foreign', ['insurance_partner_id'], false, false],
            ['appointments', 'appointments_created_by_user_id_foreign', ['created_by_user_id'], false, false],
            ['appointments', 'appointments_cancelled_by_user_id_foreign', ['cancelled_by_user_id'], false, false],
            ['appointment_status_histories', 'primary', ['id'], true, true],
            ['appointment_status_histories', 'appointment_status_histories_appointment_id_created_at_index', ['appointment_id', 'created_at'], false, false],
            ['appointment_status_histories', 'appointment_status_histories_actor_user_id_foreign', ['actor_user_id'], false, false],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases["{$row[0]}.{$row[1]}"] = $row;
        }

        return $cases;
    }

    /**
     * Foreign key: tabel, nama FK, kolom, tabel rujukan, aksi ON DELETE.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function expectedForeignKeys(): array
    {
        return [
            'patient_profiles.user_id' => ['patient_profiles', 'patient_profiles_user_id_foreign', 'user_id', 'users', 'set null'],
            'patient_profiles.created_by_user_id' => ['patient_profiles', 'patient_profiles_created_by_user_id_foreign', 'created_by_user_id', 'users', 'set null'],
            'doctor_schedules.doctor_id' => ['doctor_schedules', 'doctor_schedules_doctor_id_foreign', 'doctor_id', 'doctors', 'restrict'],
            'doctor_schedules.clinic_id' => ['doctor_schedules', 'doctor_schedules_clinic_id_foreign', 'clinic_id', 'clinics', 'restrict'],
            'doctor_schedules.created_by' => ['doctor_schedules', 'doctor_schedules_created_by_foreign', 'created_by', 'users', 'set null'],
            'doctor_schedules.updated_by' => ['doctor_schedules', 'doctor_schedules_updated_by_foreign', 'updated_by', 'users', 'set null'],
            'schedule_exceptions.doctor_id' => ['schedule_exceptions', 'schedule_exceptions_doctor_id_foreign', 'doctor_id', 'doctors', 'restrict'],
            'schedule_exceptions.created_by' => ['schedule_exceptions', 'schedule_exceptions_created_by_foreign', 'created_by', 'users', 'set null'],
            'practice_sessions.doctor_id' => ['practice_sessions', 'practice_sessions_doctor_id_foreign', 'doctor_id', 'doctors', 'restrict'],
            'practice_sessions.clinic_id' => ['practice_sessions', 'practice_sessions_clinic_id_foreign', 'clinic_id', 'clinics', 'restrict'],
            'practice_sessions.doctor_schedule_id' => ['practice_sessions', 'practice_sessions_doctor_schedule_id_foreign', 'doctor_schedule_id', 'doctor_schedules', 'set null'],
            'practice_sessions.schedule_exception_id' => ['practice_sessions', 'practice_sessions_schedule_exception_id_foreign', 'schedule_exception_id', 'schedule_exceptions', 'set null'],
            'practice_sessions.reviewed_by' => ['practice_sessions', 'practice_sessions_reviewed_by_foreign', 'reviewed_by', 'users', 'set null'],
            'appointments.patient_profile_id' => ['appointments', 'appointments_patient_profile_id_foreign', 'patient_profile_id', 'patient_profiles', 'restrict'],
            'appointments.practice_session_id' => ['appointments', 'appointments_practice_session_id_foreign', 'practice_session_id', 'practice_sessions', 'restrict'],
            'appointments.doctor_id' => ['appointments', 'appointments_doctor_id_foreign', 'doctor_id', 'doctors', 'restrict'],
            'appointments.clinic_id' => ['appointments', 'appointments_clinic_id_foreign', 'clinic_id', 'clinics', 'restrict'],
            'appointments.insurance_partner_id' => ['appointments', 'appointments_insurance_partner_id_foreign', 'insurance_partner_id', 'insurance_partners', 'restrict'],
            'appointments.created_by_user_id' => ['appointments', 'appointments_created_by_user_id_foreign', 'created_by_user_id', 'users', 'set null'],
            'appointments.cancelled_by_user_id' => ['appointments', 'appointments_cancelled_by_user_id_foreign', 'cancelled_by_user_id', 'users', 'set null'],
            'appointment_status_histories.appointment_id' => ['appointment_status_histories', 'appointment_status_histories_appointment_id_foreign', 'appointment_id', 'appointments', 'cascade'],
            'appointment_status_histories.actor_user_id' => ['appointment_status_histories', 'appointment_status_histories_actor_user_id_foreign', 'actor_user_id', 'users', 'set null'],
        ];
    }

    /**
     * Pelanggaran CHECK: nama constraint dan penyisipan yang melanggarnya. Setiap kasus hanya melanggar
     * satu constraint agar nama pada pesan galat tidak ambigu.
     *
     * @return array<string, array{0: string, 1: Closure}>
     */
    public static function checkViolations(): array
    {
        return [
            'chk_schedules_time: jam terbalik' => ['chk_schedules_time', fn (self $t) => $t->insertSchedule(['start_time' => '12:00:00', 'end_time' => '08:00:00'])],
            'chk_schedules_time: jam sama' => ['chk_schedules_time', fn (self $t) => $t->insertSchedule(['start_time' => '08:00:00', 'end_time' => '08:00:00'])],
            'chk_schedules_day: hari 0' => ['chk_schedules_day', fn (self $t) => $t->insertSchedule(['day_of_week' => 0])],
            'chk_schedules_day: hari 8' => ['chk_schedules_day', fn (self $t) => $t->insertSchedule(['day_of_week' => 8])],
            'chk_schedules_quota: kuota 0' => ['chk_schedules_quota', fn (self $t) => $t->insertSchedule(['quota' => 0])],
            'chk_schedules_quota: kuota 201' => ['chk_schedules_quota', fn (self $t) => $t->insertSchedule(['quota' => 201])],
            'chk_schedules_validity: berlaku terbalik' => ['chk_schedules_validity', fn (self $t) => $t->insertSchedule(['valid_from' => '2026-10-10', 'valid_until' => '2026-10-09'])],
            'chk_exceptions_dates: rentang terbalik' => ['chk_exceptions_dates', fn (self $t) => $t->insertException(['start_date' => '2026-10-12', 'end_date' => '2026-10-11'])],
            'chk_schedule_exceptions_scope: cuti dokter tanpa dokter' => ['chk_schedule_exceptions_scope', fn (self $t) => $t->insertException(['type' => 'doctor_leave', 'doctor_id' => null])],
            'chk_schedule_exceptions_scope: libur RS berdokter' => ['chk_schedule_exceptions_scope', fn (self $t) => $t->insertException(['type' => 'hospital_holiday', 'doctor_id' => $t->doctorId()])],
            'chk_schedule_exceptions_scope: libur nasional berdokter' => ['chk_schedule_exceptions_scope', fn (self $t) => $t->insertException(['type' => 'national_holiday', 'doctor_id' => $t->doctorId()])],
            'chk_sessions_quota: terisi melebihi kuota' => ['chk_sessions_quota', fn (self $t) => $t->insertSession(['quota' => 5, 'booked_count' => 6, 'last_queue_number' => 6])],
            'chk_sessions_quota_range: kuota 0' => ['chk_sessions_quota_range', fn (self $t) => $t->insertSession(['quota' => 0])],
            'chk_sessions_quota_range: kuota 201' => ['chk_sessions_quota_range', fn (self $t) => $t->insertSession(['quota' => 201])],
            'chk_sessions_time: jam terbalik' => ['chk_sessions_time', fn (self $t) => $t->insertSession(['start_time' => '12:00:00', 'end_time' => '08:00:00'])],
            'chk_sessions_time: jam sama' => ['chk_sessions_time', fn (self $t) => $t->insertSession(['start_time' => '08:00:00', 'end_time' => '08:00:00'])],
            'chk_sessions_queue: pencacah di bawah terisi' => ['chk_sessions_queue', fn (self $t) => $t->insertSession(['quota' => 5, 'booked_count' => 2, 'last_queue_number' => 1])],
            'chk_appointments_queue: nomor antrean 0' => ['chk_appointments_queue', fn (self $t) => $t->insertAppointment(['queue_number' => 0])],
            'pending_change: bukan JSON' => ['pending_change', fn (self $t) => $t->insertSession(['pending_change' => 'bukan json'])],
        ];
    }

    // --- Pembantu skema ---

    /** @return array<string, array<string, mixed>> */
    private function columnsOf(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = $column;
        }

        return $columns;
    }

    /** MariaDB 10.4 melaporkan default NULL sebagai string "NULL" dan default string dengan kutip. */
    private function normalizedDefault(?string $default): ?string
    {
        if ($default === null || strtoupper($default) === 'NULL') {
            return null;
        }

        return trim($default, "'");
    }

    /** @return array<string, array<string, mixed>> */
    private function indexesOf(string $table): array
    {
        $indexes = [];

        foreach (Schema::getIndexes($table) as $index) {
            $indexes[$index['name']] = $index;
        }

        return $indexes;
    }

    /** @return list<string> */
    private function namedChecks(): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS constraint_name
             FROM information_schema.CHECK_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME LIKE ?
               AND TABLE_NAME IN (?, ?, ?, ?)',
            ['chk\\_%', 'doctor_schedules', 'schedule_exceptions', 'practice_sessions', 'appointments'],
        );

        $names = array_map(fn ($row): string => $row->table_name.'.'.$row->constraint_name, $rows);
        sort($names);

        return $names;
    }

    // --- Pembantu insert ---

    private function doctorId(): int
    {
        if ($this->doctorId === null) {
            $doctor = Doctor::factory()->create();
            $this->doctorId = $doctor->id;
            $this->clinicId = $doctor->clinic_id;
        }

        return $this->doctorId;
    }

    private function clinicId(): int
    {
        $this->doctorId();

        return (int) $this->clinicId;
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertUserId(array $overrides = []): int
    {
        return User::factory()->create($overrides)->id;
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertSchedule(array $overrides = []): int
    {
        return DB::table('doctor_schedules')->insertGetId(array_merge([
            'doctor_id' => $this->doctorId(),
            'clinic_id' => $this->clinicId(),
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-05',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertException(array $overrides = []): int
    {
        return DB::table('schedule_exceptions')->insertGetId(array_merge([
            'doctor_id' => null,
            'type' => 'other',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
            'reason' => 'Contoh pengecualian',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertSession(array $overrides = []): int
    {
        return DB::table('practice_sessions')->insertGetId(array_merge([
            'doctor_id' => $this->doctorId(),
            'clinic_id' => $this->clinicId(),
            'session_date' => '2026-10-12',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertPatient(array $overrides = []): int
    {
        return DB::table('patient_profiles')->insertGetId(array_merge([
            'full_name' => 'Pasien Contoh',
            'birth_date' => '1990-01-01',
            'gender' => 'L',
            'phone' => '081234567890',
            'address' => 'Jl. Contoh No. 1',
            'registration_channel' => 'online',
            'consent_at' => '2026-10-05 08:00:00',
            'consent_version' => '1.0',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertInsurancePartner(array $overrides = []): int
    {
        $this->counter++;

        return DB::table('insurance_partners')->insertGetId(array_merge([
            'name' => "Asuransi Contoh {$this->counter}",
            'slug' => "asuransi-contoh-{$this->counter}",
            'type' => 'asuransi',
        ], $overrides));
    }

    /**
     * Appointment pada sesi bersama (kuota 50). Pasien baru dibuat untuk setiap pemanggilan kecuali
     * `patient_profile_id` diberikan; nomor antrean dan kode booking dibuat unik otomatis.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function insertAppointment(array $overrides = []): int
    {
        $this->counter++;

        if ($this->sessionId === null) {
            $this->sessionId = $this->insertSession(['quota' => 50]);
        }

        return DB::table('appointments')->insertGetId(array_merge([
            'booking_code' => sprintf('UPH-%04d-TEST', $this->counter),
            'patient_profile_id' => $this->insertPatient(),
            'practice_session_id' => $this->sessionId,
            'doctor_id' => $this->doctorId(),
            'clinic_id' => $this->clinicId(),
            'session_date' => '2026-10-12',
            'queue_number' => $this->counter,
            'status' => 'pending',
            'channel' => 'online',
            'payment_type' => 'umum',
        ], $overrides));
    }

    // --- Skema: kolom, urutan, index, FK, nama berkas ---

    #[DataProvider('expectedColumns')]
    public function test_column_matches_the_data_dictionary(string $table, string $name, string $typeName, string $type, bool $nullable, ?string $default): void
    {
        $columns = $this->columnsOf($table);

        $this->assertArrayHasKey($name, $columns, "Kolom {$table}.{$name} belum ada.");
        $this->assertSame($typeName, $columns[$name]['type_name'], "type_name {$table}.{$name}");
        $this->assertSame($type, $columns[$name]['type'], "type {$table}.{$name}");
        $this->assertSame($nullable, $columns[$name]['nullable'], "nullable {$table}.{$name}");
        $this->assertSame($default, $this->normalizedDefault($columns[$name]['default']), "default {$table}.{$name}");
    }

    /** @param  list<string>  $order */
    #[DataProvider('expectedColumnOrder')]
    public function test_tables_have_exactly_the_documented_columns_in_order(string $table, array $order): void
    {
        $this->assertSame($order, Schema::getColumnListing($table));
    }

    public function test_primary_keys_are_auto_increment(): void
    {
        foreach (['patient_profiles', 'doctor_schedules', 'schedule_exceptions', 'practice_sessions', 'insurance_partners', 'appointments', 'appointment_status_histories'] as $table) {
            $this->assertTrue($this->columnsOf($table)['id']['auto_increment'], "{$table}.id auto increment");
        }
    }

    #[DataProvider('expectedIndexes')]
    public function test_index_matches_the_data_dictionary(string $table, string $name, array $columns, bool $unique, bool $primary): void
    {
        $indexes = $this->indexesOf($table);

        $this->assertArrayHasKey($name, $indexes, "Index {$table}.{$name} belum ada.");
        $this->assertSame($columns, $indexes[$name]['columns']);
        $this->assertSame($unique, $indexes[$name]['unique']);
        $this->assertSame($primary, $indexes[$name]['primary']);
    }

    #[DataProvider('expectedForeignKeys')]
    public function test_foreign_key_actions_match_the_data_dictionary(string $table, string $name, string $column, string $foreignTable, string $onDelete): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($table))->keyBy('name');

        $this->assertTrue($foreignKeys->has($name), "FK {$name} belum ada.");
        $this->assertSame([$column], $foreignKeys[$name]['columns']);
        $this->assertSame($foreignTable, $foreignKeys[$name]['foreign_table']);
        $this->assertSame(['id'], $foreignKeys[$name]['foreign_columns']);
        $this->assertSame($onDelete, $foreignKeys[$name]['on_delete']);
    }

    public function test_migrations_are_registered_under_the_documented_names(): void
    {
        $names = DB::table('migrations')->pluck('migration')->all();

        foreach ([
            '2026_10_02_000100_create_patient_profiles_table',
            '2026_10_02_000600_create_doctor_schedules_table',
            '2026_10_02_000700_create_schedule_exceptions_table',
            '2026_10_02_000800_create_practice_sessions_table',
            '2026_10_02_000900_create_insurance_partners_table',
            '2026_10_02_001000_create_appointments_table',
            '2026_10_02_001100_create_appointment_status_histories_table',
        ] as $migration) {
            $this->assertContains($migration, $names);
            $this->assertFileExists(database_path("migrations/{$migration}.php"));
        }
    }

    public function test_the_eleven_named_check_constraints_exist(): void
    {
        $this->assertSame([
            'appointments.chk_appointments_queue',
            'doctor_schedules.chk_schedules_day',
            'doctor_schedules.chk_schedules_quota',
            'doctor_schedules.chk_schedules_time',
            'doctor_schedules.chk_schedules_validity',
            'practice_sessions.chk_sessions_queue',
            'practice_sessions.chk_sessions_quota',
            'practice_sessions.chk_sessions_quota_range',
            'practice_sessions.chk_sessions_time',
            'schedule_exceptions.chk_exceptions_dates',
            'schedule_exceptions.chk_schedule_exceptions_scope',
        ], $this->namedChecks());
    }

    public function test_status_history_created_at_is_not_null_and_defaults_to_the_current_timestamp(): void
    {
        $column = $this->columnsOf('appointment_status_histories')['created_at'];

        $this->assertSame('timestamp', $column['type_name']);
        $this->assertFalse($column['nullable']);
        $this->assertStringStartsWith('current_timestamp', strtolower((string) $column['default']));
    }

    public function test_active_flag_is_a_stored_generated_column_over_the_active_statuses(): void
    {
        $column = $this->columnsOf('appointments')['active_flag'];

        $this->assertNotNull($column['generation'], 'active_flag harus kolom generated.');
        $this->assertStringContainsString('checked_in', strtolower($column['generation']['expression']));

        $create = (array) DB::selectOne('SHOW CREATE TABLE appointments');
        $ddl = (string) ($create['Create Table'] ?? '');

        $this->assertStringContainsString('GENERATED ALWAYS AS', $ddl);
        $this->assertStringContainsString('STORED', $ddl);
    }

    // --- Perilaku CHECK (errno 4025) ---

    #[DataProvider('checkViolations')]
    public function test_check_constraint_rejects_the_value_with_errno_4025(string $constraint, Closure $insert): void
    {
        try {
            $insert($this);
            $this->fail("Constraint {$constraint} seharusnya menolak penyisipan.");
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0], 'SQLSTATE');
            $this->assertSame(4025, $e->errorInfo[1], 'errno');
            $this->assertStringContainsString("`{$constraint}`", $e->getMessage());
        }
    }

    public function test_boundary_values_are_accepted_by_the_check_constraints(): void
    {
        $this->insertSchedule(['day_of_week' => 1, 'quota' => 1]);
        $this->insertSchedule(['day_of_week' => 7, 'quota' => 200, 'valid_from' => '2026-10-10', 'valid_until' => '2026-10-10']);
        $this->insertSchedule(['valid_until' => null]);
        $this->insertException(['start_date' => '2026-10-10', 'end_date' => '2026-10-10']);
        $this->insertSession(['quota' => 1, 'booked_count' => 1, 'last_queue_number' => 1]);
        $this->insertSession(['quota' => 200, 'session_date' => '2026-10-13']);
        $this->insertSession(['quota' => 5, 'booked_count' => 2, 'last_queue_number' => 2, 'session_date' => '2026-10-14']);
        $this->insertAppointment(['queue_number' => 1]);

        $this->assertSame(3, DB::table('doctor_schedules')->count());
        $this->assertSame(1, DB::table('schedule_exceptions')->count());
        $this->assertSame(4, DB::table('practice_sessions')->count());
        $this->assertSame(1, DB::table('appointments')->count());
    }

    public function test_exception_scope_accepts_the_valid_type_and_doctor_combinations_jdw18(): void
    {
        $doctorId = $this->doctorId();

        $this->insertException(['type' => 'doctor_leave', 'doctor_id' => $doctorId]);
        $this->insertException(['type' => 'hospital_holiday', 'doctor_id' => null]);
        $this->insertException(['type' => 'national_holiday', 'doctor_id' => null]);
        $this->insertException(['type' => 'other', 'doctor_id' => $doctorId]);
        $this->insertException(['type' => 'other', 'doctor_id' => null]);

        $this->assertSame(5, DB::table('schedule_exceptions')->count());
    }

    public function test_pending_change_accepts_null_and_valid_json(): void
    {
        $withJson = $this->insertSession(['pending_change' => json_encode(['source' => 'exception', 'exception_id' => 12])]);
        $withNull = $this->insertSession(['session_date' => '2026-10-13', 'pending_change' => null]);

        $this->assertSame(['source' => 'exception', 'exception_id' => 12], json_decode((string) DB::table('practice_sessions')->where('id', $withJson)->value('pending_change'), true));
        $this->assertNull(DB::table('practice_sessions')->where('id', $withNull)->value('pending_change'));
    }

    // --- Perilaku UNIQUE ---

    public function test_session_unique_accepts_many_null_schedule_ids_jdw13(): void
    {
        $this->insertSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-12']);
        $this->insertSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-12', 'start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $this->insertSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-12', 'start_time' => '16:00:00', 'end_time' => '18:00:00']);

        $this->assertSame(3, DB::table('practice_sessions')->whereNull('doctor_schedule_id')->count());
    }

    public function test_session_unique_rejects_the_same_schedule_on_the_same_date_but_not_on_another_date(): void
    {
        $scheduleId = $this->insertSchedule();
        $this->insertSession(['doctor_schedule_id' => $scheduleId, 'session_date' => '2026-10-12']);
        $this->insertSession(['doctor_schedule_id' => $scheduleId, 'session_date' => '2026-10-19']);

        try {
            $this->insertSession(['doctor_schedule_id' => $scheduleId, 'session_date' => '2026-10-12']);
            $this->fail('UNIQUE (doctor_schedule_id, session_date) seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('practice_sessions_doctor_schedule_id_session_date_unique', $e->getMessage());
        }

        $this->assertSame(2, DB::table('practice_sessions')->where('doctor_schedule_id', $scheduleId)->count());
    }

    public function test_insert_ignore_is_idempotent_on_the_session_unique_key(): void
    {
        $scheduleId = $this->insertSchedule();
        $row = [
            'doctor_id' => $this->doctorId(), 'clinic_id' => $this->clinicId(), 'doctor_schedule_id' => $scheduleId,
            'session_date' => '2026-10-12', 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'quota' => 20,
        ];

        $this->assertSame(1, DB::table('practice_sessions')->insertOrIgnore($row));
        $this->assertSame(0, DB::table('practice_sessions')->insertOrIgnore($row));
        $this->assertSame(1, DB::table('practice_sessions')->count());
    }

    public function test_patient_profile_user_id_and_patient_number_are_unique_but_allow_many_nulls(): void
    {
        $userId = $this->insertUserId();

        $this->insertPatient(['user_id' => null, 'patient_number' => null]);
        $this->insertPatient(['user_id' => null, 'patient_number' => null]);
        $this->insertPatient(['user_id' => $userId, 'patient_number' => 'P000001']);

        try {
            $this->insertPatient(['user_id' => $userId]);
            $this->fail('user_id ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('patient_profiles_user_id_unique', $e->getMessage());
        }

        try {
            $this->insertPatient(['patient_number' => 'P000001']);
            $this->fail('patient_number ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('patient_profiles_patient_number_unique', $e->getMessage());
        }

        $this->assertSame(3, DB::table('patient_profiles')->count());
    }

    public function test_insurance_partner_slug_is_unique(): void
    {
        $this->insertInsurancePartner(['slug' => 'mitra-contoh']);

        $this->expectException(QueryException::class);

        $this->insertInsurancePartner(['slug' => 'mitra-contoh']);
    }

    public function test_appointment_booking_code_is_unique_and_case_insensitive(): void
    {
        $this->insertAppointment(['booking_code' => 'UPH-ABCD-EFGH']);

        try {
            $this->insertAppointment(['booking_code' => 'uph-abcd-efgh']);
            $this->fail('Kode booking ganda (beda huruf besar/kecil) seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('appointments_booking_code_unique', $e->getMessage());
        }
    }

    public function test_appointment_queue_number_is_unique_per_session(): void
    {
        $this->insertAppointment(['queue_number' => 1]);

        try {
            $this->insertAppointment(['queue_number' => 1]);
            $this->fail('Nomor antrean ganda pada satu sesi seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('appointments_practice_session_id_queue_number_unique', $e->getMessage());
        }

        $otherSession = $this->insertSession(['session_date' => '2026-10-13']);
        $this->insertAppointment(['queue_number' => 1, 'practice_session_id' => $otherSession]);

        $this->assertSame(2, DB::table('appointments')->count());
    }

    // --- active_flag dan UNIQUE aktif (KON-01 sisi skema) ---

    public function test_active_flag_matches_the_active_statuses_for_every_enum_case(): void
    {
        $expected = array_map(fn (AppointmentStatus $status): string => $status->value, AppointmentStatus::ACTIVE);

        foreach (AppointmentStatus::cases() as $status) {
            $id = $this->insertAppointment(['status' => $status->value]);
            $flag = DB::table('appointments')->where('id', $id)->value('active_flag');

            if (in_array($status->value, $expected, true)) {
                $this->assertSame(1, (int) $flag, "active_flag untuk {$status->value}");
            } else {
                $this->assertNull($flag, "active_flag untuk {$status->value}");
            }
        }
    }

    public function test_the_database_rejects_a_value_written_to_active_flag(): void
    {
        try {
            $this->insertAppointment(['active_flag' => 1]);
            $this->fail('Menulis active_flag seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1906, $e->errorInfo[1]);
        }
    }

    public function test_a_second_active_appointment_for_the_same_patient_and_session_is_rejected(): void
    {
        $patientId = $this->insertPatient();

        foreach (['pending', 'confirmed', 'checked_in'] as $status) {
            DB::table('appointments')->delete();
            $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => $status, 'queue_number' => 1]);

            try {
                $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'pending', 'queue_number' => 2]);
                $this->fail("Appointment aktif kedua seharusnya ditolak (pertama: {$status}).");
            } catch (QueryException $e) {
                $this->assertSame(1062, $e->errorInfo[1]);
                $this->assertStringContainsString('appointments_patient_session_active_unique', $e->getMessage());
            }
        }
    }

    public function test_cancelled_and_other_final_appointments_do_not_block_a_new_active_one(): void
    {
        $patientId = $this->insertPatient();

        $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'cancelled', 'queue_number' => 1]);
        $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'cancelled', 'queue_number' => 2]);
        $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'no_show', 'queue_number' => 3]);
        $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'pending', 'queue_number' => 4]);

        $this->assertSame(4, DB::table('appointments')->where('patient_profile_id', $patientId)->count());
        $this->assertSame(1, DB::table('appointments')->where('patient_profile_id', $patientId)->whereNotNull('active_flag')->count());
    }

    public function test_cancelling_the_active_appointment_frees_the_patient_to_book_again(): void
    {
        $patientId = $this->insertPatient();
        $first = $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'confirmed', 'queue_number' => 1]);

        DB::table('appointments')->where('id', $first)->update(['status' => 'cancelled']);
        $this->assertNull(DB::table('appointments')->where('id', $first)->value('active_flag'));

        $this->insertAppointment(['patient_profile_id' => $patientId, 'status' => 'pending', 'queue_number' => 2]);

        $this->assertSame(1, DB::table('appointments')->where('patient_profile_id', $patientId)->whereNotNull('active_flag')->count());
    }

    // --- Perilaku FK ---

    public function test_deleting_a_user_sets_patient_profile_and_schedule_links_to_null(): void
    {
        $userId = $this->insertUserId();
        $patientId = $this->insertPatient(['user_id' => $userId, 'created_by_user_id' => $userId]);
        $scheduleId = $this->insertSchedule(['created_by' => $userId, 'updated_by' => $userId]);
        $exceptionId = $this->insertException(['created_by' => $userId]);
        $sessionId = $this->insertSession(['reviewed_by' => $userId]);

        DB::table('users')->where('id', $userId)->delete();

        $profile = DB::table('patient_profiles')->find($patientId);
        $this->assertNull($profile->user_id);
        $this->assertNull($profile->created_by_user_id);

        $schedule = DB::table('doctor_schedules')->find($scheduleId);
        $this->assertNull($schedule->created_by);
        $this->assertNull($schedule->updated_by);
        $this->assertNull(DB::table('schedule_exceptions')->where('id', $exceptionId)->value('created_by'));
        $this->assertNull(DB::table('practice_sessions')->where('id', $sessionId)->value('reviewed_by'));
    }

    public function test_deleting_a_user_sets_appointment_and_history_actor_links_to_null(): void
    {
        $userId = $this->insertUserId();
        $appointmentId = $this->insertAppointment(['created_by_user_id' => $userId, 'cancelled_by_user_id' => $userId]);
        $historyId = DB::table('appointment_status_histories')->insertGetId([
            'appointment_id' => $appointmentId, 'to_status' => 'pending', 'actor_type' => 'staff', 'actor_user_id' => $userId,
        ]);

        DB::table('users')->where('id', $userId)->delete();

        $appointment = DB::table('appointments')->find($appointmentId);
        $this->assertNull($appointment->created_by_user_id);
        $this->assertNull($appointment->cancelled_by_user_id);
        $this->assertNull(DB::table('appointment_status_histories')->where('id', $historyId)->value('actor_user_id'));
    }

    public function test_a_doctor_with_a_schedule_cannot_be_deleted(): void
    {
        $doctorId = Doctor::factory()->create()->id;
        $clinicId = Clinic::factory()->create()->id;
        $this->insertSchedule(['doctor_id' => $doctorId, 'clinic_id' => $clinicId]);

        try {
            DB::table('doctors')->where('id', $doctorId)->delete();
            $this->fail('FK doctor_schedules.doctor_id RESTRICT seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('doctor_schedules_doctor_id_foreign', $e->getMessage());
        }
    }

    public function test_a_clinic_with_a_schedule_cannot_be_deleted(): void
    {
        $clinicId = Clinic::factory()->create()->id;
        $this->insertSchedule(['clinic_id' => $clinicId]);

        try {
            DB::table('clinics')->where('id', $clinicId)->delete();
            $this->fail('FK doctor_schedules.clinic_id RESTRICT seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('doctor_schedules_clinic_id_foreign', $e->getMessage());
        }
    }

    public function test_a_doctor_with_an_exception_cannot_be_deleted(): void
    {
        $doctorId = Doctor::factory()->create()->id;
        $this->insertException(['type' => 'doctor_leave', 'doctor_id' => $doctorId]);

        try {
            DB::table('doctors')->where('id', $doctorId)->delete();
            $this->fail('FK schedule_exceptions.doctor_id RESTRICT seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('schedule_exceptions_doctor_id_foreign', $e->getMessage());
        }
    }

    public function test_deleting_a_schedule_or_exception_sets_the_session_links_to_null(): void
    {
        $scheduleId = $this->insertSchedule();
        $exceptionId = $this->insertException();
        $sessionId = $this->insertSession(['doctor_schedule_id' => $scheduleId, 'schedule_exception_id' => $exceptionId, 'status' => 'cancelled']);

        DB::table('schedule_exceptions')->where('id', $exceptionId)->delete();
        $this->assertNull(DB::table('practice_sessions')->where('id', $sessionId)->value('schedule_exception_id'));
        $this->assertSame($scheduleId, (int) DB::table('practice_sessions')->where('id', $sessionId)->value('doctor_schedule_id'));

        DB::table('doctor_schedules')->where('id', $scheduleId)->delete();
        $this->assertNull(DB::table('practice_sessions')->where('id', $sessionId)->value('doctor_schedule_id'));
        $this->assertSame(1, DB::table('practice_sessions')->where('id', $sessionId)->count());
    }

    public function test_a_session_with_an_appointment_cannot_be_deleted(): void
    {
        $this->insertAppointment();

        try {
            DB::table('practice_sessions')->where('id', $this->sessionId)->delete();
            $this->fail('FK appointments.practice_session_id RESTRICT seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('appointments_practice_session_id_foreign', $e->getMessage());
        }
    }

    public function test_a_patient_profile_with_an_appointment_cannot_be_deleted(): void
    {
        $patientId = $this->insertPatient();
        $this->insertAppointment(['patient_profile_id' => $patientId]);

        try {
            DB::table('patient_profiles')->where('id', $patientId)->delete();
            $this->fail('FK appointments.patient_profile_id RESTRICT seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('appointments_patient_profile_id_foreign', $e->getMessage());
        }
    }

    public function test_an_insurance_partner_used_by_an_appointment_cannot_be_deleted(): void
    {
        $partnerId = $this->insertInsurancePartner();
        $this->insertAppointment(['payment_type' => 'asuransi', 'insurance_partner_id' => $partnerId]);

        try {
            DB::table('insurance_partners')->where('id', $partnerId)->delete();
            $this->fail('FK appointments.insurance_partner_id RESTRICT seharusnya menolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('appointments_insurance_partner_id_foreign', $e->getMessage());
        }
    }

    public function test_deleting_an_appointment_cascades_to_its_status_history(): void
    {
        $appointmentId = $this->insertAppointment();
        DB::table('appointment_status_histories')->insert([
            ['appointment_id' => $appointmentId, 'from_status' => null, 'to_status' => 'pending', 'actor_type' => 'patient'],
            ['appointment_id' => $appointmentId, 'from_status' => 'pending', 'to_status' => 'confirmed', 'actor_type' => 'staff'],
        ]);

        DB::table('appointments')->where('id', $appointmentId)->delete();

        $this->assertSame(0, DB::table('appointment_status_histories')->count());
    }

    // --- Nilai bawaan ---

    public function test_column_defaults_are_applied_by_the_database(): void
    {
        $scheduleId = $this->insertSchedule();
        $sessionId = $this->insertSession();
        $patientId = $this->insertPatient();
        $partnerId = $this->insertInsurancePartner();
        $appointmentId = $this->insertAppointment();
        $historyId = DB::table('appointment_status_histories')->insertGetId([
            'appointment_id' => $appointmentId, 'to_status' => 'pending', 'actor_type' => 'system',
        ]);

        $this->assertSame(1, (int) DB::table('doctor_schedules')->find($scheduleId)->is_active);

        $session = DB::table('practice_sessions')->find($sessionId);
        $this->assertSame([0, 0, 'open', 0], [(int) $session->booked_count, (int) $session->last_queue_number, $session->status, (int) $session->is_manually_modified]);
        $this->assertNull($session->pending_change);
        $this->assertNull($session->doctor_schedule_id);

        $this->assertSame(1, (int) DB::table('patient_profiles')->find($patientId)->is_active);

        $partner = DB::table('insurance_partners')->find($partnerId);
        $this->assertSame([1, 0], [(int) $partner->is_active, (int) $partner->sort_order]);

        $this->assertNotNull(DB::table('appointment_status_histories')->find($historyId)->created_at);
    }
}
