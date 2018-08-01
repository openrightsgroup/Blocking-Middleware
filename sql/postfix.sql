
grant select(id,email,mailname) on isp_reports to postfix;
grant insert on isp_report_emails to postfix;
grant select,update on isp_report_emails_id_seq to postfix;

