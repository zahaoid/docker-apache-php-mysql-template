CREATE TABLE `books` (
  `book_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `published_year` INT(4),
  `genre` VARCHAR(100),
  `isbn` VARCHAR(13) UNIQUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1
);
