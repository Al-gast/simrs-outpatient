-- Local dummy UAT only. Run while all writers stopped, before and after migration.
-- Compare row counts and deterministic hashes; no raw patient/user/payment rows exported.
-- Exclude Yii migration history (expected to change) and login_attempts (not business data).
BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;
SET LOCAL TIME ZONE 'UTC';
SELECT format('SELECT %L, count(*), md5(coalesce(string_agg(h, %L ORDER BY h), %L)) FROM (SELECT md5(to_jsonb(t)::text) AS h FROM %I.%I AS t) AS hashes;',tablename,'','',schemaname,tablename)
FROM pg_tables WHERE schemaname='public' AND tablename NOT IN ('migration','login_attempts') ORDER BY tablename
\gexec
COMMIT;
