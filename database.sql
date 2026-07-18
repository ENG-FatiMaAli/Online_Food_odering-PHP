-- ============================================================
-- Food Ordering System - Complete Database
-- PHP 8+ / MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `food_ordering_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `food_ordering_system`;

-- ============================================================
-- ROLES
-- ============================================================
CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `roles` (`name`,`description`) VALUES
('admin','Full system administrator'),
('staff','Restaurant staff member'),
('driver','Delivery driver'),
('customer','Regular customer');

-- ============================================================
-- USERS  (base table for all accounts)
-- ============================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT 'default.png',
  `is_active` TINYINT(1) DEFAULT 1,
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `reset_expires` DATETIME DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- CUSTOMER PROFILES
-- ============================================================
CREATE TABLE `customer_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `zip_code` VARCHAR(20) DEFAULT NULL,
  `latitude` DECIMAL(10,7) DEFAULT NULL,
  `longitude` DECIMAL(10,7) DEFAULT NULL,
  `loyalty_points` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- STAFF PROFILES
-- ============================================================
CREATE TABLE `staff_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `position` VARCHAR(100) DEFAULT NULL,
  `hire_date` DATE DEFAULT NULL,
  `salary` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DRIVER PROFILES
-- ============================================================
CREATE TABLE `driver_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL UNIQUE,
  `vehicle_type` VARCHAR(100) DEFAULT NULL,
  `license_plate` VARCHAR(30) DEFAULT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `current_latitude` DECIMAL(10,7) DEFAULT NULL,
  `current_longitude` DECIMAL(10,7) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FOOD CATEGORIES
-- ============================================================
CREATE TABLE `food_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(170) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- FOOD MENU ITEMS
-- ============================================================
CREATE TABLE `food_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(220) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `ingredients` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `discount_price` DECIMAL(10,2) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `preparation_time` INT DEFAULT 15 COMMENT 'minutes',
  `calories` INT DEFAULT NULL,
  `rating_avg` DECIMAL(3,2) DEFAULT 0.00,
  `rating_count` INT DEFAULT 0,
  `order_count` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `food_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FOOD ITEM IMAGES  (multiple images per item)
-- ============================================================
CREATE TABLE `food_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `food_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CART
-- ============================================================
CREATE TABLE `cart` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `food_id` INT UNSIGNED NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_user_food` (`user_id`,`food_id`)
) ENGINE=InnoDB;

-- ============================================================
-- COUPONS
-- ============================================================
CREATE TABLE `coupons` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `value` DECIMAL(10,2) NOT NULL,
  `min_order` DECIMAL(10,2) DEFAULT 0.00,
  `max_uses` INT DEFAULT NULL,
  `used_count` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `starts_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- ORDERS
-- ============================================================
CREATE TABLE `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT UNSIGNED NOT NULL,
  `driver_id` INT UNSIGNED DEFAULT NULL,
  `coupon_id` INT UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) DEFAULT 0.00,
  `delivery_fee` DECIMAL(10,2) DEFAULT 0.00,
  `tax` DECIMAL(10,2) DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_address` TEXT NOT NULL,
  `delivery_latitude` DECIMAL(10,7) DEFAULT NULL,
  `delivery_longitude` DECIMAL(10,7) DEFAULT NULL,
  `payment_method` ENUM('cod','mobile_money','card') DEFAULT 'cod',
  `payment_status` ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
  `order_status` ENUM('pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `order_notes` TEXT DEFAULT NULL,
  `estimated_delivery` DATETIME DEFAULT NULL,
  `actual_delivery` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ORDER ITEMS
-- ============================================================
CREATE TABLE `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `food_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `total` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- PAYMENTS
-- ============================================================
CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `method` ENUM('cod','mobile_money','card') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_id` VARCHAR(255) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- DELIVERY TRACKING
-- ============================================================
CREATE TABLE `delivery_tracking` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `driver_id` INT UNSIGNED NOT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `status` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`driver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- REVIEWS & RATINGS
-- ============================================================
CREATE TABLE `reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `food_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT DEFAULT NULL,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- FAVORITES
-- ============================================================
CREATE TABLE `favorites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `food_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`food_id`) REFERENCES `food_items`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_fav` (`user_id`,`food_id`)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','danger') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `link` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ACTIVITY LOGS
-- ============================================================
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- RESTAURANT SETTINGS
-- ============================================================
CREATE TABLE `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Default Admin (password: Admin@123)
INSERT INTO `users` (`role_id`,`first_name`,`last_name`,`email`,`password`,`phone`,`is_active`) VALUES
(1,'Admin','User','admin@foodapp.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+1234567890',1);

-- Staff
INSERT INTO `users` (`role_id`,`first_name`,`last_name`,`email`,`password`,`phone`,`is_active`) VALUES
(2,'Sara','Ahmed','staff@foodapp.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+1234567891',1);

-- Driver
INSERT INTO `users` (`role_id`,`first_name`,`last_name`,`email`,`password`,`phone`,`is_active`) VALUES
(3,'John','Driver','driver@foodapp.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+1234567892',1);

-- Customers
INSERT INTO `users` (`role_id`,`first_name`,`last_name`,`email`,`password`,`phone`,`is_active`) VALUES
(4,'Fatima','Ali','customer@foodapp.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+1234567893',1),
(4,'Ahmed','Hassan','ahmed@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+1234567894',1),
(4,'Mona','Ali','mona@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','+1234567895',1);

INSERT INTO `customer_profiles` (`user_id`,`address`,`city`,`state`,`zip_code`,`loyalty_points`) VALUES
(4,'123 Main St','New York','NY','10001',150),
(5,'456 Oak Ave','Brooklyn','NY','11201',80),
(6,'789 Pine Rd','Queens','NY','11375',220);

INSERT INTO `staff_profiles` (`user_id`,`position`,`hire_date`,`salary`) VALUES
(2,'Head Chef','2023-01-15',3500.00);

INSERT INTO `driver_profiles` (`user_id`,`vehicle_type`,`license_plate`,`is_available`) VALUES
(3,'Motorcycle','NY-1234',1);

-- Categories
INSERT INTO `food_categories` (`name`,`slug`,`description`,`is_active`,`sort_order`) VALUES
('Appetizers','appetizers','Start your meal with our delicious appetizers',1,1),
('Main Course','main-course','Hearty main dishes to satisfy your hunger',1,2),
('Burgers','burgers','Juicy burgers grilled to perfection',1,3),
('Pizza','pizza','Hand-tossed pizzas with premium toppings',1,4),
('Pasta','pasta','Italian-style pasta dishes',1,5),
('Salads','salads','Fresh and healthy salad options',1,6),
('Desserts','desserts','Sweet treats to end your meal',1,7),
('Beverages','beverages','Refreshing drinks and cocktails',1,8);

-- Food Items
INSERT INTO `food_items` (`category_id`,`name`,`slug`,`description`,`ingredients`,`price`,`discount_price`,`is_available`,`is_featured`,`preparation_time`,`calories`) VALUES
(1,'Crispy Spring Rolls','crispy-spring-rolls','Golden crispy spring rolls with sweet chili sauce','Cabbage, Carrot, Spring Onion, Breadcrumbs, Oil',8.99,NULL,1,1,10,250),
(1,'Garlic Bread','garlic-bread','Toasted bread with garlic butter and herbs','Bread, Garlic, Butter, Parsley, Cheese',6.49,4.99,1,0,8,180),
(1,'Chicken Wings','chicken-wings','Spicy buffalo chicken wings with ranch dip','Chicken, Hot Sauce, Butter, Garlic, Ranch',12.99,NULL,1,1,15,420),
(2,'Grilled Salmon','grilled-salmon','Fresh Atlantic salmon with lemon butter sauce','Salmon, Lemon, Butter, Dill, Garlic',24.99,19.99,1,1,20,380),
(2,'Steak Deluxe','steak-deluxe','Premium ribeye steak cooked to your preference','Beef Ribeye, Salt, Pepper, Rosemary, Garlic',32.99,NULL,1,1,25,550),
(2,'Chicken Tikka Masala','chicken-tikka-masala','Creamy tomato curry with tender chicken pieces','Chicken, Tomato, Cream, Spices, Rice',18.99,NULL,1,0,20,480),
(3,'Classic Cheeseburger','classic-cheeseburger','Beef patty with cheddar, lettuce, tomato, and special sauce','Beef, Cheddar, Lettuce, Tomato, Bun, Sauce',14.99,NULL,1,1,12,650),
(3,'Bacon BBQ Burger','bacon-bbq-burger','Smoky BBQ burger with crispy bacon and onion rings','Beef, Bacon, BBQ Sauce, Onion Rings, Cheese',17.99,15.99,1,0,15,780),
(4,'Margherita Pizza','margherita-pizza','Classic pizza with mozzarella, tomato sauce, and basil','Dough, Tomato Sauce, Mozzarella, Basil, Olive Oil',16.99,NULL,1,1,18,800),
(4,'Pepperoni Pizza','pepperoni-pizza','Loaded with pepperoni and melted mozzarella cheese','Dough, Tomato Sauce, Mozzarella, Pepperoni',19.99,NULL,1,0,18,920),
(5,'Spaghetti Carbonara','spaghetti-carbonara','Creamy pasta with pancetta and parmesan cheese','Spaghetti, Pancetta, Egg, Parmesan, Black Pepper',16.99,NULL,1,0,15,720),
(5,'Penne Arrabiata','penne-arrabiata','Spicy tomato sauce with penne pasta','Penne, Tomato, Chili, Garlic, Basil',14.99,NULL,1,0,12,550),
(6,'Caesar Salad','caesar-salad','Crisp romaine with Caesar dressing and croutons','Romaine, Parmesan, Croutons, Caesar Dressing',11.99,NULL,1,0,8,320),
(6,'Greek Salad','greek-salad','Fresh vegetables with feta cheese and olive oil dressing','Tomato, Cucumber, Feta, Olives, Onion, Olive Oil',12.99,NULL,1,0,8,280),
(7,'Chocolate Lava Cake','chocolate-lava-cake','Warm chocolate cake with a molten center','Chocolate, Butter, Eggs, Sugar, Flour',9.99,NULL,1,1,20,450),
(7,'Cheesecake','cheesecake','New York style creamy cheesecake','Cream Cheese, Sugar, Eggs, Graham Crust, Vanilla',8.99,NULL,1,0,5,380),
(8,'Fresh Lemonade','fresh-lemonade','Freshly squeezed lemonade with mint','Lemon, Sugar, Water, Mint',4.99,NULL,1,0,3,120),
(8,'Mango Smoothie','mango-smoothie','Creamy mango smoothie with yogurt','Mango, Yogurt, Honey, Ice',6.99,5.99,1,0,5,180);

-- Coupons
INSERT INTO `coupons` (`code`,`type`,`value`,`min_order`,`max_uses`,`starts_at`,`expires_at`) VALUES
('WELCOME10','percentage',10.00,15.00,100,'2024-01-01','2026-12-31'),
('SAVE5','fixed',5.00,20.00,50,'2024-01-01','2026-12-31'),
('FOODIE20','percentage',20.00,30.00,30,'2024-06-01','2026-12-31');

-- Sample Orders
INSERT INTO `orders` (`order_number`,`customer_id`,`subtotal`,`delivery_fee`,`tax`,`total`,`delivery_address`,`payment_method`,`payment_status`,`order_status`,`order_notes`) VALUES
('ORD-2024-001',4,38.98,5.00,3.12,47.10,'123 Main St, New York, NY 10001','cod','paid','delivered','Extra sauce please'),
('ORD-2024-002',5,34.98,5.00,2.80,42.78,'456 Oak Ave, Brooklyn, NY 11201','mobile_money','paid','delivered',NULL),
('ORD-2024-003',6,42.98,5.00,3.44,51.42,'789 Pine Rd, Queens, NY 11375','cod','paid','preparing','No onions'),
('ORD-2024-004',4,24.98,5.00,2.00,31.98,'123 Main St, New York, NY 10001','mobile_money','pending','pending',NULL),
('ORD-2024-005',5,50.97,0.00,4.08,55.05,'456 Oak Ave, Brooklyn, NY 11201','card','paid','confirmed','Ring the doorbell');

INSERT INTO `order_items` (`order_id`,`food_id`,`name`,`price`,`quantity`,`total`) VALUES
(1,1,'Crispy Spring Rolls',8.99,2,17.98),
(1,7,'Classic Cheeseburger',14.99,1,14.99),
(2,3,'Chicken Wings',12.99,1,12.99),
(2,9,'Margherita Pizza',16.99,1,16.99),
(3,5,'Steak Deluxe',32.99,1,32.99),
(3,13,'Caesar Salad',11.99,1,11.99),
(4,4,'Grilled Salmon',24.99,1,24.99),
(5,9,'Margherita Pizza',16.99,2,33.98),
(5,17,'Fresh Lemonade',4.99,2,9.98),
(5,15,'Chocolate Lava Cake',9.99,1,9.99);

-- Payments
INSERT INTO `payments` (`order_id`,`method`,`amount`,`status`,`paid_at`) VALUES
(1,'cod',47.10,'completed','2024-01-15 14:30:00'),
(2,'mobile_money',42.78,'completed','2024-01-16 12:15:00'),
(3,'cod',51.42,'pending',NULL),
(4,'mobile_money',31.98,'pending',NULL),
(5,'card',55.05,'completed','2024-01-17 18:45:00');

-- Reviews
INSERT INTO `reviews` (`food_id`,`user_id`,`order_id`,`rating`,`comment`) VALUES
(1,4,1,5,'Amazing spring rolls! Very crispy.'),
(7,4,1,4,'Great burger, loved the special sauce.'),
(9,5,2,5,'Best pizza in town!'),
(3,5,2,4,'Wings were spicy and delicious.'),
(5,6,3,4,'Perfectly cooked steak.');

UPDATE `food_items` SET `rating_avg`=4.50, `rating_count`=2, `order_count`=3 WHERE `id`=1;
UPDATE `food_items` SET `rating_avg`=4.00, `rating_count`=1, `order_count`=1 WHERE `id`=7;
UPDATE `food_items` SET `rating_avg`=5.00, `rating_count`=1, `order_count`=3 WHERE `id`=9;
UPDATE `food_items` SET `rating_avg`=4.50, `rating_count`=2, `order_count`=2 WHERE `id`=3;
UPDATE `food_items` SET `rating_avg`=4.00, `rating_count`=1, `order_count`=1 WHERE `id`=5;
UPDATE `food_items` SET `rating_avg`=4.50, `rating_count`=1, `order_count`=1 WHERE `id`=13;
UPDATE `food_items` SET `order_count`=1 WHERE `id`=4;
UPDATE `food_items` SET `order_count`=2 WHERE `id`=16;
UPDATE `food_items` SET `order_count`=1 WHERE `id`=15;
UPDATE `food_items` SET `order_count`=1 WHERE `id`=17;

-- Notifications
INSERT INTO `notifications` (`user_id`,`title`,`message`,`type`,`link`) VALUES
(4,'Order Confirmed','Your order ORD-2024-001 has been confirmed!','success','customer/orders.php'),
(4,'Order Delivered','Your order ORD-2024-001 has been delivered. Enjoy your meal!','success','customer/orders.php'),
(5,'Order Confirmed','Your order ORD-2024-002 has been confirmed!','success','customer/orders.php'),
(4,'Welcome!','Welcome to FoodieApp. Enjoy 10% off with code WELCOME10','info',NULL),
(2,'New Order','New order ORD-2024-003 received','warning','admin/modules/orders/');

-- Activity Logs
INSERT INTO `activity_logs` (`user_id`,`action`,`description`,`ip_address`) VALUES
(1,'Login','Admin logged in successfully','127.0.0.1'),
(4,'Register','New customer registered','127.0.0.1'),
(4,'Place Order','Order ORD-2024-001 placed','127.0.0.1'),
(5,'Place Order','Order ORD-2024-002 placed','127.0.0.1'),
(1,'Update Settings','Restaurant settings updated','127.0.0.1');

-- Settings
INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
('restaurant_name','FoodieApp Restaurant'),
('restaurant_email','info@foodapp.com'),
('restaurant_phone','+1234567890'),
('restaurant_address','123 Food Street, New York, NY 10001'),
('delivery_fee','5.00'),
('free_delivery_min','30.00'),
('tax_rate','8.00'),
('currency','$'),
('currency_code','USD'),
('opening_time','09:00'),
('closing_time','22:00'),
('min_order_amount','10.00');
