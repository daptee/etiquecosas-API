-- Carga las 4 formas de etiqueta clásicas (ya usadas hoy en public/css/etiquetas.css
-- y en las vistas de resources/views/tematica/**) como formas precargadas del
-- catálogo label_shapes, para que el editor del front las tenga disponibles.
INSERT INTO `label_shapes` (`name`, `shape_type`, `width_cm`, `height_cm`, `is_system`, `data`, `status_id`)
VALUES
  ('Maxi', 'rect', 4.4, 2.4, 1, JSON_OBJECT('css_class', 'etiqueta-maxi'), 1),
  ('Vertical', 'rect', 2.7, 3.2, 1, JSON_OBJECT('css_class', 'etiqueta-vertical'), 1),
  ('Super Maxi', 'rect', 6.0, 3.6, 1, JSON_OBJECT('css_class', 'etiqueta-super-maxi'), 1),
  ('Super Mini', 'rect', 2.9, 1.15, 1, JSON_OBJECT('css_class', 'etiqueta-super-mini'), 1);
