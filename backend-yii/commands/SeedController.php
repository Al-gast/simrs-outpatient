<?php
namespace app\commands;
use yii\db\Query;
final class SeedController extends \yii\console\Controller
{
    public function actionIndex(): int
    {
        $prefix=getenv('DEMO_PASSWORD_PREFIX') ?: '';
        if (strlen($prefix)<8 || strlen($prefix)>48 || str_starts_with($prefix,'replace-')) { $this->stderr("Set DEMO_PASSWORD_PREFIX via setup-env.sh.\n"); return 1; }
        $db=\Yii::$app->db;
        $db->transaction(function () use ($db,$prefix) {
            $db->createCommand("INSERT INTO clinics(code,name,registration_fee) VALUES ('UMUM','Poli Umum',25000),('GIGI','Poli Gigi',30000) ON CONFLICT (code) DO NOTHING")->execute();
            $db->createCommand("INSERT INTO medical_staff(code,name,profession) VALUES ('D001','dr. Andi Pratama (Dummy)','doctor'),('N001','Nina (Dummy)','nurse') ON CONFLICT (code) DO NOTHING")->execute();
            $staff=(new Query())->select('id')->from('medical_staff')->where(['code'=>'D001'])->scalar();
            $clinic=(new Query())->select('id')->from('clinics')->where(['code'=>'UMUM'])->scalar();
            foreach (range(1,7) as $day) $db->createCommand('INSERT INTO doctor_schedules(doctor_id,clinic_id,weekday,start_time,end_time) VALUES (:d,:c,:w,\'08:00\',\'16:00\') ON CONFLICT (doctor_id,clinic_id,weekday,start_time) DO NOTHING',[':d'=>$staff,':c'=>$clinic,':w'=>$day])->execute();
            $names=['admin'=>'Administrator','registration'=>'Petugas Pendaftaran','doctor'=>'dr. Andi Pratama','pharmacy'=>'Petugas Farmasi','laboratory'=>'Petugas Laboratorium','cashier'=>'Kasir'];
            foreach ($names as $role=>$name) {
                $db->createCommand('INSERT INTO users(username,password_hash,name,role,staff_id) VALUES (:u,:p,:n,:r,:s) ON CONFLICT (username) DO NOTHING',[':u'=>$role,':p'=>password_hash($prefix.'-'.$role,PASSWORD_DEFAULT),':n'=>$name,':r'=>$role,':s'=>$role==='doctor'?$staff:null])->execute();
            }
            $db->createCommand("INSERT INTO patients(medical_record_no,name,birth_date,sex) SELECT 'RM-'||lpad(n::text,greatest(6,length(n::text)),'0'),'Budi Santoso (Dummy)','1992-05-12','M' FROM (SELECT nextval('medical_record_no_seq') AS n WHERE NOT EXISTS (SELECT 1 FROM patients WHERE name='Budi Santoso (Dummy)')) AS seq")->execute();
            $db->createCommand("INSERT INTO procedures(code,name,price) VALUES ('T001','Konsultasi',100000),('T002','Pemeriksaan umum',50000) ON CONFLICT (code) DO NOTHING")->execute();
            $db->createCommand("INSERT INTO medicines(code,name,unit,price) VALUES ('OB001','Obat A (Dummy)','tablet',2500),('OB002','Obat B (Dummy)','tablet',5000) ON CONFLICT (code) DO NOTHING")->execute();
            $db->createCommand("INSERT INTO lab_tests(code,name,unit,price) VALUES ('L001','Darah lengkap',NULL,150000) ON CONFLICT (code) DO NOTHING")->execute();
            $admin=(new Query())->select('id')->from('users')->where(['username'=>'admin'])->scalar();
            $db->createCommand("WITH updated AS (UPDATE medicines m SET stock=stock+100 WHERE code IN ('OB001','OB002') AND NOT EXISTS (SELECT 1 FROM stock_movements s WHERE s.medicine_id=m.id) AND stock=0 RETURNING id,stock) INSERT INTO stock_movements(medicine_id,kind,quantity_delta,balance_after,reason,actor_id) SELECT id,'opening',100,stock,'Dummy opening stock',:admin FROM updated",[':admin'=>$admin])->execute();
        });
        $this->stdout("Dummy seed complete; existing accounts not overwritten.\n"); return 0;
    }
}
