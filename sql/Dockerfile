
FROM postgres:9.3

COPY pg.schema.sql /docker-entrypoint-initdb.d/000-schema.sql
COPY blockedadmin.grants.sql /docker-entrypoint-initdb.d/001-blockedadmin.sql


