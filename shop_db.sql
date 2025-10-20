CREATE DATABASE IF NOT EXISTS shop_db;
USE shop_db;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    location VARCHAR(255),
    md5 VARCHAR(255),
    paid TINYINT(1) DEFAULT 0,
    last_payment_time TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO products (name, price) VALUES
('T-Shirt', 2.00),
('Hoodie', 3.50),
('Cap', 1.50);
