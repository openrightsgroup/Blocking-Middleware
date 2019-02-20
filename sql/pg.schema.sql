--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: ltree; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS ltree WITH SCHEMA public;


--
-- Name: EXTENSION ltree; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION ltree IS 'data type for hierarchical tree-like structures';


SET search_path = public, pg_catalog;

--
-- Name: enum_url_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE enum_url_status AS ENUM (
    'ok',
    'disallowed-by-robots-txt',
    'disallowed-mime-type',
    'disallowed-content-length',
    'invalid',
    'duplicate',
    'restricted-malware',
    'restricted-by-admin'
);

CREATE TYPE enum_report_status as ENUM (
    'new',
    'pending',
    'sent',
    'abuse',
    'cancelled',
    'rejected',
    'unblocked'
);

CREATE TYPE enum_url_type as ENUM (
    'DOMAIN',
    'SUBDOMAIN',
    'PAGE'
);

--
-- Name: enum_user_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE enum_user_status AS ENUM (
    'pending',
    'ok',
    'suspended',
    'banned'
);

CREATE TYPE enum_isp_type AS ENUM(
    'fixed',
    'mobile'
);

CREATE TYPE enum_isp_status AS ENUM(
    'running',
    'down'
);

--
-- Name: insert_contact(character varying, character varying, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION insert_contact(p_email character varying, p_fullname character varying, p_joinlist integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
BEGIN
update contacts set joinlist = (p_joinlist::bool or joinlist::int::bool)::int, fullname=case when p_fullname = '' then fullname else p_fullname end where email = p_email;
IF NOT FOUND
then
insert into contacts(email, fullname, joinlist, createdat) values (p_email, p_fullname, p_joinlist, now());
end if;
end;
$$;


--
-- Name: insert_url_subscription(integer, integer, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION insert_url_subscription(p_urlid integer, p_contactid integer, p_subscribereports integer) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE subid int; BEGIN
update url_subscriptions set subscribereports = p_subscribereports, created = now() where urlid = p_urlid and contactid = p_contactid returning id into subid; 
IF NOT FOUND
THEN
insert into url_subscriptions(urlid, contactid, subscribereports, created) values (p_urlid, p_contactid, p_subscribereports, now()) returning id into subid;
END IF;
return subid; END; $$;


--
-- Name: record_change(integer, character varying, character varying, character varying, timestamp with time zone); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION record_change(p_urlid integer, p_network_name character varying, p_oldstatus character varying, p_newstatus character varying, p_created timestamp with time zone) RETURNS void
    LANGUAGE plpgsql
    AS $$
begin
if p_oldstatus <> p_newstatus or p_oldstatus is null then
insert into url_status_changes(urlid, network_name, old_status, new_status, created) values (p_urlid, p_network_name,  p_oldstatus, p_newstatus, p_created);
end if;
END;
$$;


--
-- Name: trig_categories_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION trig_categories_ins_upd() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
select to_tsvector('english', NEW.name) into NEW.name_fts;
return NEW;
END;
$$;


--
-- Name: trig_isp_reports_insert(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION trig_isp_reports_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
update urls set last_reported = NEW.created where urls.urlid = NEW.urlid;
return NEW;
END;
$$;


--
-- Name: trig_result_insert(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION trig_result_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
 if NEW.status = 'blocked'
 then
   update urls set first_blocked = NEW.created where urls.urlid = NEW.urlid and first_blocked is null;
   update urls set last_blocked = NEW.created where urls.urlid = NEW.urlid;
 end if;

 UPDATE url_latest_status SET
 status = NEW.status, created = NEW.created, category = NEW.category, blocktype = NEW.blocktype, result_id = NEW.id
 WHERE urlid = NEW.urlid and network_name = NEW.network_name;
 
 IF NOT FOUND
 THEN
 insert into url_latest_status (
  status ,
  created ,
  category ,
  blocktype ,
  urlid ,
  network_name,
  result_id)
 values (
 NEW.status,
 NEW.created,
 NEW.category,
 NEW.blocktype,
 NEW.urlid,
 NEW.network_name,
 NEW.id
 );
 END IF; 
 RETURN NEW;
END;
$$;


--
-- Name: trig_uls_after_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION trig_uls_after_ins_upd() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
if TG_OP = 'UPDATE'
then
perform record_change(NEW.urlid, NEW.network_name,OLD.status, NEW.status, NEW.created);
else
perform record_change(NEW.urlid, NEW.network_name,NULL, NEW.status, NEW.created);
end if;
perform update_cache_block_count(NEW.urlid);
return NEW; END;
$$;


--
-- Name: trig_uls_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION trig_uls_ins_upd() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
if NEW.status = 'blocked'
then
  if TG_OP = 'INSERT' or (TG_OP = 'UPDATE' AND OLD.first_blocked is NULL ) 
  then    
    select NEW.created into NEW.first_blocked ;
  end if;
  select NEW.created into NEW.last_blocked ;

end if;
return NEW;
END; 
$$;


--
-- Name: update_cache_block_count(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION update_cache_block_count(p_urlid integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
DECLARE
 x int;
BEGIN
select urlid into x from cache_block_count where urlid = p_urlid;
if not found
then
insert into cache_block_count(urlid) values (p_urlid);
end if;
update cache_block_count set block_count_active = (select count(distinct network_name)
from url_latest_status uls where uls.urlid = p_urlid and uls.status = 'blocked' and uls.network_name in (select isps.name from isps where show_results=1)), block_count_all = (select count(distinct network_name)
from url_latest_status uls where uls.urlid = p_urlid and uls.status = 'blocked'), last_updated=now() where urlid = p_urlid;
END;
$$;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: cache_block_count; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE cache_block_count (
    urlid integer NOT NULL,
    block_count_active integer DEFAULT 0,
    block_count_all integer DEFAULT 0,
    last_updated timestamp with time zone
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE categories (
    id integer DEFAULT nextval('categories_id_seq'::regclass) NOT NULL,
    display_name text,
    org_category_id integer,
    block_count integer,
    blocked_url_count integer,
    total_block_count integer,
    total_blocked_url_count integer,
    tree ltree,
    name text,
    name_fts tsvector,
    namespace varchar(16),
    created timestamptz,
    last_updated timestamptz
);


--
-- Name: contacts; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE contacts (
    id integer NOT NULL,
    email character varying(128) NOT NULL,
    verified smallint DEFAULT 0 NOT NULL,
    joinlist smallint DEFAULT 0 NOT NULL,
    fullname character varying(60),
    createdat timestamp with time zone,
    token character varying(36),
    verify_attempts smallint default 0,
    verify_last_attempt timestamp with time zone
);


--
-- Name: contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE contacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE contacts_id_seq OWNED BY contacts.id;


--
-- Name: isp_aliases; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE isp_aliases (
    id integer NOT NULL,
    ispid integer NOT NULL,
    alias character varying(64) NOT NULL,
    created timestamp with time zone
);


--
-- Name: isp_aliases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE isp_aliases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_aliases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE isp_aliases_id_seq OWNED BY isp_aliases.id;


--
-- Name: isp_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE isp_cache (
    ip character varying(128) NOT NULL,
    network character varying(64) NOT NULL,
    created timestamp with time zone
);


--
-- Name: isp_reports; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE isp_reports (
    id serial primary key not null,
    name text,
    email text,
    urlid integer,
    network_name text,
    created timestamp with time zone,
    message text,
    report_type character varying(32),
    unblocked integer DEFAULT 0,
    notified integer,
    send_updates integer,
    last_updated timestamp with time zone,
    submitted timestamp with time zone,
    contact_id int,
    allow_publish int default 0,
    status enum_report_status default 'new',
    site_category varchar(64),
    allow_contact int default 0,
    mailname varchar(32) null unique,
    resolved_email_id int null,
    
    matches_policy bool null default null,
    egregious_block bool null,
    featured_block bool null,
    reporter_category_id int
);


--
-- Name: isp_stats_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE isp_stats_cache (
    network_name character varying(64) NOT NULL,
    ok integer DEFAULT 0 NOT NULL,
    blocked integer DEFAULT 0 NOT NULL,
    timeout integer DEFAULT 0 NOT NULL,
    error integer DEFAULT 0 NOT NULL,
    dnsfail integer DEFAULT 0 NOT NULL,
    last_updated timestamp with time zone,
    total integer DEFAULT 0 NOT NULL
);


--
-- Name: isps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE isps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isps; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE isps (
    id integer DEFAULT nextval('isps_id_seq'::regclass) NOT NULL,
    name character varying(64),
    description text,
    queue_name text,
    created timestamp with time zone,
    show_results integer,
    admin_email text,
    admin_name text,
    isp_type enum_isp_type,
    isp_status enum_isp_status,
    regions varchar[]
);


--
-- Name: org_categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE org_categories (
    id integer NOT NULL,
    name character varying(64) NOT NULL
);


--
-- Name: org_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE org_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: org_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE org_categories_id_seq OWNED BY org_categories.id;

CREATE TYPE enum_probe_status as enum(
    'inactive',
    'testing',
    'active',
    'retired'
);

--
-- Name: probes; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE probes (
    id integer NOT NULL,
    uuid character varying(32) NOT NULL,
    userid integer,
    secret character varying(128),
    type character varying(10) NOT NULL,
    ispublic smallint DEFAULT 1 NOT NULL,
    enabled smallint DEFAULT 1 NOT NULL,
    lastseen timestamp with time zone,
    proberesprecv integer DEFAULT 0,
    isp_id integer,
    probe_status enum_probe_status default 'active'::enum_probe_status,
    location text,
    filter_enabled bool,
    owner_link varchar null
);


--
-- Name: probes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE probes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: probes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE probes_id_seq OWNED BY probes.id;


--
-- Name: queue_length; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE queue_length (
    created timestamp with time zone NOT NULL,
    isp character varying(64) NOT NULL,
    type character varying(8) NOT NULL,
    length integer
);


--
-- Name: requests; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE requests (
    id integer NOT NULL,
    urlid integer NOT NULL,
    userid integer NOT NULL,
    contactid integer,
    submission_info text,
    created timestamp with time zone,
    allowcontact smallint DEFAULT 0 NOT NULL,
    information text
);


--
-- Name: requests_additional_data; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE requests_additional_data (
    id integer NOT NULL,
    request_id integer NOT NULL,
    name character varying(64),
    value character varying(255) NOT NULL,
    created timestamp with time zone
);


--
-- Name: requests_additional_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE requests_additional_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: requests_additional_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE requests_additional_data_id_seq OWNED BY requests_additional_data.id;


--
-- Name: requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE requests_id_seq OWNED BY requests.id;


--
-- Name: results_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE results_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: results; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE results(
    id integer DEFAULT nextval('results_id_seq'::regclass) NOT NULL,
    urlid integer,
    probeid integer,
    config integer,
    ip_network character varying(16),
    status character varying(8),
    http_status integer,
    network_name character varying(64),
    created timestamp with time zone,
    filter_level character varying(16),
    category character varying(64),
    blocktype character varying(16),
    title varchar(2048),
    remote_ip varchar(64),
    ssl_verified bool, 
    ssl_fingerprint varchar(256),
    request_id int,
    final_url varchar(2048),
    resolved_ip cidr
);


--
-- Name: site_description; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE site_description (
    id integer NOT NULL,
    urlid integer NOT NULL,
    created timestamp with time zone,
    description text
);


--
-- Name: site_description_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE site_description_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: site_description_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE site_description_id_seq OWNED BY site_description.id;


--
-- Name: stats_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE stats_cache (
    name character varying(64) NOT NULL,
    value integer,
    last_updated timestamp with time zone
);


--
-- Name: test; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE test (
    "testID" integer
);


--
-- Name: url_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE url_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE url_categories (
    id integer DEFAULT nextval('url_categories_id_seq'::regclass),
    urlid integer,
    category_id integer,
    enabled bool default true,
    userid int null,
    created timestamptz,
    last_updated timestamptz
);


CREATE UNIQUE INDEX ON url_categories(urlid, category_id);


ALTER TABLE url_categories ADD FOREIGN KEY(category_id) REFERENCES categories(id);

--
-- Name: url_latest_status_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE url_latest_status_id_seq
    START WITH 50510239
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_latest_status; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE url_latest_status (
    id integer DEFAULT nextval('url_latest_status_id_seq'::regclass) NOT NULL,
    urlid integer,
    network_name text,
    status text,
    created timestamp with time zone,
    category character varying(64),
    blocktype character varying(16),
    first_blocked timestamp with time zone,
    last_blocked timestamp with time zone,
    result_id int
);


--
-- Name: url_status_changes; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE url_status_changes (
    id integer NOT NULL,
    urlid integer NOT NULL,
    network_name character varying(64) NOT NULL,
    old_status character varying(16),
    new_status character varying(16),
    created timestamp with time zone,
    notified smallint DEFAULT 0 NOT NULL
);


--
-- Name: url_status_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE url_status_changes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_status_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE url_status_changes_id_seq OWNED BY url_status_changes.id;


--
-- Name: url_subscriptions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE url_subscriptions (
    id integer NOT NULL,
    urlid integer NOT NULL,
    contactid integer NOT NULL,
    subscribereports smallint DEFAULT 0,
    created timestamp with time zone,
    token character varying(36),
    verified smallint DEFAULT 0 NOT NULL,
    last_notification timestamp with time zone
);


--
-- Name: url_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE url_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE url_subscriptions_id_seq OWNED BY url_subscriptions.id;


--
-- Name: urls_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE urls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: urls; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE urls (
    urlid integer DEFAULT nextval('urls_id_seq'::regclass) NOT NULL,
    url text,
    hash character varying(32),
    source character varying(32),
    lastpolled timestamp with time zone,
    inserted timestamp with time zone,
    status enum_url_status DEFAULT 'ok'::enum_url_status,
    last_reported timestamp with time zone,
    first_blocked timestamp with time zone,
    last_blocked timestamp with time zone,
    polledsuccess integer DEFAULT 0,
    title varchar(255),
    tags varchar[] default '{}'::varchar[],
    whois_expiry timestamptz null,
    whois_expiry_last_checked timestamptz null,
    url_type enum_url_type null
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE users (
    id integer NOT NULL,
    email character varying(128) NOT NULL,
    password character varying(255),
    preference text,
    fullname character varying(60),
    ispublic smallint DEFAULT 1,
    countrycode character varying(3),
    probehmac character varying(32),
    status enum_user_status DEFAULT 'ok'::enum_user_status,
    secret character varying(128),
    createdat timestamp with time zone,
    administrator smallint DEFAULT 0
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE users_id_seq OWNED BY users.id;


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY contacts ALTER COLUMN id SET DEFAULT nextval('contacts_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY isp_aliases ALTER COLUMN id SET DEFAULT nextval('isp_aliases_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY org_categories ALTER COLUMN id SET DEFAULT nextval('org_categories_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY probes ALTER COLUMN id SET DEFAULT nextval('probes_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY requests ALTER COLUMN id SET DEFAULT nextval('requests_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY requests_additional_data ALTER COLUMN id SET DEFAULT nextval('requests_additional_data_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY site_description ALTER COLUMN id SET DEFAULT nextval('site_description_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY url_status_changes ALTER COLUMN id SET DEFAULT nextval('url_status_changes_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY url_subscriptions ALTER COLUMN id SET DEFAULT nextval('url_subscriptions_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY users ALTER COLUMN id SET DEFAULT nextval('users_id_seq'::regclass);


--
-- Name: cache_block_count_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY cache_block_count
    ADD CONSTRAINT cache_block_count_pkey PRIMARY KEY (urlid);


--
-- Name: categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: contacts_email_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY contacts
    ADD CONSTRAINT contacts_email_key UNIQUE (email);


--
-- Name: contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY contacts
    ADD CONSTRAINT contacts_pkey PRIMARY KEY (id);


--
-- Name: isp_aliases_alias_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY isp_aliases
    ADD CONSTRAINT isp_aliases_alias_key UNIQUE (alias);


--
-- Name: isp_aliases_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY isp_aliases
    ADD CONSTRAINT isp_aliases_pkey PRIMARY KEY (id);


--
-- Name: isp_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY isp_cache
    ADD CONSTRAINT isp_cache_pkey PRIMARY KEY (ip, network);


--
-- Name: isp_stats_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY isp_stats_cache
    ADD CONSTRAINT isp_stats_cache_pkey PRIMARY KEY (network_name);


--
-- Name: isps_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY isps
    ADD CONSTRAINT isps_pkey PRIMARY KEY (id);


--
-- Name: org_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY org_categories
    ADD CONSTRAINT org_categories_pkey PRIMARY KEY (id);


--
-- Name: probes_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY probes
    ADD CONSTRAINT probes_pkey PRIMARY KEY (id);


--
-- Name: probes_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY probes
    ADD CONSTRAINT probes_uuid_key UNIQUE (uuid);


--
-- Name: queue_length_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY queue_length
    ADD CONSTRAINT queue_length_pkey PRIMARY KEY (type, isp, created);


--
-- Name: requests_additional_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY requests_additional_data
    ADD CONSTRAINT requests_additional_data_pkey PRIMARY KEY (id);


--
-- Name: requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_pkey PRIMARY KEY (id);


--
-- Name: results_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY results
    ADD CONSTRAINT results_pkey PRIMARY KEY (id);


--
-- Name: site_description_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY site_description
    ADD CONSTRAINT site_description_pkey PRIMARY KEY (id);


--
-- Name: stats_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY stats_cache
    ADD CONSTRAINT stats_cache_pkey PRIMARY KEY (name);


--
-- Name: url_latest_status_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY url_latest_status
    ADD CONSTRAINT url_latest_status_pkey PRIMARY KEY (id);


--
-- Name: url_status_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY url_status_changes
    ADD CONSTRAINT url_status_changes_pkey PRIMARY KEY (id);


--
-- Name: url_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY url_subscriptions
    ADD CONSTRAINT url_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: url_subscriptions_token_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY url_subscriptions
    ADD CONSTRAINT url_subscriptions_token_key UNIQUE (token);


--
-- Name: urls_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY urls
    ADD CONSTRAINT urls_pkey PRIMARY KEY (urlid);


--
-- Name: users_email_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cat_tree; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX cat_tree ON categories USING gist (tree);


--
-- Name: categories_name_fts; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX categories_name_fts ON categories USING gin (name_fts);

CREATE UNIQUE INDEX categories_name on categories (name, namespace) where namespace <> 'dmoz';


--
-- Name: isp_aliases_ispid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX isp_aliases_ispid ON isp_aliases USING btree (ispid);


--
-- Name: isp_name; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX isp_name ON isps USING btree (name);


--
-- Name: results_url_network; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX results_url_network ON results USING btree (urlid, network_name);


--
-- Name: site_description_urlid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX site_description_urlid ON site_description USING btree (urlid);


--
-- Name: source; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX source ON urls USING btree (source);

CREATE INDEX url_tags on urls using gin(tags);

--
-- Name: uls_url_network; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX uls_url_network ON url_latest_status USING btree (urlid, network_name);


--
-- Name: url_status_changes_created; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_status_changes_created ON url_status_changes USING btree (created);


--
-- Name: urls_url; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX urls_url ON urls USING btree (url);


--
-- Name: urlsub_contact; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX urlsub_contact ON url_subscriptions USING btree (urlid, contactid);


--
-- Name: urls_insert_ignore; Type: RULE; Schema: public; Owner: -
--

CREATE RULE urls_insert_ignore AS ON INSERT TO urls WHERE (EXISTS (SELECT 1 FROM urls WHERE (urls.url = new.url))) DO INSTEAD NOTHING;


--
-- Name: trig_categories_ins_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_categories_ins_upd BEFORE INSERT OR UPDATE ON categories FOR EACH ROW EXECUTE PROCEDURE trig_categories_ins_upd();


--
-- Name: trig_isp_reports_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_isp_reports_insert AFTER INSERT ON isp_reports FOR EACH ROW EXECUTE PROCEDURE trig_isp_reports_insert();


--
-- Name: trig_result_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_result_insert AFTER INSERT ON results FOR EACH ROW EXECUTE PROCEDURE trig_result_insert();


--
-- Name: trig_uls_after_ins_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_uls_after_ins_upd AFTER INSERT OR UPDATE ON url_latest_status FOR EACH ROW EXECUTE PROCEDURE trig_uls_after_ins_upd();


--
-- Name: trig_uls_ins_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_uls_ins_upd BEFORE INSERT OR UPDATE ON url_latest_status FOR EACH ROW EXECUTE PROCEDURE trig_uls_ins_upd();


--
-- Name: isp_aliases_ispid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY isp_aliases
    ADD CONSTRAINT isp_aliases_ispid_fkey FOREIGN KEY (ispid) REFERENCES isps(id) ON DELETE CASCADE;


--
-- Name: requests_contactid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY requests
    ADD CONSTRAINT requests_contactid_fkey FOREIGN KEY (contactid) REFERENCES contacts(id) ON DELETE SET NULL;


--
-- Name: public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

create or replace function fmtime(x timestamptz) returns varchar AS $$ 
begin
return to_char(x, 'YYYY-MM-DD HH24:MI:SS');
end;
$$ language plpgsql immutable;

create or replace function fmtime(x timestamp) returns varchar AS $$ 
begin
return to_char(x, 'YYYY-MM-DD HH24:MI:SS');
end;
$$ language plpgsql immutable;

create or replace function makearray(x varchar) returns varchar[] as $$
BEGIN
return array_append('{}'::varchar[], x);
END;
$$ language plpgsql immutable;

CREATE SCHEMA stats;

CREATE TABLE stats.category_stats (
    category character varying(64),
    network_name text,
    count bigint
);

create table stats.domain_stats(
    id varchar(32) not null,
    name varchar(64), 
    description text, 
    block_count int, 
    total int
);

create table stats.domain_isp_stats(
    id serial, 
    tag varchar(32), 
    network_name varchar(64), 
    block_count int
);

create table domain_blacklist(
    id serial primary key not null,
    domain varchar not null unique,
    created timestamptz not null
);

create table courtorders (
    id serial primary key not null,
    name varchar(256) not null unique,
    date date,
    url varchar,
    judgment varchar,
    judgment_date date,
    judgment_url varchar,
    created timestamptz
);

create table courtorder_isp_urls (
    id serial primary key not null,
    order_id int not null,
    isp_id int not null,
    url varchar,
    created timestamptz
);

create table courtorder_urls (
    id serial primary key not null,
    order_id int not null,
    urlid int not null,
    created timestamptz
);

create unique index courtorder_order_url on courtorder_urls(order_id, urlid);
alter table courtorder_urls add foreign key (urlid) references urls(urlid) on delete cascade;
alter table courtorder_urls add foreign key (order_id) references courtorders(id) on delete cascade;

create unique index courtorder_isp_order_network on courtorder_isp_urls(order_id, isp_id);
alter table courtorder_isp_urls add foreign key (order_id) references courtorders(id) on delete cascade;


create table isp_report_emails(
    id serial primary key not null,
    report_id int not null,
    message text,
    created timestamptz
    );

alter table isp_report_emails add foreign key (report_id) references isp_reports(id) on delete cascade;


CREATE TABLE tags (
    id varchar not null primary key,
    name varchar(64),
    description text,
    type varchar(32)
);

create rule tag_insert_ignore  as on insert to tags where (exists(select 1 from tags where tags.id = new.id)) do instead nothing;

CREATE TABLE search_ignore_terms (
    id integer not null primary key,
    term varchar not null,
    created timestamptz not null,
    last_updated timestamptz null
);

CREATE UNIQUE INDEX search_ignore_terms_term on search_ignore_terms(term);

CREATE VIEW selected_categories AS SELECT * FROM categories WHERE (namespace != 'dmoz') OR (namespace = 'dmoz' AND tree ~ '!worl13.*{0}');

CREATE TABLE url_category_comments(
    id serial primary key not null,
    urlid int not null,
    description text null,
    userid int,
    created timestamptz,
    last_updated timestamptz
);

CREATE INDEX url_category_comments_urlid on url_category_comments(urlid);
ALTER TABLE url_category_comments ADD FOREIGN KEY (urlid) REFERENCES urls(id);

CREATE TABLE isp_report_comments (
    id serial primary key not null,
    report_id int not null,
    matches_policy bool null,
    egregious_block bool null,
    featured_block bool null,
    review_notes text,
    userid int not null,
    created timestamptz,
    last_updated timestamptz
);

CREATE INDEX isp_report_comments_report_id on isp_report_comments(report_id);

create table isp_report_categories(
    id serial primary key, 
    name varchar unique, 
    category_type varchar(8), 
    created timestamptz, 
    last_updated timestamptz
);

create table isp_report_category_asgt(
    id serial primary key,
    report_id int not null,
    category_id int not null,
    created timestamptz, 
    last_updated timestamptz
);

create unique index isp_report_category_asgt_unq on isp_report_category_asgt(report_id, category_id);

CREATE TABLE isp_report_category_comments(
    id serial primary key,
    report_id int not null,
    damage_category_id int,
    reporter_category_id int,
    review_notes text,
    userid int,
    created timestamptz,
    last_updated timestamptz
);

create index isp_report_category_comments_report_id on isp_report_category_comments(report_id);
