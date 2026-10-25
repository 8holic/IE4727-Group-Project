-- seed_doctors.sql
-- Two staff logins for the doctor page. Import once:
--   mysql -u root < sql/seed_doctors.sql
--
-- Logins (change the passwords by re-running with new hashes if needed):
--   Dr Bryan   bryan@localhost   bryan123
--   Dr Javier  javier@localhost  javier123
--
-- Hashes are password_hash(..., PASSWORD_DEFAULT) output. Re-running resets
-- both passwords to the values above.

USE jnb_clinic;

INSERT INTO doctors (name, email, password_hash) VALUES
  ('Dr Bryan',  'bryan@localhost',  '$2y$10$bZyjLPb/bnoHIAvo2vCy/OEaS2N0iTudfw4gfh/G1uzHfTxZZbTou'),
  ('Dr Javier', 'javier@localhost', '$2y$10$GhxMKU7RWxdRXwbOjyBMd.aJuOYcc5Chy5vKpLK/S.zueMFsczMBe')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  password_hash = VALUES(password_hash);
