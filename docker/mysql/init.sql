-- =============================================================================
-- MySQL Initialization Script
-- Creates all required databases and grants permissions
-- =============================================================================

-- Create databases
CREATE DATABASE IF NOT EXISTS `admin` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `manager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `trader` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `signalvision` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant all privileges to the application user on all databases
GRANT ALL PRIVILEGES ON `admin`.* TO 'signalvision'@'%';
GRANT ALL PRIVILEGES ON `manager`.* TO 'signalvision'@'%';
GRANT ALL PRIVILEGES ON `trader`.* TO 'signalvision'@'%';
GRANT ALL PRIVILEGES ON `signalvision`.* TO 'signalvision'@'%';

-- Apply privileges
FLUSH PRIVILEGES;
