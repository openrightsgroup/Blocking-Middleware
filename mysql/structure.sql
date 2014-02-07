/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`bowdlerize` /*!40100 DEFAULT CHARACTER SET latin1 */;

/*Table structure for table `probes` */

DROP TABLE IF EXISTS `probes`;

CREATE TABLE `probes` (
  `id` int(11) NOT NULL auto_increment,
  `uuid` varchar(32) NOT NULL,
  `userID` int(11) default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `type` enum('raspi','android','atlas','web') NOT NULL,
  `lastSeen` datetime default NULL,
  `gcmRegID` text,
  `isPublic` tinyint(1) default '0',
  `countryCode` varchar(3) default NULL,
  `probeReqSent` int(11) default NULL,
  `probeRespRecv` int(11) default NULL,
  `enabled` tinyint(1) default '1',
  `frequency` int(11) default '2',
  `gcmType` int(11) default '0',
  PRIMARY KEY  (`uuid`,`id`),
  UNIQUE KEY `probeUUID` (`uuid`),
  KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

/*Table structure for table `tempURLs` */

DROP TABLE IF EXISTS `tempURLs`;

CREATE TABLE `tempURLs` (
  `tempID` int(11) NOT NULL auto_increment,
  `URL` text,
  `hash` varchar(32) default NULL,
  `headers` text,
  `content_type` text,
  `code` int(11) default NULL,
  `fullFidelityReq` tinyint(1) default '0',
  `urgency` int(11) default '0',
  `source` enum('social','user','canary','probe') default NULL,
  `targetASN` int(11) default NULL,
  `status` enum('pending','failed','ready','complete') default NULL,
  `lastPolled` datetime default NULL,
  `inserted` timestamp NULL default CURRENT_TIMESTAMP,
  `polledAttempts` int(11) default '0',
  `polledSuccess` int(11) default '0',
  PRIMARY KEY  (`tempID`)
) ENGINE=MyISAM AUTO_INCREMENT=1401808 DEFAULT CHARSET=latin1;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) NOT NULL auto_increment,
  `email` varchar(128) NOT NULL,
  `password` varchar(255) default NULL,
  `preference` text,
  `fullName` varchar(60) default NULL,
  `isPublic` tinyint(1) default '0',
  `countryCode` varchar(3) default NULL,
  `probeHMAC` varchar(32) default NULL,
  `status` enum('pending','ok','suspended','banned') default 'pending',
  `pgpKey` text,
  `yubiKey` varchar(12) default NULL,
  `publicKey` text,
  `secret` varchar(128),
  `createdAt` timestamp NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
