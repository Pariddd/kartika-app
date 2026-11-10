-- Radar Narasi — setup database PostgreSQL (lokal dan server)
-- Diuji pada PostgreSQL 18.
--
-- Cara pakai:
--   1. Jalankan BAGIAN A sebagai superuser (postgres), tersambung ke database "postgres".
--   2. Jalankan "php artisan migrate" dari folder web/ (memakai user radar_web).
--   3. Jalankan BAGIAN B sebagai superuser (postgres), tersambung ke database "radar_narasi".
--   4. Jalankan BAGIAN C untuk menguji.
--   Cek encoding: SHOW server_encoding;  -- harus UTF8
--
-- PENTING: setiap kali menjalankan "php artisan migrate:fresh", ulangi BAGIAN B.
-- migrate:fresh membuat ulang tabel; hak default (ALTER DEFAULT PRIVILEGES) memang
-- otomatis berlaku lagi, tetapi REVOKE untuk tabel internal Laravel ikut hilang.
--
-- Sebelum setiap bagian, pastikan:  SELECT current_user, current_database();
-- Ganti kedua password. Password asli hanya disimpan di web/.env dan engine/.env.

------------------------------------------------------------
-- BAGIAN A — user dan database
-- Harus: current_user = postgres, current_database = postgres
------------------------------------------------------------
CREATE ROLE radar_web    LOGIN PASSWORD 'GANTI_PASSWORD_WEB';
CREATE ROLE radar_engine LOGIN PASSWORD 'GANTI_PASSWORD_ENGINE';
-- ENCODING 'UTF8' wajib: tanpa ini PostgreSQL di Windows memakai WIN1252 dan tidak bisa menyimpan emoji.
-- LC_COLLATE/LC_CTYPE 'C' dipakai karena locale Windows ("English_United States.1252") tidak cocok dengan UTF8.
CREATE DATABASE radar_narasi OWNER radar_web
  ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C' TEMPLATE template0;   -- butuh Auto-Commit aktif di DBeaver

------------------------------------------------------------
-- BAGIAN B — hak akses radar_engine (setelah migrate)
-- Harus: current_user = postgres, current_database = radar_narasi
-- Peringatan "no privileges were granted" = dijalankan oleh user yang salah.
------------------------------------------------------------
GRANT CONNECT ON DATABASE radar_narasi TO radar_engine;
GRANT USAGE ON SCHEMA public TO radar_engine;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;

-- Tabel dan sequence yang sudah ada
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO radar_engine;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO radar_engine;

-- Tabel yang nanti dibuat migration Laravel (radar_web)
ALTER DEFAULT PRIVILEGES FOR ROLE radar_web IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO radar_engine;
ALTER DEFAULT PRIVILEGES FOR ROLE radar_web IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO radar_engine;

-- Tabel internal Laravel yang tidak boleh disentuh Python.
-- Tambahkan di sini jika ada migration baru yang membuat tabel khusus Laravel.
-- Tabel yang belum ada dilewati, jadi blok ini aman dijalankan kapan saja.
DO $$
DECLARE
  t text;
BEGIN
  FOREACH t IN ARRAY ARRAY['users', 'password_reset_tokens', 'sessions', 'cache',
                           'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
                           'migrations', 'audit_logs']
  LOOP
    IF to_regclass('public.' || t) IS NOT NULL THEN
      EXECUTE format('REVOKE ALL ON public.%I FROM radar_engine', t);
    ELSE
      RAISE NOTICE 'Tabel % belum ada, dilewati', t;
    END IF;
  END LOOP;
END $$;

------------------------------------------------------------
-- BAGIAN C — uji (jalankan baris per baris)
-- Harus: current_user = postgres, current_database = radar_narasi
------------------------------------------------------------
SET ROLE radar_engine;
SELECT current_user;                            -- radar_engine
SELECT count(*) FROM posts;                     -- berhasil
SELECT has_table_privilege('posts', 'INSERT');  -- true
SELECT count(*) FROM users;                     -- GAGAL: permission denied (benar)
CREATE TABLE uji_coba (id int);                 -- GAGAL: permission denied (benar)
RESET ROLE;                                     -- WAJIB, jangan lupa
SELECT current_user;                            -- postgres
