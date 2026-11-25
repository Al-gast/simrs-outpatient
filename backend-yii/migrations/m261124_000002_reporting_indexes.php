<?php
use yii\db\Migration;
final class m261124_000002_reporting_indexes extends Migration
{
    public function safeUp()
    {
        $this->execute('CREATE INDEX idx_audit_date_id ON audit_logs(created_at DESC,id DESC)');
        $this->execute('CREATE INDEX idx_audit_actor_date ON audit_logs(actor_id,created_at DESC,id DESC)');
        $this->execute("CREATE INDEX idx_stock_dispense_date ON stock_movements(created_at,id) WHERE kind='dispense'");
        // Preserve view column names/types and existing SELECT-only grants.
        $this->execute("CREATE OR REPLACE VIEW reporting.invoice_status AS SELECT i.id,v.visit_date,i.total_amount,coalesce(p.paid,0)::bigint AS paid_amount,CASE WHEN coalesce(p.paid,0) >= i.total_amount THEN 'paid' WHEN coalesce(p.paid,0)>0 THEN 'partial' ELSE 'unpaid' END AS status FROM invoices i JOIN visits v ON v.id=i.visit_id LEFT JOIN LATERAL (SELECT sum(amount) AS paid FROM payments WHERE invoice_id=i.id) p ON true");
    }
    public function safeDown()
    {
        $this->execute("CREATE OR REPLACE VIEW reporting.invoice_status AS SELECT i.id,v.visit_date,i.total_amount,coalesce(p.paid,0)::bigint AS paid_amount,CASE WHEN coalesce(p.paid,0) >= i.total_amount THEN 'paid' WHEN coalesce(p.paid,0)>0 THEN 'partial' ELSE 'unpaid' END AS status FROM invoices i JOIN visits v ON v.id=i.visit_id LEFT JOIN (SELECT invoice_id,sum(amount) AS paid FROM payments GROUP BY invoice_id) p ON p.invoice_id=i.id");
        foreach(['idx_stock_dispense_date','idx_audit_actor_date','idx_audit_date_id'] as $name)$this->execute('DROP INDEX '.$name);
    }
}
