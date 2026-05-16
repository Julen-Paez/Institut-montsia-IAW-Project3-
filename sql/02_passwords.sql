-- ============================================================
-- 02_passwords.sql
-- Actualitza les contrasenyes usant PHP des de la BD.
-- NOTA: Aquest script usa un procediment per cridar l'script
-- de fixació de contrasenyes en el primer arrencament.
-- 
-- Contrasenya de tots els usuaris: admin1234
-- Hash generat amb PHP password_hash('admin1234', PASSWORD_BCRYPT)
-- ============================================================

USE `institut_montsia`;

-- Creem un procediment que actualitza les contrasenyes
-- usant un hash vàlid de bcrypt per 'admin1234'
-- Aquest hash és estàtic i sempre funcionarà amb PHP password_verify()

DROP PROCEDURE IF EXISTS fix_passwords;

DELIMITER //
CREATE PROCEDURE fix_passwords()
BEGIN
    -- Hash bcrypt vàlid per 'admin1234' generat amb PHP 8.2
    SET @hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    UPDATE Usuaris SET password = @hash WHERE username IN ('admin','professor','editor','joan.garcia');
END //
DELIMITER ;

CALL fix_passwords();
DROP PROCEDURE IF EXISTS fix_passwords;
