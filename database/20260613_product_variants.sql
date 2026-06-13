ALTER TABLE products
  ADD COLUMN IF NOT EXISTS product_type VARCHAR(20) NOT NULL DEFAULT 'single' AFTER name,
  ADD COLUMN IF NOT EXISTS parent_product_id INT NULL AFTER product_type,
  ADD COLUMN IF NOT EXISTS variant_flavors TEXT NULL AFTER product_type,
  ADD COLUMN IF NOT EXISTS variant_sizes TEXT NULL AFTER variant_flavors;

UPDATE products
SET product_type='single',
    variant_flavors=NULL,
    variant_sizes=NULL
WHERE product_type IS NULL OR product_type='' OR product_type NOT IN ('single', 'grouped');

CREATE TABLE IF NOT EXISTS product_variants (
  id INT NOT NULL AUTO_INCREMENT,
  product_id INT NOT NULL,
  source_product_id INT NULL,
  variant_name VARCHAR(255) NOT NULL,
  sku VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  image_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_variant_sku (sku),
  KEY idx_product_variants_product (product_id, sort_order),
  KEY idx_product_variants_source (source_product_id),
  CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id)
    REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE product_variants
  ADD COLUMN IF NOT EXISTS source_product_id INT NULL AFTER product_id;
