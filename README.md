# Mugna Leather Arts

A comprehensive e-commerce platform built with PHP, featuring user authentication, product management, shopping cart functionality, and order processing.

> **Note:** This project was developed as a final term requirement for academic compliance. It serves as a demonstration of web development skills.

> **Privacy Notice:** This is a proprietary project. The database schema and full source code are not publicly available. For access to the database schema or inquiries about purchasing the project, please contact the developers.

## Features

### Implemented Features

- **User Management**
  - User registration and login
  - Google Authentication integration
  - Password reset functionality
  - User profile management
  - OTP verification

- **Product Management**
  - Product browsing and searching
  - Product categories
  - Search suggestions

- **Shopping Experience**
  - Shopping cart functionality
  - Add to cart
  - View cart
  - Checkout process
  - Order confirmation

- **Admin Features**
  - Admin dashboard
  - Product management
  - Order management
  - User management
  - Print reports
    - Sales reports
    - Inventory reports
    - User reports
  - Export to PDF
    - User data export
    - Order history export
    - Product catalog export

- **Additional Features**
  - Real-time notifications
  - Shipping fee calculation
  - Responsive design
  - Secure payment processing
  

### Planned Features (Not Yet Implemented)

- **Product Management**
  - Product wishlist
  - Product rating system
  - Deals and promotions

- **Shopping Experience**
  - Order tracking via receipt
  - Customer support system

- **Admin Features**
  - Bulk actions for products
  - Advanced product CRUD operations

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (PHP package manager)
- XAMPP/WAMP/MAMP (for local development)

## Step-by-Step Installation

### Step 1: Environment Setup
1. Create a new file named `.env` in the root directory
2. Copy and paste the following configuration:
   ```env
   DB_HOST=localhost
   DB_NAME=mugna
   DB_USER=your_database_user
   DB_PASS=your_database_password
   EMAIL_ADMIN=your_admin_email@gmail.com
   EMAIL_PASS=your_gmail_app_password
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   ```
3. Replace the placeholder values with your actual credentials

### Step 2: Database Setup
1. Open your MySQL client (e.g., phpMyAdmin)
2. Create a new database named `mugna`
3. Contact developers at dharzdesu@gmail.com to request the database schema
4. Import the database using the command:
   ```bash
   mysql -u your_database_user -p mugna < import.sql
   ```

### Step 3: Google OAuth Setup
1. Visit [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project
3. Enable the Google+ API
4. Go to Credentials
5. Create OAuth 2.0 Client ID
6. Add authorized redirect URI:
   `http://localhost/mugna/googleAuth/google-callback.php`
7. Copy the Client ID and Client Secret
8. Update the `.env` file with these credentials

### Step 4: Email Configuration
1. Set up a Gmail account for sending emails
2. Enable 2-Step Verification in your Google Account
3. Generate an App Password:
   - Go to Google Account Settings
   - Security > App Passwords
   - Generate new app password
4. Update the `.env` file with your email and app password

### Step 5: Install Dependencies
1. Open terminal in project directory
2. Run the following command:
   ```bash
   composer install
   ```

### Step 6: Web Server Setup
1. Ensure your web server (Apache/Nginx) is running
2. Point your web server to the project directory
3. Make sure the `uploads` directory is writable

### Step 7: Access the System
1. Open your web browser
2. Navigate to: `http://localhost/mugna`
3. You should see the login page

## Directory Structure

- `admin/` - Admin panel files
- `components/` - Reusable PHP components
- `css/` - Stylesheet files
- `data/` - Data files
- `images/` - Image assets
- `includes/` - PHP include files
- `js/` - JavaScript files
- `uploads/` - User uploaded files
- `vendor/` - Composer dependencies

## Security Considerations

- Keep your database credentials secure
- Regularly update dependencies
- Use HTTPS in production
- Implement proper input validation
- Follow security best practices for file uploads

## Support

For any issues, questions, or inquiries about purchasing the project, please contact:
- **Developers:**
  - DHARRYL DAVE B CLERIGO
  - LORD LYLE AGREDA
  - ERICH LORAINE DELA CERNA
- **Email:** dharzdesu@gmail.com

## License

Copyright © 2025 DHARRYL DAVE B CLERIGO, LORD LYLE AGREDA, ERICH LORAINE DELA CERNA. All rights reserved.

This project is proprietary software developed for academic purposes. Unauthorized copying, distribution, or use of this software, via any medium, is strictly prohibited. The database schema and full source code are not publicly available. For access or purchase inquiries, please contact the developers.