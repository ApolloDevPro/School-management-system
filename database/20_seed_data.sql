USE ssms;
SET NAMES utf8mb4;

INSERT INTO schools (name,motto,ownership_type,currency,currency_symbol,date_format,timezone)
VALUES ('Demo Secondary School','Excellence Through Hard Work','private','UGX','Ugx','d/m/Y','Africa/Kampala');

SET @school_id = (SELECT id FROM schools ORDER BY id LIMIT 1);

INSERT INTO roles (school_id,name,slug,description,is_system) VALUES
(@school_id,'Administrator','administrator','Full access to every module and setting',1),
(@school_id,'Head Teacher','head_teacher','Head of school: approves key decisions across modules',1),
(@school_id,'Deputy Head Teacher','deputy_head_teacher','Assists the head teacher in administration and discipline',1),
(@school_id,'Director of Studies','director_of_studies','Academic planning, timetabling, examinations and performance',1),
(@school_id,'Teacher','teacher','Records attendance, marks and continuous assessment',1),
(@school_id,'Bursar','bursar','Fees, payments, expenditure approvals and financial reports',1),
(@school_id,'Accountant','accountant','Records transactions, reconciliation and reporting',1),
(@school_id,'Registrar','registrar','Admission, student records, promotion and transfers',1),
(@school_id,'Librarian','librarian','Library circulation, stock and fines',1),
(@school_id,'Nurse','nurse','Health records, sick bay and welfare (restricted data)',1),
(@school_id,'Boarding Warden','warden','Dormitories, bed allocation, roll calls and exeats',1),
(@school_id,'Gate Officer','gate_officer','Visitors, gate passes, exeat verification and vehicle log',1),
(@school_id,'HR Officer','hr_officer','Staff records, contracts, leave and payroll preparation',1),
(@school_id,'Storekeeper','storekeeper','Stock, requisitions, purchase orders and assets',1),
(@school_id,'Parent','parent','Portal access to fees, results, attendance and messages',1),
(@school_id,'Student','student','Portal access to timetable, homework, results and notices',1);

INSERT INTO permissions (module_code,code,name,description,sort_order) VALUES
('administration','administration','Administration and System Configuration','School profile, users, roles, academic year, terms, classes, streams, departments, houses, settings, audit logs',1),
('students','students','Student Management','Admission, registration, profiles, allocation, promotion, transfers, discipline, documents, alumni',2),
('academics','academics','Academic Management','Curriculum, subjects, combinations, timetables, schemes, syllabus, homework, calendar',3),
('examinations','examinations','Examination and Assessment Management','Exams, marks, grading, results, report cards, transcripts, UNEB registration and submissions',4),
('uneb','uneb','UNEB Management','UNEB candidate registration, continuous assessment submission, national results records',4.1),
('finance','finance','Finance and Fees Management','Fee structures, invoicing, payments, receipts, clearance, budgets, expenses, statements',5),
('online_payments','online_payments','Online Payment Integration','Mobile money and bank gateways, payment references, auto posting, transaction logs',6),
('hr','hr','Human Resource and Payroll Management','Staff records, contracts, leave, payroll, statutory deductions, appraisals',7),
('attendance','attendance','Attendance Management','Daily and lesson attendance, staff attendance, lateness, absentee follow-up, notifications',8),
('library','library','Library Management','Books, copies, borrowing, returns, overdue notices and fines',9),
('boarding','boarding','Boarding Management','Dormitories, rooms, beds, boarding registration, roll calls, exeats, maintenance',10),
('transport','transport','Transport Management','Vehicles, drivers, routes, student route allocation, transport fees, maintenance, fuel',11),
('inventory','inventory','Inventory and Procurement Management','Items, stock, suppliers, requisitions, approvals, purchase orders, assets',12),
('communication','communication','Communication Management','Announcements, SMS, email, portal messages, events, PTA, message history',13),
('health','health','Health and Student Welfare Management','Medical records, sick bay, medication, incidents, counselling, welfare (restricted)',14),
('portal','portal','Parent and Student Portal','Parent and student self-service access and portal activity',15),
('cocurricular','cocurricular','Co-curricular Activities Management','Clubs, sports, house competitions, events, talents',16),
('gate','gate','Visitor and Gate Management','Visitors, gate passes, exeat verification, vehicle log, security incidents',17),
('canteen','canteen','Canteen and School Shop Management','Product catalogue, sales, student wallet, stock control, sales reports',18),
('compliance','compliance','Government Compliance and Capitation','EMIS returns, enrolment returns, UPE/USE grants, inspections, licences',19),
('reports','reports','Reports and Analytics','Consolidated reports, analytics, export and role-based dashboards',20),
('security','security','System Security, Data Management and Support','Password policy, sessions, backup, import/export, data protection, support tickets',21);

INSERT INTO role_permissions
  (role_id,permission_id,can_view,can_add,can_edit,can_approve,can_print,can_delete)
SELECT r.id, p.id,
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list) OR FIND_IN_SET(p.module_code, m.view_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list) OR FIND_IN_SET(p.module_code, m.manage_list) OR FIND_IN_SET(p.module_code, m.view_list), 1, 0),
  IF(FIND_IN_SET(p.module_code, m.full_list), 1, 0)
FROM roles r
CROSS JOIN permissions p
JOIN (
  SELECT 'administrator' AS slug,
    'administration,students,academics,examinations,uneb,finance,online_payments,hr,attendance,library,boarding,transport,inventory,communication,health,portal,cocurricular,gate,canteen,compliance,reports,security' AS full_list,
    '' AS manage_list, '' AS view_list
  UNION ALL SELECT 'head_teacher',
    'students,academics,examinations,attendance,communication,reports,cocurricular,library,boarding,health,portal,transport,gate',
    'administration,finance,online_payments,hr,inventory,canteen,compliance,security',''
  UNION ALL SELECT 'deputy_head_teacher',
    'students,academics,examinations,attendance,communication,cocurricular,library,boarding,health,portal,gate,transport',
    'reports,compliance,inventory',
    'administration,finance,online_payments,hr,canteen,security'
  UNION ALL SELECT 'director_of_studies',
    'academics,examinations,attendance,library,cocurricular,communication,portal',
    'students,reports',
    'administration,finance,online_payments,hr,inventory,boarding,transport,health,gate,canteen,compliance,security'
  UNION ALL SELECT 'teacher',
    '',
    'attendance,academics,examinations,cocurricular,communication',
    'administration,students,library,boarding,health,reports,portal,transport'
  UNION ALL SELECT 'bursar',
    'finance,online_payments,canteen',
    'reports,compliance,inventory,communication,administration',
    'students,academics,examinations,attendance,portal,transport,boarding,health,gate,hr,security,library,cocurricular'
  UNION ALL SELECT 'accountant',
    'finance,online_payments',
    'reports,inventory,canteen,communication',
    'students,administration,portal,compliance,hr,attendance,academics,examinations'
  UNION ALL SELECT 'registrar',
    'students',
    'academics,compliance,reports,communication,administration',
    'examinations,attendance,portal,library,health,boarding,transport,gate,cocurricular,finance,hr,inventory,security,online_payments,canteen'
  UNION ALL SELECT 'librarian',
    'library','reports,communication',
    'students,academics,portal,attendance,administration'
  UNION ALL SELECT 'nurse',
    'health','reports,communication',
    'students,attendance,boarding,portal,administration,examinations'
  UNION ALL SELECT 'warden',
    'boarding','attendance,communication,gate,transport',
    'students,health,portal,reports,examinations,administration'
  UNION ALL SELECT 'gate_officer',
    'gate','communication,transport',
    'students,boarding,portal,administration,attendance'
  UNION ALL SELECT 'hr_officer',
    'hr','reports,communication,administration',
    'students,academics,attendance,finance,security,portal'
  UNION ALL SELECT 'storekeeper',
    'inventory','reports,communication',
    'students,administration,transport,canteen,finance'
  UNION ALL SELECT 'parent','','','portal'
  UNION ALL SELECT 'student','','','portal'
) m ON m.slug = r.slug
WHERE r.school_id = @school_id;

INSERT INTO users (school_id,username,full_name,email,user_type,role_id,must_change_password,is_active)
SELECT @school_id,'admin','System Administrator','admin@demo.sc.ug','staff',
       (SELECT id FROM roles WHERE slug='administrator' AND school_id=@school_id), 1, 1;

INSERT INTO password_history (user_id,password_hash,change_reason)
SELECT u.id, 'FORCE_CHANGE_ON_FIRST_LOGIN', 'admin_reset'
FROM users u WHERE u.username='admin' AND u.school_id=@school_id;

INSERT INTO academic_years (school_id,name,start_date,end_date,is_active,status) VALUES
(@school_id,'2026','2026-01-26','2026-12-11',1,'active');

SET @year_id = (SELECT id FROM academic_years WHERE school_id=@school_id ORDER BY id LIMIT 1);

INSERT INTO terms (academic_year_id,name,term_number,start_date,end_date,report_date,is_active,status) VALUES
(@year_id,'Term 1',1,'2026-01-26','2026-04-10','2026-04-17',0,'closed'),
(@year_id,'Term 2',2,'2026-04-27','2026-07-31','2026-08-07',0,'closed'),
(@year_id,'Term 3',3,'2026-08-24','2026-12-11','2026-12-18',1,'active');

INSERT INTO classes (school_id,name,level,order_index) VALUES
(@school_id,'S1','O-Level',1),
(@school_id,'S2','O-Level',2),
(@school_id,'S3','O-Level',3),
(@school_id,'S4','O-Level',4),
(@school_id,'S5','A-Level',5),
(@school_id,'S6','A-Level',6);

INSERT INTO streams (class_id,name,capacity)
SELECT c.id, s.stream_name, 50
FROM classes c
JOIN (SELECT 'East' AS stream_name UNION ALL SELECT 'West') s
WHERE c.school_id = @school_id;

INSERT INTO departments (school_id,name,type,description) VALUES
(@school_id,'Sciences','academic','Physics, Chemistry, Biology and practical sciences'),
(@school_id,'Mathematics','academic','Mathematics and ICT'),
(@school_id,'Languages','academic','English, Literature, Luganda and French'),
(@school_id,'Humanities','academic','History, Geography, Religious Education and Social Studies'),
(@school_id,'Finance','non-academic','Fees, accounts and procurement'),
(@school_id,'Boarding','non-academic','Dormitories, welfare and discipline'),
(@school_id,'Sports and Co-curricular','non-academic','Games, clubs and house activities');

INSERT INTO houses (school_id,name,colour,motto) VALUES
(@school_id,'Nile','Blue','Steady Flow'),
(@school_id,'Victoria','Green','Greatness'),
(@school_id,'Albert','Yellow','Strength'),
(@school_id,'Kyoga','Red','Unity');

INSERT INTO curricula (school_id,name,code,level_scope,description,status) VALUES
(@school_id,'New Lower Secondary Curriculum (Competency-Based)','CBC_LOWER','O-Level','S1-S4 competency-based curriculum with 20% school continuous assessment and 80% UNEB end-of-cycle examination','active'),
(@school_id,'Uganda Advanced Certificate of Education (A-Level)','UACE','A-Level','Current S5-S6 A-Level programme pending competency-based reform (reported around 2028)','active');

INSERT INTO subjects (school_id,code,name,short_name,level,category,department_id,order_index) VALUES
(@school_id,'MAT','Mathematics','Maths','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),1),
(@school_id,'ENG','English Language','English','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),2),
(@school_id,'SST','Social Studies','SST','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),3),
(@school_id,'CRE','Religious Education (CRE)','CRE','O-Level','compulsory',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),4),
(@school_id,'PHY','Physics','Physics','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),5),
(@school_id,'CHE','Chemistry','Chemistry','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),6),
(@school_id,'BIO','Biology','Biology','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),7),
(@school_id,'GEO','Geography','Geography','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),8),
(@school_id,'HIS','History','History','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),9),
(@school_id,'ICT','Information and Communication Technology','ICT','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),10),
(@school_id,'AGR','Agriculture','Agriculture','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),11),
(@school_id,'LIT','Literature in English','Literature','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),12),
(@school_id,'LUG','Luganda','Luganda','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),13),
(@school_id,'FRE','French','French','O-Level','elective',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),14),
(@school_id,'MUS','Music','Music','O-Level','elective',NULL,15),
(@school_id,'ART','Art and Design','Art','O-Level','elective',NULL,16),
(@school_id,'PE','Physical Education','PE','O-Level','elective',NULL,17),
(@school_id,'APHY','Physics (A-Level)','Physics A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),21),
(@school_id,'ACHE','Chemistry (A-Level)','Chemistry A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),22),
(@school_id,'AMAT','Mathematics (A-Level)','Maths A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),23),
(@school_id,'ABIO','Biology (A-Level)','Biology A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),24),
(@school_id,'AHIS','History (A-Level)','History A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),25),
(@school_id,'AGEO','Geography (A-Level)','Geography A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),26),
(@school_id,'AECON','Economics (A-Level)','Economics A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),27),
(@school_id,'ALIT','Literature in English (A-Level)','Literature A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),28),
(@school_id,'ADIV','Divinity (A-Level)','Divinity','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Humanities'),29),
(@school_id,'AAGR','Agriculture (A-Level)','Agriculture A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Sciences'),30),
(@school_id,'AFRE','French (A-Level)','French A','A-Level','principal',(SELECT id FROM departments WHERE school_id=@school_id AND name='Languages'),31),
(@school_id,'ASUBICT','Subsidiary ICT','Sub ICT','A-Level','subsidiary',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),41),
(@school_id,'ASUBMATH','Subsidiary Mathematics','Sub Maths','A-Level','subsidiary',(SELECT id FROM departments WHERE school_id=@school_id AND name='Mathematics'),42);

INSERT INTO alevel_combinations (school_id,code,name,description) VALUES
(@school_id,'PCM','Physics, Chemistry, Mathematics','Science combination for engineering and physical sciences'),
(@school_id,'BCG','Biology, Chemistry, Geography','Science combination for health and environmental sciences'),
(@school_id,'BCM','Biology, Chemistry, Mathematics','Science combination for medical and biological sciences'),
(@school_id,'HEG','History, Economics, Geography','Arts/social sciences combination');

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('APHY','ACHE','AMAT')
WHERE cb.code = 'PCM' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('ABIO','ACHE','AGEO')
WHERE cb.code = 'BCG' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('ABIO','ACHE','AMAT')
WHERE cb.code = 'BCM' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'principal', 1, s.order_index
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code IN ('AHIS','AECON','AGEO')
WHERE cb.code = 'HEG' AND cb.school_id = @school_id;

INSERT INTO combination_subjects (combination_id,subject_id,subject_role,is_compulsory,order_index)
SELECT cb.id, s.id, 'subsidiary', 0, 40
FROM alevel_combinations cb
JOIN subjects s ON s.school_id = cb.school_id AND s.code = 'ASUBICT'
WHERE cb.school_id = @school_id;

INSERT INTO generic_skills (school_id,code,name,description,level_scope) VALUES
(@school_id,'COMM','Communication','Listening, speaking, reading and writing effectively','O-Level'),
(@school_id,'CRIT','Critical Thinking and Problem Solving','Analysing situations and proposing solutions','O-Level'),
(@school_id,'COLL','Collaboration and Teamwork','Working with others towards a shared goal','O-Level'),
(@school_id,'SELF','Self-Management and Independence','Planning, organising and taking responsibility for learning','O-Level'),
(@school_id,'DIGI','Digital Literacy','Responsible and effective use of technology','O-Level'),
(@school_id,'CITZ','Citizenship and Values','Participation and responsibility in the community','O-Level'),
(@school_id,'CREA','Creativity and Innovation','Generating and developing original ideas','O-Level'),
(@school_id,'LEAD','Leadership','Guiding and influencing others positively','Both');

INSERT INTO grading_scales (school_id,code,name,level_scope,uses_division,uses_points,description,is_default) VALUES
(@school_id,'CBC_AE','CBC Achievement Levels A-E (S1-S4)','O-Level',0,0,'Five achievement levels reported with descriptors. Divisions and aggregates are not used for new-curriculum learners.',1),
(@school_id,'UACE','UACE A-Level Grading and Points (S5-S6)','A-Level',0,1,'Current UACE principal/subsidiary grading and points. Rules are configurable pending the A-Level curriculum reform.',1),
(@school_id,'LEGACY_D1_F9','Legacy D1-F9 Scale (historical records only)','Legacy',1,1,'Former UCE scale kept only for old report cards, transcripts and alumni records.',0);

INSERT INTO grade_bands (grading_scale_id,band_order,min_percent,max_percent,grade_code,grade_name,descriptor,points,division,is_pass)
SELECT s.id, b.band_order, b.min_percent, b.max_percent, b.grade_code, b.grade_name, b.descriptor, NULL, NULL, b.is_pass
FROM grading_scales s
JOIN (
  SELECT 1 AS band_order, 80.00 AS min_percent, 100.00 AS max_percent, 'A' AS grade_code, 'Exceptional' AS grade_name,
         'Exceptional: the learner consistently demonstrates mastery of the competencies with independence and insight.' AS descriptor, 1 AS is_pass
  UNION ALL SELECT 2, 70.00, 79.99, 'B', 'Outstanding',
         'Outstanding: the learner demonstrates strong competence and only needs occasional support.' , 1
  UNION ALL SELECT 3, 60.00, 69.99, 'C', 'Satisfactory',
         'Satisfactory: the learner demonstrates the expected competencies with some support.', 1
  UNION ALL SELECT 4, 50.00, 59.99, 'D', 'Basic',
         'Basic: the learner demonstrates minimum competence and needs regular support.', 1
  UNION ALL SELECT 5, 0.00, 49.99, 'E', 'Elementary',
         'Elementary: the learner has not yet demonstrated the required competencies and needs remedial support.', 0
) b
WHERE s.school_id = @school_id AND s.code = 'CBC_AE';

INSERT INTO grade_bands (grading_scale_id,band_order,min_percent,max_percent,grade_code,grade_name,descriptor,points,division,is_pass)
SELECT s.id, b.band_order, b.min_percent, b.max_percent, b.grade_code, b.grade_name, b.descriptor, b.points, NULL, b.is_pass
FROM grading_scales s
JOIN (
  SELECT 1 AS band_order, 80.00 AS min_percent, 100.00 AS max_percent, 'A' AS grade_code, 'Grade A' AS grade_name, 'Distinction' AS descriptor, 5.00 AS points, 1 AS is_pass
  UNION ALL SELECT 2, 70.00, 79.99, 'B', 'Grade B', 'Credit', 4.00, 1
  UNION ALL SELECT 3, 60.00, 69.99, 'C', 'Grade C', 'Pass (ordinary)', 3.00, 1
  UNION ALL SELECT 4, 50.00, 59.99, 'D', 'Grade D', 'Marginal pass', 2.00, 1
  UNION ALL SELECT 5, 40.00, 49.99, 'E', 'Grade E', 'Subsidiary pass', 1.00, 1
  UNION ALL SELECT 6, 0.00, 39.99, 'F', 'Grade F', 'Fail', 0.00, 0
) b
WHERE s.school_id = @school_id AND s.code = 'UACE';

INSERT INTO grade_bands (grading_scale_id,band_order,min_percent,max_percent,grade_code,grade_name,descriptor,points,division,is_pass)
SELECT s.id, b.band_order, b.min_percent, b.max_percent, b.grade_code, b.grade_name, NULL, b.points, NULL, b.is_pass
FROM grading_scales s
JOIN (
  SELECT 1 AS band_order, 80.00 AS min_percent, 100.00 AS max_percent, 'D1' AS grade_code, 'Distinction 1' AS grade_name, 1.00 AS points, 1 AS is_pass
  UNION ALL SELECT 2, 75.00, 79.99, 'D2', 'Distinction 2', 2.00, 1
  UNION ALL SELECT 3, 70.00, 74.99, 'C3', 'Credit 3', 3.00, 1
  UNION ALL SELECT 4, 65.00, 69.99, 'C4', 'Credit 4', 4.00, 1
  UNION ALL SELECT 5, 60.00, 64.99, 'C5', 'Credit 5', 5.00, 1
  UNION ALL SELECT 6, 55.00, 59.99, 'C6', 'Credit 6', 6.00, 1
  UNION ALL SELECT 7, 50.00, 54.99, 'P7', 'Pass 7', 7.00, 1
  UNION ALL SELECT 8, 45.00, 49.99, 'P8', 'Pass 8', 8.00, 1
  UNION ALL SELECT 9, 0.00, 44.99, 'F9', 'Fail 9', 9.00, 0
) b
WHERE s.school_id = @school_id AND s.code = 'LEGACY_D1_F9';

INSERT INTO assessment_components (school_id,level_scope,component_code,name,weight_percent,scale_min,scale_max,applies_to,notes) VALUES
(@school_id,'O-Level','school_ca','School-based Continuous Assessment',20.00,0,100,'official_results','Accumulated 1-3 scale scores for activities, projects and integrated tasks, converted to a percentage'),
(@school_id,'O-Level','project_work','Project Work (part of school CA)',0.00,1,3,'official_results','Mandatory component recorded per subject; feeds the 20% school-based score'),
(@school_id,'O-Level','uneb_exam','UNEB End-of-Cycle Examination',80.00,0,100,'official_results','UCE examination contribution set by UNEB'),
(@school_id,'O-Level','midterm','Mid-Term Assessment',30.00,0,100,'internal_reports','Internal term reports only'),
(@school_id,'O-Level','end_of_term','End-of-Term Assessment',70.00,0,100,'internal_reports','Internal term reports only'),
(@school_id,'A-Level','midterm','Mid-Term Assessment',40.00,0,100,'internal_reports','Internal weights for S5-S6 reports; UNEB sets official A-Level grading'),
(@school_id,'A-Level','end_of_term','End-of-Term Examination',60.00,0,100,'internal_reports','Internal weights for S5-S6 reports; UNEB sets official A-Level grading');

INSERT INTO payment_methods (school_id,code,name,channel,requires_verification,sort_order) VALUES
(@school_id,'cash','Cash at School','cash',0,1),
(@school_id,'bank_deposit','Bank Deposit','bank',1,2),
(@school_id,'cheque','Cheque','cheque',1,3),
(@school_id,'mtn_momo','MTN Mobile Money','mobile_money',0,4),
(@school_id,'airtel_money','Airtel Money','mobile_money',0,5),
(@school_id,'online_gateway','Online Payment Gateway','online',0,6);

INSERT INTO settings (school_id,setting_group,setting_key,setting_value,value_type,description) VALUES
(@school_id,'general','date_format','d/m/Y','string','Display format for dates'),
(@school_id,'general','currency','UGX','string','Default currency code'),
(@school_id,'general','currency_symbol','Ugx','string','Currency shown on documents'),
(@school_id,'general','academic_week_starts','Monday','string','First day of the academic week'),
(@school_id,'general','time_format','12h','string','Time display format'),
(@school_id,'numbering','admission_prefix','ADM/','string','Prefix for admission numbers'),
(@school_id,'numbering','receipt_prefix','REC/','string','Prefix for receipt numbers'),
(@school_id,'numbering','invoice_prefix','INV/','string','Prefix for fee invoice numbers'),
(@school_id,'numbering','payment_prefix','PAY/','string','Prefix for payment references'),
(@school_id,'numbering','staff_prefix','STF/','string','Prefix for staff numbers'),
(@school_id,'fees','fee_clearance_required','1','bool','Block report cards and examinations for unpaid balances (1 = on)'),
(@school_id,'fees','fee_reminder_days_before','7','int','Days before due date to send reminders'),
(@school_id,'fees','allow_part_payment','1','bool','Allow part payments against invoices'),
(@school_id,'examinations','show_ranking_o_level','0','bool','Ranking is switched off by default for S1-S4 (0 = off)'),
(@school_id,'examinations','show_divisions','0','bool','Divisions/aggregates are not shown for new-curriculum learners'),
(@school_id,'examinations','min_subjects_for_uce','8','int','Minimum subjects for a UCE candidate'),
(@school_id,'security','password_min_length','8','int','Minimum password length'),
(@school_id,'security','password_expiry_days','90','int','Days before a password must be changed'),
(@school_id,'security','max_failed_logins','5','int','Failed attempts before an account is locked'),
(@school_id,'security','session_timeout_minutes','30','int','Automatic logout after inactivity'),
(@school_id,'security','require_two_factor_admin','0','bool','Require 2FA for administrator and bursar (optional)'),
(@school_id,'communication','sms_sender_name','SCHOOL','string','SMS sender ID (alpha-numeric)'),
(@school_id,'communication','email_from_address','no-reply@demo.sc.ug','string','From address for outgoing email'),
(@school_id,'communication','default_notification_channel','sms','string','Default channel for parent notifications'),
(@school_id,'reporting','report_card_footer','Report generated by SSMS','string','Footer text printed on report cards'),
(@school_id,'backup','backup_schedule','daily','string','Backup frequency: daily, weekly, manual'),
(@school_id,'data_protection','retain_alumni_data','1','bool','Keep alumni records after completion'),
(@school_id,'data_protection','consent_required_photo_use','1','bool','Require recorded consent before publishing student photos');

INSERT INTO settings (school_id,setting_group,setting_key,setting_value,value_type,description) VALUES
(@school_id,'smtp','smtp_host','','string','SMTP server hostname for outgoing email'),
(@school_id,'smtp','smtp_port','587','int','SMTP server port'),
(@school_id,'smtp','smtp_username','','string','SMTP authentication username'),
(@school_id,'smtp','smtp_password','','string','SMTP authentication password (stored as plain text - consider encryption in production)'),
(@school_id,'smtp','smtp_encryption','tls','string','Encryption method: none, ssl, or tls'),
(@school_id,'smtp','smtp_from_name','SSMS','string','Display name shown as email sender'),
(@school_id,'smtp','smtp_from_address','no-reply@demo.sc.ug','string','Email address shown as sender'),

(@school_id,'sms','sms_provider','','string','SMS provider name (e.g. Africa\'s Talking, Twilio, local gateway)'),
(@school_id,'sms','sms_api_key','','string','SMS API key or username'),
(@school_id,'sms','sms_api_secret','','string','SMS API secret or password'),
(@school_id,'sms','sms_sender_id','SSMS','string','SMS sender ID displayed on recipient phone'),
(@school_id,'sms','sms_gateway_url','','string','SMS gateway HTTP endpoint URL'),
(@school_id,'sms','sms_default_language','en','string','Default language for SMS templates'),

(@school_id,'grading','grading_pass_mark','50','int','Minimum percentage score to be considered a pass'),
(@school_id,'grading','default_grading_scale','CBC_AE','string','Default grading scale code used for new learners'),
(@school_id,'grading','show_class_ranking','0','bool','Display class ranking on report cards (0 = off)'),
(@school_id,'grading','round_marks','0','bool','Round computed marks to nearest whole number'),
(@school_id,'grading','include_project_in_ca','1','bool','Include project work in continuous assessment score'),

(@school_id,'portal','portal_parent_access','1','bool','Allow parents to access the portal (1 = on)'),
(@school_id,'portal','portal_student_access','1','bool','Allow students to access the portal (1 = on)'),
(@school_id,'portal','portal_publish_results','0','bool','Automatically publish results to portal when processed'),
(@school_id,'portal','portal_show_fee_balance','1','bool','Show fee balances to parents in portal'),
(@school_id,'portal','portal_show_attendance','1','bool','Show attendance records to parents in portal'),
(@school_id,'portal','portal_session_timeout','15','int','Portal session timeout in minutes'),

(@school_id,'uneb','uneb_center_number','','string','UNEB center/examination number assigned to the school'),
(@school_id,'uneb','uneb_school_code','','string','UNEB school code for candidate registration'),
(@school_id,'uneb','uneb_district','','string','District where the school is registered with UNEB'),
(@school_id,'uneb','ca_submission_deadline','','date','Deadline for submitting continuous assessment scores to UNEB'),
(@school_id,'uneb','project_work_mandatory','1','bool','Project work is mandatory for UCE candidates (1 = yes)'),
(@school_id,'uneb','min_subjects_uce','8','int','Minimum number of subjects for UCE certification'),

(@school_id,'academic','lessons_per_day','8','int','Default number of teaching periods per day'),
(@school_id,'academic','lesson_duration_minutes','40','int','Duration of each teaching period in minutes'),
(@school_id,'academic','default_class_teacher','','string','Default class teacher assignment policy'),
(@school_id,'academic','terms_per_year','3','int','Number of academic terms per year'),
(@school_id,'academic','promotion_min_average','50','int','Minimum average score required for promotion'),
(@school_id,'academic','allow_repeaters','1','bool','Allow students to repeat a class (1 = yes)'),

(@school_id,'finance','default_tax_rate','0','int','Default tax rate percentage applied to invoices'),
(@school_id,'finance','receipt_footer','Thank you for your payment','string','Footer text printed on receipts'),
(@school_id,'finance','invoice_payment_terms','Payment due within 14 days','string','Standard payment terms shown on invoices'),
(@school_id,'finance','auto_generate_invoices','0','bool','Automatically generate fee invoices at term start'),
(@school_id,'finance','late_payment_fee','0','decimal','Flat late payment fee amount'),

(@school_id,'security','ip_whitelist','','string','Comma-separated list of allowed IP addresses (blank = all allowed)'),
(@school_id,'security','login_notification_email','','string','Email address to receive login notifications'),
(@school_id,'security','max_concurrent_sessions','1','int','Maximum number of simultaneous sessions per user'),
(@school_id,'security','force_https','0','bool','Force HTTPS connections (1 = yes)'),
(@school_id,'security','audit_log_retention_days','365','int','Number of days to retain audit log entries'),

(@school_id,'curriculum','cbc_enabled','1','bool','Competency-Based Curriculum is active for O-Level'),
(@school_id,'curriculum','ca_weight_percent','20','int','Continuous assessment weight percentage (S1-S4)'),
(@school_id,'curriculum','exam_weight_percent','80','int','End-of-cycle examination weight percentage (S1-S4)'),
(@school_id,'curriculum','generic_skills_required','1','bool','Generic skills assessment is required'),
(@school_id,'curriculum','project_work_scale_min','1','int','Minimum project work score (1-3 scale)'),
(@school_id,'curriculum','project_work_scale_max','3','int','Maximum project work score (1-3 scale)');

INSERT INTO sequences (school_id,seq_key,prefix,next_value,padding,description) VALUES
(@school_id,'admission_number','ADM/',1,5,'Student admission numbers'),
(@school_id,'staff_number','STF/',1,4,'Staff numbers'),
(@school_id,'invoice_number','INV/',1,6,'Fee invoice numbers'),
(@school_id,'receipt_number','REC/',1,6,'Receipt numbers'),
(@school_id,'payment_reference','PAY/',1,6,'Payment references'),
(@school_id,'application_number','APP/',1,5,'Admission application numbers'),
(@school_id,'gate_pass','GP/',1,5,'Gate pass numbers'),
(@school_id,'exeat_number','EX/',1,5,'Exeat numbers'),
(@school_id,'purchase_request','PR/',1,5,'Purchase request numbers'),
(@school_id,'purchase_order','PO/',1,5,'Purchase order numbers'),
(@school_id,'expense_voucher','PV/',1,5,'Expense voucher numbers'),
(@school_id,'petty_cash_voucher','PCV/',1,5,'Petty cash voucher numbers'),
(@school_id,'sponsor_billing','SB/',1,4,'Sponsor billing numbers'),
(@school_id,'sale_number','SL/',1,5,'Canteen/shop sale numbers'),
(@school_id,'wallet_number','WLT/',1,6,'Student wallet numbers'),
(@school_id,'support_ticket','TKT/',1,5,'Support ticket numbers'),
(@school_id,'security_incident','SEC/',1,4,'Security incident numbers'),
(@school_id,'asset_tag','AST/',1,5,'Fixed asset tags');

INSERT INTO gl_accounts (school_id,account_code,account_name,account_type) VALUES
(@school_id,'1000','Assets','asset'),
(@school_id,'2000','Liabilities','liability'),
(@school_id,'3000','Equity','equity'),
(@school_id,'4000','Income','income'),
(@school_id,'5000','Expenditure','expense');

INSERT INTO gl_accounts (school_id,account_code,account_name,account_type,parent_id) VALUES
(@school_id,'1010','Cash on hand','asset',NULL),
(@school_id,'1020','Bank account - operating','asset',NULL),
(@school_id,'1030','Mobile money collections','asset',NULL),
(@school_id,'1040','Fees receivable','asset',NULL),
(@school_id,'1050','Fixed assets','asset',NULL),
(@school_id,'1060','Inventory and stores','asset',NULL),
(@school_id,'1070','Petty cash fund','asset',NULL),
(@school_id,'2010','Accounts payable','liability',NULL),
(@school_id,'2020','NSSF payable','liability',NULL),
(@school_id,'2030','PAYE payable (URA)','liability',NULL),
(@school_id,'2040','Accrued salaries and wages','liability',NULL),
(@school_id,'2050','Advance fees received','liability',NULL),
(@school_id,'3010','School development fund','equity',NULL),
(@school_id,'3020','Retained earnings','equity',NULL),
(@school_id,'4010','Tuition fees - O-Level','income',NULL),
(@school_id,'4020','Tuition fees - A-Level','income',NULL),
(@school_id,'4030','Boarding fees','income',NULL),
(@school_id,'4040','Transport fees','income',NULL),
(@school_id,'4050','Examination and registration fees','income',NULL),
(@school_id,'4060','Uniform and shop sales','income',NULL),
(@school_id,'4070','Canteen sales','income',NULL),
(@school_id,'4080','Government grants (UPE/USE)','income',NULL),
(@school_id,'4090','Donations and PTA contributions','income',NULL),
(@school_id,'4100','Library and other charges','income',NULL),
(@school_id,'4110','Interest income','income',NULL),
(@school_id,'5010','Salaries and wages','expense',NULL),
(@school_id,'5020','NSSF and statutory contributions','expense',NULL),
(@school_id,'5030','Utilities (water and electricity)','expense',NULL),
(@school_id,'5040','Learning materials and textbooks','expense',NULL),
(@school_id,'5050','Food and catering supplies','expense',NULL),
(@school_id,'5060','Repairs and maintenance','expense',NULL),
(@school_id,'5070','Transport and fuel','expense',NULL),
(@school_id,'5080','Administration expenses','expense',NULL),
(@school_id,'5090','Security services','expense',NULL),
(@school_id,'5100','Medical and health','expense',NULL),
(@school_id,'5110','Communication (SMS and internet)','expense',NULL),
(@school_id,'5120','Depreciation','expense',NULL);

UPDATE gl_accounts c
JOIN gl_accounts p
  ON p.school_id = c.school_id
 AND p.account_code = CONCAT(LEFT(c.account_code, 1), '000')
SET c.parent_id = p.id
WHERE c.parent_id IS NULL
  AND c.account_code <> CONCAT(LEFT(c.account_code, 1), '000');

INSERT INTO item_categories (school_id,name,code) VALUES
(@school_id,'Food and Catering','FOOD'),
(@school_id,'Cleaning','CLEAN'),
(@school_id,'Laboratory','LAB'),
(@school_id,'Sports','SPORT'),
(@school_id,'Stationery','STAT'),
(@school_id,'ICT','ICT'),
(@school_id,'Medical','MED'),
(@school_id,'Uniform','UNIF'),
(@school_id,'Building and Furniture','BUIL');

INSERT INTO leave_types (school_id,name,code,days_per_year,is_paid,requires_document) VALUES
(@school_id,'Annual Leave','AL',21,1,0),
(@school_id,'Sick Leave','SL',30,1,1),
(@school_id,'Maternity Leave','ML',90,1,1),
(@school_id,'Paternity Leave','PL',7,1,1),
(@school_id,'Compassionate Leave','CL',7,1,1),
(@school_id,'Study Leave','STL',0,0,1),
(@school_id,'Unpaid Leave','UL',0,0,0);

INSERT INTO notification_templates (school_id,template_code,name,channel,subject,body,placeholders,related_module) VALUES
(@school_id,'fee_reminder','Fee balance reminder','sms',NULL,'Dear {guardian_name}, {student_name} ( {admission_number} ) has an outstanding balance of UGX {balance} due on {due_date}. Please pay at the school office or by mobile money using reference {payment_reference}. - {school_name}','{guardian_name},{student_name},{admission_number},{balance},{due_date},{payment_reference},{school_name}','finance'),
(@school_id,'absence_alert','Student absence alert','sms',NULL,'Dear {guardian_name}, {student_name} was reported {status} on {date} for {class_name}. Please contact the school on {school_phone}. - {school_name}','{guardian_name},{student_name},{status},{date},{class_name},{school_phone}','attendance'),
(@school_id,'late_alert','Late arrival alert','sms',NULL,'Dear {guardian_name}, {student_name} arrived at {time} on {date} ({minutes} minutes late). - {school_name}','{guardian_name},{student_name},{time},{date},{minutes}','attendance'),
(@school_id,'result_ready','Results released','sms',NULL,'Dear {guardian_name}, results for {student_name} for {term} are ready. View or download them from the parent portal. - {school_name}','{guardian_name},{student_name},{term}','examinations'),
(@school_id,'payment_receipt','Payment received','sms',NULL,'We confirm receipt of UGX {amount} from {payer_name} for {student_name}. Receipt number {receipt_number}. Balance: UGX {balance}. - {school_name}','{amount},{payer_name},{student_name},{receipt_number},{balance}','finance'),
(@school_id,'meeting_invite','Meeting invitation','email','School meeting invitation','Dear {guardian_name}, you are invited to {event_name} on {event_date} at {venue}. Please confirm attendance. - {school_name}','{guardian_name},{event_name},{event_date},{venue}','communication'),
(@school_id,'exeat_notification','Exeat approval','sms',NULL,'Dear {guardian_name}, exeat {exeat_number} for {student_name} to leave on {out_date} and return by {return_time} has been {status}. - {school_name}','{guardian_name},{exeat_number},{student_name},{out_date},{return_time},{status}','boarding'),
(@school_id,'report_card_notice','Report card available','portal','New report card available','Dear {guardian_name}, the report card for {student_name} ({term}) has been released and can now be downloaded from the portal.','{guardian_name},{student_name},{term}','examinations');

INSERT INTO gateway_configs (school_id,provider_code,provider_name,provider_type,currency,environment,is_active) VALUES
(@school_id,'mtn_momo','MTN Mobile Money','mobile_money','UGX','sandbox',1),
(@school_id,'airtel_money','Airtel Money','mobile_money','UGX','sandbox',1);

INSERT INTO statutory_rates (school_id,code,name,rate_type,rate_value,band_min,band_max,is_employer,effective_from,notes) VALUES
(@school_id,'NSSF_EMPLOYEE','NSSF employee contribution','percentage',5.000,NULL,NULL,0,'2026-01-01','Confirm the current NSSF rate with NSSF Uganda'),
(@school_id,'NSSF_EMPLOYER','NSSF employer contribution','percentage',10.000,NULL,NULL,1,'2026-01-01','Confirm the current NSSF rate with NSSF Uganda'),
(@school_id,'PAYE_BAND_1','PAYE band 0 - 235,000','band',0.000,0.00,235000.00,0,'2026-01-01','Verify current URA PAYE bands and personal relief'),
(@school_id,'PAYE_BAND_2','PAYE band 235,001 - 335,000','band',10.000,235000.01,335000.00,0,'2026-01-01','Verify current URA PAYE bands and personal relief'),
(@school_id,'PAYE_BAND_3','PAYE band 335,001 - 410,000','band',20.000,335000.01,410000.00,0,'2026-01-01','Verify current URA PAYE bands and personal relief'),
(@school_id,'PAYE_BAND_4','PAYE band above 410,000','band',30.000,410000.01,NULL,0,'2026-01-01','Verify current URA PAYE bands and personal relief');

INSERT INTO allowances (school_id,code,name,amount_type,default_amount,is_taxable) VALUES
(@school_id,'HOUSING','Housing Allowance','fixed',0.00,1),
(@school_id,'TRANSPORT','Transport Allowance','fixed',0.00,1),
(@school_id,'MEAL','Meal Allowance','fixed',0.00,1),
(@school_id,'RESPONSIBILITY','Responsibility Allowance','fixed',0.00,1),
(@school_id,'DUTY','Duty Allowance','fixed',0.00,1),
(@school_id,'MEDICAL','Medical Allowance','fixed',0.00,1);
