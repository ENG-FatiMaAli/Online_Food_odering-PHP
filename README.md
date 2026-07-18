# FoodieApp - Food Ordering System

A comprehensive food ordering system with separate panels for administrators and customers, featuring secure authentication, mobile-responsive design, and real-time order management.

<img width="883" height="635" alt="image" src="https://github.com/user-attachments/assets/28fb827e-ea02-426a-9a7b-003cdbac6d68" />

<!-- <img width="512" height="768" alt="image" src="https://github.com/user-attachments/assets/d4cadaa3-11e0-4357-84d1-fdc8313329bd" /> -->


## Installation Guide

### Requirements
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- XAMPP, WAMP, Laragon, or any PHP development server
- Modern web browser

### Installation Steps

#### Step 1: Download/Extract Files
1. Copy the `Food_Ordering_System` folder to your web server's document root:
   - **XAMPP**: `C:\xampp\htdocs\`
   - **WAMP**: `C:\wamp\www\`
   - **Laragon**: `C:\laragon\www\`
   - **Linux/Mac**: `/var/www/html/`

#### Step 2: Create Database
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Click on the **SQL** tab
3. Open the `database.sql` file from the project folder
4. Copy and paste the entire SQL content
5. Click **Go** to execute the query

**OR** using command line:
```bash
mysql -u root -p < database.sql
```

#### Step 3: Configure Database Connection
1. Open `config/database.php`
2. Update the database credentials if needed:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'food_ordering_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

#### Step 4: Configure Base URL
1. Open `config/constants.php`
2. Update `BASE_URL` to match your project folder:
```php
define('BASE_URL', '/Food_Ordering_System');
```

#### Step 5: Set Upload Permissions
Make sure the `uploads/` directory is writable:
```bash
chmod -R 755 uploads/
```

#### Step 6: Start Development Server
Option A - Using XAMPP/WAMP:
1. Start Apache and MySQL from XAMPP/WAMP control panel
2. Navigate to http://localhost/Food_Ordering_System/

Option B - Using PHP built-in server:
```bash
cd Food_Ordering_System
php -S localhost:8000
```
3. Navigate to http://localhost:8000/

### Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@foodapp.com | password |
| Staff | staff@foodapp.com | password |
| Driver | driver@foodapp.com | password |
| Customer | customer@foodapp.com | password |

### Features

#### Authentication System
- Secure login with password hashing (bcrypt)
- Customer registration
- Admin/Staff/Driver login
- Forgot password with token reset
- Change password
- Session management with regeneration
- CSRF protection on all forms

#### Admin Panel
- Dashboard with statistics cards and charts (Chart.js)
- User Management (CRUD)
- Customer Management
- Staff Management
- Delivery Driver Management
- Food Category Management
- Food Menu Management (with multiple images)
- Order Management with status tracking
- Payment Management
- Delivery Tracking with driver assignment
- Coupon & Discount Management
- Reviews & Ratings moderation
- Notification Management
- Reports (Daily/Monthly/Annual/Best Sellers/Customers)
- Restaurant Settings
- Activity Logs
- Dark/Light mode
- Responsive sidebar

#### Customer Panel
- Browse menu with search and category filters
- Food item details with gallery, reviews, ingredients
- Shopping cart with quantity controls
- Coupon/discount application
- Checkout with multiple payment methods
- Order history with status tracking
- Reorder functionality
- Favorite foods management
- Profile management
- Change password

#### Security Features
- Password hashing with bcrypt (PHP password_hash)
- CSRF token protection on all forms
- PDO prepared statements (SQL injection prevention)
- Input sanitization with htmlspecialchars
- Role-based access control
- Session regeneration on login
- Secure file upload validation

### Project Structure
```
Food_Ordering_System/
├── admin/                    # Admin panel
│   ├── includes/             # Header, sidebar, footer
│   ├── modules/              # Admin CRUD modules
│   │   ├── users/
│   │   ├── customers/
│   │   ├── staff/
│   │   ├── drivers/
│   │   ├── categories/
│   │   ├── menu/
│   │   ├── orders/
│   │   ├── payments/
│   │   ├── delivery/
│   │   ├── coupons/
│   │   ├── reviews/
│   │   ├── notifications/
│   │   ├── reports/
│   │   ├── settings/
│   │   └── activity_logs/
│   └── index.php             # Admin dashboard
├── customer/                 # Customer panel
│   ├── includes/             # Header, footer, functions
│   ├── modules/              # Customer pages
│   │   ├── menu/
│   │   ├── cart/
│   │   ├── checkout/
│   │   ├── orders/
│   │   ├── profile/
│   │   └── favorites/
│   └── index.php             # Customer home/menu
├── api/                      # AJAX API endpoints
│   ├── cart.php
│   └── favorites.php
├── assets/                   # Static assets
│   ├── css/
│   │   ├── style.css         # Customer styles
│   │   └── admin.css         # Admin styles
│   ├── js/
│   │   ├── main.js           # Customer JS
│   │   ├── admin.js          # Admin JS
│   │   └── charts.js         # Chart.js configs
│   └── images/
├── config/                   # Configuration
│   ├── database.php          # Database connection
│   └── constants.php         # App constants
├── includes/                 # Shared includes
│   └── helpers.php           # Helper functions
├── uploads/                  # Uploaded files
│   ├── food/
│   └── avatar/
├── database.sql              # Database schema + sample data
├── index.php                 # Landing page
├── login.php                 # Login page
├── register.php              # Registration page
├── forgot_password.php       # Forgot password
├── reset_password.php        # Reset password
├── change_password.php       # Change password
└── logout.php                # Logout handler
```

### Database Tables
- `roles` - User roles (admin, staff, driver, customer)
- `users` - All user accounts
- `customer_profiles` - Customer-specific data
- `staff_profiles` - Staff-specific data
- `driver_profiles` - Driver-specific data
- `food_categories` - Food categories
- `food_items` - Menu items
- `food_images` - Multiple images per food item
- `cart` - Shopping cart
- `coupons` - Discount coupons
- `orders` - Customer orders
- `order_items` - Individual order items
- `payments` - Payment records
- `delivery_tracking` - Delivery location tracking
- `reviews` - Food reviews and ratings
- `favorites` - Customer favorites
- `notifications` - User notifications
- `activity_logs` - System activity logs
- `settings` - Restaurant configuration

### Technology Stack
- **Backend**: PHP 8+, PDO (MySQL)
- **Database**: MySQL 8.0 / MariaDB
- **Frontend**: HTML5, CSS3, Bootstrap 5.3
- **Icons**: Font Awesome 6.5
- **Charts**: Chart.js 4
- **Alerts**: SweetAlert2
- **Tables**: DataTables
- **Fonts**: Google Fonts (Poppins)

### Browser Support
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

### Troubleshooting
1. **Blank page**: Enable error reporting in php.ini (`display_errors = On`)
2. **Database connection failed**: Check credentials in `config/database.php`
3. **404 errors**: Ensure `BASE_URL` is correctly set in `config/constants.php`
4. **Upload errors**: Check `uploads/` directory permissions
5. **Session errors**: Ensure PHP sessions are enabled in php.ini

### License
This project is for educational purposes. Free to use and modify.
