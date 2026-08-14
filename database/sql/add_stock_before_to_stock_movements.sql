-- Suma el stock disponible inmediatamente antes de aplicar el movimiento,
-- para que la auditoría muestre "cuánto había antes" junto a "cuánto se quitó/agregó" (quantity).
-- NULL cuando el producto/variante no tiene control de stock (always_in_stock o sin stock configurado).
ALTER TABLE `stock_movements`
  ADD COLUMN `stock_before` INT NULL AFTER `quantity`;
