-- Create a database if it doesn't exist (optional)
-- CREATE DATABASE IF NOT EXISTS modular_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE modular_db;
 
-- Table for the Book Tracker example
CREATE TABLE IF NOT EXISTS `my_books` (
  `book_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `author_name` VARCHAR(255) NOT NULL,
  `publication_year` INT,
  `date_read` DATE NULL,
  `rating_stars` TINYINT NULL,
  `summary_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for Expense Categories (for foreign key in expenses)

CREATE TABLE IF NOT EXISTS `expense_categories` (
  `cat_id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample categories
INSERT IGNORE INTO `expense_categories` (`category_name`, `description`) VALUES
('Groceries', 'Food and household supplies'),
('Utilities', 'Electricity, water, gas, internet'),
('Transport', 'Fuel, public transport tickets'),
('Entertainment', 'Movies, concerts, etc.'),
('Healthcare', 'Medication, doctor visits'),
('Other', 'Miscellaneous expenses');

-- Table for the Expense Tracker example
CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `transaction_id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_date` DATE NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `category_id` INT NULL,
  `amount_spent` DECIMAL(10, 2) NOT NULL,
  `payment_method` VARCHAR(50) NULL,
  `receipt_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `expense_categories`(`cat_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample books
INSERT INTO `my_books` (`title`, `author_name`, `publication_year`, `date_read`, `rating_stars`, `summary_notes`) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', 1925, '2024-01-15', 5, 'A classic novel about the Jazz Age.'),
('To Kill a Mockingbird', 'Harper Lee', 1960, '2024-02-20', 4, 'A powerful story addressing racial injustice.'),
('1984', 'George Orwell', 1949, '2024-03-10', 5, 'A dystopian novel about totalitarianism.');

-- Insert some sample financial transactions
INSERT INTO `financial_transactions` (`transaction_date`, `vendor_name`, `category_id`, `amount_spent`, `payment_method`, `receipt_notes`) VALUES
('2025-05-01', 'SuperMart', (SELECT cat_id FROM expense_categories WHERE category_name = 'Groceries'), 75.50, 'Credit Card', 'Weekly groceries'),
('2025-05-03', 'City Power', (SELECT cat_id FROM expense_categories WHERE category_name = 'Utilities'), 120.00, 'Bank Transfer', 'Electricity bill'),
('2025-05-05', 'Gas Station', (SELECT cat_id FROM expense_categories WHERE category_name = 'Transport'), 50.25, 'Cash', 'Fuel for car');
