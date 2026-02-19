--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: stats; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA stats;


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


--
-- Name: enum_isp_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_isp_status AS ENUM (
    'running',
    'down'
);


--
-- Name: enum_isp_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_isp_type AS ENUM (
    'fixed',
    'mobile',
    'dns'
);


--
-- Name: enum_policy_match; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_policy_match AS ENUM (
    'consistent',
    'inconsistent',
    'unknown',
    'no_longer_match'
);


--
-- Name: enum_probe_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_probe_status AS ENUM (
    'inactive',
    'testing',
    'active',
    'retired'
);


--
-- Name: enum_report_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_report_status AS ENUM (
    'new',
    'hold',
    'pending',
    'sent',
    'abuse',
    'cancelled',
    'auto-closed',
    'rejected',
    'unblocked',
    'no-decision',
    'resent',
    'escalated'
);


--
-- Name: enum_url_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_url_status AS ENUM (
    'ok',
    'disallowed-by-robots-txt',
    'disallowed-mime-type',
    'disallowed-content-length',
    'invalid',
    'duplicate',
    'restricted-malware',
    'restricted-admin',
    'restricted-by-admin'
);


--
-- Name: enum_url_type; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_url_type AS ENUM (
    'DOMAIN',
    'SUBDOMAIN',
    'PAGE'
);


--
-- Name: enum_user_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.enum_user_status AS ENUM (
    'pending',
    'ok',
    'suspended',
    'banned'
);


--
-- Name: isp_region; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.isp_region AS ENUM (
    'gb',
    'eu'
);


--
-- Name: add_tag(character varying[], character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.add_tag(p_tags character varying[], p_newtag character varying) RETURNS character varying[]
    LANGUAGE plpgsql
    AS $$
BEGIN
  IF p_tags && ARRAY[p_newtag]
  THEN
    RETURN p_tags;
  ELSE
    RETURN p_tags || p_newtag;
  END IF;
END;
$$;


--
-- Name: fmtime(timestamp without time zone); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.fmtime(x timestamp without time zone) RETURNS character varying
    LANGUAGE plpgsql IMMUTABLE
    AS $$
begin
return to_char(x, 'YYYY-MM-DD HH24:MI:SS');
end;
$$;


--
-- Name: fmtime(timestamp with time zone); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.fmtime(x timestamp with time zone) RETURNS character varying
    LANGUAGE plpgsql IMMUTABLE
    AS $$
begin
return to_char(x, 'YYYY-MM-DD HH24:MI:SS');
end;
$$;


--
-- Name: get_blocked_networks(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_blocked_networks(p_urlid integer) RETURNS character varying[]
    LANGUAGE plpgsql
    AS $$
 DECLARE
 out varchar[];
 rec RECORD;
 BEGIN
   FOR rec IN select * FROM public.url_latest_status WHERE STATUS = 'blocked' AND urlid = p_urlid ORDER BY network_name LOOP
      out = array_append(out , rec.network_name::varchar);
   END LOOP;
   RETURN out;
END;
$$;


--
-- Name: get_network_id(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.get_network_id(p character varying) RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE x int; BEGIN select id into x from isps where name = p; return x;
END; 
$$;


--
-- Name: getjsontitle(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.getjsontitle(js character varying) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
begin
return (js::json)->'title';
exception
when others then
return null;
end;
$$;


--
-- Name: insert_contact(character varying, character varying, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.insert_contact(p_email character varying, p_fullname character varying, p_joinlist integer) RETURNS void
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

CREATE FUNCTION public.insert_url_subscription(p_urlid integer, p_contactid integer, p_subscribereports integer) RETURNS integer
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
-- Name: makearray(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.makearray(x character varying) RETURNS character varying[]
    LANGUAGE plpgsql IMMUTABLE
    AS $$
BEGIN
return array_append('{}'::varchar[], x);
END;
$$;


--
-- Name: record_change(integer, character varying, character varying, character varying, timestamp with time zone, integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.record_change(p_urlid integer, p_network_name character varying, p_oldstatus character varying, p_newstatus character varying, p_created timestamp with time zone, p_oldresult_id integer) RETURNS void
    LANGUAGE plpgsql
    AS $$
begin
if p_oldstatus <> p_newstatus or p_oldstatus is null then
insert into url_status_changes(urlid, network_name, old_status, new_status, created, old_result_id) values (p_urlid, p_network_name,  p_oldstatus, p_newstatus, p_created, p_oldresult_id);
end if;
END;
$$;


--
-- Name: report_email_lookup(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.report_email_lookup(p_email character varying) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
DECLARE
  r_email varchar;
BEGIN
    IF POSITION('reply-isp' in p_email) > 0 THEN
        SELECT admin_email INTO r_email FROM isps INNER JOIN isp_reports ON network_name = isps.name
                    WHERE mailname = replace(p_email, 'reply-isp', 'reply');
    ELSE
        SELECT email INTO r_email FROM isp_reports WHERE mailname = p_email;
    END IF;
    RETURN r_email;
END
$$;


--
-- Name: trig_categories_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.trig_categories_ins_upd() RETURNS trigger
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

CREATE FUNCTION public.trig_isp_reports_insert() RETURNS trigger
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

CREATE FUNCTION public.trig_result_insert() RETURNS trigger
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
  result_id,
  isp_id)
 values (
 NEW.status,
 NEW.created,
 NEW.category,
 NEW.blocktype,
 NEW.urlid,
 NEW.network_name,
 NEW.id,
 (SELECT id FROM isps WHERE name = NEW.network_name)
 );
 END IF; 
 RETURN NEW;
END;
$$;


--
-- Name: trig_site_desc_before_insert(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.trig_site_desc_before_insert() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
delete from site_description where urlid = NEW.urlid; return NEW;
end;
$$;


--
-- Name: trig_uls_after_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.trig_uls_after_ins_upd() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
if TG_OP = 'UPDATE'
then
perform record_change(NEW.urlid, NEW.network_name,OLD.status, NEW.status, NEW.created, OLD.result_id);
else
perform record_change(NEW.urlid, NEW.network_name,NULL, NEW.status, NEW.created, NULL);
end if;
perform update_cache_block_count(NEW.urlid);
return NEW; END;
$$;


--
-- Name: trig_uls_ins_upd(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.trig_uls_ins_upd() RETURNS trigger
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

CREATE FUNCTION public.update_cache_block_count(p_urlid integer) RETURNS void
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


--
-- Name: url_root(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.url_root(p1 character varying) RETURNS character varying
    LANGUAGE plpgsql
    AS $$
BEGIN
    return replace(replace(replace(p1, 'http://', ''), 'https://', ''), 'www.', '');
END;
$$;


--
-- Name: urls_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.urls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: urls; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.urls (
    urlid integer DEFAULT nextval('public.urls_id_seq'::regclass) NOT NULL,
    url text,
    hash character varying(32),
    source character varying(32),
    lastpolled timestamp with time zone,
    inserted timestamp with time zone,
    status public.enum_url_status DEFAULT 'ok'::public.enum_url_status,
    last_reported timestamp with time zone,
    first_blocked timestamp with time zone,
    last_blocked timestamp with time zone,
    polledsuccess integer DEFAULT 0,
    title text,
    tags character varying[] DEFAULT '{}'::character varying[],
    whois_expiry timestamp with time zone,
    whois_expiry_last_checked timestamp with time zone,
    url_type public.enum_url_type
);


--
-- Name: url_variants(character varying); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.url_variants(p1 character varying) RETURNS SETOF public.urls
    LANGUAGE plpgsql
    AS $$
BEGIN
return query select * from urls where url in ('http://'||p1, 'https://'||p1, 'http://www.'||p1, 'https://www.'||p1);
END;
$$;


--
-- Name: adm_check_recirc; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.adm_check_recirc AS
 SELECT urls.urlid,
    urls.url,
    urls.lastpolled,
    urls.source,
    urls.tags
   FROM public.urls
  WHERE (((urls.lastpolled < (now() - '7 days'::interval)) AND ((urls.source)::text <> ALL (ARRAY[('social'::character varying)::text, ('dmoz'::character varying)::text, ('uk-zone'::character varying)::text, ('org-uk-zone'::character varying)::text, ('me-uk-zone'::character varying)::text, ('dot-uk-zone'::character varying)::text, ('dotorg'::character varying)::text]))) AND (urls.status = 'ok'::public.enum_url_status))
  ORDER BY urls.lastpolled
 LIMIT 10;


--
-- Name: blocked_dmoz; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.blocked_dmoz (
    urlid integer NOT NULL
);


--
-- Name: cache_block_count; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.cache_block_count (
    urlid integer NOT NULL,
    block_count_active integer DEFAULT 0,
    block_count_all integer DEFAULT 0,
    last_updated timestamp with time zone
);


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.categories (
    id integer DEFAULT nextval('public.categories_id_seq'::regclass) NOT NULL,
    display_name text,
    org_category_id integer,
    block_count integer,
    blocked_url_count integer,
    total_block_count integer,
    total_blocked_url_count integer,
    tree public.ltree,
    name text,
    name_fts tsvector,
    hold bool default false,
    namespace character varying(16),
    created timestamp with time zone,
    last_updated timestamp with time zone
);


--
-- Name: contacts; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.contacts (
    id integer NOT NULL,
    email character varying(128) NOT NULL,
    verified smallint DEFAULT 0 NOT NULL,
    joinlist smallint DEFAULT 0 NOT NULL,
    fullname character varying(60),
    createdat timestamp with time zone,
    token character varying(36),
    verify_attempts smallint DEFAULT 0,
    verify_last_attempt timestamp with time zone,
    enabled bool default true
);


--
-- Name: contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contacts_id_seq OWNED BY public.contacts.id;


--
-- Name: domain_blacklist; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.domain_blacklist (
    id integer NOT NULL,
    domain character varying NOT NULL,
    created timestamp with time zone NOT NULL
);


--
-- Name: domain_blacklist_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domain_blacklist_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain_blacklist_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domain_blacklist_id_seq OWNED BY public.domain_blacklist.id;


--
-- Name: isp_aliases; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isp_aliases (
    id integer NOT NULL,
    ispid smallint NOT NULL,
    alias character varying(64) NOT NULL,
    created timestamp with time zone
);


--
-- Name: isp_aliases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_aliases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_aliases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_aliases_id_seq OWNED BY public.isp_aliases.id;


--
-- Name: isp_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isp_cache (
    ip character varying(128) NOT NULL,
    network character varying(255) NOT NULL,
    created timestamp with time zone
);


--
-- Name: url_report_categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_report_categories (
    id integer NOT NULL,
    name character varying,
    category_type character varying(8),
    created timestamp with time zone,
    last_updated timestamp with time zone
);


--
-- Name: isp_report_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_report_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_report_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_report_categories_id_seq OWNED BY public.url_report_categories.id;


--
-- Name: url_report_category_asgt; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_report_category_asgt (
    id integer NOT NULL,
    report_id_x_del integer,
    category_id integer NOT NULL,
    created timestamp with time zone,
    last_updated timestamp with time zone,
    urlid integer NOT NULL
);


--
-- Name: isp_report_category_asgt_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_report_category_asgt_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_report_category_asgt_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_report_category_asgt_id_seq OWNED BY public.url_report_category_asgt.id;


--
-- Name: url_report_category_comments; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_report_category_comments (
    id integer NOT NULL,
    report_id_x_del integer,
    damage_category_id integer,
    reporter_category_id integer,
    review_notes text,
    userid integer,
    created timestamp with time zone,
    last_updated timestamp with time zone,
    urlid integer NOT NULL
);


--
-- Name: isp_report_category_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_report_category_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_report_category_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_report_category_comments_id_seq OWNED BY public.url_report_category_comments.id;


--
-- Name: isp_report_comments; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isp_report_comments (
    id integer NOT NULL,
    report_id integer NOT NULL,
    x_matches_policy boolean,
    egregious_block boolean,
    featured_block boolean,
    maybe_harmless boolean,
    review_notes text,
    userid integer NOT NULL,
    created timestamp with time zone,
    last_updated timestamp with time zone,
    policy_match public.enum_policy_match
);


--
-- Name: isp_report_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_report_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_report_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_report_comments_id_seq OWNED BY public.isp_report_comments.id;


--
-- Name: isp_report_emails; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isp_report_emails (
    id integer NOT NULL,
    report_id integer NOT NULL,
    message text,
    created timestamp with time zone
);


--
-- Name: isp_report_emails_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_report_emails_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_report_emails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.isp_report_emails_id_seq OWNED BY public.isp_report_emails.id;


--
-- Name: url_category_comments; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_category_comments (
    id integer NOT NULL,
    urlid integer NOT NULL,
    description text,
    userid integer,
    created timestamp with time zone,
    last_updated timestamp with time zone
);


--
-- Name: isp_report_users; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.isp_report_users AS
 SELECT isp_report_comments.report_id,
    NULL::integer AS urlid,
    isp_report_comments.userid
   FROM public.isp_report_comments
UNION
 SELECT NULL::integer AS report_id,
    url_report_category_comments.urlid,
    url_report_category_comments.userid
   FROM public.url_report_category_comments
UNION
 SELECT NULL::integer AS report_id,
    url_category_comments.urlid,
    url_category_comments.userid
   FROM public.url_category_comments;


--
-- Name: isp_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.isp_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isp_reports; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isp_reports (
    id integer DEFAULT nextval('public.isp_reports_id_seq'::regclass) NOT NULL,
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
    contact_id integer,
    allow_publish integer DEFAULT 0,
    status public.enum_report_status DEFAULT 'new'::public.enum_report_status,
    submitted timestamp with time zone,
    site_category character varying(64),
    allow_contact integer DEFAULT 0,
    mailname character varying(32),
    resolved_email_id integer,
    x_matches_policy boolean,
    egregious_block boolean,
    featured_block boolean,
    reporter_category_id integer,
    maybe_harmless boolean,
    user_type character varying[],
    resolved_userid integer,
    policy_match public.enum_policy_match,
    last_reminder timestamp with time zone,
    reminder_count integer DEFAULT 0
);


--
-- Name: isp_reports_sent; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.isp_reports_sent AS
 SELECT isp_reports.id,
    isp_reports.name,
    isp_reports.email,
    isp_reports.urlid,
    isp_reports.network_name,
    isp_reports.created,
    isp_reports.message,
    isp_reports.report_type,
    isp_reports.unblocked,
    isp_reports.notified,
    isp_reports.send_updates,
    isp_reports.last_updated,
    isp_reports.contact_id,
    isp_reports.allow_publish,
    isp_reports.status,
    isp_reports.submitted,
    isp_reports.site_category,
    isp_reports.allow_contact,
    isp_reports.mailname,
    isp_reports.resolved_email_id,
    isp_reports.x_matches_policy,
    isp_reports.egregious_block,
    isp_reports.featured_block,
    isp_reports.reporter_category_id,
    isp_reports.maybe_harmless,
    isp_reports.user_type,
    isp_reports.resolved_userid,
    isp_reports.policy_match,
    isp_reports.last_reminder,
    isp_reports.reminder_count
   FROM public.isp_reports
  WHERE (isp_reports.status = ANY (ARRAY['sent'::public.enum_report_status, 'unblocked'::public.enum_report_status, 'rejected'::public.enum_report_status, 'no-decision'::public.enum_report_status]));


--
-- Name: isp_reports_sent_old; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.isp_reports_sent_old AS
 SELECT isp_reports.id,
    isp_reports.name,
    isp_reports.email,
    isp_reports.urlid,
    isp_reports.network_name,
    isp_reports.created,
    isp_reports.message,
    isp_reports.report_type,
    isp_reports.unblocked,
    isp_reports.notified,
    isp_reports.send_updates,
    isp_reports.last_updated,
    isp_reports.contact_id,
    isp_reports.allow_publish,
    isp_reports.status,
    isp_reports.submitted,
    isp_reports.site_category,
    isp_reports.allow_contact,
    isp_reports.mailname,
    isp_reports.resolved_email_id,
    isp_reports.x_matches_policy AS matches_policy,
    isp_reports.egregious_block,
    isp_reports.featured_block,
    isp_reports.reporter_category_id,
    isp_reports.maybe_harmless,
    isp_reports.user_type,
    isp_reports.resolved_userid
   FROM public.isp_reports
  WHERE (isp_reports.status = ANY (ARRAY['sent'::public.enum_report_status, 'unblocked'::public.enum_report_status, 'rejected'::public.enum_report_status, 'no-decision'::public.enum_report_status]));


--
-- Name: isp_stats_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isp_stats_cache (
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

CREATE SEQUENCE public.isps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: isps; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.isps (
    id smallint DEFAULT nextval('public.isps_id_seq'::regclass) NOT NULL,
    name character varying(64),
    description text,
    queue_name text,
    created timestamp with time zone,
    show_results integer,
    admin_email text,
    admin_name text,
    filter_level character varying(20) DEFAULT 'default'::character varying,
    isp_type public.enum_isp_type,
    isp_status public.enum_isp_status,
    regions character varying[]
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.jobs (
    id character varying NOT NULL,
    updated timestamp with time zone,
    message character varying
);


--
-- Name: org_categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.org_categories (
    id integer NOT NULL,
    name character varying(64) NOT NULL
);


--
-- Name: org_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.org_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: org_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.org_categories_id_seq OWNED BY public.org_categories.id;


--
-- Name: probes; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.probes (
    id integer NOT NULL,
    uuid character varying(32) NOT NULL,
    userid integer,
    secret character varying(128),
    type character varying(10) NOT NULL,
    ispublic smallint DEFAULT 1 NOT NULL,
    enabled smallint DEFAULT 1 NOT NULL,
    lastseen timestamp with time zone,
    proberesprecv integer DEFAULT 0,
    isp_id smallint,
    probe_status public.enum_probe_status,
    location text,
    filter_enabled boolean,
    owner_link character varying,
    selftest_status integer,
    selftest_updated timestamp with time zone
);


--
-- Name: probes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.probes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: probes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.probes_id_seq OWNED BY public.probes.id;


--
-- Name: queue_length; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.queue_length (
    created timestamp with time zone NOT NULL,
    isp character varying(64) NOT NULL,
    type character varying(8) NOT NULL,
    length integer
);


--
-- Name: registry_suspensions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.registry_suspensions (
    id integer NOT NULL,
    urlid integer NOT NULL,
    registry character varying NOT NULL,
    created timestamp with time zone,
    lastseen timestamp with time zone
);


--
-- Name: registry_suspension_urls; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.registry_suspension_urls AS
 SELECT r.id,
    r.urlid,
    r.registry,
    r.created,
    r.lastseen,
    urls.url
   FROM (public.registry_suspensions r
     JOIN public.urls USING (urlid))
  ORDER BY r.created DESC, urls.url;


--
-- Name: registry_suspensions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.registry_suspensions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: registry_suspensions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.registry_suspensions_id_seq OWNED BY public.registry_suspensions.id;


--
-- Name: requests; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.requests (
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

CREATE TABLE public.requests_additional_data (
    id integer NOT NULL,
    request_id integer NOT NULL,
    name character varying(64),
    value character varying(255) NOT NULL,
    created timestamp with time zone
);


--
-- Name: requests_additional_data_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.requests_additional_data_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: requests_additional_data_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.requests_additional_data_id_seq OWNED BY public.requests_additional_data.id;


--
-- Name: requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.requests_id_seq OWNED BY public.requests.id;


--
-- Name: results_base; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.results_base (
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
    blocktype character varying(16),
    title text,
    remote_ip character varying(64),
    ssl_verified boolean,
    ssl_fingerprint character varying(256),
    request_id integer,
    final_url character varying(2048),
    resolved_ip cidr,
    result_uuid uuid
);


--
-- Name: results; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.results (
    id integer,
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
    title text,
    remote_ip character varying(64),
    ssl_verified boolean,
    ssl_fingerprint character varying(256),
    request_id integer,
    final_url character varying(2048),
    resolved_ip cidr,
    result_uuid uuid
)
INHERITS (public.results_base);


--
-- Name: results_extract; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.results_extract (
    id integer,
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
    title text,
    remote_ip character varying(64),
    ssl_verified boolean,
    ssl_fingerprint character varying(256),
    request_id integer,
    final_url character varying(2048),
    resolved_ip cidr,
    result_uuid uuid
)
INHERITS (public.results_base);


--
-- Name: results_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.results_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: results_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.results_id_seq OWNED BY public.results.id;


--
-- Name: search_ignore_terms; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.search_ignore_terms (
    id integer NOT NULL,
    term character varying NOT NULL,
    enabled boolean DEFAULT true NOT NULL,
    created timestamp with time zone NOT NULL,
    last_updated timestamp with time zone
);


--
-- Name: search_ignore_terms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.search_ignore_terms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: search_ignore_terms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.search_ignore_terms_id_seq OWNED BY public.search_ignore_terms.id;


--
-- Name: selected_categories; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.selected_categories AS
 SELECT categories.id,
    categories.display_name,
    categories.org_category_id,
    categories.block_count,
    categories.blocked_url_count,
    categories.total_block_count,
    categories.total_blocked_url_count,
    categories.tree,
    categories.name,
    categories.name_fts,
    categories.namespace,
    categories.created,
    categories.last_updated
   FROM public.categories
  WHERE ((categories.namespace)::text = 'ORG'::text)
  ORDER BY categories.namespace, categories.name;


--
-- Name: site_description; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.site_description (
    id integer NOT NULL,
    urlid integer NOT NULL,
    created timestamp with time zone,
    description text
);


--
-- Name: site_description_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.site_description_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: site_description_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.site_description_id_seq OWNED BY public.site_description.id;


--
-- Name: sources; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.sources (
    id integer NOT NULL,
    name character varying(32) NOT NULL,
    created timestamp with time zone,
    requeue boolean DEFAULT false
);


--
-- Name: sources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sources_id_seq OWNED BY public.sources.id;


--
-- Name: stats_cache; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.stats_cache (
    name character varying(64) NOT NULL,
    value integer,
    last_updated timestamp with time zone
);


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.tags (
    id character varying NOT NULL,
    name character varying(64),
    description text,
    type character varying(32)
);


--
-- Name: url_latest_status_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.url_latest_status_id_seq
    START WITH 50510239
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_latest_status; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_latest_status (
    id integer DEFAULT nextval('public.url_latest_status_id_seq'::regclass) NOT NULL,
    urlid integer NOT NULL,
    network_name text NOT NULL,
    status text,
    created timestamp with time zone,
    category character varying(64),
    blocktype character varying(16),
    first_blocked timestamp with time zone,
    last_blocked timestamp with time zone,
    result_id integer,
    isp_id smallint NOT NULL
);


--
-- Name: uls; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.uls AS
 SELECT url_latest_status.id,
    url_latest_status.urlid,
    url_latest_status.network_name,
    url_latest_status.status,
    url_latest_status.created,
    url_latest_status.category,
    url_latest_status.blocktype,
    url_latest_status.first_blocked,
    url_latest_status.last_blocked,
    url_latest_status.result_id
   FROM public.url_latest_status;


--
-- Name: url_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.url_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_categories; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_categories (
    id integer DEFAULT nextval('public.url_categories_id_seq'::regclass) NOT NULL,
    urlid integer,
    category_id integer,
    created timestamp with time zone,
    enabled boolean DEFAULT true,
    userid integer,
    last_updated timestamp with time zone,
    primary_category boolean
);


--
-- Name: url_category_comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.url_category_comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_category_comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.url_category_comments_id_seq OWNED BY public.url_category_comments.id;


--
-- Name: url_hierarchy; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_hierarchy (
    id integer NOT NULL,
    urlid integer NOT NULL,
    parent_urlid integer NOT NULL,
    created timestamp with time zone NOT NULL
);


--
-- Name: url_hierarchy_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.url_hierarchy_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_hierarchy_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.url_hierarchy_id_seq OWNED BY public.url_hierarchy.id;


--
-- Name: url_primary_categories; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.url_primary_categories AS
 SELECT url_categories.id,
    url_categories.urlid,
    url_categories.category_id,
    url_categories.created,
    url_categories.enabled,
    url_categories.userid,
    url_categories.last_updated,
    url_categories.primary_category
   FROM public.url_categories
  WHERE (url_categories.primary_category = true);


--
-- Name: url_status_changes; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_status_changes (
    id integer NOT NULL,
    urlid integer NOT NULL,
    network_name character varying(64) NOT NULL,
    old_status character varying(16),
    new_status character varying(16),
    old_result_id int null,
    created timestamp with time zone,
    notified smallint DEFAULT 0 NOT NULL
);


--
-- Name: url_status_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.url_status_changes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_status_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.url_status_changes_id_seq OWNED BY public.url_status_changes.id;


--
-- Name: url_subscriptions; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.url_subscriptions (
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

CREATE SEQUENCE public.url_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: url_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.url_subscriptions_id_seq OWNED BY public.url_subscriptions.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE public.users (
    id integer NOT NULL,
    email character varying(128) NOT NULL,
    password character varying(255),
    preference text,
    fullname character varying(60),
    ispublic smallint DEFAULT 1,
    countrycode character varying(3),
    probehmac character varying(32),
    status public.enum_user_status DEFAULT 'ok'::public.enum_user_status,
    secret character varying(128),
    createdat timestamp with time zone,
    administrator smallint DEFAULT 0
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;

--
-- anomaly checker
--

CREATE TYPE public.enum_anomaly_review_status AS ENUM(
    'new',
    'blocked',
    'not-blocked'
);

CREATE TABLE public.anomaly_check_results (
    id serial not null primary key,
    urlid int not null,
    result_json json not null,
    review public.enum_anomaly_review_status default 'new',
    reviewed_timestamp timestamptz,
    reviewed_by text,
    created timestamptz not null,
    last_updated timestamptz null
);

CREATE TABLE public.anomaly_check_responses (
    id serial not null primary key,
    result_id int not null,
    region varchar(10) not null,
    response_json json not null,
    created timestamptz not null,
    last_updated timestamptz null
);

CREATE TABLE public.archived_urls (
    id serial primary key not null,
    urlid int null,
    url varchar not null,
    snapshot_url varchar not null,
    created timestamptz not null,
    last_updated timestamptz null
);

--
-- Name: cache_copyright_blocks; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.cache_copyright_blocks (
    url text,
    networks text[],
    first_blocked character varying,
    last_blocked character varying,
    regions character varying[]
);


--
-- Name: category_stats; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.category_stats (
    category character varying(64),
    network_name text,
    count bigint,
    total integer
);


--
-- Name: domain_isp_stats; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.domain_isp_stats (
    id integer NOT NULL,
    tag character varying(32),
    network_name character varying(64),
    block_count integer
);


--
-- Name: domain_isp_stats_id_seq; Type: SEQUENCE; Schema: stats; Owner: -
--

CREATE SEQUENCE stats.domain_isp_stats_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain_isp_stats_id_seq; Type: SEQUENCE OWNED BY; Schema: stats; Owner: -
--

ALTER SEQUENCE stats.domain_isp_stats_id_seq OWNED BY stats.domain_isp_stats.id;


--
-- Name: domain_stats; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.domain_stats (
    id character varying(32) NOT NULL,
    name character varying(64),
    description text,
    block_count integer,
    total integer
);


--
-- Name: mobile_blocks; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.mobile_blocks (
    network_name text,
    count bigint,
    block_count bigint
);


--
-- Name: savedlist_summary; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.savedlist_summary (
    id integer,
    name character varying,
    username character varying,
    public boolean,
    frontpage boolean,
    item_count bigint,
    reported_count bigint,
    block_count bigint,
    unblock_count bigint,
    active_block_count integer,
    item_block_count integer
);


--
-- Name: savedlist_summary_no_btstrict; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.savedlist_summary_no_btstrict (
    id integer,
    name character varying,
    username character varying,
    public boolean,
    frontpage boolean,
    item_count bigint,
    reported_count bigint,
    block_count bigint,
    unblock_count bigint,
    active_blocks bigint,
    item_block_count bigint
);


--
-- Name: savedlist_summary_save; Type: TABLE; Schema: stats; Owner: -; Tablespace: 
--

CREATE TABLE stats.savedlist_summary_save (
    id integer,
    name character varying,
    username character varying,
    public boolean,
    frontpage boolean,
    item_count bigint,
    reported_count bigint,
    block_count bigint,
    unblock_count bigint,
    active_block_count integer,
    item_block_count integer
);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contacts ALTER COLUMN id SET DEFAULT nextval('public.contacts_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_blacklist ALTER COLUMN id SET DEFAULT nextval('public.domain_blacklist_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_aliases ALTER COLUMN id SET DEFAULT nextval('public.isp_aliases_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_report_comments ALTER COLUMN id SET DEFAULT nextval('public.isp_report_comments_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_report_emails ALTER COLUMN id SET DEFAULT nextval('public.isp_report_emails_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.org_categories ALTER COLUMN id SET DEFAULT nextval('public.org_categories_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.probes ALTER COLUMN id SET DEFAULT nextval('public.probes_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.registry_suspensions ALTER COLUMN id SET DEFAULT nextval('public.registry_suspensions_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requests ALTER COLUMN id SET DEFAULT nextval('public.requests_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requests_additional_data ALTER COLUMN id SET DEFAULT nextval('public.requests_additional_data_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.results ALTER COLUMN id SET DEFAULT nextval('public.results_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.search_ignore_terms ALTER COLUMN id SET DEFAULT nextval('public.search_ignore_terms_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.site_description ALTER COLUMN id SET DEFAULT nextval('public.site_description_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sources ALTER COLUMN id SET DEFAULT nextval('public.sources_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_category_comments ALTER COLUMN id SET DEFAULT nextval('public.url_category_comments_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_hierarchy ALTER COLUMN id SET DEFAULT nextval('public.url_hierarchy_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_report_categories ALTER COLUMN id SET DEFAULT nextval('public.isp_report_categories_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_report_category_asgt ALTER COLUMN id SET DEFAULT nextval('public.isp_report_category_asgt_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_report_category_comments ALTER COLUMN id SET DEFAULT nextval('public.isp_report_category_comments_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_status_changes ALTER COLUMN id SET DEFAULT nextval('public.url_status_changes_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.url_subscriptions_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: stats; Owner: -
--

ALTER TABLE ONLY stats.domain_isp_stats ALTER COLUMN id SET DEFAULT nextval('stats.domain_isp_stats_id_seq'::regclass);


--
-- Name: blocked_dmoz_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.blocked_dmoz
    ADD CONSTRAINT blocked_dmoz_pkey PRIMARY KEY (urlid);


--
-- Name: cache_block_count2_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.cache_block_count
    ADD CONSTRAINT cache_block_count2_pkey PRIMARY KEY (urlid);


--
-- Name: categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: contacts_email_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_email_key UNIQUE (email);


--
-- Name: contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.contacts
    ADD CONSTRAINT contacts_pkey PRIMARY KEY (id);


--
-- Name: domain_blacklist_domain_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.domain_blacklist
    ADD CONSTRAINT domain_blacklist_domain_key UNIQUE (domain);


--
-- Name: domain_blacklist_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.domain_blacklist
    ADD CONSTRAINT domain_blacklist_pkey PRIMARY KEY (id);


--
-- Name: isp_aliases_alias_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_aliases
    ADD CONSTRAINT isp_aliases_alias_key UNIQUE (alias);


--
-- Name: isp_aliases_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_aliases
    ADD CONSTRAINT isp_aliases_pkey PRIMARY KEY (id);


--
-- Name: isp_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_cache
    ADD CONSTRAINT isp_cache_pkey PRIMARY KEY (ip, network);


--
-- Name: isp_report_categories_name_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_report_categories
    ADD CONSTRAINT isp_report_categories_name_key UNIQUE (name);


--
-- Name: isp_report_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_report_categories
    ADD CONSTRAINT isp_report_categories_pkey PRIMARY KEY (id);


--
-- Name: isp_report_category_asgt_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_report_category_asgt
    ADD CONSTRAINT isp_report_category_asgt_pkey PRIMARY KEY (id);


--
-- Name: isp_report_category_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_report_category_comments
    ADD CONSTRAINT isp_report_category_comments_pkey PRIMARY KEY (id);


--
-- Name: isp_report_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_report_comments
    ADD CONSTRAINT isp_report_comments_pkey PRIMARY KEY (id);


--
-- Name: isp_report_emails_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_report_emails
    ADD CONSTRAINT isp_report_emails_pkey PRIMARY KEY (id);


--
-- Name: isp_reports_mailname_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_reports
    ADD CONSTRAINT isp_reports_mailname_key UNIQUE (mailname);


--
-- Name: isp_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_reports
    ADD CONSTRAINT isp_reports_pkey PRIMARY KEY (id);


--
-- Name: isp_stats_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isp_stats_cache
    ADD CONSTRAINT isp_stats_cache_pkey PRIMARY KEY (network_name);


--
-- Name: isps_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.isps
    ADD CONSTRAINT isps_pkey PRIMARY KEY (id);


--
-- Name: jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: org_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.org_categories
    ADD CONSTRAINT org_categories_pkey PRIMARY KEY (id);


--
-- Name: probes_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.probes
    ADD CONSTRAINT probes_pkey PRIMARY KEY (id);


--
-- Name: probes_uuid_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.probes
    ADD CONSTRAINT probes_uuid_key UNIQUE (uuid);


--
-- Name: queue_length_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.queue_length
    ADD CONSTRAINT queue_length_pkey PRIMARY KEY (type, isp, created);


--
-- Name: registry_suspensions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.registry_suspensions
    ADD CONSTRAINT registry_suspensions_pkey PRIMARY KEY (id);


--
-- Name: requests_additional_data_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.requests_additional_data
    ADD CONSTRAINT requests_additional_data_pkey PRIMARY KEY (id);


--
-- Name: requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.requests
    ADD CONSTRAINT requests_pkey PRIMARY KEY (id);


--
-- Name: results_pkey1; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.results
    ADD CONSTRAINT results_pkey1 PRIMARY KEY (id);


--
-- Name: search_ignore_terms_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.search_ignore_terms
    ADD CONSTRAINT search_ignore_terms_pkey PRIMARY KEY (id);


--
-- Name: site_description_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.site_description
    ADD CONSTRAINT site_description_pkey PRIMARY KEY (id);


--
-- Name: sources_name_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.sources
    ADD CONSTRAINT sources_name_key UNIQUE (name);


--
-- Name: sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.sources
    ADD CONSTRAINT sources_pkey PRIMARY KEY (id);


--
-- Name: stats_cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.stats_cache
    ADD CONSTRAINT stats_cache_pkey PRIMARY KEY (name);


--
-- Name: tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: uls_url_ispid; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_latest_status
    ADD CONSTRAINT uls_url_ispid PRIMARY KEY (urlid, isp_id);


--
-- Name: url_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_categories
    ADD CONSTRAINT url_categories_pkey PRIMARY KEY (id);


--
-- Name: url_category_comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_category_comments
    ADD CONSTRAINT url_category_comments_pkey PRIMARY KEY (id);


--
-- Name: url_hierarchy_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_hierarchy
    ADD CONSTRAINT url_hierarchy_pkey PRIMARY KEY (id);


--
-- Name: url_status_changes_new_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_status_changes
    ADD CONSTRAINT url_status_changes_new_pkey PRIMARY KEY (id);


--
-- Name: url_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_subscriptions
    ADD CONSTRAINT url_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: url_subscriptions_token_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.url_subscriptions
    ADD CONSTRAINT url_subscriptions_token_key UNIQUE (token);


--
-- Name: urls_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.urls
    ADD CONSTRAINT urls_pkey PRIMARY KEY (urlid);


--
-- Name: users_email_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: blocktype_created2; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX blocktype_created2 ON public.url_latest_status USING btree (first_blocked) WHERE ((blocktype)::text = 'COPYRIGHT'::text);


--
-- Name: cat_tree; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX cat_tree ON public.categories USING gist (tree);


--
-- Name: categories_name_fts; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX categories_name_fts ON public.categories USING gin (name_fts);


--
-- Name: categories_name_namespace_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX categories_name_namespace_idx ON public.categories USING btree (name, namespace) WHERE ((namespace)::text <> 'dmoz'::text);


--
-- Name: isp_aliases_ispid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX isp_aliases_ispid ON public.isp_aliases USING btree (ispid);


--
-- Name: isp_name; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX isp_name ON public.isps USING btree (name);


--
-- Name: isp_report_comments_report_id; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX isp_report_comments_report_id ON public.isp_report_comments USING btree (report_id);


--
-- Name: isp_report_urlid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX isp_report_urlid ON public.isp_reports USING btree (urlid, network_name);


--
-- Name: registry_suspensions_urlid_registry_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX registry_suspensions_urlid_registry_idx ON public.registry_suspensions USING btree (urlid, registry);


--
-- Name: requests_urlid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX requests_urlid ON public.requests USING btree (urlid);


--
-- Name: results_extract_id_pkey; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX results_extract_id_pkey ON public.results_extract USING btree (id);


--
-- Name: results_url_network; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX results_url_network ON public.results USING btree (urlid, network_name);


--
-- Name: search_ignore_terms_term; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX search_ignore_terms_term ON public.search_ignore_terms USING btree (term);


--
-- Name: site_description_urlid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX site_description_urlid ON public.site_description USING btree (urlid);


--
-- Name: url_cat_unq; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX url_cat_unq ON public.url_categories USING btree (category_id, urlid);


--
-- Name: url_cat_urlid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_cat_urlid ON public.url_categories USING btree (urlid);


--
-- Name: url_categories_urlid_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX url_categories_urlid_idx ON public.url_categories USING btree (urlid) WHERE (primary_category = true);


--
-- Name: url_latest_status_urlid_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_latest_status_urlid_idx ON public.url_latest_status USING btree (urlid) WHERE ((blocktype)::text = 'SUSPENSION'::text);


--
-- Name: url_report_category_asgt_unq; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX url_report_category_asgt_unq ON public.url_report_category_asgt USING btree (urlid, category_id);


--
-- Name: url_report_category_comments_urlid; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_report_category_comments_urlid ON public.url_report_category_comments USING btree (urlid);


--
-- Name: url_status_changes_new_created_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_status_changes_new_created_idx ON public.url_status_changes USING btree (created);


--
-- Name: url_status_changes_new_urlid_idx; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_status_changes_new_urlid_idx ON public.url_status_changes USING btree (urlid);


--
-- Name: url_tags; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE INDEX url_tags ON public.urls USING gin (tags);


--
-- Name: urls_url; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX urls_url ON public.urls USING btree (url);


--
-- Name: urlsub_contact; Type: INDEX; Schema: public; Owner: -; Tablespace: 
--

CREATE UNIQUE INDEX urlsub_contact ON public.url_subscriptions USING btree (urlid, contactid);


--
-- Name: tags tag_insert_ignore; Type: RULE; Schema: public; Owner: -
--

CREATE RULE tag_insert_ignore AS
    ON INSERT TO public.tags
   WHERE (EXISTS ( SELECT 1
           FROM public.tags
          WHERE ((tags.id)::text = (new.id)::text))) DO INSTEAD NOTHING;


--
-- Name: urls urls_insert_ignore; Type: RULE; Schema: public; Owner: -
--

CREATE RULE urls_insert_ignore AS
    ON INSERT TO public.urls
   WHERE (EXISTS ( SELECT 1
           FROM public.urls
          WHERE (urls.url = new.url))) DO INSTEAD NOTHING;


--
-- Name: trig_categories_ins_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_categories_ins_upd BEFORE INSERT OR UPDATE ON public.categories FOR EACH ROW EXECUTE PROCEDURE public.trig_categories_ins_upd();


--
-- Name: trig_isp_reports_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_isp_reports_insert AFTER INSERT ON public.isp_reports FOR EACH ROW EXECUTE PROCEDURE public.trig_isp_reports_insert();


--
-- Name: trig_result_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_result_insert AFTER INSERT ON public.results FOR EACH ROW EXECUTE PROCEDURE public.trig_result_insert();


--
-- Name: trig_site_desc_before_insert; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_site_desc_before_insert BEFORE INSERT ON public.site_description FOR EACH ROW EXECUTE PROCEDURE public.trig_site_desc_before_insert();


--
-- Name: trig_uls_after_ins_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_uls_after_ins_upd AFTER INSERT OR UPDATE ON public.url_latest_status FOR EACH ROW EXECUTE PROCEDURE public.trig_uls_after_ins_upd();


--
-- Name: trig_uls_ins_upd; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trig_uls_ins_upd BEFORE INSERT OR UPDATE ON public.url_latest_status FOR EACH ROW EXECUTE PROCEDURE public.trig_uls_ins_upd();


--
-- Name: isp_aliases_ispid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_aliases
    ADD CONSTRAINT isp_aliases_ispid_fkey FOREIGN KEY (ispid) REFERENCES public.isps(id) DEFERRABLE;


--
-- Name: isp_report_emails_report_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.isp_report_emails
    ADD CONSTRAINT isp_report_emails_report_id_fkey FOREIGN KEY (report_id) REFERENCES public.isp_reports(id) ON DELETE CASCADE;


--
-- Name: probes_isp_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.probes
    ADD CONSTRAINT probes_isp_id_fkey FOREIGN KEY (isp_id) REFERENCES public.isps(id) DEFERRABLE;


--
-- Name: requests_contactid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.requests
    ADD CONSTRAINT requests_contactid_fkey FOREIGN KEY (contactid) REFERENCES public.contacts(id) ON DELETE SET NULL;


--
-- Name: url_categories_category_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_categories
    ADD CONSTRAINT url_categories_category_id_fkey FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: url_category_comments_urlid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_category_comments
    ADD CONSTRAINT url_category_comments_urlid_fkey FOREIGN KEY (urlid) REFERENCES public.urls(urlid);


--
-- Name: url_hierarchy_parent_urlid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_hierarchy
    ADD CONSTRAINT url_hierarchy_parent_urlid_fkey FOREIGN KEY (parent_urlid) REFERENCES public.urls(urlid);


--
-- Name: url_hierarchy_urlid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.url_hierarchy
    ADD CONSTRAINT url_hierarchy_urlid_fkey FOREIGN KEY (urlid) REFERENCES public.urls(urlid);


--
-- anomaly
--

ALTER TABLE public.anomaly_check_responses add foreign key (result_id) references public.anomaly_check_results (id)  ON DELETE CASCADE;
ALTER TABLE public.anomaly_check_results add foreign key (urlid) references public.urls (urlid);



--
-- PostgreSQL database dump complete
--

