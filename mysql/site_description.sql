CREATE TABLE `site_description` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urlid` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `description` text,
  PRIMARY KEY (`id`),
  KEY `site_description_urlid` (`urlid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
