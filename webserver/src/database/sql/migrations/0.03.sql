CREATE TABLE `corrections` ( 
  `entryid` INT NOT NULL,
  `correction` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`entryid`, `correction`),
  FOREIGN KEY (`entryid`) REFERENCES `entries` (`id`) 
    ON DELETE NO ACTION ON UPDATE NO ACTION
);
