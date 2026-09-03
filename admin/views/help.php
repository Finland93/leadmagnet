<?php
/**
 * Admin view: Help (in-admin instructions).
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap lmf93-admin lmf93-help">
	<h1><?php esc_html_e( 'Help', 'leadmagnet' ); ?></h1>
	<p class="description" style="max-width:760px;">
		<?php esc_html_e( 'This page explains how to edit forms and use the plugin\'s features. Forms are defined in JSON on the Forms page.', 'leadmagnet' ); ?>
	</p>

	<div style="max-width:820px;">

	<h2><?php esc_html_e( '1. Adding a form to a page', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'Add a form to any page or post with a shortcode. Put this in the content:', 'leadmagnet' ); ?></p>
	<pre class="lmf93-code">[leadmagnet id="1"]</pre>
	<p><?php esc_html_e( 'The number is the form ID (you can see it on the Forms page). A site can have several forms with different IDs.', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '2. Adding and editing form fields', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'Open a form on the Forms page and edit its settings in the JSON editor. Fields live in the "fields" list. Each field looks like this:', 'leadmagnet' ); ?></p>
	<pre class="lmf93-code">{
  "key": "postal_code",
  "type": "text",
  "label": "Postal code",
  "required": true,
  "map": "postal_code"
}</pre>
	<table class="widefat striped" style="max-width:820px;">
		<thead><tr><th><?php esc_html_e( 'Key', 'leadmagnet' ); ?></th><th><?php esc_html_e( 'Description', 'leadmagnet' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>key</code></td><td><?php esc_html_e( 'Technical field name (lowercase, no spaces). Also used in pricing and scoring rules.', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>type</code></td><td><?php esc_html_e( 'Field type: text, email, tel, number, textarea, select, radio or checkbox.', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>label</code></td><td><?php esc_html_e( 'The visible label the customer sees.', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>required</code></td><td><?php esc_html_e( 'true = required, false = optional.', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>map</code></td><td><?php esc_html_e( 'Optional. Links the field to a standard column so it gets its own column: first_name, last_name, email, phone, postal_code, city. Leave out for ordinary extra fields.', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>options</code></td><td><?php esc_html_e( 'Only for select, radio and checkbox fields. A list of choices (see below).', 'leadmagnet' ); ?></td></tr>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Choice fields (select / radio / checkbox)', 'leadmagnet' ); ?></h3>
	<p><?php esc_html_e( 'When the type is select, radio or checkbox, add an "options" list. Each option has a machine "value" and a visible "label":', 'leadmagnet' ); ?></p>
	<pre class="lmf93-code">{
  "key": "service_type",
  "type": "radio",
  "label": "What do you need help with?",
  "required": true,
  "options": [
    { "value": "installation", "label": "Installation" },
    { "value": "repair",       "label": "Repair" },
    { "value": "maintenance",  "label": "Maintenance" }
  ]
}</pre>
	<p><strong><?php esc_html_e( 'Important:', 'leadmagnet' ); ?></strong> <?php esc_html_e( 'Keep "value" simple and stable (lowercase, no spaces), because pricing and scoring rules reference it. "label" can be anything and can be changed at any time.', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '3. Consents (checkboxes)', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'Consents live in the "consents" list. Each has a "purpose", a required flag, and visible text:', 'leadmagnet' ); ?></p>
	<pre class="lmf93-code">"consents": [
  {
    "purpose": "lead_processing",
    "required": true,
    "label": "I have read the privacy policy and accept the processing of my request."
  },
  {
    "purpose": "marketing_email",
    "required": false,
    "label": "I want to receive occasional tips and offers by email."
  }
]</pre>
	<p><?php esc_html_e( 'A form cannot be submitted without a required consent. Every consent is logged as proof (timestamp, text, privacy policy version).', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '4. Lead pricing (value_rules)', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'Pricing sets how much each lead is worth (e.g. for partner billing). Rules live in the "value_rules" list. The first matching rule wins, so put the most specific rules first and a general fallback last.', 'leadmagnet' ); ?></p>

	<h3><?php esc_html_e( 'Single-condition rule', 'leadmagnet' ); ?></h3>
	<pre class="lmf93-code">{
  "field": "service_type",
  "operator": "equals",
  "value": "installation",
  "base_price": 40,
  "multiplier_field": "unit_count",
  "multiplier_step": 0.25
}</pre>

	<h3><?php esc_html_e( 'Multi-condition rule (all conditions must match)', 'leadmagnet' ); ?></h3>
	<pre class="lmf93-code">{
  "conditions": [
    { "field": "service_type", "operator": "equals", "value": "maintenance" },
    { "field": "customer_type", "operator": "equals", "value": "business" }
  ],
  "base_price": 30,
  "multiplier_field": "unit_count",
  "multiplier_step": 0.25
}</pre>

	<table class="widefat striped" style="max-width:820px;">
		<thead><tr><th><?php esc_html_e( 'Key', 'leadmagnet' ); ?></th><th><?php esc_html_e( 'Description', 'leadmagnet' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>field</code></td><td><?php esc_html_e( 'Which field value to test (the field "key").', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>operator</code></td><td><?php esc_html_e( 'Comparison: equals, not_equals, contains, gte (greater or equal), lte (less or equal), exists.', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>value</code></td><td><?php esc_html_e( 'The value to compare against (an option "value").', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>base_price</code></td><td><?php esc_html_e( 'The lead base price in your currency (tax excluded).', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>multiplier_field</code></td><td><?php esc_html_e( 'Optional. A field to read a quantity from (e.g. unit_count).', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>multiplier_step</code></td><td><?php esc_html_e( 'Optional. Increase per extra unit. 0.25 = +25% for each unit after the first. Formula: price × (1 + 0.25 × (n − 1)).', 'leadmagnet' ); ?></td></tr>
			<tr><td><code>conditions</code></td><td><?php esc_html_e( 'Optional. A list of conditions that must ALL match. Use this OR a single field/operator/value, not both in the same rule.', 'leadmagnet' ); ?></td></tr>
		</tbody>
	</table>
	<p><strong><?php esc_html_e( 'Example:', 'leadmagnet' ); ?></strong> <?php esc_html_e( 'Maintenance, 3 units at a base price of 30: 30 × (1 + 0.25 × 2) = 45.', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '5. Scoring (optional)', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'Scoring works like pricing but adds points instead of a price, so you can rate lead quality. Same condition structure, but with "points" instead of "base_price":', 'leadmagnet' ); ?></p>
	<pre class="lmf93-code">{
  "field": "customer_type",
  "operator": "equals",
  "value": "business",
  "points": 20
}</pre>

	<h2><?php esc_html_e( '6. Billing page', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'The Billing page shows, per partner, the leads that can be invoiced. Included are leads routed to a partner that are not cancelled or refunded. After you send an invoice, select the leads and press "Mark selected as billed" – they leave the list (you can show them again with the filter).', 'leadmagnet' ); ?></p>
	<p><?php esc_html_e( 'To keep a lead out of billing, set its job status to "cancelled" or "refunded" on the lead\'s own page. The lead value is set automatically from the value_rules when the lead arrives.', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '7. Feedback and customer reviews', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'The review request is sent as part of the follow-up messages (when enabled on the Emails page and the lead\'s job status is set to "completed"). To enable reviews:', 'leadmagnet' ); ?></p>
	<ol>
		<li><?php esc_html_e( 'Create a new WordPress page, for example named "Review".', 'leadmagnet' ); ?></li>
		<li><?php echo wp_kses_post( __( 'Add the shortcode <code>[lmf93_review]</code> as the page content.', 'leadmagnet' ) ); ?></li>
		<li><?php esc_html_e( 'Copy the page address into the "Review page URL" field on the Settings page. The page is hidden from search engines automatically (noindex).', 'leadmagnet' ); ?></li>
		<li><?php esc_html_e( 'Set the "Redirect threshold": this many stars or more sends the customer to your public review service.', 'leadmagnet' ); ?></li>
		<li><?php esc_html_e( 'Paste your Google or Trustpilot review link into the "Public review URL" field.', 'leadmagnet' ); ?></li>
	</ol>
	<p><?php esc_html_e( 'When a customer gives a high rating, they are sent to your public review service. A low rating asks for a reason and a free comment, visible only to you on the Feedback page – so you can reach out and handle it personally. On the Feedback page you can mark each item\'s status (new, contacted, resolved).', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '8. Follow-up messages', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'On the Emails page you can enable two built-in messages sent when a lead\'s job status is "completed": a review request and a service reminder. Their text is edited on the Emails page too.', 'leadmagnet' ); ?></p>
	<p><?php esc_html_e( 'You can also create your own scheduled follow-up messages under "Scheduled follow-up messages". For each you choose a trigger, a delay in days, a subject and a body. Available triggers:', 'leadmagnet' ); ?></p>
	<ul>
		<li><strong><?php esc_html_e( 'Form submitted', 'leadmagnet' ); ?></strong> — <?php esc_html_e( 'as soon as the customer submits the lead.', 'leadmagnet' ); ?></li>
		<li><strong><?php esc_html_e( 'Routed to partner', 'leadmagnet' ); ?></strong> — <?php esc_html_e( 'when the lead is routed to a partner.', 'leadmagnet' ); ?></li>
		<li><strong><?php esc_html_e( 'Marked as won', 'leadmagnet' ); ?></strong> — <?php esc_html_e( 'when the lead status is set to "won".', 'leadmagnet' ); ?></li>
		<li><strong><?php esc_html_e( 'Job completed', 'leadmagnet' ); ?></strong> — <?php esc_html_e( 'when the lead\'s job status is set to "completed".', 'leadmagnet' ); ?></li>
	</ul>
	<p><?php esc_html_e( 'The delay can be zero (sent immediately when the trigger fires) or e.g. 30 or 365 days. You can use the same placeholders as in other emails, for example {first_name} and {message}.', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '9. Sending test messages', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'At the top of the Emails page there is a "Send a test message" section. Choose any message and send it to the admin email. It is sent with example data (a fake customer, Jane Doe), so you can see the result without a real lead. The subject is prefixed with [TEST].', 'leadmagnet' ); ?></p>

	<h2><?php esc_html_e( '10. Adapting to your country', 'leadmagnet' ); ?></h2>
	<p><?php esc_html_e( 'This plugin ships country-agnostic. Out of the box the postal code accepts any format and the city is a normal text field, so it works anywhere. On the Settings page (Localization) you can:', 'leadmagnet' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Set the postal code format to "Numeric only" for countries with digit-only codes, or leave it "Any" so codes like SW1A 1AA (UK) or K1A 0B1 (Canada) work.', 'leadmagnet' ); ?></li>
		<li><?php esc_html_e( 'Set the currency symbol, its position, and the price note (e.g. "excl. VAT").', 'leadmagnet' ); ?></li>
	</ul>
	<h3><?php esc_html_e( 'Optional: auto-fill the city from the postal code', 'leadmagnet' ); ?></h3>
	<p><?php esc_html_e( 'If you want the city to fill in automatically, tick "Auto-fill city from postal code" and provide a dataset URL. The dataset is a simple JSON object mapping postal codes to city names:', 'leadmagnet' ); ?></p>
	<pre class="lmf93-code">{
  "00100": "Helsinki",
  "10001": "New York",
  "SW1A 1AA": "London"
}</pre>
	<p><?php echo wp_kses_post( __( 'Steps to add your country:', 'leadmagnet' ) ); ?></p>
	<ol>
		<li><?php esc_html_e( 'Get a postal-code → locality list for your country (many national postal services and open-data projects publish one).', 'leadmagnet' ); ?></li>
		<li><?php echo wp_kses_post( __( 'Convert it to the JSON format above and save it as a file, e.g. <code>my-country.json</code>.', 'leadmagnet' ) ); ?></li>
		<li><?php echo wp_kses_post( __( 'Upload it (e.g. to the Media Library or the plugin\'s <code>public/data/examples/</code> folder) and copy its URL.', 'leadmagnet' ) ); ?></li>
		<li><?php esc_html_e( 'Paste the URL into "Postal dataset URL" on the Settings page.', 'leadmagnet' ); ?></li>
	</ol>
	<p><?php echo wp_kses_post( __( 'A Finnish dataset is bundled as an example at <code>public/data/examples/fi-postal-codes.json</code>; if you leave the URL empty but enable auto-fill, that example is used.', 'leadmagnet' ) ); ?></p>
	<p><?php esc_html_e( 'Partner routing by postal code works with any format: a partner\'s postal-code list matches by prefix, so "SW1" matches "SW1A 1AA", and "10" matches "10001". Leave a partner\'s postal list empty to match the whole country.', 'leadmagnet' ); ?></p>

	</div>
</div>
