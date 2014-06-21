SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema bowdlerize
-- -----------------------------------------------------
-- Schema for the Blocking-Middleware database.
DROP SCHEMA IF EXISTS `bowdlerize` ;
CREATE SCHEMA IF NOT EXISTS `bowdlerize` DEFAULT CHARACTER SET utf8 ;
USE `bowdlerize` ;

-- -----------------------------------------------------
-- Table `bowdlerize`.`alexa_10k`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`alexa_10k` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`alexa_10k` (
  `url` VARCHAR(128) NULL DEFAULT NULL,
  `inserted` DATETIME NULL DEFAULT NULL)
ENGINE = MyISAM
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`alexa_1m`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`alexa_1m` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`alexa_1m` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(128) NULL DEFAULT NULL,
  `inserted` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`))
ENGINE = MyISAM
AUTO_INCREMENT = 1727918
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`censorlist`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`censorlist` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`censorlist` (
  `urlID` INT(11) NOT NULL AUTO_INCREMENT,
  `md5Hash` VARCHAR(32) NOT NULL,
  `hmac` VARCHAR(32) NULL DEFAULT NULL,
  `confidence` INT(11) NOT NULL DEFAULT '0',
  `inserted` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `isp` TEXT NULL DEFAULT NULL,
  `sim` TEXT NULL DEFAULT NULL,
  `censored` TINYINT(1) NULL DEFAULT '1',
  PRIMARY KEY (`urlID`))
ENGINE = MyISAM
AUTO_INCREMENT = 7994
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`devices`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`devices` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`devices` (
  `deviceID` VARCHAR(32) NOT NULL,
  `gcm_regid` TEXT NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  `urlsPassed` INT(11) NULL DEFAULT NULL,
  `urlsReturned` INT(11) NULL DEFAULT NULL,
  `enabled` TINYINT(1) NULL DEFAULT '1',
  `delay` INT(11) NULL DEFAULT '2',
  `lastPolled` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`deviceID`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`isps`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`isps` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`isps` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `created` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `name` (`name` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 15617
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`isp_aliases`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`isp_aliases` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`isp_aliases` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ispID` INT(10) UNSIGNED NULL DEFAULT NULL,
  `alias` VARCHAR(64) NULL DEFAULT NULL,
  `created` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `isp_aliases_alias` (`alias` ASC),
  INDEX `ispID` (`ispID` ASC),
  CONSTRAINT `isp_aliases_ibfk_1`
    FOREIGN KEY (`ispID`)
    REFERENCES `bowdlerize`.`isps` (`id`)
    ON DELETE CASCADE)
ENGINE = InnoDB
AUTO_INCREMENT = 25
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`isp_cache`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`isp_cache` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`isp_cache` (
  `ip` VARCHAR(128) NOT NULL,
  `network` VARCHAR(64) NOT NULL DEFAULT '',
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`ip`, `network`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`modx_copy`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`modx_copy` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`modx_copy` (
  `id` INT(10) UNSIGNED NOT NULL,
  `last_id` INT(10) UNSIGNED NOT NULL,
  `last_checked` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`))
ENGINE = MyISAM
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`probes`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`probes` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`probes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(32) NOT NULL,
  `userID` INT(11) NULL DEFAULT NULL,
  `publicKey` TEXT NULL DEFAULT NULL,
  `secret` VARCHAR(128) NULL DEFAULT NULL,
  `type` ENUM('raspi','android','atlas','web') NOT NULL,
  `lastSeen` DATETIME NULL DEFAULT NULL,
  `gcmRegID` TEXT NULL DEFAULT NULL,
  `isPublic` TINYINT(1) NULL DEFAULT '1',
  `countryCode` VARCHAR(3) NULL DEFAULT NULL,
  `probeReqSent` INT(11) NULL DEFAULT '0',
  `probeRespRecv` INT(11) NULL DEFAULT '0',
  `enabled` TINYINT(1) NULL DEFAULT '1',
  `frequency` INT(11) NULL DEFAULT '2',
  `gcmType` INT(11) NULL DEFAULT '0',
  PRIMARY KEY (`uuid`, `id`),
  UNIQUE INDEX `probeUUID` (`uuid` ASC),
  INDEX `id` (`id` ASC))
ENGINE = MyISAM
AUTO_INCREMENT = 28
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`queue_length`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`queue_length` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`queue_length` (
  `created` DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
  `isp` VARCHAR(64) NOT NULL DEFAULT '',
  `type` VARCHAR(8) NOT NULL DEFAULT '',
  `length` INT(10) UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`type`, `isp`, `created`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`contacts`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`contacts` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`contacts` (
  `id` INT(10) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(128) NOT NULL COMMENT 'Contact\'s email address',
  `verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Set when the contact\'s email address has been verified, either by verifying a request, or by the double opt-in mechanism for the main ORG mailing list',
  `joinlist` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Set when the contact has subscribed to ORG\'s mailing list',
  `fullName` VARCHAR(60) NULL DEFAULT NULL COMMENT 'Contact\'s given name (so we can address messages personally)',
  `createdAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Time this record was created',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_UNIQUE` (`id` ASC),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COMMENT = 'Contains information about how to get in touch with an actor' /* comment truncated */ /* (who may have made one or more requests or be running one or more probes)*/;


-- -----------------------------------------------------
-- Table `bowdlerize`.`requests`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`requests` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`requests` (
  `id` INT(10) NOT NULL AUTO_INCREMENT,
  `urlID` INT(11) NOT NULL,
  `userID` INT(11) NOT NULL,
  `contactID` INT(11) NULL DEFAULT NULL COMMENT 'Record in the contacts table that stores the contact details of the actor that made this request',
  `submission_info` TEXT NULL DEFAULT NULL,
  `created` DATETIME NULL DEFAULT NULL,
  `subscribereports` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Contact wishes to receive regular email updates about this URL',
  `allowcontact` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Contact will accept communication from ORG about this request',
  `information` TEXT NULL COMMENT 'Extra info about this request provided by the contact',
  PRIMARY KEY (`id`),
  INDEX `fk_requests_contacts_idx` (`contactID` ASC),
  CONSTRAINT `fk_requests_contacts`
    FOREIGN KEY (`contactID`)
    REFERENCES `bowdlerize`.`contacts` (`id`)
    ON DELETE SET NULL
    ON UPDATE NO ACTION)
ENGINE = InnoDB
AUTO_INCREMENT = 1701
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`results`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`results` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`results` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `urlID` INT(11) NOT NULL,
  `probeID` INT(11) NOT NULL,
  `config` INT(11) NOT NULL,
  `ip_network` VARCHAR(16) NULL DEFAULT NULL,
  `status` VARCHAR(8) NULL DEFAULT NULL,
  `http_status` INT(11) NULL DEFAULT NULL,
  `network_name` VARCHAR(64) NULL DEFAULT NULL,
  `created` DATETIME NULL DEFAULT NULL,
  `filter_level` VARCHAR(16) NULL DEFAULT '',
  PRIMARY KEY (`id`),
  INDEX `result_idx` (`urlID` ASC, `network_name` ASC, `status` ASC, `created` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1860387
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`results_baseline`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`results_baseline` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`results_baseline` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `urlID` INT(11) NOT NULL,
  `probeID` INT(11) NOT NULL,
  `config` INT(11) NOT NULL,
  `ip_network` VARCHAR(16) NULL DEFAULT NULL,
  `status` VARCHAR(8) NULL DEFAULT NULL,
  `http_status` INT(11) NULL DEFAULT NULL,
  `network_name` VARCHAR(64) NULL DEFAULT NULL,
  `created` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `result_idx` (`urlID` ASC, `network_name` ASC, `status` ASC, `created` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1497362
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `bowdlerize`.`tempURLs`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`tempURLs` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`tempURLs` (
  `tempID` INT(11) NOT NULL AUTO_INCREMENT,
  `URL` TEXT NULL DEFAULT NULL,
  `hash` VARCHAR(32) NULL DEFAULT NULL,
  `headers` TEXT NULL DEFAULT NULL,
  `content_type` TEXT NULL DEFAULT NULL,
  `code` INT(11) NULL DEFAULT NULL,
  `fullFidelityReq` TINYINT(1) NULL DEFAULT '0',
  `urgency` INT(11) NULL DEFAULT '0',
  `source` ENUM('social','user','canary','probe') NULL DEFAULT NULL,
  `targetASN` INT(11) NULL DEFAULT NULL,
  `status` ENUM('pending','failed','ready','complete') NULL DEFAULT NULL,
  `lastPolled` DATETIME NULL DEFAULT NULL,
  `inserted` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `polledAttempts` INT(11) NULL DEFAULT '0',
  `polledSuccess` INT(11) NULL DEFAULT '0',
  PRIMARY KEY (`tempID`))
ENGINE = MyISAM
AUTO_INCREMENT = 1403793
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`tmp_del_urls`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`tmp_del_urls` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`tmp_del_urls` (
  `urlid` INT(10) UNSIGNED NOT NULL DEFAULT '0')
ENGINE = MyISAM
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`urls`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`urls` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`urls` (
  `urlID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `URL` VARCHAR(2048) CHARACTER SET 'latin1' COLLATE 'latin1_bin' NOT NULL,
  `hash` VARCHAR(32) NULL DEFAULT NULL,
  `source` ENUM('social','user','canary','probe','alexa') NULL DEFAULT NULL,
  `lastPolled` DATETIME NULL DEFAULT NULL,
  `inserted` DATETIME NOT NULL,
  `polledAttempts` INT(10) UNSIGNED NULL DEFAULT '0',
  `polledSuccess` INT(10) UNSIGNED NULL DEFAULT '0',
  PRIMARY KEY (`urlID`),
  UNIQUE INDEX `urls_url` (`URL`(767) ASC),
  INDEX `source` (`source` ASC))
ENGINE = InnoDB
AUTO_INCREMENT = 1729527
DEFAULT CHARACTER SET = latin1;


-- -----------------------------------------------------
-- Table `bowdlerize`.`users`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `bowdlerize`.`users` ;

CREATE TABLE IF NOT EXISTS `bowdlerize`.`users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(128) NOT NULL,
  `password` VARCHAR(255) NULL DEFAULT NULL,
  `preference` TEXT NULL DEFAULT NULL,
  `fullName` VARCHAR(60) NULL DEFAULT NULL,
  `isPublic` TINYINT(1) NULL DEFAULT '0',
  `countryCode` VARCHAR(3) NULL DEFAULT NULL,
  `probeHMAC` VARCHAR(32) NULL DEFAULT NULL,
  `status` ENUM('pending','ok','suspended','banned') NULL DEFAULT 'ok',
  `pgpKey` TEXT NULL DEFAULT NULL,
  `yubiKey` VARCHAR(12) NULL DEFAULT NULL,
  `publicKey` TEXT NULL DEFAULT NULL,
  `secret` VARCHAR(128) NULL DEFAULT NULL,
  `createdAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `administrator` TINYINT(4) NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `email` (`email` ASC))
ENGINE = MyISAM
AUTO_INCREMENT = 37
DEFAULT CHARACTER SET = latin1;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
