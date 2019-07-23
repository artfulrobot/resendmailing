{* HEADER *}
<h2>Re-send previous mailing to these contacts</h2>
<p>This will create (but not send/schedule) a new mailing with the same content
and settings as a previous mailing, but with your selected search results as
the recipients.</p>

<p><strong>You will need to select an Unsubscribe Group</strong> for the
mailing; this is the group that people will be unsubscribed from if they click
unsubscribe.</p>

<div class="crm-section">
  <div class="label"  >{$form.mailing_id.label}</div>
  <div class="content">{$form.mailing_id.html}</div>
  <div class="clear"></div>
</div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
