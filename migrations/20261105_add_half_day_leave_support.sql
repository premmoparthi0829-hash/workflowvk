-- Run once on existing installations to support half-day leave applications.
ALTER TABLE employee_leave_balance
    MODIFY casual_leave DECIMAL(6,1) DEFAULT 12.0,
    MODIFY sick_leave DECIMAL(6,1) DEFAULT 12.0,
    MODIFY earned_leave DECIMAL(6,1) DEFAULT 15.0;

ALTER TABLE employee_leaves
    MODIFY total_days DECIMAL(6,1) NOT NULL;
