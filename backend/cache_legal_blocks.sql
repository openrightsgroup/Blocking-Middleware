BEGIN;
delete from stats.cache_copyright_blocks;
insert into stats.cache_copyright_blocks  select url, array_agg(network_name) as networks, fmtime(min(uls.first_blocked)) as first_blocked,
             fmtime(max(uls.last_blocked)) as last_blocked, regions                                        
             from url_latest_status uls                                                           
             inner join urls using (urlid)                                                        
             inner join isps on uls.network_name = isps.name          
             where blocktype = 'COPYRIGHT'  and urls.status = 'ok'  and urls.url ~* '^https?://[^/]+$'
             group by url, regions                                                                         
             order by url;
commit;
