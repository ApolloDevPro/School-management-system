USE ssms;
SET NAMES utf8mb4;

DELIMITER $$

DROP TRIGGER IF EXISTS trg_payments_ai_sync_invoice$$
CREATE TRIGGER trg_payments_ai_sync_invoice AFTER INSERT ON payments
FOR EACH ROW
BEGIN
  DECLARE v_paid DECIMAL(12,2);
  IF NEW.invoice_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = NEW.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = NEW.invoice_id AND status <> 'void';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payments_au_sync_invoice$$
CREATE TRIGGER trg_payments_au_sync_invoice AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
  DECLARE v_paid DECIMAL(12,2);
  IF OLD.invoice_id IS NOT NULL AND (NEW.invoice_id IS NULL OR NEW.invoice_id <> OLD.invoice_id) THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = OLD.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = OLD.invoice_id AND status <> 'void';
  END IF;
  IF NEW.invoice_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = NEW.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = NEW.invoice_id AND status <> 'void';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payments_ad_sync_invoice$$
CREATE TRIGGER trg_payments_ad_sync_invoice AFTER DELETE ON payments
FOR EACH ROW
BEGIN
  DECLARE v_paid DECIMAL(12,2);
  IF OLD.invoice_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_local),0) INTO v_paid
      FROM payments
     WHERE invoice_id = OLD.invoice_id AND status IN ('verified','posted');
    UPDATE fee_invoices
       SET paid_amount = v_paid,
           balance = GREATEST(total_amount - v_paid, 0),
           status = CASE WHEN v_paid >= total_amount AND total_amount > 0 THEN 'paid'
                         WHEN v_paid > 0 THEN 'part_paid'
                         ELSE 'unpaid' END
     WHERE id = OLD.invoice_id AND status <> 'void';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payments_bi_guard$$
CREATE TRIGGER trg_payments_bi_guard BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
  DECLARE v_student INT;
  IF NEW.amount <= 0 OR NEW.amount_local <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment amounts must be greater than zero.';
  END IF;
  IF NEW.invoice_id IS NOT NULL THEN
    SELECT student_id INTO v_student FROM fee_invoices WHERE id = NEW.invoice_id;
    IF v_student IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected invoice does not exist.';
    END IF;
    IF NEW.student_id IS NOT NULL AND v_student <> NEW.student_id THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The payment student does not match the invoice student.';
    END IF;
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_fee_invoices_bu_guard$$
CREATE TRIGGER trg_fee_invoices_bu_guard BEFORE UPDATE ON fee_invoices
FOR EACH ROW
BEGIN
  IF NEW.total_amount < 0 OR NEW.paid_amount < 0 OR NEW.balance < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice amounts cannot be negative.';
  END IF;
  IF ABS(NEW.balance - GREATEST(NEW.total_amount - NEW.paid_amount, 0)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice balance must equal the total less the amount paid.';
  END IF;
  IF OLD.status = 'void' AND (
       NEW.status <> 'void'
       OR NEW.total_amount <> OLD.total_amount
       OR NEW.paid_amount <> OLD.paid_amount
       OR NEW.balance <> OLD.balance
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Void invoices cannot be changed.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_fee_invoices_bd_guard$$
CREATE TRIGGER trg_fee_invoices_bd_guard BEFORE DELETE ON fee_invoices
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1 FROM payments WHERE invoice_id = OLD.id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoices with payments cannot be deleted.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_fee_invoice_items_bi_net$$
CREATE TRIGGER trg_fee_invoice_items_bi_net BEFORE INSERT ON fee_invoice_items
FOR EACH ROW
BEGIN
  IF NEW.amount < 0 OR COALESCE(NEW.discount_amount,0) < 0 OR COALESCE(NEW.waived_amount,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice item amounts cannot be negative.';
  END IF;
  IF EXISTS (SELECT 1 FROM fee_invoices WHERE id = NEW.invoice_id AND status = 'void') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice items cannot be added to a void invoice.';
  END IF;
  SET NEW.net_amount = ROUND(GREATEST(NEW.amount - COALESCE(NEW.discount_amount,0) - COALESCE(NEW.waived_amount,0), 0), 2);
END$$

DROP TRIGGER IF EXISTS trg_fee_invoice_items_bu_net$$
CREATE TRIGGER trg_fee_invoice_items_bu_net BEFORE UPDATE ON fee_invoice_items
FOR EACH ROW
BEGIN
  IF NEW.amount < 0 OR COALESCE(NEW.discount_amount,0) < 0 OR COALESCE(NEW.waived_amount,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice item amounts cannot be negative.';
  END IF;
  IF EXISTS (SELECT 1 FROM fee_invoices WHERE id = NEW.invoice_id AND status = 'void') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invoice items cannot be changed on a void invoice.';
  END IF;
  SET NEW.net_amount = ROUND(GREATEST(NEW.amount - COALESCE(NEW.discount_amount,0) - COALESCE(NEW.waived_amount,0), 0), 2);
END$$

DROP TRIGGER IF EXISTS trg_receipts_bi_guard$$
CREATE TRIGGER trg_receipts_bi_guard BEFORE INSERT ON receipts
FOR EACH ROW
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE v_amount DECIMAL(12,2);
  SELECT status, amount_local INTO v_status, v_amount FROM payments WHERE id = NEW.payment_id;
  IF v_status IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The receipt payment does not exist.';
  END IF;
  IF v_status NOT IN ('verified','posted') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Receipts can only be issued for verified or posted payments.';
  END IF;
  IF ABS(NEW.amount - v_amount) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The receipt amount must match the payment amount.';
  END IF;
  IF EXISTS (SELECT 1 FROM receipts WHERE payment_id = NEW.payment_id AND is_void = 0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'An active receipt already exists for this payment.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_receipts_bu_guard$$
CREATE TRIGGER trg_receipts_bu_guard BEFORE UPDATE ON receipts
FOR EACH ROW
BEGIN
  IF NEW.payment_id <> OLD.payment_id OR NEW.amount <> OLD.amount THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A receipt payment and amount cannot be changed.';
  END IF;
  IF OLD.is_void = 1 AND NEW.is_void = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A voided receipt cannot be restored.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_ledger_entries_bi_guard$$
CREATE TRIGGER trg_ledger_entries_bi_guard BEFORE INSERT ON ledger_entries
FOR EACH ROW
BEGIN
  IF NEW.debit < 0 OR NEW.credit < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger amounts cannot be negative.';
  END IF;
  IF NOT ((NEW.debit > 0 AND NEW.credit = 0) OR (NEW.credit > 0 AND NEW.debit = 0)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A ledger entry needs exactly one debit or credit amount.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_wallet_transactions_bi_guard$$
CREATE TRIGGER trg_wallet_transactions_bi_guard BEFORE INSERT ON wallet_transactions
FOR EACH ROW
BEGIN
  IF NEW.amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet transaction amounts must be greater than zero.';
  END IF;
  IF NEW.transaction_type IN ('deposit','refund') THEN
    IF ABS(NEW.balance_after - (NEW.balance_before + NEW.amount)) > 0.01 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet balance after does not match the amount added.';
    END IF;
  ELSEIF NEW.transaction_type IN ('purchase','withdrawal') THEN
    IF ABS(NEW.balance_after - (NEW.balance_before - NEW.amount)) > 0.01 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Wallet balance after does not match the amount deducted.';
    END IF;
  END IF;
  IF NEW.balance_after < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A wallet balance cannot go below zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_wallet_transactions_ai_sync$$
CREATE TRIGGER trg_wallet_transactions_ai_sync AFTER INSERT ON wallet_transactions
FOR EACH ROW
BEGIN
  UPDATE student_wallets SET balance = NEW.balance_after WHERE id = NEW.wallet_id;
END$$

DROP TRIGGER IF EXISTS trg_petty_cash_vouchers_bi_guard$$
CREATE TRIGGER trg_petty_cash_vouchers_bi_guard BEFORE INSERT ON petty_cash_vouchers
FOR EACH ROW
BEGIN
  DECLARE v_balance DECIMAL(12,2);
  IF NEW.amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Voucher amounts must be greater than zero.';
  END IF;
  SELECT current_balance INTO v_balance FROM petty_cash_funds WHERE id = NEW.fund_id;
  IF v_balance IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The petty cash fund does not exist.';
  END IF;
  IF NEW.amount > v_balance THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The voucher exceeds the petty cash fund balance.';
  END IF;
  SET NEW.balance_after = ROUND(v_balance - NEW.amount, 2);
END$$

DROP TRIGGER IF EXISTS trg_petty_cash_vouchers_au_balance$$
CREATE TRIGGER trg_petty_cash_vouchers_au_balance AFTER UPDATE ON petty_cash_vouchers
FOR EACH ROW
BEGIN
  DECLARE v_balance DECIMAL(12,2);
  IF OLD.status = 'paid' AND NEW.status = 'paid' AND (
       NEW.amount <> OLD.amount OR NEW.fund_id <> OLD.fund_id
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A paid voucher cannot change its amount or fund.';
  END IF;
  IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
    SELECT current_balance INTO v_balance FROM petty_cash_funds WHERE id = NEW.fund_id;
    IF v_balance IS NULL OR v_balance < NEW.amount THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The petty cash fund has insufficient balance.';
    END IF;
    UPDATE petty_cash_funds SET current_balance = current_balance - NEW.amount WHERE id = NEW.fund_id;
  END IF;
  IF OLD.status = 'paid' AND NEW.status <> 'paid' THEN
    UPDATE petty_cash_funds SET current_balance = current_balance + OLD.amount WHERE id = OLD.fund_id;
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_marks_bi_compute$$
CREATE TRIGGER trg_marks_bi_compute BEFORE INSERT ON marks
FOR EACH ROW
BEGIN
  DECLARE v_max DECIMAL(10,2);
  IF COALESCE(NEW.ca_mark,0) < 0 OR COALESCE(NEW.exam_mark,0) < 0 OR COALESCE(NEW.project_mark,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mark scores cannot be negative.';
  END IF;
  IF NEW.max_ca_mark IS NOT NULL AND NEW.ca_mark IS NOT NULL AND NEW.ca_mark > NEW.max_ca_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The CA mark cannot exceed its maximum.';
  END IF;
  IF NEW.max_exam_mark IS NOT NULL AND NEW.exam_mark IS NOT NULL AND NEW.exam_mark > NEW.max_exam_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam mark cannot exceed its maximum.';
  END IF;
  SET NEW.total_mark = ROUND(COALESCE(NEW.ca_mark,0) + COALESCE(NEW.exam_mark,0), 2);
  SET v_max = COALESCE(NEW.max_ca_mark,0) + COALESCE(NEW.max_exam_mark,0);
  SET NEW.percentage = IF(v_max > 0, ROUND(NEW.total_mark / v_max * 100, 2), 0);
END$$

DROP TRIGGER IF EXISTS trg_marks_bu_compute$$
CREATE TRIGGER trg_marks_bu_compute BEFORE UPDATE ON marks
FOR EACH ROW
BEGIN
  DECLARE v_max DECIMAL(10,2);
  IF OLD.status = 'locked' AND (
       NOT (NEW.ca_mark <=> OLD.ca_mark)
       OR NOT (NEW.exam_mark <=> OLD.exam_mark)
       OR NOT (NEW.project_mark <=> OLD.project_mark)
       OR NOT (NEW.max_ca_mark <=> OLD.max_ca_mark)
       OR NOT (NEW.max_exam_mark <=> OLD.max_exam_mark)
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Locked marks cannot be edited. Unlock the mark first.';
  END IF;
  IF COALESCE(NEW.ca_mark,0) < 0 OR COALESCE(NEW.exam_mark,0) < 0 OR COALESCE(NEW.project_mark,0) < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mark scores cannot be negative.';
  END IF;
  IF NEW.max_ca_mark IS NOT NULL AND NEW.ca_mark IS NOT NULL AND NEW.ca_mark > NEW.max_ca_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The CA mark cannot exceed its maximum.';
  END IF;
  IF NEW.max_exam_mark IS NOT NULL AND NEW.exam_mark IS NOT NULL AND NEW.exam_mark > NEW.max_exam_mark + 0.001 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam mark cannot exceed its maximum.';
  END IF;
  IF NOT (NEW.ca_mark <=> OLD.ca_mark)
     OR NOT (NEW.exam_mark <=> OLD.exam_mark)
     OR NOT (NEW.max_ca_mark <=> OLD.max_ca_mark)
     OR NOT (NEW.max_exam_mark <=> OLD.max_exam_mark) THEN
    SET NEW.total_mark = ROUND(COALESCE(NEW.ca_mark,0) + COALESCE(NEW.exam_mark,0), 2);
    SET v_max = COALESCE(NEW.max_ca_mark,0) + COALESCE(NEW.max_exam_mark,0);
    SET NEW.percentage = IF(v_max > 0, ROUND(NEW.total_mark / v_max * 100, 2), 0);
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exams_bi_dates$$
CREATE TRIGGER trg_exams_bi_dates BEFORE INSERT ON exams
FOR EACH ROW
BEGIN
  IF NEW.start_date IS NOT NULL AND NEW.end_date IS NOT NULL AND NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam end date cannot be before the start date.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exams_bu_dates$$
CREATE TRIGGER trg_exams_bu_dates BEFORE UPDATE ON exams
FOR EACH ROW
BEGIN
  IF NEW.start_date IS NOT NULL AND NEW.end_date IS NOT NULL AND NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam end date cannot be before the start date.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The exam maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exam_schedule_bi_dates$$
CREATE TRIGGER trg_exam_schedule_bi_dates BEFORE INSERT ON exam_schedule
FOR EACH ROW
BEGIN
  DECLARE v_start DATE;
  DECLARE v_end DATE;
  SELECT start_date, end_date INTO v_start, v_end FROM exams WHERE id = NEW.exam_id;
  IF NEW.paper_date IS NOT NULL AND v_start IS NOT NULL AND v_end IS NOT NULL
     AND (NEW.paper_date < v_start OR NEW.paper_date > v_end) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper date must fall inside the exam dates.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_exam_schedule_bu_dates$$
CREATE TRIGGER trg_exam_schedule_bu_dates BEFORE UPDATE ON exam_schedule
FOR EACH ROW
BEGIN
  DECLARE v_start DATE;
  DECLARE v_end DATE;
  SELECT start_date, end_date INTO v_start, v_end FROM exams WHERE id = NEW.exam_id;
  IF NEW.paper_date IS NOT NULL AND v_start IS NOT NULL AND v_end IS NOT NULL
     AND (NEW.paper_date < v_start OR NEW.paper_date > v_end) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper date must fall inside the exam dates.';
  END IF;
  IF NEW.max_mark IS NOT NULL AND NEW.max_mark <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The paper maximum mark must be greater than zero.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_leave_requests_bi_dates$$
CREATE TRIGGER trg_leave_requests_bi_dates BEFORE INSERT ON leave_requests
FOR EACH ROW
BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave end date cannot be before the start date.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_leave_requests_bu_dates$$
CREATE TRIGGER trg_leave_requests_bu_dates BEFORE UPDATE ON leave_requests
FOR EACH ROW
BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The leave end date cannot be before the start date.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payroll_items_bi_guard$$
CREATE TRIGGER trg_payroll_items_bi_guard BEFORE INSERT ON payroll_items
FOR EACH ROW
BEGIN
  IF ABS(NEW.gross_pay - (NEW.basic_pay + NEW.allowances_total)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gross pay must equal basic pay plus allowances.';
  END IF;
  IF NEW.total_deductions + 0.01 < (COALESCE(NEW.nssf_employee,0) + COALESCE(NEW.paye,0) + COALESCE(NEW.other_deductions,0)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Total deductions are less than their component parts.';
  END IF;
  IF ABS(NEW.net_pay - (NEW.gross_pay - NEW.total_deductions)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net pay must equal gross pay less total deductions.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_payroll_items_bu_guard$$
CREATE TRIGGER trg_payroll_items_bu_guard BEFORE UPDATE ON payroll_items
FOR EACH ROW
BEGIN
  IF OLD.is_paid = 1 AND (
       NEW.basic_pay <> OLD.basic_pay
       OR NEW.allowances_total <> OLD.allowances_total
       OR NEW.gross_pay <> OLD.gross_pay
       OR NEW.total_deductions <> OLD.total_deductions
       OR NEW.net_pay <> OLD.net_pay
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'A paid payroll item cannot change its amounts.';
  END IF;
  IF ABS(NEW.gross_pay - (NEW.basic_pay + NEW.allowances_total)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Gross pay must equal basic pay plus allowances.';
  END IF;
  IF NEW.total_deductions + 0.01 < (COALESCE(NEW.nssf_employee,0) + COALESCE(NEW.paye,0) + COALESCE(NEW.other_deductions,0)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Total deductions are less than their component parts.';
  END IF;
  IF ABS(NEW.net_pay - (NEW.gross_pay - NEW.total_deductions)) > 0.01 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Net pay must equal gross pay less total deductions.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_borrowings_bi_guard$$
CREATE TRIGGER trg_borrowings_bi_guard BEFORE INSERT ON borrowings
FOR EACH ROW
BEGIN
  DECLARE v_book INT;
  IF NEW.borrower_type = 'student' AND NEW.student_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Choose the student borrowing this copy.';
  END IF;
  IF NEW.borrower_type = 'staff' AND NEW.staff_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Choose the staff member borrowing this copy.';
  END IF;
  IF NEW.due_date < NEW.issue_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The due date cannot be before the issue date.';
  END IF;
  SELECT book_id INTO v_book FROM book_copies WHERE id = NEW.copy_id;
  IF v_book IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The selected book copy does not exist.';
  END IF;
  IF v_book <> NEW.book_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The book copy does not belong to the selected book.';
  END IF;
  IF NEW.status IN ('borrowed','overdue') AND EXISTS (
       SELECT 1 FROM borrowings
        WHERE copy_id = NEW.copy_id AND status IN ('borrowed','overdue')
     ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'This copy is already on loan.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_visitors_bi_times$$
CREATE TRIGGER trg_visitors_bi_times BEFORE INSERT ON visitors
FOR EACH ROW
BEGIN
  IF NEW.time_in IS NULL THEN
    SET NEW.time_in = NOW();
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_visitors_bu_times$$
CREATE TRIGGER trg_visitors_bu_times BEFORE UPDATE ON visitors
FOR EACH ROW
BEGIN
  IF NEW.time_out IS NOT NULL AND NEW.time_in IS NOT NULL THEN
    IF NEW.time_out < NEW.time_in THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The visitor check-out time cannot be before check-in.';
    END IF;
    SET NEW.duration_minutes = TIMESTAMPDIFF(MINUTE, NEW.time_in, NEW.time_out);
  END IF;
END$$

DELIMITER ;
