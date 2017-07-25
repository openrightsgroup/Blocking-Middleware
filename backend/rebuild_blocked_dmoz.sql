
BEGIN;
delete from blocked_dmoz;

INSERT INTO blocked_dmoz(urlid) 
SELECT distinct Urls.urlid 
FROM urls 
INNER JOIN url_latest_status uls USING (urlid) 
INNER JOIN isps on uls.network_name = isps.name
WHERE uls.status = 'blocked' AND 
    urls.status = 'ok' AND 
    source = 'dmoz' and 
    isps.queue_name is not null;

COMMIT;

