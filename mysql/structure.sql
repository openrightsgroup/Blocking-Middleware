/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`bowdlerize` /*!40100 DEFAULT CHARACTER SET utf8 */;

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

/*Table structure for table `tempURLs` */

DROP TABLE IF EXISTS `tempURLs`;

CREATE TABLE `tempURLs` (
  `tempID` int(11) unsigned NOT NULL auto_increment,
  `URL` text,
  `hash` varchar(32) default NULL,
  `headers` text,
  `content_type` text,
  `code` int(11) unsigned default NULL,
  `fullFidelityReq` tinyint(1) unsigned default '0',
  `urgency` int(11) unsigned default '0',
  `source` enum('social','user','canary','probe') default NULL,
  `targetASN` int(11) unsigned default NULL,
  `status` enum('pending','failed','ready','complete') default NULL,
  `lastPolled` datetime default NULL,
  `inserted` timestamp NULL default CURRENT_TIMESTAMP,
  `polledAttempts` int(11) unsigned default '0',
  `polledSuccess` int(11) unsigned default '0',
  PRIMARY KEY  (`tempID`),
  UNIQUE KEY `tempurl_url` (`URL`(255))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `email` varchar(128) NOT NULL,
  `password` varchar(255) default NULL,
  `preference` text,
  `fullName` varchar(60) default NULL,
  `isPublic` tinyint(1) unsigned default '0',
  `countryCode` varchar(3) default NULL,
  `probeHMAC` varchar(32) default NULL,
  `status` enum('pending','ok','suspended','banned') default 'pending',
  `pgpKey` text,
  `yubiKey` varchar(12) default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `createdAt` timestamp NULL default CURRENT_TIMESTAMP,
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
  PRIMARY KEY (`id`),
  KEY `result_idx` (`urlID`,`network_name`,`status`,`created`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `requests`;

CREATE TABLE `requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `urlID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `submission_info` text,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `isps`;

CREATE TABLE `isps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `queue`;

CREATE TABLE `queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ispID` int(10) unsigned NOT NULL,
  `urlID` int(10) unsigned NOT NULL,
  `priority` smallint(5) unsigned not null default 5,
  `lastSent` datetime DEFAULT NULL,
  `results` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `queue_unq` (`ispID`,`urlID`),
  KEY `cvr` (`ispID`,`priority`,`results`,`lastSent`,`urlID`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `urls`;
CREATE TABLE `urls` (
  `urlID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `URL` varchar(2048) NOT NULL COLLATE latin1_bin,
  `hash` varchar(32) DEFAULT NULL,
  `source` enum('social','user','canary','probe','alexa') DEFAULT NULL,
  `lastPolled` datetime DEFAULT NULL,
  `inserted` datetime NOT NULL,
  `polledAttempts` int(10) unsigned DEFAULT '0',
  `polledSuccess` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`urlID`),
  UNIQUE KEY `urls_url` (`URL`(767))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `isp_aliases`;
CREATE TABLE `isp_aliases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ispID` int(10) unsigned DEFAULT NULL,
  `alias` varchar(64) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isp_aliases_alias` (`alias`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `isp_cache`;
CREATE TABLE `isp_cache` (
  `ip` varchar(16) NOT NULL,
  `network` varchar(64) NOT NULL,
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
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `isp` varchar(64) NOT NULL DEFAULT '',
  `type` varchar(8) NOT NULL DEFAULT '',
  `length` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`type`,`isp`,`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
