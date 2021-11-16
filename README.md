# Resend mailing extension

CiviCRM extension to allow you to select a pre-existing mailing and send it to
your search results.

1. Do a search

2. Select all or some of the contacts and choose "Email - resend a CiviMail
   mailing"

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

## New, beta: Immediately resend a mailing to a single contact EXPERIMENTAL!!

DANGER!

There’s a new feature you'll find under **Mailings
» Resend a sent Mailing** which lets you resend a mailing to any single
contact. This may have various uses, from convenience to testing.

**In some situations it has resent an entire mailing** Please see
https://github.com/artfulrobot/resendmailing/issues/3 I have a hack in place to
avoid this now, but I cannot reproduce it with a test, so I'm not super confident. Help wanted to track this down.

Known quirk: this essentially adds a new job to an existing mailing, and
is designed NOT to change the recipients table. This means you get reports
like "Intended Recipients: *N*, Successful Deliveries: *N + 1*".

(Nb. it would be nice to work a link to this into the Sent Mailings table,
under the 'more' menu, but I couldn't figure that out due to my QuickForm
alergies. I have designed the angular path so it can take a mailing ID
like `#/resendmailing/<mailingID>`, so if you can do that, please do
a PR.)

**BETA** I've not used this much, though it seems to work just fine.
