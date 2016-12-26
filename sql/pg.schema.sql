--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

SET search_path = public, pg_catalog;

--
-- Name: trig_isp_reports_insert(); Type: FUNCTION; Schema: public; Owner: root
--

CREATE FUNCTION trig_isp_reports_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
update urls set last_reported = NEW.created where urls.urlid = NEW.urlid;
return NEW;
END;
$$;


ALTER FUNCTION public.trig_isp_reports_insert() OWNER TO root;

--
-- Name: trig_result_insert(); Type: FUNCTION; Schema: public; Owner: root
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


ALTER FUNCTION public.trig_result_insert() OWNER TO root;

--
-- Name: trig_uls_ins_upd(); Type: FUNCTION; Schema: public; Owner: root
--

CREATE FUNCTION trig_uls_ins_upd() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
perform update_cache_block_count(NEW.urlid);
return NEW; END;
$$;


ALTER FUNCTION public.trig_uls_ins_upd() OWNER TO root;

--
-- Name: update_cache_block_count(integer); Type: FUNCTION; Schema: public; Owner: root
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


ALTER FUNCTION public.update_cache_block_count(p_urlid integer) OWNER TO root;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: cache_block_count; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
--

CREATE TABLE cache_block_count (
    urlid integer NOT NULL,
    block_count_active integer DEFAULT 0,
    block_count_all integer DEFAULT 0,
    last_updated timestamp with time zone
);


ALTER TABLE public.cache_block_count OWNER TO blocked;

--
-- Name: categories; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
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


ALTER TABLE public.categories OWNER TO blocked;

--
-- Name: isp_reports; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
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


ALTER TABLE public.isp_reports OWNER TO blocked;

--
-- Name: isps; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
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


ALTER TABLE public.isps OWNER TO blocked;

--
-- Name: results; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
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


ALTER TABLE public.results OWNER TO blocked;

--
-- Name: url_categories; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
--

CREATE TABLE url_categories (
    id integer,
    urlid integer,
    category_id integer
);


ALTER TABLE public.url_categories OWNER TO blocked;

--
-- Name: url_latest_status_id_seq; Type: SEQUENCE; Schema: public; Owner: root
--

CREATE SEQUENCE url_latest_status_id_seq
    START WITH 50510239
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.url_latest_status_id_seq OWNER TO root;

--
-- Name: url_latest_status; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
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


ALTER TABLE public.url_latest_status OWNER TO blocked;

--
-- Name: urls; Type: TABLE; Schema: public; Owner: blocked; Tablespace: 
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


ALTER TABLE public.urls OWNER TO blocked;

--
-- Name: cache_block_count_pkey; Type: CONSTRAINT; Schema: public; Owner: blocked; Tablespace: 
--

ALTER TABLE ONLY cache_block_count
    ADD CONSTRAINT cache_block_count_pkey PRIMARY KEY (urlid);


--
-- Name: categories_pkey; Type: CONSTRAINT; Schema: public; Owner: blocked; Tablespace: 
--

ALTER TABLE ONLY categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: isps_pkey; Type: CONSTRAINT; Schema: public; Owner: blocked; Tablespace: 
--

ALTER TABLE ONLY isps
    ADD CONSTRAINT isps_pkey PRIMARY KEY (id);


--
-- Name: results_pkey; Type: CONSTRAINT; Schema: public; Owner: blocked; Tablespace: 
--

ALTER TABLE ONLY results
    ADD CONSTRAINT results_pkey PRIMARY KEY (id);


--
-- Name: url_latest_status_pkey; Type: CONSTRAINT; Schema: public; Owner: blocked; Tablespace: 
--

ALTER TABLE ONLY url_latest_status
    ADD CONSTRAINT url_latest_status_pkey PRIMARY KEY (id);


--
-- Name: urls_pkey; Type: CONSTRAINT; Schema: public; Owner: blocked; Tablespace: 
--

ALTER TABLE ONLY urls
    ADD CONSTRAINT urls_pkey PRIMARY KEY (urlid);


--
-- Name: cat_tree; Type: INDEX; Schema: public; Owner: blocked; Tablespace: 
--

CREATE INDEX cat_tree ON categories USING gist (tree);


--
-- Name: isp_name; Type: INDEX; Schema: public; Owner: blocked; Tablespace: 
--

CREATE UNIQUE INDEX isp_name ON isps USING btree (name);


--
-- Name: results_url_network; Type: INDEX; Schema: public; Owner: blocked; Tablespace: 
--

CREATE INDEX results_url_network ON results USING btree (urlid, network_name);


--
-- Name: uls_url_network; Type: INDEX; Schema: public; Owner: blocked; Tablespace: 
--

CREATE UNIQUE INDEX uls_url_network ON url_latest_status USING btree (urlid, network_name);


--
-- Name: trig_isp_reports_insert; Type: TRIGGER; Schema: public; Owner: blocked
--

CREATE TRIGGER trig_isp_reports_insert AFTER INSERT ON isp_reports FOR EACH ROW EXECUTE PROCEDURE trig_isp_reports_insert();


--
-- Name: trig_result_insert; Type: TRIGGER; Schema: public; Owner: blocked
--

CREATE TRIGGER trig_result_insert AFTER INSERT ON results FOR EACH ROW EXECUTE PROCEDURE trig_result_insert();


--
-- Name: trig_uls_ins; Type: TRIGGER; Schema: public; Owner: blocked
--

CREATE TRIGGER trig_uls_ins AFTER INSERT ON url_latest_status FOR EACH ROW EXECUTE PROCEDURE trig_uls_ins_upd();


--
-- Name: trig_uls_upd; Type: TRIGGER; Schema: public; Owner: blocked
--

CREATE TRIGGER trig_uls_upd AFTER UPDATE ON url_latest_status FOR EACH ROW EXECUTE PROCEDURE trig_uls_ins_upd();


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

