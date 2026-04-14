-- Estructura de Base de Datos para Sistema de Cobranzas

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','supervisor') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario por defecto: admin / admin123
INSERT INTO `usuarios` (`username`, `password`, `rol`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('supervisor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'supervisor');

CREATE TABLE IF NOT EXISTS `cierres_semanales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zona` varchar(50) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `saldo_favor` decimal(10,2) DEFAULT 0.00,
  `saldo_concepto` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cierre` (`zona`, `fecha_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `detalles_diarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cierre_id` int(11) NOT NULL,
  `dia_semana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO') NOT NULL,
  `efectivo` decimal(10,2) DEFAULT 0.00,
  `transferencia` decimal(10,2) DEFAULT 0.00,
  `gasto_monto` decimal(10,2) DEFAULT 0.00,
  `gasto_concepto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`cierre_id`) REFERENCES `cierres_semanales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columnas adicionales necesarias para el módulo de horas y descuentos
-- Ejecutar solo si se crea la base desde cero, o usar los ALTER TABLE de abajo

-- Para instalaciones existentes, ejecutar manualmente:
ALTER TABLE `cierres_semanales`
  ADD COLUMN IF NOT EXISTS `descuento_creditos` decimal(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `descuento_creditos_concepto` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `valor_hora` decimal(10,2) DEFAULT 0.00;

ALTER TABLE `detalles_diarios`
  ADD COLUMN IF NOT EXISTS `hora_entrada` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `hora_salida` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `hora_entrada_tarde` time DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `hora_salida_tarde` time DEFAULT NULL;