<?php

/*
Plugin Name: Contact Form 7 Vision 6
Plugin URI: https://github.com/hironozu/wpcf7-vision6
Description: Subscribe to Vision6 upon Contact Form 7 submission.
Author: Hiro Nozu
Version: 1.0
Author URI: https://hironozu.com/
*/
/**
 * Contact Form 7 Additional Settings
 * https://contactform7.com/en/additional-settings/
 *
 * Contct Form 7 Acceptance Checkbox
 * https://contactform7.com/acceptance-checkbox/
 *
 * Contact Form 7 ReCaptcha
 * https://contactform7.com/recaptcha/
 *
 * Contact Form 7 Repository for old versions
 * https://plugins.trac.wordpress.org/browser/contact-form-7/tags
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define('WPCF7VISION6_PLUGIN', __FILE__);
define('WPCF7VISION6_PLUGIN_DIR', dirname(WPCF7VISION6_PLUGIN));
define('WPCF7VISION6_CALLBACK', 'wpcf7vision6_wpcf7_editor_panels_callback');
define('WPCF7VISION6_RESULT', 'wpcf7vision6_results');

require_once WPCF7VISION6_PLUGIN_DIR . '/api.php';

/**
 * Class WPCF7VISION6
 *
 * Requirements for accessing Vision 6 API
 * https://developers.vision6.com.au/guide/getting-started
 *
 * Basic Structure of Vision 6 API
 * https://developers.vision6.com.au/3.3/guide/basic-structure-of-the-api
 *
 * Method Reference Guide
 * https://developers.vision6.com.au/reference-guide
 */
Class WPCF7VISION6 {

    const URL = 'https://www.vision6.com.au/api/jsonrpcserver';
    /* @var Vision6Api */
    private $api;

    /**
     * WPCF7VISION6 constructor.
     * @param string $api_key
     */
    public function __construct($api_key) {
        $this->api = new Vision6Api(self::URL, $api_key, '3.3');
    }

    /**
     * @param WPCF7_ContactForm $contact_form
     * @return string|null
     */
    public static function getApiKey($contact_form) {
        $data = $contact_form->additional_setting('vision6_api_key');
        if (count($data)) {
            return array_pop($data);
        } else {
            return null;
        }
    }

    /**
     * @param WPCF7_ContactForm $contact_form
     * @return bool
     */
    public static function tiedToVision6($contact_form) {
        return ! is_null(WPCF7VISION6::getApiKey($contact_form));
    }

    /**
     * Get the error code from the last method call.
     *
     * @return  int
     */
    public function getErrorCode() {
        return $this->api->getErrorCode();
    }

    /**
     * Get the error message from the last method call.
     *
     * @return  string
     */
    public function getErrorMessage() {
        return $this->api->getErrorMessage();
    }

    /**
     * Debug
     * If something wrong happened, die() is called.
     */
    public function check() {
        $output = $this->api->invokeMethod('getTimezoneList');
        return (is_array($output) && count($output));
    }

    /**
     * @return mixed
     */
    public function search_list() {
        return $this->api->invokeMethod('searchLists', [], 0, 0, 'name', 'ASC');
    }

    /**
     * @param string $value
     * @return string|null
     */
    private function parse_list_id($value) {
        preg_match('#^([0-9]+)\s?#', $value, $matches);
        if (empty($matches[1])) {
            return null;
        } else {
            return $matches[1];
        }
    }

    /**
     * @param int $list_id
     * @return mixed
     */
    public function get_list($list_id) {
        return $this->api->invokeMethod('getListById', $list_id);
    }

    /**
     * @param WPCF7_ContactForm $contact_form
     * @return int
     */
    public function has_list_id($contact_form) {
        $settings = $contact_form->additional_setting('vision6_list_id');
        return count($settings);
    }

    /**
     * @param WPCF7_ContactForm $contact_form
     * @return int|null
     */
    public function get_list_id($contact_form) {
        $settings = $contact_form->additional_setting('vision6_list_id');
        if (count($settings)) {
            $list_id = array_pop($settings);
            return $this->parse_list_id($list_id);
        } else {
            return null;
        }
    }

    /**
     * Debug
     *
     * @param WPCF7_ContactForm $contact_form
     * @param string $method
     * @return mixed
     */
    public static function debug($contact_form, $method = 'getTimezoneList') {
        $api_key = WPCF7VISION6::getApiKey($contact_form);
        $instance = new WPCF7VISION6($api_key);
        $instance->api->setDebug(true);
        return $instance->api->invokeMethod($method);
    }

    /**
     * Let other code to use Vision 6 API
     *
     * @return Vision6Api
     */
    public function getApi() {
        return $this->api;
    }

    /**
     * subscribeContact
     * https://developers.vision6.com.au/3.3/method/subscribecontact
     *
     * @param WPCF7_ContactForm $contact_form
     * @param array $contact_details
     * @return mixed
     */
    public function submit($contact_form, $contact_details) {
        $list_id = $this->get_list_id($contact_form);
        return $this->api->invokeMethod('subscribeContact', $list_id, $contact_details);
    }
}

/**
 * @param WPCF7_ContactForm $post
 * @see wpcf7_editor_panel_additional_settings()
 */
function wpcf7vision6_wpcf7_editor_panel_additional_settings($post) {

    ob_start();
    $_GET[WPCF7VISION6_CALLBACK]($post);
    $output = ob_get_contents();
    ob_end_clean();

    $error = '';
    $info = '';
    $message = '';

    $api_key = WPCF7VISION6::getApiKey($post);
    if ($api_key) {

        $api = new WPCF7VISION6($api_key);
        if ($api->check()) {


            if ($api->has_list_id($post)) {
                $list_id = $api->get_list_id($post);
                $list_data = $api->get_list($list_id);
                if ($list_data === false) {
                    $error .= "\"vision6_list_id\" is invalid:\n"
                            . $api->getErrorMessage() . "\n";
                } else {
                    $info .= __('The setting looks fine.', 'wpcf7-vision6') . "\n";
                }
            } else {
                $info .= __('You must set "vision6_list_id".', 'wpcf7-vision6') . "\n";
            }

            $lists = $api->search_list();
            if (count($lists)) {
                $info .= __('You can set ONE of the following lines:', 'wpcf7-vision6') . "\n";
                foreach ($lists as $item) {
                    $message .= "vision6_list_id: {$item['id']} ({$item['name']})\n";
                }
            } else {
                $error .= __('No Lists found.', 'wpcf7-vision6') . "\n";
            }
        } else {
            $error = __('Failed to connect to Vision 6 ("vision6_api_key" is invalid).', 'wpcf7-vision6') . "\n"
                   . $api->getErrorMessage() . "\n";
        }
    } else {
        $info = __('Vision 6 Setting not found, set the followings to start the integration:', 'wpcf7-vision6') . "\n";
        $message .= <<<EOT
vision6_api_key: Get at https://www.vision6.com.au/integration/api_keys/
do_not_store: true
skip_mail: on
acceptance_as_validation: on

EOT;

    }

    if ($error) $error = '<p style="color: red">' . nl2br(trim($error)) . '</p>';
    if ($info) $info = '<p>' . nl2br(trim($info)) . '</p>';
    if ($message) $message = '<textarea style="width: 100%; height: 150px">' . $message . '</textarea>';

    $output_extra = <<<EOH
<div style="padding-top: 10px">
    <h3>Vision 6 Info</h3>
    {$error}
    {$info}
    {$message}
</div>

EOH;
    $output = str_replace('</fieldset>', $output_extra . '</fieldset>', $output);

    echo $output;
}

/**
 * Enable to manipulate HTML in "Additional Settings" Tab
 * @param array $panels
 * @return array
 */
function wpcf7vision6_wpcf7_editor_panels($panels) {
    $_GET[WPCF7VISION6_CALLBACK] = $panels['additional-settings-panel']['callback'];
    $panels['additional-settings-panel']['callback'] = 'wpcf7vision6_wpcf7_editor_panel_additional_settings';
    return $panels;
}
add_action('wpcf7_editor_panels', 'wpcf7vision6_wpcf7_editor_panels');

if (version_compare(WPCF7_VERSION, '5.0', '>=')) {

    /**
     * @param WPCF7_ContactForm $contact_form
     * @param boolean $abort
     * @param WPCF7_Submission $submission
     */
    function wpcf7vision6_wpcf7_before_send_mail($contact_form, &$abort, $submission) {

        if (WPCF7VISION6::tiedToVision6($contact_form) && ($submission->get_status() == 'init')) {

            $api_key = WPCF7VISION6::getApiKey($contact_form);
            $api = new WPCF7VISION6($api_key);
            /**
             * Enable you to set the key in case you cannot be bothered at spending time to match up the fields
             * between Contact Form 7 and Vision 6 List
             */
            $contact_details = apply_filters('wpcf7vision6_get_contact_details', $contact_form, $api);
            /**
             * Enable you send the extra parameters
             *
             * Example:
             * function my_wpcf7vision6_submit_exec_submit($contact_details, $contact_form, $api) {
             *     $list_id = $this->get_list_id($contact_form);
             *     return $api->invokeMethod(
             *         'subscribeContact',
             *         $list_id,
             *         $contact_details,
             *         'gdpr',
             *         'By submitting this form I consent to receiving marketing content.',
             *     );
             * }
             * add_filter('wpcf7vision6_submit_exec_submit', 'my_wpcf7vision6_submit_exec_submit', 10, 3);
             * add_filter('wpcf7vision6_submit_use_default_method', false);
             */
            if (apply_filters('wpcf7vision6_submit_use_default_method', $contact_form, $api)) {
                $result = $api->submit($contact_form, $contact_details);
            } else {
                $result = apply_filters('wpcf7vision6_submit_exec_submit', $contact_details, $contact_form, $api);
            }
            if ( ! $result) {
                $abort = true;
                $submission->set_status('validation_failed');
                $submission->set_response($contact_form->filter_message(
                    __($api->getErrorMessage(), 'wpcf7-vision6')
                ));
            }
            /**
             * Let other code do more with Vision 6 API
             *
             * Example:
             * function my_wpcf7vision6_submit($result, $contact_details, $api, $contact_form) {
             *     if ($result)) {
             *         $list_id = $this->get_list_id($contact_form);
             *         $api->api->invokeMethod('countContacts', );
             *     }
             * }
             * add_action('wpcf7vision6_submit', 'my_wpcf7vision6_submit', 10, 4);
             */
            do_action('wpcf7vision6_submit', $result, $contact_details, $api, $contact_form);
        }
    }
    add_filter('wpcf7_before_send_mail', 'wpcf7vision6_wpcf7_before_send_mail', 10, 3);

} elseif (version_compare(WPCF7_VERSION, '4.4', '>=')) {

    /**
     * @param bool $skip_mail
     * @param WPCF7_ContactForm $contact_form
     * @return bool
     */
    function wpcf7vision6_wpcf7_skip_mail($skip_mail, $contact_form) {

        if ( ! $skip_mail && WPCF7VISION6::tiedToVision6($contact_form)) {
            $skip_mail = $contact_form->is_true('skip_mail');
        }
        return $skip_mail;
    }
    add_filter('wpcf7_skip_mail', 'wpcf7vision6_wpcf7_skip_mail', 10, 2);

    /**
     * @param WPCF7_ContactForm $contact_form
     * @param array $result
     */
    function wpcf7vision6_wpcf7_submit($contact_form, $result) {

        if (WPCF7VISION6::tiedToVision6($contact_form) && ($result['status'] == 'mail_sent')) {

            $api_key = WPCF7VISION6::getApiKey($contact_form);
            $api = new WPCF7VISION6($api_key);

            /* @see wpcf7vision6_wpcf7_before_send_mail() */
            $contact_details = apply_filters('wpcf7vision6_get_contact_details', $contact_form, $api);

            /* @see wpcf7vision6_wpcf7_before_send_mail() */
            if (apply_filters('wpcf7vision6_submit_use_default_method', $contact_form, $api)) {
                $v6result = $api->submit($contact_form, $contact_details);
            } else {
                $v6result = apply_filters('wpcf7vision6_submit_exec_submit', $contact_details, $contact_form, $api);
            }
            if ( ! $v6result) {
                // Older versions do not really have a good way to handle the logic when the process failed
                $_GET[WPCF7VISION6_RESULT] = [
                    'status' => 'validation_failed',
                    'message' => __($api->getErrorMessage(), 'wpcf7-vision6'),
                ];
            }
            /* @see wpcf7vision6_wpcf7_before_send_mail() */
            do_action('wpcf7vision6_submit', $result, $contact_details, $api, $contact_form);
        }
    }
    add_action('wpcf7_submit', 'wpcf7vision6_wpcf7_submit', 10, 2);

    if (version_compare(WPCF7_VERSION, '4.8', '>=')) {

        /**
         * @param $response
         * @return array
         */
        function wpcf7vision6_wpcf7_ajax_json_echo($response) {
            if ( ! empty($_GET[WPCF7VISION6_RESULT])) {
                foreach ($_GET[WPCF7VISION6_RESULT] as $key => $value) {
                    $response[$key] = $value;
                }
            }
            return $response;
        }
    } else {

        /**
         * @param array $items
         * @return mixed
         */
        function wpcf7vision6_wpcf7_ajax_json_echo($items) {
            if ( ! empty($_GET[WPCF7VISION6_RESULT])) {
                foreach ($_GET[WPCF7VISION6_RESULT] as $key => $value) {
                    $items[$key] = $value;
                }
                $items['mailSent'] = false;
            }
            return $items;
        }
    }
    add_filter('wpcf7_ajax_json_echo', 'wpcf7vision6_wpcf7_ajax_json_echo');

} else {
    // Older than version 4.4 is not supported at the moment, but you can try adding the logic to "functions.php"
}

/**
 * @param WPCF7_ContactForm $contact_form
 * @param $api
 * @return array
 */
function wpcf7vision6_get_contact_details_default($contact_form, $api) {
    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();
    $contact_details = [];
    foreach ($posted_data as $key => $value) {
        if (preg_match('#^wcpf_#', $key)) continue;
        $contact_details[$key] = $value;
    }
    return $contact_details;
}
add_filter('wpcf7vision6_get_contact_details', 'wpcf7vision6_get_contact_details_default', 1, 2);
