-- Limpieza para volver a correr product_pdf_designs.sql / product_pdf_design_products.sql
-- desde cero (por ejemplo, si ya se había creado product_pdf_designs con el
-- esquema viejo que tenía product_id/theme_key como columnas propias).
-- Se borra primero la tabla que depende (FK) antes que la que referencia.
DROP TABLE IF EXISTS `product_pdf_design_products`;
DROP TABLE IF EXISTS `product_pdf_designs`;
