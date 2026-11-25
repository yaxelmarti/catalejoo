-- Crear la tabla de productos
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(10,2) NOT NULL,
    imagen_url VARCHAR(500),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertar algunos productos de ejemplo
INSERT INTO productos (nombre, descripcion, precio, imagen_url) VALUES
('Smartphone Galaxy A14', 'Pantalla 6.6", 128GB, 4GB RAM', 189.99, 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=300'),
('Laptop HP Pavilion', '15.6", Intel Core i5, 8GB RAM, 256GB SSD', 599.99, 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=300'),
('Auriculares Bluetooth', 'Inalámbricos, cancelación de ruido, 20h batería', 49.99, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300'),
('Smartwatch Deportivo', 'Monitor cardíaco, GPS, resistente al agua', 129.99, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=300'),
('Tablet Android', '10.5", 64GB, ideal para entretenimiento', 199.99, 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=300');