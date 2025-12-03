-- Crear triggers para actualizar progreso automáticamente
-- Ejecutar desde MySQL directamente

DELIMITER //

CREATE TRIGGER trg_subtarea_after_update 
AFTER UPDATE ON subtareas
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
    
    IF v_total > 0 AND v_completadas = v_total THEN
        UPDATE tasks 
        SET status = 'completed', 
            completed_at = NOW() 
        WHERE id = NEW.task_id AND status NOT IN ('completed', 'closed');
    END IF;
END//

CREATE TRIGGER trg_subtarea_after_insert 
AFTER INSERT ON subtareas
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

CREATE TRIGGER trg_subtarea_after_delete 
AFTER DELETE ON subtareas
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
