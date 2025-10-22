-- Seed data for initial admin user and basic statuses
-- PostgreSQL only. Ensure you created schema beforehand (see db/schema.sql)

-- Enable pgcrypto for bcrypt hashing
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Insert admin user (password: Admin@123)
INSERT INTO users (username, password_hash, role, is_active)
VALUES (
  'admin',
  crypt('Admin@123', gen_salt('bf')),
  'admin',
  TRUE
)
ON CONFLICT (username) DO NOTHING;
