-- composition (determination letter)
'TBD' as author
organizations can come from SITESPROJECTS

-- study
select
	pr_id,
	pr_title,
	'TBD' as status,
	lead_si_id,
	'TBD' as pi, -- there seem to be multiple pis per site/project in irex... 
	reviewer_si_id -- maybe add to composition org ids
from projects p

-- organizations
select 
	si_id,
	si_name,
	'TBD' as contact -- such thing as primary or should we include all?
from sites
where si_id in (1607)

-- practitioners
select
	usr_id,
	usr_firstname,
	usr_lastname,
	usr_email
from users
