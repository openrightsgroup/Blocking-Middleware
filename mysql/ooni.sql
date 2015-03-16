
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` text,
  `created` datetime DEFAULT NULL,
  `complete` tinyint DEFAULT 0 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE `report_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `data` text,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

