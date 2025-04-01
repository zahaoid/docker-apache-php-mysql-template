CREATE TABLE `entries` ( 
  `id` INT AUTO_INCREMENT,
  `origin` CHAR(2) NOT NULL,
  `original` VARCHAR(64) NOT NULL,
  `translationese` VARCHAR(64) NOT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `submitter` VARCHAR(64) NULL,
  `approvedby` VARCHAR(64) NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`approvedby`) REFERENCES `users` (`username`) 
    ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY (`submitter`) REFERENCES `users` (`username`) 
    ON DELETE NO ACTION ON UPDATE NO ACTION
);
