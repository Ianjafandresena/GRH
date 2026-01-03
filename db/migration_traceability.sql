ALTER TABLE validation_cng ADD COLUMN val_by_emp INTEGER;
ALTER TABLE validation_cng ADD CONSTRAINT fk_val_by_emp FOREIGN KEY (val_by_emp) REFERENCES employee(emp_code);
