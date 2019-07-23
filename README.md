# resendmailing

**This is an idea. Your thoughts welcome.**

CiviCRM extension to allow you to select a pre-existing mailing and send it to search results

1. Do a search
2. Choose "Email - Resend a previous mailing to these contacts"
3. Prepare and send/schedule the mailing as you wish, but starting from a copy of the previous message.

## Why not use "Re-use" mailing

CiviMail provides a re-use mailing link, which does create a duplicate mailing, however it will not let you select search results to mail to, nor will it allow you to select an "unsubscribe" group.

## Why not create a new mailing group and then do a Re-use mailing?

Because when your dear supporters unsubscribe from your new mailing group, they might not realise that they are still subscribed to the original mailing group(s), so might be narked when you send your next newsletter.

## How does (would) this work?

1. Add a search action which allows you to select an existing mailing (sent or draft).
2. Create a copy of the mailing (ideally reusing the "re-use" code) but then remove the groups and put the new hidden group in its place.

