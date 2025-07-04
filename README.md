# WooCommerce Import Zakupowych Cen

Плагин WordPress для импорта закупочных цен из CSV или Excel и автоматического пересчета на основе граммовки вариантов.

## Требования
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- ACF Pro
- PhpSpreadsheet (для .xlsx)

## Установка
1. Скопируйте плагин в папку `/wp-content/plugins/`
2. Убедитесь, что установлена библиотека `PhpSpreadsheet`
3. Активируйте плагин в админке WordPress

## Использование
1. Перейдите в WooCommerce → Import cen zakupu
2. Загрузите CSV или Excel с колонками:
   - `nr. sku`
   - `cena_zakupu_za_1kg`
3. Плагин найдет все вариации вида `SKU-500G`, `SKU-1KG`
4. Цена будет рассчитана и сохранена в поле ACF `cena_zakupu`