
insert into users(email, secret, status, createdat, administrator) values ('admin@example.com','azbycxdw55','ok',now(), 1);

insert into isps(name, description, queue_name, created, show_results, isp_type, isp_status, regions)
	values ('ExampleISP','ExampleISP','exampleisp',now(), 1, 'fixed', 'running', '{gb}');

insert into isp_aliases (ispid, alias, created) select id, name, now() from isps;

insert into probes(uuid, userid, secret, type, isp_id, probe_status)
	values ('probe-1', 1, 'muntosprq', 'raspi', 1, 'active'); 
