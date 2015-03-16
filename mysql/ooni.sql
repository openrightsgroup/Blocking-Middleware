
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` text,
  `created` datetime DEFAULT NULL,
  `complete` tinyint DEFAULT 0 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `report_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `data` mediumtext,
  `created` datetime DEFAULT NULL,
  `processed` tinyint DEFAULT 0 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

ALTER TABLE report_entries ADD FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE;
