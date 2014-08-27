
-- initialization data for examples
-- will be run from vagrant-provision.sh

-- this inserts the user record required for the example-client to work
-- against a newly created vagrant VM

INSERT INTO `users` VALUES (1,'web@blocked.org.uk',NULL,NULL,NULL,1,NULL,NULL,'ok',NULL,NULL,NULL,'abcdefghijklmnopqrstuvwxyz123','2014-07-14 21:23:05',0);

