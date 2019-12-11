
insert into users(email, secret, status, createdat, administrator) values ('admin@example.com','azbycxdw55','ok',now(), 1);

insert into isps(name, description, queue_name, created, show_results, isp_type, isp_status, regions)
	values ('ExampleISP','ExampleISP','exampleisp',now(), 1, 'fixed', 'running', '{gb}');

insert into isp_aliases (ispid, alias, created) select id, name, now() from isps;

insert into probes(uuid, userid, secret, type, isp_id, probe_status)
  values ('probe-1', 1, 'muntosprq', 'raspi', 1, 'active'); 

insert into urls(url, hash, source, inserted, url_type)
  values ('http://www.example.com', '847310eb455f9ae37cb56962213c491d', 'user', now(), 'SUBDOMAIN');

insert into results(urlid, probeid, config, ip_network, status, http_status, network_name, created)
  values (
    (select urlid from urls where url = 'http://www.example.com'),
    (select id from probes where uuid = 'probe-1'), 
    -1,
    '172.17.0.99',
    'ok',
    200,
    'ExampleISP',
    now()
  );

