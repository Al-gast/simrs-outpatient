-- Phase 1 design baseline; apply only to a fresh PostgreSQL database.
-- Phase 2 will convert this into Yii migrations and provision read-only grants.
BEGIN;
CREATE SEQUENCE medical_record_no_seq;
CREATE SEQUENCE invoice_no_seq;
CREATE SEQUENCE receipt_no_seq;
CREATE TABLE clinics (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code text NOT NULL UNIQUE, name text NOT NULL, registration_fee bigint NOT NULL CHECK (registration_fee >= 0), active boolean NOT NULL DEFAULT true
);
CREATE TABLE medical_staff (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code text NOT NULL UNIQUE, name text NOT NULL, profession text NOT NULL CHECK (profession IN ('doctor','nurse','other')), active boolean NOT NULL DEFAULT true
);
CREATE TABLE users (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, username text NOT NULL UNIQUE CHECK (username = lower(username)), password_hash text NOT NULL, name text NOT NULL,
 role text NOT NULL CHECK (role IN ('admin','registration','doctor','pharmacy','laboratory','cashier')), staff_id bigint UNIQUE REFERENCES medical_staff(id), active boolean NOT NULL DEFAULT true, token_version integer NOT NULL DEFAULT 0,
 CHECK (role <> 'doctor' OR staff_id IS NOT NULL)
);
CREATE TABLE patients (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, medical_record_no text NOT NULL UNIQUE, identity_no text UNIQUE, name text NOT NULL, birth_date date NOT NULL, sex text NOT NULL CHECK (sex IN ('M','F','U')), phone text, address text, active boolean NOT NULL DEFAULT true, created_at timestamptz NOT NULL DEFAULT now(), CHECK (identity_no IS NULL OR length(trim(identity_no)) > 0)
);
CREATE TABLE doctor_schedules (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, doctor_id bigint NOT NULL REFERENCES medical_staff(id), clinic_id bigint NOT NULL REFERENCES clinics(id), weekday smallint NOT NULL CHECK (weekday BETWEEN 1 AND 7), start_time time NOT NULL, end_time time NOT NULL, active boolean NOT NULL DEFAULT true, CHECK (start_time < end_time), UNIQUE (doctor_id,clinic_id,weekday,start_time), UNIQUE (id,doctor_id,clinic_id)
);
CREATE TABLE procedures (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code text NOT NULL UNIQUE, name text NOT NULL, price bigint NOT NULL CHECK (price >= 0), active boolean NOT NULL DEFAULT true
);
CREATE TABLE medicines (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code text NOT NULL UNIQUE, name text NOT NULL, unit text NOT NULL, stock integer NOT NULL DEFAULT 0 CHECK (stock >= 0), price bigint NOT NULL CHECK (price >= 0), active boolean NOT NULL DEFAULT true
);
CREATE TABLE lab_tests (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code text NOT NULL UNIQUE, name text NOT NULL, unit text, price bigint NOT NULL CHECK (price >= 0), active boolean NOT NULL DEFAULT true
);
CREATE TABLE queue_counters (
 clinic_id bigint NOT NULL REFERENCES clinics(id), visit_date date NOT NULL, last_number integer NOT NULL CHECK (last_number > 0), PRIMARY KEY (clinic_id,visit_date)
);
CREATE TABLE visits (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, patient_id bigint NOT NULL REFERENCES patients(id), doctor_id bigint NOT NULL REFERENCES medical_staff(id), clinic_id bigint NOT NULL REFERENCES clinics(id), schedule_id bigint NOT NULL,
 visit_date date NOT NULL, queue_number integer NOT NULL CHECK (queue_number > 0), registration_fee bigint NOT NULL CHECK (registration_fee >= 0), status text NOT NULL DEFAULT 'waiting' CHECK (status IN ('waiting','in_exam','services_pending','ready_for_billing','completed')),
 registered_by bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
 FOREIGN KEY (schedule_id,doctor_id,clinic_id) REFERENCES doctor_schedules(id,doctor_id,clinic_id), UNIQUE (patient_id,clinic_id,visit_date), UNIQUE (clinic_id,visit_date,queue_number)
);
CREATE TABLE medical_records (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, visit_id bigint NOT NULL UNIQUE REFERENCES visits(id), complaint text NOT NULL, anamnesis text NOT NULL, vital_signs jsonb NOT NULL CHECK (jsonb_typeof(vital_signs) = 'object'), physical_exam text NOT NULL, primary_diagnosis text NOT NULL, secondary_diagnoses jsonb NOT NULL DEFAULT '[]' CHECK (jsonb_typeof(secondary_diagnoses) = 'array'), version integer NOT NULL DEFAULT 1 CHECK (version > 0), updated_by bigint NOT NULL REFERENCES users(id), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE visit_procedures (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, visit_id bigint NOT NULL REFERENCES visits(id), procedure_id bigint NOT NULL REFERENCES procedures(id), name_snapshot text NOT NULL, unit_price bigint NOT NULL CHECK (unit_price >= 0), quantity integer NOT NULL CHECK (quantity > 0), UNIQUE (visit_id,procedure_id)
);
CREATE TABLE prescriptions (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, visit_id bigint NOT NULL UNIQUE REFERENCES visits(id), status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','dispensed')), created_by bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(), dispensed_by bigint REFERENCES users(id), dispensed_at timestamptz,
 CHECK ((status = 'dispensed') = (dispensed_by IS NOT NULL AND dispensed_at IS NOT NULL))
);
CREATE TABLE prescription_items (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, prescription_id bigint NOT NULL REFERENCES prescriptions(id), medicine_id bigint NOT NULL REFERENCES medicines(id), name_snapshot text NOT NULL, dosage text NOT NULL, frequency text NOT NULL, quantity integer NOT NULL CHECK (quantity > 0), unit_price bigint NOT NULL CHECK (unit_price >= 0), UNIQUE (prescription_id,medicine_id)
);
CREATE TABLE stock_movements (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, medicine_id bigint NOT NULL REFERENCES medicines(id), prescription_item_id bigint UNIQUE REFERENCES prescription_items(id), kind text NOT NULL CHECK (kind IN ('opening','adjustment','dispense')), quantity_delta integer NOT NULL CHECK (quantity_delta <> 0), balance_after integer NOT NULL CHECK (balance_after >= 0), reason text NOT NULL, actor_id bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(), CHECK ((kind = 'dispense' AND quantity_delta < 0 AND prescription_item_id IS NOT NULL) OR (kind <> 'dispense' AND prescription_item_id IS NULL)), CHECK (kind <> 'opening' OR quantity_delta > 0)
);
CREATE TABLE lab_orders (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, visit_id bigint NOT NULL REFERENCES visits(id), status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','completed')), created_by bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE lab_order_items (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, lab_order_id bigint NOT NULL REFERENCES lab_orders(id), lab_test_id bigint NOT NULL REFERENCES lab_tests(id), name_snapshot text NOT NULL, unit_price bigint NOT NULL CHECK (unit_price >= 0), result_text text, resulted_by bigint REFERENCES users(id), resulted_at timestamptz, UNIQUE (lab_order_id,lab_test_id), CHECK ((result_text IS NULL AND resulted_by IS NULL AND resulted_at IS NULL) OR (result_text IS NOT NULL AND resulted_by IS NOT NULL AND resulted_at IS NOT NULL))
);
CREATE TABLE lab_result_events (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, event_key uuid NOT NULL UNIQUE, lab_order_item_id bigint NOT NULL REFERENCES lab_order_items(id), payload_hash text NOT NULL, actor_id bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE invoices (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, visit_id bigint NOT NULL UNIQUE REFERENCES visits(id), invoice_no text NOT NULL UNIQUE, total_amount bigint NOT NULL CHECK (total_amount >= 0), revision integer NOT NULL DEFAULT 1 CHECK (revision > 0), created_by bigint NOT NULL REFERENCES users(id), created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE invoice_items (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, invoice_id bigint NOT NULL REFERENCES invoices(id), category text NOT NULL CHECK (category IN ('registration','procedure','laboratory','medicine')), source_id bigint NOT NULL, description text NOT NULL, unit_price bigint NOT NULL CHECK (unit_price >= 0), quantity integer NOT NULL CHECK (quantity > 0), UNIQUE (invoice_id,category,source_id)
);
CREATE TABLE payments (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, invoice_id bigint NOT NULL REFERENCES invoices(id), receipt_no text NOT NULL UNIQUE, idempotency_key uuid NOT NULL UNIQUE, payload_hash text NOT NULL, amount bigint NOT NULL CHECK (amount > 0), method text NOT NULL CHECK (method IN ('cash','non_cash')), cashier_id bigint NOT NULL REFERENCES users(id), paid_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE audit_logs (
 id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, actor_id bigint NOT NULL REFERENCES users(id), action text NOT NULL, entity_type text NOT NULL, entity_id bigint NOT NULL, before_data jsonb, after_data jsonb, reason text, request_id text, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_visits_date_clinic ON visits(visit_date,clinic_id);
CREATE INDEX idx_visits_doctor_queue ON visits(doctor_id,visit_date,status,queue_number);
CREATE INDEX idx_visits_patient_history ON visits(patient_id,visit_date DESC,id DESC);
CREATE INDEX idx_patients_name ON patients(lower(name) text_pattern_ops);
CREATE INDEX idx_schedules_clinic_weekday ON doctor_schedules(clinic_id,weekday,active);
CREATE INDEX idx_prescriptions_status ON prescriptions(status,id);
CREATE INDEX idx_lab_orders_status ON lab_orders(status,id);
CREATE INDEX idx_lab_orders_visit ON lab_orders(visit_id);
CREATE INDEX idx_payments_invoice ON payments(invoice_id);
CREATE INDEX idx_payments_date ON payments(paid_at,invoice_id);
CREATE INDEX idx_stock_movements_date ON stock_movements(created_at,medicine_id);
CREATE INDEX idx_audit_entity ON audit_logs(entity_type,entity_id,created_at);
-- Read-model views, exposed to Node through SELECT grants on these views only.
CREATE SCHEMA reporting;
CREATE VIEW reporting.visits AS SELECT v.id,v.visit_date,v.clinic_id,c.name AS clinic_name,v.status FROM visits v JOIN clinics c ON c.id=v.clinic_id;
CREATE VIEW reporting.procedures AS SELECT v.visit_date,p.id,p.procedure_id,p.name_snapshot,p.quantity FROM visit_procedures p JOIN visits v ON v.id=p.visit_id;
CREATE VIEW reporting.revenue AS SELECT id,invoice_id,paid_at,amount,method FROM payments;
CREATE VIEW reporting.medicine_usage AS SELECT s.id,s.created_at,s.medicine_id,p.name_snapshot AS medicine_name,-s.quantity_delta AS quantity FROM stock_movements s JOIN prescription_items p ON p.id=s.prescription_item_id WHERE s.kind='dispense';
CREATE VIEW reporting.invoice_status AS SELECT i.id,v.visit_date,i.total_amount,coalesce(p.paid,0)::bigint AS paid_amount,CASE WHEN coalesce(p.paid,0) >= i.total_amount THEN 'paid' WHEN coalesce(p.paid,0)>0 THEN 'partial' ELSE 'unpaid' END AS status FROM invoices i JOIN visits v ON v.id=i.visit_id LEFT JOIN (SELECT invoice_id,sum(amount) AS paid FROM payments GROUP BY invoice_id) p ON p.invoice_id=i.id;
COMMIT;
