<?php
use yii\db\Migration;
final class m261124_000001_foundation extends Migration
{
    public function safeUp()
    {
        $path = dirname(__DIR__).'/database/schema.sql';
        if (!is_file($path)) $path=dirname(__DIR__,2).'/database/schema.sql';
        $sql=file_get_contents($path);
        if ($sql === false) throw new RuntimeException('Schema baseline missing');
        // Yii manages the transaction. Remove only standalone outer markers.
        $sql=preg_replace('/^\s*(BEGIN|COMMIT);\s*$/mi','',$sql);
        $this->db->pdo->exec($sql);
        $this->execute('CREATE TABLE login_attempts (bucket text PRIMARY KEY, window_start timestamptz NOT NULL, attempts integer NOT NULL CHECK (attempts > 0))');
        $this->execute('CREATE INDEX idx_login_attempts_window ON login_attempts(window_start)');
        $this->execute('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO simrs_core');
        $this->execute('REVOKE ALL ON TABLE migration FROM simrs_core');
        $this->execute('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO simrs_core');
        $this->execute('REVOKE ALL ON SCHEMA reporting FROM PUBLIC');
        $this->execute('GRANT USAGE ON SCHEMA reporting TO simrs_report');
        $this->execute('GRANT SELECT ON reporting.visits, reporting.procedures, reporting.revenue, reporting.medicine_usage, reporting.invoice_status TO simrs_report');
    }
    public function safeDown() { echo "Irreversible baseline; back up and use a new database.\n"; return false; }
}
