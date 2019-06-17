GRANT UPDATE(tags) ON TABLE urls TO blockedadmin;
GRANT UPDATE(unblocked) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(last_updated) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(status) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(resolved_email_id) ON TABLE isp_reports TO blockedadmin;

GRANT SELECT,INSERT ON TABLE isp_report_comments TO blockedadmin;
GRANT SELECT,UPDATE ON TABLE isp_report_comments_id_seq TO blockedadmin;

GRANT SELECT,UPDATE,INSERT,DELETE on table url_report_categories to blockedadmin;
GRANT SELECT,UPDATE on sequence url_report_categories_id_seq to blockedadmin;
GRANT SELECT,UPDATE,INSERT,DELETE on table url_report_category_asgt to blockedadmin;
GRANT SELECT,UPDATE on sequence url_report_category_asgt_id_seq to blockedadmin;
GRANT SELECT,UPDATE,INSERT,DELETE on table url_report_category_comments to blockedadmin;
GRANT SELECT,UPDATE on sequence url_report_category_comments_id_seq to blockedadmin;


GRANT UPDATE(egregious_block, featured_block, maybe_harmless) ON TABLE isp_reports TO blockedadmin;

GRANT USAGE ON SCHEMA stats TO blockedadmin;
GRANT SELECT ON ALL TABLES IN SCHEMA stats TO blockedadmin;
GRANT SELECT,UPDATE,INSERT,DELETE ON search_ignore_terms TO blockedadmin;
GRANT SELECT,UPDATE ON search_ignore_terms_id_seq TO blockedadmin;

GRANT update,insert,delete on url_categories to blockedadmin;
GRANT update,insert,delete on categories to blockedadmin;

GRANT SELECT ON isp_reports_sent TO blockedadmin;
GRANT SELECT ON url_primary_categories TO blockedadmin;
GRANT select ON contacts to blockedadmin;
GRANT select ON tags to blockedadmin;

