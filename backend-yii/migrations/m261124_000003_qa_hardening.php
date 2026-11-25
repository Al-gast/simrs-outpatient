<?php
use yii\db\Migration;
final class m261124_000003_qa_hardening extends Migration
{
    public function safeUp()
    {
        // The runtime role is not table owner; these ledgers must be append-only.
        $this->execute('REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs, payments, stock_movements, lab_result_events FROM simrs_core');
        $this->execute("CREATE TABLE stock_adjustment_requests (event_key uuid PRIMARY KEY, medicine_id bigint NOT NULL REFERENCES medicines(id), actor_id bigint NOT NULL REFERENCES users(id), payload_hash text NOT NULL, movement_id bigint NOT NULL UNIQUE REFERENCES stock_movements(id), created_at timestamptz NOT NULL DEFAULT now())");
        $this->execute('GRANT SELECT, INSERT ON stock_adjustment_requests TO simrs_core');
        $this->execute("CREATE INDEX idx_visits_billing_queue ON visits(visit_date DESC,id DESC) WHERE status IN ('ready_for_billing','completed')");
    }
    public function safeDown()
    {
        // Refuse a destructive rollback once the retry ledger contains data.
        if ((int)$this->db->createCommand('SELECT count(*) FROM stock_adjustment_requests')->queryScalar() > 0) return false;
        $this->execute('DROP TABLE stock_adjustment_requests');
        $this->execute('DROP INDEX idx_visits_billing_queue');
        $this->execute('GRANT UPDATE, DELETE ON audit_logs, payments, stock_movements, lab_result_events TO simrs_core');
    }
}
