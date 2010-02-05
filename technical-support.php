<?php
/*
Plugin Name: Technical Support
Plugin URI: http://kovshenin.com/wordpress/plugins/technical-support/
Description: Enhance your clients' websites with a bug reporting tool
Author: Konstantin Kovshenin
Version: 0.1
Author URI: http://kovshenin.com/
*/
/*
	License

    Technical Support for WordPress
    Copyright (C) 2010 Konstantin Kovshenin (kovshenin@live.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class TechnicalSupport {
	var $settings = array();
	var $defaultsettings = array();
	var $notices = array();

	function TechnicalSupport() {
		$this->defaultsettings = array(
			"provider_name" => "",
			"provider_email" => "",
			"provider_logo" => "",
			"provider_url" => "",
			
			"topics" => "Theme Layout\nAdmin Panel\nPlugin Issue\nCore Functionality\nCore Upgrade Request\nPlugin Upgrade Request\nTheme Modification Request\nGeneral Support\nOther (please state below)",
			"subject_format" => "New Support Ticket: [title]",
			"message_format" => "Attention! A support ticket has been submitted at [url]. Details follow:\n\nName: [firstname] [lastname] ([email])\nRelated to: [topic]\n\n[title]\n\n[message]\n\n~ Technical Support for WordPress (http://kovshenin.com/1992)"
		);

		// Setup the settings by using the default as a base and then adding in any changed values
		// This allows settings arrays from old versions to be used even though they are missing values
		$usersettings = (array) get_option("technical_support");
		
		$this->settings = $this->defaultsettings;
		if ( $usersettings !== $this->defaultsettings ) {
			foreach ( (array) $usersettings as $key1 => $value1 ) {
				if ( is_array($value1) ) {
					foreach ( $value1 as $key2 => $value2 ) {
						$this->settings[$key1][$key2] = $value2;
					}
				} else {
					$this->settings[$key1] = $value1;
				}
			}
		}
		
		// Register general hooks
		add_action("admin_notices", array(&$this, "admin_notices"));
		add_action("admin_menu", array(&$this, "admin_menu"));
		
		// Let's see if there's an HTTP POST request
		if (isset($_POST["technical-support-submit"]))
			$this->process_post();
		
		// If there are not settings shoot an admin notice
		if (empty($this->settings["provider_name"]) || empty($this->settings["provider_email"]))
			$this->notices[] = "The <strong>Technical Support</strong> plugin requires configuration. Please proceed to the <a href='options-general.php?page=technical-support/technical-support.php'>plugin settings page</a>.";
		
		// If everything's fine, load the working hooks
		else
		{
			// Register working hooks
			add_action('wp_dashboard_setup', array(&$this, 'dashboard_setup'));
			add_action('admin_head', array(&$this, 'admin_js'));
			add_action('wp_ajax_technical_support_submit', array(&$this, 'submit'));
		}
	}
	
	// Javascript for the dashboard widget
	function admin_js()
	{
	?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {

			jQuery("#technical-support-form").submit(function() {
				
				// Format the request
				var data = {
					action: 'technical_support_submit',
					formdata: jQuery(this).serialize()
				};

				// Let's disable the form for a while and show the loading image
				jQuery(':input','#technical-support-form').attr("disabled", "disabled");
				jQuery(".technical-support-loader").show();
		
				// Post the request and handle the response
				jQuery.post(ajaxurl, data, function(response) {
					jQuery(".technical-support-loader").hide();
					jQuery(':input','#technical-support-form').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
					jQuery(':input','#technical-support-form').removeAttr("disabled");
					alert(response);
				});
				
				// Don't leave the page
				return false;
			});
		});
		</script>
	<?php
	}
	
	// AJAX submit response function
	function submit()
	{
		// Organize form data into an array
		$form_data = array();
		$fields = explode('&', $_POST["formdata"]);
		foreach ($fields as $field)
		{
			$key_value = explode('=', $field);
			$key = urldecode($key_value[0]);
			$value = urldecode($key_value[1]);
			$form_data[$key] = $value;
		}
		
		print_r($form_data);
		
		// If we've got an empty title and en empty message.. Whaa?
		if (strlen($form_data["title"]) < 1 && strlen($form_data["content"]) < 1)
			die("Please don't submit empty reports!");
		
		// Let's use these
		global $current_user;
		get_currentuserinfo();
		
		// Format the headers
		$headers = 'From: ' . get_bloginfo('title') . ' <ticket@' . $_SERVER["SERVER_NAME"] . '>' . "\r\n\\";
		$headers .= 'Reply-To: ' . $current_user->user_firstname . ' ' . $current_user->user_lastname . ' <' . $current_user->user_email . '>' . "\r\n";
		
		// Shortcode arrays for str_replace
		$shortcodes = array(
			"[title]",
			"[message]",
			"[topic]",
			"[url]",
			"[firstname]",
			"[lastname]",
			"[email]"
		);
		
		$shortcodes_replace = array(
			$form_data["title"],
			$form_data["content"],
			$form_data["relatedto"],
			get_bloginfo('home'),
			$current_user->user_firstname,
			$current_user->user_lastname,
			$current_user->user_email
		);
		
		// Format the subject and message
		$subject =  str_replace($shortcodes, $shortcodes_replace, $this->settings["subject_format"]);
		$message = str_replace($shortcodes, $shortcodes_replace, $this->settings["message_format"]);
		
		// Send the mail and echo the response
		if (wp_mail($this->settings["provider_email"], $subject, $message, $headers)) echo "Your e-mail has been sent!";
		else echo "E-mail could not be sent, please contact support directly: " . $this->settings["provider_email"];

		die();
	}

	// Setup the dashboard widget
	function dashboard_setup()
	{
		wp_add_dashboard_widget("technical-support-dashboard", "Technical Support", array(&$this, "technical_support"), $control_callback = null);
	}
	
	// This is how the dashboard widget looks and feels
	function technical_support()
	{
		global $current_user;
		get_currentuserinfo();

	?>

	<p>Please use this form to report encountered bugs, issues and other support requests directly to <strong><?php echo $this->settings["provider_name"]; ?></strong>. Note that a response e-mail will be sent to the address associated with your profile (<a href="mailto:<?php echo $current_user->user_email; ?>"><?php echo $current_user->user_email; ?></a>). In order to change that please visit your <a href="profile.php">profile page</a>.</p>

	<style>

	#technical-support-form table {
		border-collapse: collapse;
	}

	#technical-support-form span.description {
		line-height: 16px;
	}

	#technical-support-form table td {
		padding-top: 10px;
	}

	.technical-support-left {
		vertical-align: top;
		padding-top: 14px !important;
		width: 130px;
		text-align: right;
		padding-right: 8px;
	}

	.technical-support-right {
		width: 90%;
	}

	.technical-support-right p {
		margin-top: 0;
		margin-bottom: 3px;
	}

	.technical-support-footer {
		text-align: right;
	}
	
	.technical-support-loader {
		display: none;
		margin-right: 8px;
		position: relative;
		top: 5px;
	}

	</style>

	<form id="technical-support-form">
		<table>
			<tr>
				<td class="technical-support-left"><label for="title">Title</label></td>
				<td class="technical-support-right">
					<p><input type="text" value="" autocomplete="off" tabindex="1" id="title" name="title" style="width:99%"></p>
					<span class="description">Give your issue a short name</span>
				</td>
			</tr>
			<tr>
				<td class="technical-support-left"><label for="relatedto">Related to</label></td>
				<td class="technical-support-right">
					<p>
						<select name="relatedto" id="relatedto">
<?php
	$topics = explode("\n", $this->settings["topics"]);
	foreach($topics as $topic)
		echo "<option>" . $topic . "</option>";
?>
						</select>
					</p>
					<span class="description">Pick one that suits your issue.</span>
				</td>
			</tr>
			<tr>
				<td class="technical-support-left"><label for="content">Description</label></td>
				<td class="technical-support-right">
					<p><textarea tabindex="2" class="mceEditor" id="content" name="content" style="width:99%; height: 100px;"></textarea></p>
					<span class="description">Please describe your issue as detailed as possible. If it's plugin related, please provide the full name of the plugin as well.</span>
				</td>
			</tr>
			<tr>
				<td class="technical-support-left"></td>
				<td class="technical-support-right">
					<p class="submit">
						<input type="submit" value="Send Report" class="button-primary" tabindex="5" accesskey="p" id="publish" name="publish">
						<span class="description"><img class="technical-support-loader" src="<?php echo plugins_url();?>/technical-support/ajax-loader.gif" alt="Loading" />This report will be sent by e-mail to <a href="mailto:<?php echo $this->settings["provider_email"]; ?>"><?php echo $this->settings["provider_email"]; ?></a></span>
					</p>
				</td>
			</tr>
		</table>
	</form>

	<p class="technical-support-footer">Support provided by <a href="<?php echo $this->settings["provider_url"]; ?>"><img src="<?php echo $this->settings["provider_logo"]; ?>" alt="<?php echo $this->settings["provider_name"]; ?>" /></a></p>

	<?	
	}
	
	// Handle and output admin notices
	function admin_notices()
	{
		$this->notices = array_unique($this->notices);
		foreach($this->notices as $key => $value)
			echo "<div id='technical-support-info' class='updated fade'><p>" . $value . "</p></div>";
	}
	
	// Register an admin menu entry
	function admin_menu() {
		add_options_page('Technical Support', 'Technical Support', 8, __FILE__, array(&$this, 'options'));
	}
	
	// Use this to save the settings array
	function save_settings()
	{
		update_option("technical_support", $this->settings);
		return true;
	}
	
	// Process the HTTP POST data if there's any
	function process_post()
	{
		if (isset($_POST["technical-support-submit"]))
		{
			$this->settings["provider_name"] = $_POST["provider_name"];
			$this->settings["provider_email"] = $_POST["provider_email"];
			$this->settings["provider_logo"] = $_POST["provider_logo"];
			$this->settings["provider_url"] = $_POST["provider_url"];
			
			$this->settings["topics"] = $_POST["topics"];
			$this->settings["subject_format"] = $_POST["subject_format"];
			$this->settings["message_format"] = $_POST["message_format"];

			$this->save_settings();
			
			$this->notices[] = "Your <strong>Technical Support</strong> settings have been saved.";
		}
	}

	// Here's the Technical Support settings screen
	function options() {
		$provider_name = $this->settings["provider_name"];
		$provider_email = $this->settings["provider_email"];
		$provider_logo = $this->settings["provider_logo"];
		$provider_url = $this->settings["provider_url"];
		
		$topics = $this->settings["topics"];
		$subject_format = $this->settings["subject_format"];
		$message_format = $this->settings["message_format"];
?>
		<div class="wrap">
		<h2>Technical Support Settings</h2>
		<h3>Support Provider</h3>
		<p>Make sure you test e-mail sending after setup. Also note that if WordPress cannot send e-mails, then neither can this plugin, so make sure WordPress e-mail settings are correctly configured. Also note that the e-mails will be sent from <a href="mailto:<?php echo '<ticket@' . $_SERVER["SERVER_NAME"] . '>'; ?>"><?php echo '<ticket@' . $_SERVER["SERVER_NAME"] . '>'; ?></a> so make sure you add it to your contacts. If you're encountering problems receiving e-mails, try setting up <a href="http://en.wikipedia.org/wiki/Sender_Policy_Framework">SPF</a> for this domain.</p>
		<form method="post">
			<input type="hidden" value="1" name="technical-support-submit"/>
			<table class="form-table" style="margin-bottom:10px;">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="provider_name">Provider Name</label></th>
					<td>
						<input class="regular-text" type="text" value="<?php echo $provider_name; ?>" id="provider_name" name="provider_name" /><br />
						<span class="description">Use your company name, for instance: Microsoft</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="provider_email">Provider E-mail</label></th>
					<td>
						<input class="regular-text" type="text" value="<?php echo $provider_email; ?>" id="provider_email" name="provider_email"/><br />
						<span class="description">All reports will be sent to this address: support@companydomain.com</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="provider_logo">Provider Logo</label></th>
					<td>
						<input class="regular-text" type="text" value="<?php echo $provider_logo; ?>" id="provider_logo" name="provider_logo"/><br />
						<span class="description">The URL of the provider logo. Recommended size is 74x22 pixels</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="provider_url">Provider URL</label></th>
					<td>
						<input class="regular-text" type="text" value="<?php echo $provider_url; ?>" id="provider_url" name="provider_url"/><br />
						<span class="description">The URL of the provider homepage, please start with http</span>
					</td>
				</tr>
			</tbody>
			</table>
			
			<h3>Topics &amp; E-mail Formatting</h3>
			<p>Customize your technical support widget: topics and email formatting.</p>
			<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="topics">Topics</label></th>
					<td>
						<textarea class="regular-text" id="topics" name="topics" style="width: 25em;"><?php echo htmlspecialchars($topics); ?></textarea><br />
						<span class="description">Provide a return separated list of what topics you provide support on</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="subject_format">E-mail Subject</label></th>
					<td>
						<input class="regular-text" type="text" value="<?php echo htmlspecialchars($subject_format); ?>" id="subject_format" name="subject_format"/><br />
						<span class="description">Customize the subject format, use the [title] short-tag, for instance: New Ticket - [title]</span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="message_format">Message Format</label></th>
					<td>
						<textarea class="regular-text" id="message_format" name="message_format" style="width: 25em; height: 15em;"><?php echo htmlspecialchars($message_format); ?></textarea><br />
						<span class="description">This is what you will receive by e-mail. Use the following tags: [title], [message], [topic], [url], [firstname], [lastname], [email]</span>
					</td>
				</tr>
			</tbody>
			</table>
			<p class="submit">
				<input type="submit" value="Save Changes" class="button-primary" name="Submit"/>
			</p>
		</form>
		</div>
<?php
	}
}

// Initiate the plugin
add_action("init", "TechnicalSupport"); function TechnicalSupport() { global $TechnicalSupport; $TechnicalSupport = new TechnicalSupport(); }
?>