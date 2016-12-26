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
UPDATE url_latest_status SET
status = NEW.status, created = NEW.created, category = NEW.category, blocktype = NEW.blocktype
WHERE urlid = NEW.urlid and network_name = NEW.network_name;

IF NOT FOUND
THEN
insert into url_latest_status (
 status ,
 created ,
 category ,
 blocktype ,
 urlid ,
 network_name )
values (
NEW.status,
NEW.created,
NEW.category,
NEW.blocktype,
NEW.urlid,
NEW.network_name
);
END IF; RETURN NEW;
END;
$$;


--
-- Name: trig_uls_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION trig_uls_ins_upd() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
perform update_cache_block_count(NEW.urlid);
return NEW; END;
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
-- Name: categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE categories (
    id integer NOT NULL,
    display_name text,
    org_category_id integer,
    block_count integer,
    blocked_url_count integer,
    total_block_count integer,
    total_blocked_url_count integer,
    tree ltree,
    name text
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
    token character varying(36)
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
    id integer,
    name text,
    email text,
    urlid integer,
    network_name text,
    created timestamp with time zone,
    message text,
    report_type character varying(32),
    unblocked integer,
    notified integer,
    send_updates integer,
    last_updated timestamp with time zone
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
-- Name: isps; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE isps (
    id integer NOT NULL,
    name character varying(64),
    description text,
    queue_name text,
    created timestamp with time zone,
    show_results integer,
    admin_email text,
    admin_name text
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
    lastseen timestamp with time zone
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
-- Name: results; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE results (
    id integer NOT NULL,
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
    blocktype character varying(16)
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
-- Name: url_categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE url_categories (
    id integer,
    urlid integer,
    category_id integer
);


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
    blocktype character varying(16)
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
-- Name: urls; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE urls (
    urlid integer NOT NULL,
    url text,
    hash character varying(32),
    source character varying(32),
    lastpolled timestamp with time zone,
    inserted timestamp with time zone,
    status character varying(32),
    last_reported timestamp with time zone
);


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
-- Name: cat_tree; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX cat_tree ON categories USING gist (tree);


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
-- Name: uls_url_network; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX uls_url_network ON url_latest_status USING btree (urlid, network_name);


--
-- Name: url_status_changes_created; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_status_changes_created ON url_status_changes USING btree (created);


--
-- Name: urlsub_contact; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX urlsub_contact ON url_subscriptions USING btree (urlid, contactid);


--
-- Name: trig_isp_reports_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_isp_reports_insert AFTER INSERT ON isp_reports FOR EACH ROW EXECUTE PROCEDURE trig_isp_reports_insert();


--
-- Name: trig_result_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_result_insert AFTER INSERT ON results FOR EACH ROW EXECUTE PROCEDURE trig_result_insert();


--
-- Name: trig_uls_ins; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_uls_ins AFTER INSERT ON url_latest_status FOR EACH ROW EXECUTE PROCEDURE trig_uls_ins_upd();


--
-- Name: trig_uls_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_uls_upd AFTER UPDATE ON url_latest_status FOR EACH ROW EXECUTE PROCEDURE trig_uls_ins_upd();


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

