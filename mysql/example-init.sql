
-- initialization data for examples
-- will be run from vagrant-provision.sh

-- this inserts the user record required for the example-client to work
-- against a newly created vagrant VM

INSERT INTO `users` VALUES (1,'example@blocked.org.uk',NULL,NULL,NULL,1,NULL,NULL,'ok',NULL,NULL,NULL,'abcdefghijklmnopqrstuvwxyz123','2014-07-14 21:23:05',0);

-- create fake ISP
INSERT INTO `isps` VALUES (1,'FakeISP','Example ISP','fakeisp','2014-08-28 22:56:37',1),(2,'Google Inc','Google Inc',NULL,'2014-08-28 23:03:13',0);

-- ensure that DNS resolution inside the VM points the fake internal IP at the fake ISP
INSERT INTO `isp_aliases` VALUES (1,2,'Google Inc.,US','2014-08-28 23:03:13'),(2,1,'Fake ISP,GB','2014-08-28 23:15:38');
INSERT INTO `isp_cache` VALUES ('127.99.99.1','Fake ISP,GB','2014-08-28 23:15:59'),('8.8.4.4','Google Inc.,US','2014-08-28 23:03:13');

-- insert demo probe (to match demo config file for OrgProbe)
INSERT INTO `probes` VALUES (1,'3a3c9fc22efe11e4a53fb86b23356f7c',1,NULL,'abcdefghijklmnqoprstuvwxyz0123456789','web','2014-08-28 23:14:09',NULL,0,NULL,4,4,1,2,0);
