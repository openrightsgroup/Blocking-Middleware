
CREATE TABLE domains(
    id serial primary key,
    domain varchar unique,
    created timestamptz not null,
    resolved bool default false,
    submitted timestamptz null
);

CREATE INDEX ON domains(created);
