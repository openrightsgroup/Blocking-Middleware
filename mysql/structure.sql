/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


/*Table structure for table `probes` */

DROP TABLE IF EXISTS `probes`;

CREATE TABLE `probes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `uuid` varchar(32) NOT NULL,
  `userID` int(11) unsigned default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `type` enum('raspi','android','atlas','web') NOT NULL,
  `lastSeen` datetime default NULL,
  `gcmRegID` text,
  `isPublic` tinyint(1) unsigned default '1',
  `countryCode` varchar(3) default NULL,
  `probeReqSent` int(11) unsigned default 0,
  `probeRespRecv` int(11) unsigned default 0,
  `enabled` tinyint(1) unsigned default '1',
  `frequency` int(11) unsigned default '2',
  `gcmType` int(11) unsigned default '0',
  PRIMARY KEY  (`uuid`,`id`),
  UNIQUE KEY `probeUUID` (`uuid`),
  KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `email` varchar(128) NOT NULL,
  `password` varchar(255) default NULL,
  `preference` text,
  `fullName` varchar(60) default NULL,
  `isPublic` tinyint(1) unsigned default '1',
  `countryCode` varchar(3) default NULL,
  `probeHMAC` varchar(32) default NULL,
  `status` enum('pending','ok','suspended','banned') default 'ok',
  `pgpKey` text,
  `yubiKey` varchar(12) default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `createdAt` timestamp NULL default CURRENT_TIMESTAMP,
  `administrator` tinyint(4) DEFAULT '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY email(`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `results`;

CREATE TABLE `results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `probeID` int(11) NOT NULL,
  `config` int(11) NOT NULL,
  `ip_network` varchar(16) DEFAULT NULL,
  `status` varchar(8) DEFAULT NULL,
  `http_status` int(11) DEFAULT NULL,
  `network_name` varchar(64) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `filter_level` varchar(16) DEFAULT '',
  `category` varchar(64) DEFAULT '',
  `blocktype` enum('PARENTAL','COPYRIGHT') default NULL,
  PRIMARY KEY (`id`),
  KEY `result_idx` (`urlID`,`network_name`,`status`,`created`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `requests`;

CREATE TABLE `requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `contactID` int(11) DEFAULT NULL COMMENT 'Record in the contacts table that stores the contact details of the actor that made this request',
  `submission_info` text,
  `created` datetime DEFAULT NULL,
  `allowcontact` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Contact will accept communication from ORG about this request',
  `information` text COMMENT 'Extra info about this request provided by the contact',
  PRIMARY KEY (`id`),
  KEY `fk_requests_contacts` (`contactID`),
  CONSTRAINT `fk_requests_contacts` FOREIGN KEY (`contactID`) REFERENCES `contacts` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `contacts`;

CREATE TABLE `contacts` (
  `id` INT(10) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(128) NOT NULL COMMENT 'Contact\'s email address',
  `verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Set when the contact\'s email address has been verified, either by verifying a request, or by the double opt-in mechanism for the main ORG mailing list',
  `joinlist` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Set when the contact has subscribed to ORG\'s mailing list',
  `fullName` VARCHAR(60) NULL DEFAULT NULL COMMENT 'Contact\'s given name (so we can address messages personally)',
  `createdAt` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Time this record was created',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id` ASC),
  UNIQUE KEY `email_UNIQUE` (`email` ASC)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8 COMMENT = 'Contains information about how to get in touch with an actor' /* comment truncated */ /* (who may have made one or more requests or be running one or more probes)*/;

DROP TABLE IF EXISTS `isps`;

CREATE TABLE `isps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) NOT NULL,
  `queue_name` varchar(64) NULL,
  `created` datetime DEFAULT NULL,
  `show_results` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `urls`;
CREATE TABLE `urls` (
  `urlID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(32) DEFAULT NULL,
  `domain` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `scheme` varchar(5) not null,
  `source` enum('social','user','canary','probe','alexa','dmoz') DEFAULT NULL,
  `lastPolled` datetime DEFAULT NULL,
  `inserted` datetime NOT NULL,
  `polledAttempts` int(10) unsigned DEFAULT '0',
  `polledSuccess` int(10) unsigned DEFAULT '0',
  `status` enum('ok','disallowed-by-robots-txt','disallowed-mime-type','disallowed-content-length') DEFAULT 'ok' NOT NULL,
  PRIMARY KEY (`urlID`),
  UNIQUE KEY `urls_url` (`domain`,`path`,`scheme`),
  KEY `source` (`source`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `isp_aliases`;
CREATE TABLE `isp_aliases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ispID` int(10) unsigned DEFAULT NULL,
  `alias` varchar(64) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isp_aliases_alias` (`alias`),
  KEY `ispID` (`ispID`),
  CONSTRAINT `isp_aliases_ibfk_1` FOREIGN KEY (`ispID`) REFERENCES `isps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `isp_cache`;
CREATE TABLE `isp_cache` (
  `ip` varchar(128) NOT NULL,
  `network` varchar(64) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY `unq` (`ip`,`network`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `modx_copy`;
CREATE TABLE `modx_copy` (
  `id` int(10) unsigned NOT NULL,
  `last_id` int(10) unsigned NOT NULL,
  `last_checked` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `queue_length`;
CREATE TABLE `queue_length` (
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `isp` varchar(64) NOT NULL DEFAULT '',
  `type` varchar(8) NOT NULL DEFAULT '',
  `length` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`type`,`isp`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `url_latest_status`;
CREATE TABLE `url_latest_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(10) unsigned NOT NULL,
  `network_name` varchar(64) NOT NULL,
  `status` varchar(8) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `category` varchar(64) DEFAULT '',
  `blocktype` enum('PARENTAL','COPYRIGHT') default NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_latest_unq` (`urlID`,`network_name`),
  KEY `ts` (`created`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `stats_cache`;
CREATE TABLE `stats_cache` (
  `name` varchar(64) NOT NULL DEFAULT '',
  `value` int(10) unsigned DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `isp_stats_cache`;
CREATE TABLE `isp_stats_cache` (
  `network_name` varchar(64) NOT NULL,
  `ok` int(10) unsigned NOT NULL DEFAULT '0',
  `blocked` int(10) unsigned NOT NULL DEFAULT '0',
  `timeout` int(10) unsigned NOT NULL DEFAULT '0',
  `error` int(10) unsigned NOT NULL DEFAULT '0',
  `dnsfail` int(10) unsigned NOT NULL DEFAULT '0',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`network_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `url_subscriptions`;
CREATE TABLE `url_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(10) unsigned NOT NULL,
  `contactID` int(10) unsigned NOT NULL,
  `subscribereports` tinyint(1) DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `token` varchar(36) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `last_notification` datetime DEFAULT NULL,  
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_contact` (`urlID`,`contactID`),
  UNIQUE KEY `urlsub_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `url_status_changes`;
CREATE TABLE `url_status_changes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(10) unsigned DEFAULT NULL,
  `network_name` varchar(64) CHARACTER SET utf8 DEFAULT NULL,
  `old_status` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `new_status` varchar(16) CHARACTER SET latin1 DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY (`created`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


DELIMITER //
CREATE PROCEDURE record_change(p_urlid int, p_network_name varchar(64), p_old_status varchar(16), p_newstatus varchar(16), p_created datetime) 
MODIFIES SQL DATA  
BEGIN 
  IF p_old_status <> p_newstatus 
  THEN 
    INSERT INTO url_status_changes(urlid, network_name, old_status, new_status, created) 
	VALUES (p_urlid, p_network_name, p_old_status, p_newstatus, p_created); 
  END IF; 
END;
//
DELIMITER ;

CREATE TRIGGER record_change 
BEFORE UPDATE ON url_latest_status 
FOR EACH ROW 
CALL record_change(NEW.urlID, NEW.network_name, OLD.status, NEW.status, NEW.created);


CREATE TRIGGER status_upd_trig 
AFTER INSERT ON results 
FOR EACH ROW 
INSERT INTO url_latest_status(urlID, network_name, status, created, category, blocktype) 
SELECT NEW.urlID, NEW.network_name, NEW.status, NEW.created, NEW.category, NEW.blocktype
ON DUPLICATE KEY 
UPDATE status = NEW.status, created = NEW.created, category = NEW.category, blocktype=NEW.blocktype;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `display_name` varchar(255) DEFAULT NULL,
  `name1` varchar(64) DEFAULT NULL,
  `name2` varchar(64) DEFAULT NULL,
  `name3` varchar(64) DEFAULT NULL,
  `name4` varchar(255) DEFAULT NULL,
  `name5` varchar(255) DEFAULT NULL,
  `name6` varchar(255) DEFAULT NULL,
  `name7` varchar(255) DEFAULT NULL,
  `name8` varchar(255) DEFAULT NULL,
  `name9` varchar(255) DEFAULT NULL,
  `name10` varchar(255) DEFAULT NULL,
  `org_category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx1` (`display_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `url_categories`;
CREATE TABLE `url_categories` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `urlID` int(10) unsigned NOT NULL,
	  `category_id` int(10) unsigned NOT NULL,
	  PRIMARY KEY (`id`),
	  UNIQUE KEY `urlID` (`urlID`,`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `org_categories`;
CREATE TABLE `org_categories` (
	  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `name` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
	  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
