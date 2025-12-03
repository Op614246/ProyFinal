-- ============================================================
-- MIGRACIÓN: Evidencias de Tasks a Subtareas
-- Fecha: 2025-12-03
-- Descripción: Las subtareas son las unidades de trabajo que se completan.
--              Las evidencias deben ir ligadas a subtareas, no a tasks.
--              Los tasks solo miden el progreso general.
-- ============================================================

-- IMPORTANTE: Ejecutar en orden. Hacer backup antes.

-- ============================================================
-- PASO 1: Crear nueva tabla subtarea_evidencias
-- ============================================================
CREATE TABLE IF NOT EXISTS `subtarea_evidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subtarea_id` int(11) NOT NULL,
  `archivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ruta del archivo de evidencia',
  `tipo` enum('imagen','documento','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'imagen',
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre original del archivo subido',
  `tamanio` int(11) DEFAULT NULL COMMENT 'Tamaño en bytes',
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'Usuario que subió la evidencia',
  `observaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Notas al completar la subtarea',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subtarea_evidencias_subtarea` (`subtarea_id`),
  KEY `idx_subtarea_evidencias_fecha` (`created_at`),
  KEY `idx_subtarea_evidencias_user` (`uploaded_by`),
  CONSTRAINT `fk_subtarea_evidencias_subtarea` FOREIGN KEY (`subtarea_id`) REFERENCES `subtareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subtarea_evidencias_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PASO 2: Agregar campo completed_at a subtareas (si no existe)
-- ============================================================
ALTER TABLE `subtareas` 
ADD COLUMN IF NOT EXISTS `completed_at` datetime DEFAULT NULL COMMENT 'Fecha de completado' AFTER `completada`,
ADD COLUMN IF NOT EXISTS `completed_by` int(11) DEFAULT NULL COMMENT 'Usuario que completó' AFTER `completed_at`,
ADD COLUMN IF NOT EXISTS `completion_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Notas al completar' AFTER `completed_by`;

-- Agregar FK para completed_by
ALTER TABLE `subtareas`
ADD CONSTRAINT `fk_subtarea_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ============================================================
-- PASO 3: Migrar datos existentes de task_evidencias a subtarea_evidencias
-- (Solo si hay subtareas asociadas a los tasks con evidencias)
-- ============================================================
-- Esta migración asume que cada task tiene subtareas y elegimos la primera subtarea completada
-- o la primera subtarea del task para asociar las evidencias existentes.

INSERT INTO `subtarea_evidencias` (subtarea_id, archivo, tipo, nombre_original, tamanio, uploaded_by, created_at)
SELECT 
    (SELECT id FROM subtareas WHERE task_id = te.task_id ORDER BY completada DESC, id ASC LIMIT 1) as subtarea_id,
    te.archivo,
    te.tipo,
    te.nombre_original,
    te.tamanio,
    te.uploaded_by,
    te.created_at
FROM `task_evidencias` te
WHERE EXISTS (SELECT 1 FROM subtareas WHERE task_id = te.task_id);

-- ============================================================
-- PASO 4: Actualizar tabla evidencia_imagenes para referenciar subtarea_evidencias
-- (Si se usa como tabla de múltiples imágenes por evidencia)
-- ============================================================
-- Primero eliminar FK existente
ALTER TABLE `evidencia_imagenes` DROP FOREIGN KEY IF EXISTS `fk_imagenes_evidencia`;

-- Renombrar columna
ALTER TABLE `evidencia_imagenes` 
CHANGE COLUMN `evidencia_id` `subtarea_evidencia_id` int(11) NOT NULL;

-- Agregar nueva FK
ALTER TABLE `evidencia_imagenes`
ADD CONSTRAINT `fk_imagenes_subtarea_evidencia` 
FOREIGN KEY (`subtarea_evidencia_id`) REFERENCES `subtarea_evidencias` (`id`) ON DELETE CASCADE;

-- ============================================================
-- PASO 5: (OPCIONAL) Mantener task_evidencias como histórico o eliminarlo
-- Descomentar si deseas eliminar la tabla antigua
-- ============================================================
-- DROP TABLE IF EXISTS `task_evidencias`;

-- ============================================================
-- PASO 6: Crear vista para calcular progreso del task basado en subtareas
-- ============================================================
CREATE OR REPLACE VIEW `v_task_progreso` AS
SELECT 
    t.id as task_id,
    t.title,
    t.status,
    COUNT(s.id) as total_subtareas,
    SUM(CASE WHEN s.completada = 1 THEN 1 ELSE 0 END) as subtareas_completadas,
    ROUND(
        CASE 
            WHEN COUNT(s.id) = 0 THEN 0
            ELSE (SUM(CASE WHEN s.completada = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(s.id))
        END
    , 0) as progreso_calculado
FROM tasks t
LEFT JOIN subtareas s ON s.task_id = t.id
GROUP BY t.id;

-- ============================================================
-- PASO 7: Trigger para actualizar progreso del task automáticamente
-- ============================================================
DELIMITER //

DROP TRIGGER IF EXISTS `trg_subtarea_after_update`//
CREATE TRIGGER `trg_subtarea_after_update` 
AFTER UPDATE ON `subtareas`
FOR EACH ROW
BEGIN
    DECLARE v_total INT;
    DECLARE v_completadas INT;
    DECLARE v_progreso INT;
    
    -- Calcular progreso
    SELECT 
        COUNT(*),
        SUM(CASE WHEN completada = 1 THEN 1 ELSE 0 END)
    INTO v_total, v_completadas
    FROM subtareas
    WHERE task_id = NEW.task_id;
    
    -- Calcular porcentaje
    IF v_total > 0 THEN
        SET v_progreso = ROUND((v_completadas * 100.0) / v_total);
    ELSE
        SET v_progreso = 0;
    END IF;
    
    -- Actualizar progreso del task
    UPDATE tasks SET progreso = v_progreso WHERE id = NEW.task_id;
    
    -- Si todas las subtareas están completadas, marcar task como completed
    IF v_total > 0 AND v_completadas = v_total THEN
        UPDATE tasks 
        SET status = 'completed', 
            completed_at = NOW() 
        WHERE id = NEW.task_id AND status NOT IN ('completed', 'closed');
    END IF;
END//

DROP TRIGGER IF EXISTS `trg_subtarea_after_insert`//
CREATE TRIGGER `trg_subtarea_after_insert` 
AFTER INSERT ON `subtareas`
FOR EACH ROW
BEGIN
    DECLARE v_total INT;
    DECLARE v_completadas INT;
    DECLARE v_progreso INT;
    
    SELECT 
        COUNT(*),
        SUM(CASE WHEN completada = 1 THEN 1 ELSE 0 END)
    INTO v_total, v_completadas
    FROM subtareas
    WHERE task_id = NEW.task_id;
    
    IF v_total > 0 THEN
        SET v_progreso = ROUND((v_completadas * 100.0) / v_total);
    ELSE
        SET v_progreso = 0;
    END IF;
    
    UPDATE tasks SET progreso = v_progreso WHERE id = NEW.task_id;
END//

DROP TRIGGER IF EXISTS `trg_subtarea_after_delete`//
CREATE TRIGGER `trg_subtarea_after_delete` 
AFTER DELETE ON `subtareas`
FOR EACH ROW
BEGIN
    DECLARE v_total INT;
    DECLARE v_completadas INT;
    DECLARE v_progreso INT;
    
    SELECT 
        COUNT(*),
        SUM(CASE WHEN completada = 1 THEN 1 ELSE 0 END)
    INTO v_total, v_completadas
    FROM subtareas
    WHERE task_id = OLD.task_id;
    
    IF v_total > 0 THEN
        SET v_progreso = ROUND((v_completadas * 100.0) / v_total);
    ELSE
        SET v_progreso = 0;
    END IF;
    
    UPDATE tasks SET progreso = v_progreso WHERE id = OLD.task_id;
END//

DELIMITER ;

-- ============================================================
-- VERIFICACIÓN
-- ============================================================
-- SELECT * FROM subtarea_evidencias;
-- SELECT * FROM v_task_progreso;
