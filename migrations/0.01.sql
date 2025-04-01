CREATE TABLE `users` ( 
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(128) NOT NULL,
  `passwordhash` VARCHAR(256) NOT NULL,
  `registeredat` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `isadmin` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`username`),
  UNIQUE KEY `users_email_key` (`email`)
);