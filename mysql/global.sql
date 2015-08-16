
CREATE TABLE global_api (
    id integer not null auto_increment primary key,
    server varchar(60) not null,
    username varchar(60) not null, 
    secret varchar(64) not null,
    country char(2) not null,
    live tinyint default 1 not null
    );

