-- Un diseño con 2+ páginas (data.pages[]) puede salir como un PDF por
-- página ("separate", sufijo "-1", "-2"... al final del nombre de archivo)
-- o como un único PDF con todas las páginas juntas ("combined") — ver
-- EtiquetaService::generarEtiquetasDesdeDesign()/generarEtiquetasDesdePaginas().
-- Default 'separate': mismo comportamiento que tenía el sistema antes del
-- editor (cada "vista" en su propio archivo).
ALTER TABLE `product_pdf_designs`
  ADD COLUMN `pages_output_mode` VARCHAR(20) NOT NULL DEFAULT 'separate' AFTER `fill_mode`;
