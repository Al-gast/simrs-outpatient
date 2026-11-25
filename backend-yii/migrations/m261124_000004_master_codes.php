<?php
use yii\db\Migration;

/** Additive only: existing codes, visits and ledger rows are untouched. */
final class m261124_000004_master_codes extends Migration
{
    private const SEQUENCES=['staff_code_seq','clinic_code_seq','procedure_code_seq','medicine_code_seq','lab_test_code_seq'];
    public function safeUp()
    {
        foreach(self::SEQUENCES as $seq){$this->execute("CREATE SEQUENCE $seq AS bigint START WITH 1");$this->execute("GRANT USAGE, SELECT ON SEQUENCE $seq TO simrs_core");}
    }
    public function safeDown()
    {
        // Refuse to rewind issued identifiers. Fresh, unused QA databases can roll back.
        foreach(self::SEQUENCES as $seq)if(\app\components\RolePolicy::active($this->db->createCommand("SELECT is_called FROM $seq")->queryScalar()))return false;
        foreach(self::SEQUENCES as $seq)$this->execute("DROP SEQUENCE $seq");
    }
}
