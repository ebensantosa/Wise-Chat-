<?php
/*
	Plugin Name: Wise Chat Pro
	Version: 2.3.2
	Plugin URI: https://kaine.pl/projects/wp-plugins/wise-chat-pro
	Description: Fully-featured chat plugin for WordPress. Supports multiple channels, private messages, multisite installation, bad words filtering, themes, appearance settings, avatars, filters, bans and more.
	Author: Kainex
	Author URI: https://kaine.pl
*/

require_once(dirname(__FILE__).'/src/WiseChatContainer.php');
WiseChatContainer::load('WiseChatInstaller');
WiseChatContainer::load('WiseChatOptions');

if (WiseChatOptions::getInstance()->isOptionEnabled('enabled_debug', false)) {
	error_reporting(E_ALL);
	ini_set("display_errors", 1);
}

if (is_admin()) {
	// installer:
	register_activation_hook(__FILE__, array('WiseChatInstaller', 'activate'));
	register_deactivation_hook(__FILE__, array('WiseChatInstaller', 'deactivate'));
	register_uninstall_hook(__FILE__, array('WiseChatInstaller', 'uninstall'));
	add_action('wpmu_new_blog', array('WiseChatInstaller', 'newBlog'), 10, 6);
	add_action('delete_blog', array('WiseChatInstaller', 'deleteBlog'), 10, 6);

    /** @var WiseChatSettings $settings */
	$settings = WiseChatContainer::get('WiseChatSettings');
    // initialize plugin settings page:
	$settings->initialize();

	add_action('admin_enqueue_scripts', function() {
		wp_enqueue_media();
	});
}

// register action that detects when WordPress user logs in / logs out:
function wise_chat_after_setup_theme_action() {
    /** @var WiseChatUserService $userService */
	$userService = WiseChatContainer::get('services/user/WiseChatUserService');
	$userService->initMaintenance();
	$userService->switchUser();
}
add_action('after_setup_theme', 'wise_chat_after_setup_theme_action');

// register CSS file in HEAD section:
function wise_chat_register_common_css() {
	$pluginBaseURL = plugin_dir_url(__FILE__);
	wp_enqueue_style('wise_chat_core', $pluginBaseURL.'css/wise_chat.css');
}
add_action('wp_enqueue_scripts', 'wise_chat_register_common_css');

// register chat shortcode:
function wise_chat_shortcode($atts) {
	$wiseChat = WiseChatContainer::get('WiseChat');
	$html = $wiseChat->getRenderedShortcode($atts);
	$wiseChat->registerResources();
    return $html;
}
add_shortcode('wise-chat', 'wise_chat_shortcode');

// register chat channel stats shortcode:
function wise_chat_channel_stats_shortcode($atts) {
	$wiseChatStatsShortcode = WiseChatContainer::get('WiseChatStatsShortcode');
	return $wiseChatStatsShortcode->getRenderedChannelStatsShortcode($atts);
}
add_shortcode('wise-chat-channel-stats', 'wise_chat_channel_stats_shortcode');

// register chat channel export shortcode:
function wise_chat_channel_export_shortcode($atts) {
	$wiseChatExportShortcode = WiseChatContainer::get('WiseChatExportShortcode');
	return $wiseChatExportShortcode->renderShortcode($atts);
}
add_shortcode('wise-chat-channel-export', 'wise_chat_channel_export_shortcode');
add_action('init', array(WiseChatContainer::get('WiseChatExportShortcode'), 'doExport'));

// chat function:
function wise_chat($channel = null) {
	$wiseChat = WiseChatContainer::get('WiseChat');
	echo $wiseChat->getRenderedChat($channel);
	$wiseChat->registerResources();
}

// register chat widget:
function wise_chat_widget() {
	WiseChatContainer::get('WiseChatWidget');
	return register_widget("WiseChatWidget");
}
add_action('widgets_init', 'wise_chat_widget');

// register channel users widget:
function wise_chat_widget_channel_users() {
	WiseChatContainer::get('WiseChatWidgetChannelUsers');
	return register_widget("WiseChatWidgetChannelUsers");
}
add_action('widgets_init', 'wise_chat_widget_channel_users');

// register action that auto-removes images generate by the chat (the additional thumbnail):
function wise_chat_action_delete_attachment($attachmentId) {
	$wiseChatImagesService = WiseChatContainer::get('services/WiseChatImagesService');
	$wiseChatImagesService->removeRelatedImages($attachmentId);
}
add_action('delete_attachment', 'wise_chat_action_delete_attachment');

function wise_chat_bp_init() {
	$options = WiseChatOptions::getInstance();
	if ($options->isOptionEnabled('enable_buddypress', false) && (bp_is_active( 'groups' ) || class_exists('BP_Group_Extension', false))) {
		WiseChatContainer::load('integrations/buddypress/WiseChatBuddyPressGroupExtension');
		bp_register_group_extension('WiseChatBuddyPressGroupExtension');
	}
	if ($options->isOptionEnabled('enable_buddypress', false)) {
		WiseChatContainer::get('integrations/buddypress/WiseChatBuddyPressMemberProfileExtensions');
	}
}
add_action('bp_include', 'wise_chat_bp_init');

// Endpoints fo AJAX requests:
function wise_chat_endpoint_messages() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->messagesEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_messages_endpoint", 'wise_chat_endpoint_messages');
add_action("wp_ajax_wise_chat_messages_endpoint", 'wise_chat_endpoint_messages');

function wise_chat_endpoint_message() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->messageEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_message_endpoint", 'wise_chat_endpoint_message');
add_action("wp_ajax_wise_chat_message_endpoint", 'wise_chat_endpoint_message');

function wise_chat_endpoint_get_message() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->getMessageEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_get_message_endpoint", 'wise_chat_endpoint_get_message');
add_action("wp_ajax_wise_chat_get_message_endpoint", 'wise_chat_endpoint_get_message');

function wise_chat_endpoint_message_approve() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->messageApproveEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_approve_message_endpoint", 'wise_chat_endpoint_message_approve');
add_action("wp_ajax_wise_chat_approve_message_endpoint", 'wise_chat_endpoint_message_approve');

function wise_chat_endpoint_message_delete() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->messageDeleteEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_delete_message_endpoint", 'wise_chat_endpoint_message_delete');
add_action("wp_ajax_wise_chat_delete_message_endpoint", 'wise_chat_endpoint_message_delete');

function wise_chat_endpoint_message_save() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->messageSaveEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_save_message_endpoint", 'wise_chat_endpoint_message_save');
add_action("wp_ajax_wise_chat_save_message_endpoint", 'wise_chat_endpoint_message_save');

function wise_chat_endpoint_user_ban() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->userBanEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_user_ban_endpoint", 'wise_chat_endpoint_user_ban');
add_action("wp_ajax_wise_chat_user_ban_endpoint", 'wise_chat_endpoint_user_ban');

function wise_chat_endpoint_user_kick() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->userKickEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_user_kick_endpoint", 'wise_chat_endpoint_user_kick');
add_action("wp_ajax_wise_chat_user_kick_endpoint", 'wise_chat_endpoint_user_kick');

function wise_chat_endpoint_spam_report() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->spamReportEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_spam_report_endpoint", 'wise_chat_endpoint_spam_report');
add_action("wp_ajax_wise_chat_spam_report_endpoint", 'wise_chat_endpoint_spam_report');

function wise_chat_endpoint_maintenance() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->maintenanceEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_maintenance_endpoint", 'wise_chat_endpoint_maintenance');
add_action("wp_ajax_wise_chat_maintenance_endpoint", 'wise_chat_endpoint_maintenance');

function wise_chat_endpoint_settings() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->settingsEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_settings_endpoint", 'wise_chat_endpoint_settings');
add_action("wp_ajax_wise_chat_settings_endpoint", 'wise_chat_endpoint_settings');

function wise_chat_endpoint_prepare_image() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->prepareImageEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_prepare_image_endpoint", 'wise_chat_endpoint_prepare_image');
add_action("wp_ajax_wise_chat_prepare_image_endpoint", 'wise_chat_endpoint_prepare_image');

function wise_chat_endpoint_user_command() {
	$wiseChatEndpoints = WiseChatContainer::get('endpoints/WiseChatEndpoints');
	$wiseChatEndpoints->userCommandEndpoint();
}
add_action("wp_ajax_nopriv_wise_chat_user_command_endpoint", 'wise_chat_endpoint_user_command');
add_action("wp_ajax_wise_chat_user_command_endpoint", 'wise_chat_endpoint_user_command');

function wise_chat_profile_update($userId, $oldUserData) {
	$wiseChatUserService = WiseChatContainer::get('services/user/WiseChatUserService');
	$wiseChatUserService->onWpUserProfileUpdate($userId, $oldUserData);
}
add_action("profile_update", 'wise_chat_profile_update', 10, 2);