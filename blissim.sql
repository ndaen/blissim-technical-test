-- Test technique Blissim : schéma, jeu de données et requêtes (MySQL 8)
-- Rejouable : on supprime les tables dans l'ordre inverse des clés étrangères.

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS comments;

CREATE TABLE customers
(
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email     VARCHAR(255) NOT NULL,
  firstname VARCHAR(100) NOT NULL,
  lastname  VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE products
(
  id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name  VARCHAR(255)  NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT ck_products_price CHECK (price >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE orders
(
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id INT UNSIGNED NOT NULL,
  ordered_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_orders_ordered_at (ordered_at),
  KEY idx_orders_customer (customer_id),
  -- on garde l'historique : pas de suppression d'un client qui a commandé
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

CREATE TABLE order_items
(
  id         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED   NOT NULL,
  product_id INT UNSIGNED   NOT NULL,
  quantity   INT UNSIGNED   NOT NULL,
  -- prix payé à la commande, il ne bouge pas si products.price change
  unit_price DECIMAL(10, 2) NOT NULL,
  PRIMARY KEY (id),
  -- une ligne par produit et par commande, on joue sur la quantité
  UNIQUE KEY uq_order_items_order_product (order_id, product_id),
  KEY idx_order_items_product (product_id),
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
  CONSTRAINT ck_order_items_quantity CHECK (quantity > 0),
  CONSTRAINT ck_order_items_unit_price CHECK (unit_price >= 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- Commentaires de l'appli PHP. product_id vient de l'API, donc pas de clé étrangère.
CREATE TABLE comments
(
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  author     VARCHAR(100) NOT NULL,
  content    TEXT         NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comments_product (product_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;


INSERT INTO customers (id, email, firstname, lastname)
VALUES (1, 'alice.martin@example.com', 'Alice', 'Martin'),
       (2, 'bruno.durand@example.com', 'Bruno', 'Durand'),
       (3, 'chloe.bernard@example.com', 'Chloé', 'Bernard'),
       (4, 'david.petit@example.com', 'David', 'Petit'),
       (5, 'emma.robert@example.com', 'Emma', 'Robert'),
       (6, 'farid.nguyen@example.com', 'Farid', 'Nguyen');

-- Produits repris de https://dummyjson.com/products (mêmes ids que l'API). Le 9 n'a jamais été commandé.
-- Les prix des produits 1 et 3 ont augmenté depuis les premières commandes.
INSERT INTO products (id, name, price)
VALUES (1, 'Essence Mascara Lash Princess', 9.99),
       (2, 'Eyeshadow Palette with Mirror', 19.99),
       (3, 'Powder Canister', 14.99),
       (4, 'Red Lipstick', 12.99),
       (5, 'Red Nail Polish', 8.99),
       (6, 'Calvin Klein CK One', 49.99),
       (7, 'Chanel Coco Noir Eau De', 129.99),
       (8, 'Dior J''adore', 89.99),
       (9, 'Dolce Shine Eau de', 69.99);

INSERT INTO orders (id, customer_id, ordered_at)
VALUES (1, 1, NOW() - INTERVAL 40 DAY),
       (2, 2, NOW() - INTERVAL 33 DAY),
       (3, 3, NOW() - INTERVAL 21 DAY),
       (4, 1, NOW() - INTERVAL 15 DAY),
       (5, 4, NOW() - INTERVAL 10 DAY),
       (6, 5, NOW() - INTERVAL 8 DAY),
       (7, 2, NOW() - INTERVAL 6 DAY),
       (8, 1, NOW() - INTERVAL 5 DAY),
       (9, 3, NOW() - INTERVAL 4 DAY),
       (10, 4, NOW() - INTERVAL 3 DAY),
       (11, 5, NOW() - INTERVAL 2 DAY),
       (12, 2, NOW() - INTERVAL 1 DAY),
       (13, 1, NOW() - INTERVAL 6 HOUR),
       (14, 3, NOW() - INTERVAL 1 HOUR);

INSERT INTO order_items (order_id, product_id, quantity, unit_price)
VALUES (1, 1, 1, 8.99),
       (1, 5, 2, 8.99),
       (2, 2, 1, 19.99),
       (2, 3, 1, 13.99),
       (3, 1, 2, 8.99),
       (4, 4, 1, 12.99),
       (4, 6, 3, 49.99),
       (5, 3, 2, 13.99),
       (5, 7, 1, 129.99),
       (6, 1, 1, 9.99),
       (6, 2, 1, 19.99),
       (7, 5, 1, 8.99),
       (7, 7, 2, 129.99),
       (8, 1, 1, 9.99),
       (8, 8, 1, 89.99),
       (9, 4, 2, 12.99),
       (10, 6, 4, 49.99),
       (10, 7, 1, 129.99),
       (11, 2, 2, 19.99),
       (12, 5, 3, 8.99),
       (12, 7, 2, 129.99),
       (13, 7, 1, 129.99),
       (14, 3, 1, 14.99);

-- Requête 1 : clients ayant commandé le produit 1
-- DISTINCT pour ne pas lister deux fois un client qui l'a commandé deux fois
SELECT DISTINCT c.firstname, c.lastname
FROM customers c
       JOIN orders o ON o.customer_id = c.id
       JOIN order_items oi ON oi.order_id = o.id
WHERE oi.product_id = 1
ORDER BY c.lastname, c.firstname;

-- Requête 2 : quantités vendues par produit sur les 7 derniers jours, du plus vendu au moins vendu
-- Je prends 7 jours glissants (NOW() - 7 jours), pas 7 jours calendaires.
-- Tri secondaire par nom pour avoir un ordre stable en cas d'égalité.
SELECT p.name, SUM(oi.quantity) AS total_quantity
FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
       JOIN products p ON p.id = oi.product_id
WHERE o.ordered_at >= NOW() - INTERVAL 7 DAY
GROUP BY p.id, p.name
ORDER BY total_quantity DESC, p.name ASC;
