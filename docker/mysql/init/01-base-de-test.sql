-- Base dédiée aux tests MySQL (concurrence, verrous, triggers).
CREATE DATABASE IF NOT EXISTS microfinance_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON microfinance_test.* TO 'microfinance'@'%';
FLUSH PRIVILEGES;
