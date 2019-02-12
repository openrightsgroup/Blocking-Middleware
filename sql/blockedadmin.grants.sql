GRANT UPDATE(tags) ON TABLE urls TO blockedadmin;
GRANT UPDATE(unblocked) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(last_updated) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(status) ON TABLE isp_reports TO blockedadmin;
GRANT UPDATE(resolved_email_id) ON TABLE isp_reports TO blockedadmin;

GRANT SELECT,INSERT ON TABLE isp_report_comments TO blockedadmin;
GRANT SELECT,UPDATE ON TABLE isp_report_comments_id_seq TO blockedadmin;

