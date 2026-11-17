CREATE DATABASE IF NOT EXISTS real_estate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE real_estate_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','manager','landlord') NOT NULL DEFAULT 'customer',
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE properties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  property_type ENUM('house','apartment','land','commercial') NOT NULL DEFAULT 'house',
  listed_for ENUM('rent','sale') NOT NULL DEFAULT 'rent',
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  location VARCHAR(120) NOT NULL,
  bedrooms TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bathrooms TINYINT UNSIGNED NOT NULL DEFAULT 0,
  photo VARCHAR(255) DEFAULT NULL,
  status ENUM('available','taken') NOT NULL DEFAULT 'available',
  manager_id INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_property_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE inquiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  customer_id INT NOT NULL,
  note TEXT,
  reply TEXT,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_inquiry_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_inquiry_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  name VARCHAR(60) NOT NULL,
  room_type ENUM('single','double','studio','bedsitter','master','shop','office','plot','whole') NOT NULL DEFAULT 'single',
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('available','occupied','maintenance') NOT NULL DEFAULT 'available',
  description VARCHAR(255) DEFAULT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_room_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  property_id INT NOT NULL,
  customer_id INT NOT NULL,
  move_in DATE DEFAULT NULL,
  note TEXT,
  status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  reply TEXT,
  responded_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT DEFAULT NULL,
  customer_id INT NOT NULL,
  property_id INT DEFAULT NULL,
  room_id INT DEFAULT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  method ENUM('cash','mobile_money','bank','card') NOT NULL DEFAULT 'cash',
  reference VARCHAR(80) DEFAULT NULL,
  status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  note VARCHAR(255) DEFAULT NULL,
  recorded_by INT DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_booking (booking_id),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB;

CREATE TABLE maintenance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  property_id INT DEFAULT NULL,
  room_id INT DEFAULT NULL,
  booking_id INT DEFAULT NULL,
  subject VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  reply TEXT DEFAULT NULL,
  handled_by INT DEFAULT NULL,
  responded_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_maintenance_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_maintenance_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
  CONSTRAINT fk_maintenance_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE maintenance_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  photo VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_maintenance_photo_request FOREIGN KEY (request_id) REFERENCES maintenance_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('contact_address', 'Tuskle Road, Kampala'),
('contact_email', 'info@ruth-estates.com'),
('contact_phone', '0700 000 000'),
('payment_instructions', 'Pay by mobile money or bank transfer and quote your booking reference.');

INSERT INTO users (name, email, phone, password_hash, role) VALUES
('John Kariuki', 'landlord@ruth-estates.com', '0700000001', '$2y$10$BoCvH2rtIF5qJWXkJvVzJeAJdi4akAhPylcxDZsBzR3OCtNGIO.Ga', 'landlord'),
('Sarah Wanjiku', 'manager@ruth-estates.com', '0700000002', '$2y$10$uoSljrWIwU2yAgTCnEaTp.ZT3pnu/EuwYOx6b8kY8tdW0WlPqgGYW', 'manager'),
('Brian Otieno', 'customer@ruth-estates.com', '0700000003', '$2y$10$jSJJftNWqLuQHlCl9iOfTebOaW5S0/80VyzUvI2LKEzXx00Ofa0v.', 'customer');

INSERT INTO properties (title, description, property_type, listed_for, price, location, bedrooms, bathrooms, photo, manager_id) VALUES
('4 Bedroom Townhouse - Karen', 'Spacious modern townhouse with a private garden, dsq and ample parking. Located in a gated community with 24hr security.', 'house', 'rent', 85000, 'Karen, Kampala', 4, 3, 'assets/img/prop-house1.jpg', 2),
('Modern 2 Bedroom Apartment', 'Newly built apartment with fitted kitchen, built-in wardrobes and a lift. Walking distance to the main highway.', 'apartment', 'rent', 32000, 'Kilimani, Kampala', 2, 2, 'assets/img/prop-apartment1.jpg', 2),
('Commercial Plot - Kapseret', 'Prime plot along the tarmac road, fully fenced. Ideal for rental flats or a petrol station.', 'land', 'sale', 2800000, 'Kapseret, Eldoret', 0, 0, 'assets/img/prop-land1.jpg', 2),
('3 Bedroom Bungalow - Milimani', 'Classic bungalow on half an acre with mature garden, servants quarters and borehole water.', 'house', 'sale', 9500000, 'Milimani, Kisumu', 3, 2, 'assets/img/prop-house3.jpg', 2),
('Bedsitter Units - Ngara', 'Well maintained bedsitter units with shared laundry area and a caretaker on site. Water is always available.', 'apartment', 'rent', 13500, 'Ngara, Kampala', 1, 1, 'assets/img/prop-apartment2.jpg', 2);

INSERT INTO rooms (property_id, name, room_type, price, capacity, status, description, photo) VALUES
(1, 'Whole Townhouse', 'whole', 85000, 6, 'available', 'The full 4 bedroom townhouse with private garden and DSQ.', 'assets/img/prop-house1.jpg'),
(1, 'Garden Cottage', 'double', 35000, 2, 'available', 'Self-contained cottage at the back of the compound.', 'assets/img/prop-room1.jpg'),
(2, 'Apartment 2A', 'double', 32000, 4, 'available', 'Two bedroom unit on the second floor with lift access.', 'assets/img/prop-apartment1.jpg'),
(2, 'Apartment 2B', 'double', 32000, 4, 'occupied', 'Two bedroom unit on the second floor with lift access.', 'assets/img/prop-room2.jpg'),
(3, 'Plot A - Front', 'plot', 1400000, 0, 'available', '50 by 100 plot along the tarmac, fully fenced.', 'assets/img/prop-land1.jpg'),
(3, 'Plot B - Rear', 'plot', 1400000, 0, 'available', '50 by 100 plot, ready for development.', 'assets/img/prop-land1.jpg'),
(4, 'Whole Bungalow', 'whole', 9500000, 5, 'available', 'The entire bungalow on half an acre with mature garden.', 'assets/img/prop-house3.jpg'),
(5, 'Bedsitter 1', 'bedsitter', 13500, 1, 'available', 'Ground floor bedsitter with private shower and kitchenette.', 'assets/img/prop-room1.jpg'),
(5, 'Bedsitter 2', 'bedsitter', 13500, 1, 'available', 'First floor bedsitter with a balcony.', 'assets/img/prop-room2.jpg'),
(5, 'Bedsitter 3', 'bedsitter', 13000, 1, 'available', 'Quiet rear bedsitter, shared laundry area.', 'assets/img/prop-room3.jpg'),
(5, 'Bedsitter 4', 'bedsitter', 13000, 1, 'occupied', 'Corner bedsitter with extra storage.', 'assets/img/prop-room3.jpg');

INSERT INTO bookings (room_id, property_id, customer_id, move_in, note) VALUES
(8, 5, 3, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'I would like to move in next week. Is the deposit negotiable?');

INSERT INTO inquiries (property_id, customer_id, note, status, created_at) VALUES
(1, 3, 'I would like to visit the townhouse on Saturday morning.', 'pending', NOW());

INSERT INTO messages (sender_id, receiver_id, body) VALUES
(3, 2, 'Hello Sarah, is the Karen townhouse still available for viewing this weekend?');

INSERT INTO maintenance_requests (customer_id, property_id, room_id, subject, description, priority) VALUES
(3, 5, 8, 'Leaking kitchen tap', 'The kitchen tap has been dripping since Monday and the floor gets wet.', 'normal');
