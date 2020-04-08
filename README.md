This plugin extends CiviCRM WordPress Member Sync by Christian Wach
to cater for a membership structure where the member is household,
but the login is via the Head of Household ( custom relationship id = 7 )

It does this by filtering the contact data by matching up the relationship and grabbing the email

If no email exists it generates a random email so later email changes can be applied

It also does a one way syncronisation from CiviCRM to WordPress for email changes of releationship changes in CiviCRM
using the custom relationship
 
There is no error checking so if CiviCRM is not loaded things will simple fail, but us driven off CivCRM filters so a low risk here
 
With thanks to Christian Wach for his tips and code examples
