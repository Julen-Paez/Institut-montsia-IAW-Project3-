-- ============================================================
-- 01_init.sql
-- Script d'inicialització automàtica de la BD.
-- S'executa sol quan el contenidor MariaDB arrenca per primer cop.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `institut_montsia`;
USE `institut_montsia`;

-- ── Taules ──

CREATE TABLE IF NOT EXISTS `Alumnes` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `cognom1` varchar(50) NOT NULL,
  `cognom2` varchar(50),
  `correu` varchar(50) UNIQUE NOT NULL,
  `grupClasse` varchar(10) NOT NULL
);

CREATE TABLE IF NOT EXISTS `Ubicacions` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `nom` varchar(6)
);

CREATE TABLE IF NOT EXISTS `TipusMaterial` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `tipus` varchar(50),
  `model` varchar(50),
  `origen` ENUM('GENE','DEP') DEFAULT 'DEP'
);

CREATE TABLE IF NOT EXISTS `Material` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `idTipus` int NOT NULL,
  `idInventari` varchar(10) UNIQUE,
  `etiquetaDepInf` varchar(50) UNIQUE,
  `numSerie` varchar(50) UNIQUE,
  `macEthernet` varchar(50) UNIQUE,
  `macWifi` varchar(50) UNIQUE,
  `SACE` varchar(50) UNIQUE,
  `dataAdquisicio` date,
  `idUbicacio` int NOT NULL
);

CREATE TABLE IF NOT EXISTS `Assignacions` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `idMaterial` int NOT NULL,
  `idAlumne` int NOT NULL,
  `dataInici` date NOT NULL,
  `dataFinal` date
);

CREATE TABLE IF NOT EXISTS `Estats` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `estat` varchar(50)
);

CREATE TABLE IF NOT EXISTS `Incidencies` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `informacio` varchar(5000),
  `dataOberta` date,
  `dataTancada` date,
  `idAlumne` int,
  `idDispositiu` int,
  `idEstat` int
);

CREATE TABLE IF NOT EXISTS `Usuaris` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(100) UNIQUE NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` ENUM('admin','professor','editor','alumne') NOT NULL,
  `nom` varchar(100) NOT NULL,
  `idAlumne` int DEFAULT NULL
);

-- ── Claus foranes ──

ALTER TABLE `Material`
  ADD FOREIGN KEY (`idTipus`) REFERENCES `TipusMaterial`(`id`),
  ADD FOREIGN KEY (`idUbicacio`) REFERENCES `Ubicacions`(`id`);

ALTER TABLE `Assignacions`
  ADD FOREIGN KEY (`idMaterial`) REFERENCES `Material`(`id`),
  ADD FOREIGN KEY (`idAlumne`) REFERENCES `Alumnes`(`id`);

ALTER TABLE `Incidencies`
  ADD FOREIGN KEY (`idAlumne`) REFERENCES `Alumnes`(`id`),
  ADD FOREIGN KEY (`idDispositiu`) REFERENCES `Material`(`id`),
  ADD FOREIGN KEY (`idEstat`) REFERENCES `Estats`(`id`);

ALTER TABLE `Usuaris`
  ADD FOREIGN KEY (`idAlumne`) REFERENCES `Alumnes`(`id`) ON DELETE CASCADE;

-- ── Dades de prova ──

INSERT INTO `Ubicacions` (nom) VALUES ('ICA0'),('ICA1'),('ICA2');

INSERT INTO `TipusMaterial` (tipus, model, origen) VALUES
('Portàtil','Lenovo IdeaPad','DEP'),
('Portàtil','HP ProBook','GENE'),
('Ratolí','Logitech M100','DEP');

INSERT INTO `Alumnes` (nom, cognom1, cognom2, correu, grupClasse) VALUES
('Joan','García','López','joan.garcia@institutmontsia.cat','ASIX1A'),
('Maria','Pérez','Martín','maria.perez@institutmontsia.cat','ASIX1A'),
('Pau','Fernández',NULL,'pau.fernandez@institutmontsia.cat','ASIX1B');

INSERT INTO `Material` (idTipus, idInventari, numSerie, idUbicacio) VALUES
(1,'INV-001','SN-ABC123',1),
(1,'INV-002','SN-DEF456',1),
(2,'INV-003','SN-GHI789',2);

INSERT INTO `Estats` (estat) VALUES ('Pendent'),('En reparació'),('Resolt');

INSERT INTO `Assignacions` (idMaterial, idAlumne, dataInici) VALUES
(1,1,'2025-09-01'),
(2,2,'2025-09-01');

INSERT INTO `Incidencies` (informacio, dataOberta, idAlumne, idDispositiu, idEstat) VALUES
('La pantalla parpelleja i de vegades s\'apaga sola.','2025-10-15',1,1,1),
('El teclat no respon correctament a les tecles F1-F4.','2025-11-02',2,2,2);

-- Tots els usuaris tenen contrasenya: admin1234
INSERT INTO `Usuaris` (username, password, rol, nom, idAlumne) VALUES
('admin',       '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIU0vtvFN9b7oku', 'admin',     'Administrador', NULL),
('professor',   '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIU0vtvFN9b7oku', 'professor', 'Professor Admin', NULL),
('editor',      '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIU0vtvFN9b7oku', 'editor',    'Editor Demo', NULL),
('joan.garcia', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIU0vtvFN9b7oku', 'alumne',    'Joan García', 1);
