# Resend mailing extension

CiviCRM extension to allow you to select a pre-existing mailing and send it to
your search results.

1. Do a search

2. Select all or some of the contacts and choose "Email - Resend a previous
   mailing to these contacts"

3. Edit and then send/schedule the mailing as you wish. You'll need to choose an
   Unsubscribe group - this is the group people will be unsubscribed from if
   they unsubscribe from this mailing (normally it would just be the Mailing
   Group to which you're sending the mailing, but since you're sending to search
   results, you need to give a suitable Mailing Group to handle unsubscribes.)

## Why not use "Re-use" mailing

CiviMail provides a re-use mailing link, which does create a duplicate mailing,
however it will not let you select search results to mail to, nor will it allow
you to select an "unsubscribe" group.

## Why not create a new mailing group and then do a Re-use mailing?

Because when your less committed supporters unsubscribe from your new mailing
group, they might not realise that they are still subscribed to the original
mailing group(s), so might be narked when you send your next newsletter and move
from less committed to SENDING RANTY EMAILS IN ALL CAPS.

## Technically how does this work?

1. Add a search action which allows you to select an existing mailing (sent or draft).

2. Create a copy of the mailing (ideally reusing the "re-use" code) but then remove the groups and put the new hidden group in its place.

