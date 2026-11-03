-- Usuario que creó el diseño de PDF (editor, importar desde PDF o importar
-- desde producto). Lo usa el filtro "Usuario" del listado
-- (GET /product-pdf-designs?userId=). `created_at` ya existía en la tabla.
-- Los diseños que ya existían quedan con user_id NULL (no se sabe quién los creó).
ALTER TABLE `product_pdf_designs`
  ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `label_shape_id`,
  ADD CONSTRAINT `product_pdf_designs_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
