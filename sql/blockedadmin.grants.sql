GRANT UPDATE(tags) ON TABLE urls TO blockedadmin;
GRANT UPDATE(unblocked) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(last_updated) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(status) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(resolved_email_id) ON TABLE isp_reports TO blockedadmin;

GRANT SELECT,INSERT ON TABLE isp_report_comments TO blockedadmin;
GRANT SELECT,UPDATE ON TABLE isp_report_comments_id_seq TO blockedadmin;

GRANT SELECT,UPDATE,INSERT,DELETE on table isp_report_categories to blockedadmin;
GRANT SELECT,UPDATE on sequence isp_report_categories_id_seq to blockedadmin;
GRANT SELECT,UPDATE,INSERT,DELETE on table isp_report_category_asgt to blockedadmin;
GRANT SELECT,UPDATE on sequence isp_report_category_asgt_id_seq to blockedadmin;
GRANT UPDATE(reporter_category_id) on isp_reports to blockedadmin

GRANT SELECT,UPDATE,INSERT,DELETE on table isp_report_category_comments to blockedadmin;
GRANT SELECT,UPDATE on sequence isp_report_category_comments_id_seq to blockedadmin;


