# WooCommerce Import Purchase Prices

A WordPress plugin for importing purchase prices from CSV or Excel files and automatically recalculating them based on product variation weights.

## Requirements

- WordPress 5.8 or higher  
- WooCommerce 6.0 or higher  
- PHP 7.4 or higher  
- ACF Pro plugin  
- PhpSpreadsheet library (for `.xlsx` support)

## Installation

1. Upload the plugin to the `/wp-content/plugins/` directory  
2. Ensure that the `PhpSpreadsheet` library is installed and autoloaded  
3. Activate the plugin through the WordPress admin panel  

## Usage

1. Navigate to **WooCommerce → Import Purchase Prices**  
2. Upload a CSV or Excel file containing the following columns:  
   - `nr. sku`  
   - `cena_zakupu_za_1kg`  
3. The plugin will automatically find all product variations like `SKU-500G`, `SKU-1KG`, and calculate the purchase price based on the weight  
4. The calculated price is saved to the ACF field `cena_zakupu` on each variation  

## Important Notes

- The product must be a **Variable Product**  
- It should have exactly **3 variations** with weights: `1KG`, `500G`, and `250G`  
- Variation SKUs must follow the structure `BASESKU-WEIGHT`, for example: `25128-1KG`  
- Example files are available in the `assets/sample-files` folder for testing  

## Sample CSV Format

```csv
nr. sku,cena_zakupu_za_1kg
25128,10.50
25129,13.70