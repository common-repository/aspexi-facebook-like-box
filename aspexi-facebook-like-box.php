<?php
/*
Plugin Name: Aspexi Social Media Slider
Plugin URI:  http://aspexi.com/downloads/aspexi-facebook-like-box-slider-hd/?src=free_plugin
Description: Plugin adds fancy Facebook Page Plugin (formerly Like Box) slider
Author: Aspexi
Version: 2.1.17
Author URI: http://aspexi.com/
License: GPLv2 or later

    Copyright 2015 Aspexi
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Text Domain: aspexifblikebox
Domain Path: /languages
*/

defined('ABSPATH') or exit();

esc_html__( 'Plugin adds fancy Facebook Page Plugin (formerly Like Box) slider', 'aspexifblikebox' );

if ( !class_exists( 'AspexiFBlikebox' ) ) {

    define('ASPEXIFBLIKEBOX_VERSION', '2.1.16');

    $plugin_url = plugin_dir_url( __FILE__ );

    if( is_ssl() ) {
        $plugin_url = str_replace('http://', 'https://', $plugin_url);
    }

    define('ASPEXIFBLIKEBOX_URL', $plugin_url );
    define('ASPEXIFBLIKEBOX_ADMIN_URL', 'themes.php?page=' . basename( __FILE__ ) );

    class AspexiFBlikebox {

        public $cf          = array(); // config array
        private $messages   = array(); // admin messages
        private $errors     = array(); // admin errors

        public function __construct() {

            /* Configuration */
            $this->settings();

            add_action( 'admin_menu',           array( &$this, 'admin_menu'));
            add_action( 'admin_notices',        array( &$this, 'admin_notices'));
            add_action( 'init',                 array( &$this, 'init' ), 10 );
            add_action( 'wp_footer',            array( &$this, 'get_footer_html' ), 21 );
            add_action( 'admin_enqueue_scripts',array( &$this, 'admin_scripts') );
            add_action( 'wp_ajax_afbl_hide_notice', array( &$this, 'admin_notices_handle') );
            add_action( 'wp_enqueue_scripts',   array( &$this, 'init_scripts') );
            add_filter( 'plugin_action_links',  array( &$this, 'settings_link' ), 10, 2);

            register_uninstall_hook( __FILE__, array( 'AspexiFBlikebox', 'uninstall' ) );
        }

        /* WP init action */
        public function init() {

            /* Internationalization */
            load_plugin_textdomain( 'aspexifblikebox', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

            /* Exras */
            $this->extras_init();
        }

        public function settings() {

            /* Defaults */
            $cf_default = array(
                'aspexifblikebox_version' => ASPEXIFBLIKEBOX_VERSION,
                'url' => '',
                'locale' => 'en_GB',
                'status' => 'enabled',
                'page_plugin' => 'enabled',
                'hide_notice' => '0'
            );

            if ( !get_option( 'aspexifblikebox_options' ) )
                add_option( 'aspexifblikebox_options', $cf_default, '', 'yes' );

            $this->cf = get_option( 'aspexifblikebox_options' );

            /* Upgrade */
            if( $this->cf['aspexifblikebox_version'] != ASPEXIFBLIKEBOX_VERSION ) {
                switch( $this->cf['aspexifblikebox_version'] ) {
                    case '1.0.0':
                        $this->cf['status'] = 'enabled';
                        $this->cf['aspexifblikebox_version'] = ASPEXIFBLIKEBOX_VERSION;
                        update_option( 'aspexifblikebox_options',  $this->cf, '', 'yes' );
                        break;
                    default:
                        $this->cf = array_merge( $cf_default, (array)$this->cf );
                        $this->cf['aspexifblikebox_version'] = ASPEXIFBLIKEBOX_VERSION;
                        update_option( 'aspexifblikebox_options',  $this->cf, '', 'yes' );
                }
            }
        }

        public function settings_link( $action_links, $plugin_file ){
            if( $plugin_file == plugin_basename(__FILE__) ) {

                $pro_link = $this->get_pro_link();
                array_unshift( $action_links, $pro_link );

                $settings_link = '<a href="themes.php?page=' . basename( __FILE__ )  .  '">' . esc_html__("Settings") . '</a>';
                array_unshift( $action_links, $settings_link );

            }
            return $action_links;
        }

        private function add_message( $message ) {
            $message = trim( $message );

            if( strlen( $message ) )
                $this->messages[] = $message;
        }

        private function add_error( $error ) {
            $error = trim( $error );

            if( strlen( $error ) )
                $this->errors[] = $error;
        }

        public function has_errors() {
            return count( $this->errors );
        }

        public function display_admin_notices( $echo = false ) {
            $ret = '';

            foreach( (array)$this->errors as $error ) {
                $ret .= '<div class="error fade"><p><strong>'.esc_html($error).'</strong></p></div>';
            }

            foreach( (array)$this->messages as $message ) {
                $ret .= '<div class="updated fade"><p><strong>'.esc_html($message).'</strong></p></div>';
            }

            return $ret;
        }

        public function admin_menu() {
            add_submenu_page( 'themes.php', esc_html__( 'Aspexi Social Media Slider', 'aspexifblikebox' ), esc_html__( 'Aspexi Social Media Slider', 'aspexifblikebox' ), 'manage_options', basename(__FILE__), array( &$this, 'admin_page') );
        }

        public function admin_page() {

            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
            }

            $preview = false;

            if( isset( $_REQUEST['pp'] ) && check_admin_referer( 'page_plugin_toggle' ) ) {

                if( 'enabled' == $this->is_pp() && 'disable' == sanitize_text_field( $_REQUEST['pp'] ) ) {
                    $this->cf['page_plugin'] = 'disabled';
                    update_option( 'aspexifblikebox_options',  $this->cf, '', 'yes' );
                }
            }

            // request action
            if ( isset( $_REQUEST['afblb_form_submit'] ) && check_admin_referer( plugin_basename(__FILE__), 'afblb_nonce_name' ) ) {

                if( !in_array( sanitize_text_field( $_REQUEST['afblb_status'] ), array('enabled','disabled') ) )
                    $this->add_error( esc_html__( 'Wrong or missing status. Available statuses: enabled and disabled. Settings not saved.', 'aspexifblikebox' ) );

                if( !$this->has_errors() ) {
                    $aspexifblikebox_request_options = array();

                    $aspexifblikebox_request_options['url']     = isset( $_REQUEST['afblb_url'] ) ? sanitize_text_field(trim( $_REQUEST['afblb_url'] )) : '';
                    $aspexifblikebox_request_options['locale']  = isset( $_REQUEST['afblb_locale'] ) ? sanitize_text_field($_REQUEST['afblb_locale']) : '';
                    $aspexifblikebox_request_options['status']  = isset( $_REQUEST['afblb_status'] ) ? sanitize_text_field($_REQUEST['afblb_status']) : '';
                    $this->cf = array_merge( (array)$this->cf, $aspexifblikebox_request_options );

                    update_option( 'aspexifblikebox_options',  $this->cf, '', 'yes' );
                    $this->add_message( esc_html__( 'Settings saved.', 'aspexifblikebox' ) );
                }

                // Preview maybe
                if( @$_REQUEST['preview'] )
                    $preview = true;
                else
                    $preview = false;
            }

            // Locale
            $locales = array(
                'Afrikaans' => 'af_ZA',
                'Akan' => 'ak_GH',
                'Albanian' => 'sq_AL',
                'Amharic' => 'am_ET',
                'Arabic' => 'ar_AR',
                'Armenian' => 'hy_AM',
                'Assamese' => 'as_IN',
                'Aymara' => 'ay_BO',
                'Azerbaijani' => 'az_AZ',
                'Basque' => 'eu_ES',
                'Belarusian' => 'be_BY',
                'Bengali' => 'bn_IN',
                'Bosnian' => 'bs_BA',
                'Breton' => 'br_FR',
                'Bulgarian' => 'bg_BG',
                'Burmese' => 'my_MM',
                'Catalan' => 'ca_ES',
                'Cebuano' => 'cx_PH',
                'Cherokee' => 'ck_US',
                'Chewa' => 'ny_MW',
                'Classical Greek' => 'gx_GR',
                'Corsican' => 'co_FR',
                'Croatian' => 'hr_HR',
                'Czech' => 'cs_CZ',
                'Danish' => 'da_DK',
                'Dutch (België)' => 'nl_BE',
                'Dutch' => 'nl_NL',
                'English (India)' => 'en_IN',
                'English (Pirate)' => 'en_PI',
                'English (UK)' => 'en_GB',
                'English (Upside Down)' => 'en_UD',
                'English (US)' => 'en_US',
                'Esperanto' => 'eo_EO',
                'Estonian' => 'et_EE',
                'Faroese' => 'fo_FO',
                'Filipino' => 'tl_PH',
                'Finnish' => 'fi_FI',
                'French (Canada)' => 'fr_CA',
                'French (France)' => 'fr_FR',
                'Frisian' => 'fy_NL',
                'Fulah' => 'ff_NG',
                'Galician' => 'gl_ES',
                'Ganda' => 'lg_UG',
                'Georgian' => 'ka_GE',
                'German' => 'de_DE',
                'Greek' => 'el_GR',
                'Guarani' => 'gn_PY',
                'Gujarati' => 'gu_IN',
                'Hausa' => 'ha_NG',
                'Hebrew' => 'he_IL',
                'Hindi' => 'hi_IN',
                'Hungarian' => 'hu_HU',
                'Icelandic' => 'is_IS',
                'Igbo' => 'ig_NG',
                'Indonesian' => 'id_ID',
                'Irish' => 'ga_IE',
                'Italian' => 'it_IT',
                'Japanese (Kansai)' => 'ja_KS',
                'Japanese' => 'ja_JP',
                'Javanese' => 'jv_ID',
                'Kannada' => 'kn_IN',
                'Kazakh' => 'kk_KZ',
                'Khmer' => 'km_KH',
                'Kinyarwanda' => 'rw_RW',
                'Klingon' => 'tl_ST',
                'Korean' => 'ko_KR',
                'Kurdish (Kurmanji)' => 'ku_TR',
                'Lao' => 'lo_LA',
                'Latin' => 'la_VA',
                'Latvian' => 'lv_LV',
                'Leet Speak' => 'fb_LT',
                'Limburgish' => 'li_NL',
                'Lingala' => 'ln_CD',
                'Lithuanian' => 'lt_LT',
                'Macedonian' => 'mk_MK',
                'Malagasy' => 'mg_MG',
                'Malay' => 'ms_MY',
                'Malayalam' => 'ml_IN',
                'Maltese' => 'mt_MT',
                'Marathi' => 'mr_IN',
                'Mongolian' => 'mn_MN',
                'Ndebele' => 'nd_ZW',
                'Nepali' => 'ne_NP',
                'Northern Sámi' => 'se_NO',
                'Norwegian (bokmal)' => 'nb_NO',
                'Norwegian (nynorsk)' => 'nn_NO',
                'Oriya' => 'or_IN',
                'Pashto' => 'ps_AF',
                'Persian' => 'fa_IR',
                'Polish' => 'pl_PL',
                'Portuguese (Brazil)' => 'pt_BR',
                'Portuguese (Portugal)' => 'pt_PT',
                'Punjabi' => 'pa_IN',
                'Quechua' => 'qu_PE',
                'Romanian' => 'ro_RO',
                'Romansh' => 'rm_CH',
                'Russian' => 'ru_RU',
                'Sanskrit' => 'sa_IN',
                'Sardinian' => 'sc_IT',
                'Serbian' => 'sr_RS',
                'Shona' => 'sn_ZW',
                'Silesian' => 'sz_PL',
                'Simplified Chinese (China)' => 'zh_CN',
                'Sinhala' => 'si_LK',
                'Slovak' => 'sk_SK',
                'Slovenian' => 'sl_SI',
                'Somali' => 'so_SO',
                'Sorani Kurdish' => 'cb_IQ',
                'Spanish (Colombia)' => 'es_CO',
                'Spanish (Spain)' => 'es_ES',
                'Spanish' => 'es_LA',
                'Swahili' => 'sw_KE',
                'Swedish' => 'sv_SE',
                'Syriac' => 'sy_SY',
                'Tajik' => 'tg_TJ',
                'Tamazight' => 'tz_MA',
                'Tamil' => 'ta_IN',
                'Tatar' => 'tt_RU',
                'Telugu' => 'te_IN',
                'Thai' => 'th_TH',
                'Traditional Chinese (Hong Kong)' => 'zh_HK',
                'Traditional Chinese (Taiwan)' => 'zh_TW',
                'Turkish' => 'tr_TR',
                'Turkmen' => 'tk_TM',
                'Ukrainian' => 'uk_UA',
                'Urdu' => 'ur_PK',
                'Uzbek' => 'uz_UZ',
                'Vietnamese' => 'vi_VN',
                'Welsh' => 'cy_GB',
                'Wolof' => 'wo_SN',
                'Xhosa' => 'xh_ZA',
                'Yiddish' => 'yi_DE',
                'Yoruba' => 'yo_NG',
                'Zazaki' => 'zz_TR',
                'Zulu' => 'zu_ZA'
            );

            $locales_input = '<select name="afblb_locale">';

            foreach( $locales as $k => $v ) {
                $locales_input .= '<option value="'.$v.'"'.( ( $this->cf['locale'] == $v ) ? ' selected="selected"' : '' ).'>'.$k.'</option>';
            }

            $locales_input .= '</select>';

            ?>
            <div class="wrap">
                <div id="icon-link" class="icon32"></div><h2><?php esc_html_e( 'Aspexi Social Media Slider Settings', 'aspexifblikebox' ); ?></h2>
                <?php echo $this->display_admin_notices(); ?>
                <div id="poststuff" class="metabox-holder">
                    <div id="post-body">
                        <div id="post-body-content">
                            <form method="post" action="<?php echo ASPEXIFBLIKEBOX_ADMIN_URL; ?>">

                                <input type="hidden" name="afblb_form_submit" value="submit" />

                                <div class="postbox">
                                    <h3><span><?php esc_html_e('Settings', 'aspexifblikebox'); ?></span></h3>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tbody>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Page Plugin', 'aspexifblikebox'); ?></th>
                                                <td><select name="afblb_status">
                                                        <option value="enabled"<?php if( 'enabled' == esc_html($this->cf['status']) ) echo ' selected="selected"'; ?>><?php esc_html_e('enabled', 'aspexifblikebox'); ?></option>
                                                        <option value="disabled"<?php if( 'disabled' == esc_html($this->cf['status']) ) echo ' selected="selected"'; ?>><?php esc_html_e('disabled', 'aspexifblikebox'); ?></option>
                                                    </select></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Facebook Page URL', 'aspexifblikebox'); ?></strong></th>
                                                <td>http://www.facebook.com/&nbsp;<input type="text" name="afblb_url" value="<?php esc_html_e($this->cf['url']); ?>" />
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Page Plugin Height', 'aspexifblikebox'); ?></th>
                                                <td><input type="text" name="afblb_height" value="285" size="3" disabled readonly />&nbsp;px<?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Page Plugin Width', 'aspexifblikebox'); ?></th>
                                                <td><input type="text" name="afblb_width" value="245" size="3" disabled readonly />&nbsp;px<?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Adaptive Width', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_adaptive_width" disabled readonly/><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Show Friends\' Faces', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_faces" checked disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Browser\'s Lazy Loading', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_lazy_loading" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row">
                                                    <?php esc_html_e( 'Hide CTA', 'aspexifblikebox'); ?><br>
                                                    <span style="font-size: 10px;"><?php esc_html_e( 'Hide the custom call to action button (if available)', 'aspexifblikebox' ); ?></span>
                                                </th>
                                                <td><input type="checkbox" value="on" name="afbsb_cta" disabled readonly><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Hide Cover', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Hide cover photo in the header.', 'aspexifblikebox'); ?></span></th>
                                                <td><input type="checkbox" value="on" name="afblb_header" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Small Header', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Use the small header instead.', 'aspexifblikebox'); ?></span></th>
                                                <td><input type="checkbox" value="on" name="afblb_small_header" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Localization', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Change might not be visible immediately due to Facebook / browser cache.', 'aspexifblikebox'); ?></span></th>
                                                <td><?php echo $locales_input; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Don\'t show again', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_dont_show_again" disabled readonly/><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row">
                                                    <?php esc_html_e('Slide out for unique users only', 'aspexifblikebox'); ?>
                                                    <div style="font-size: 10px"><?php esc_html_e('If you select this checkbox and enable auto slide out, this will happen only once for user', 'aspexifblikebox'); ?></div>
                                                </th>
                                                <td><input type="checkbox" value="on" name="afbsb_slide_out_unique" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row">
                                                    <?php esc_html_e('Tabs', 'aspexifblikebox'); ?>
                                                </th>
                                                <td>
                                                    <label>
                                                        <input type="checkbox" value="on" name="afblb_tabs[timeline]" disabled readonly /> Timeline <br>
                                                    </label>
                                                    <label>
                                                        <input type="checkbox" value="on" name="afblb_tabs[events]" disabled readonly /> Events <br>
                                                    </label>
                                                    <label>
                                                        <input type="checkbox" value="on" name="afblb_tabs[messages]" disabled readonly /> Messages
                                                    </label>
                                                    <p><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></p>
                                                </td>
                                            </tr>
                                            <?php
                                            echo apply_filters('aspexifblikebox_admin_settings', '');
                                            ?>
                                            </tbody>
                                        </table>

                                    </div>
                                </div>

                                <p><input class="button-primary" type="submit" name="send" value="<?php esc_html_e('Save all settings', 'aspexifblikebox'); ?>" id="submitbutton" />
                                    <input class="button-secondary" type="submit" name="preview" value="<?php esc_html_e('Save and preview', 'aspexifblikebox'); ?>" id="previewbutton" /></p>
                                <?php wp_nonce_field( plugin_basename( __FILE__ ), 'afblb_nonce_name' ); ?>

                                <div class="postbox">
                                    <h3><span><?php esc_html_e('Button Settings', 'aspexifblikebox'); ?></span></h3>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tbody>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Button Space', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Space between button and page edge', 'aspexifblikebox'); ?></span></th>
                                                <td><input type="text" name="afblb_btspace" value="0" size="3" disabled readonly />&nbsp;px<?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Button Placement', 'aspexifblikebox'); ?></th>
                                                <td><input type="radio" name="afblb_btvertical" value="top" disabled readonly />&nbsp;<?php esc_html_e('top of sidebox','aspexifblikebox'); ?><br />
                                                    <input type="radio" name="afblb_btvertical" value="middle" checked disabled readonly />&nbsp;<?php esc_html_e('middle of sidebox','aspexifblikebox'); ?><br />
                                                    <input type="radio" name="afblb_btvertical" value="bottom" disabled readonly />&nbsp;<?php esc_html_e('bottom of sidebox','aspexifblikebox'); ?><br />
                                                    <input type="radio" name="afblb_btvertical" value="fixed" disabled readonly />&nbsp;<?php esc_html_e('fixed','aspexifblikebox'); ?>
                                                    <input type="text" name="afblb_btvertical_val" value="" size="3" disabled readonly />&nbsp;px <?php esc_html_e('from slider top','aspexifblikebox'); ?><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Button Image', 'aspexifblikebox'); ?></th>
                                                <td><span><input type="radio" name="afblb_btimage" value="fb1-right" checked disabled readonly />&nbsp;<img src="<?php echo ASPEXIFBLIKEBOX_URL.'images/fb1-right.png'; ?>" alt="" style="cursor:pointer;" /></span>
                                                    <span><input type="radio" name="afblb_btimage" value="" disabled readonly />&nbsp;<img src="<?php echo ASPEXIFBLIKEBOX_URL.'images/preview-buttons.jpg'; ?>" alt="" style="cursor:pointer;" /></span><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('High Resolution', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Use SVG high quality images instead of PNG if possible. Recommended for Retina displays (iPhone, iPad, MacBook Pro).', 'aspexifblikebox'); ?></span></th>
                                                <td><input type="checkbox" value="on" name="afblb_bthq" disabled readonly />&nbsp;<img src="<?php echo ASPEXIFBLIKEBOX_URL.'images/svgonoff.png'; ?>" alt="" style="cursor:pointer;" /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <p><input class="button-primary" type="submit" name="send" value="<?php esc_html_e('Save all settings', 'aspexifblikebox'); ?>" id="submitbutton" />
                                    <input class="button-secondary" type="submit" name="preview" value="<?php esc_html_e('Save and preview', 'aspexifblikebox'); ?>" id="previewbutton" /></p>

                                <div class="postbox">
                                    <h3><span><?php esc_html_e('Advanced Look and Feel', 'aspexifblikebox'); ?></span></h3>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tbody>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Animate on page load', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_animate_on_page_load" disabled readonly />&nbsp
                                                    <?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Placement', 'aspexifblikebox'); ?></th>
                                                <td><select name="afblb_placement" disabled readonly>
                                                        <option value="left"><?php esc_html_e('left', 'aspexifblikebox'); ?></option>
                                                        <option value="right" selected="selected"><?php esc_html_e('right', 'aspexifblikebox'); ?></option>
                                                    </select><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Vertical placement', 'aspexifblikebox'); ?></th>
                                                <td><input type="radio" name="afblb_vertical" value="middle" checked disabled readonly />&nbsp;<?php esc_html_e('middle','aspexifblikebox'); ?><br />
                                                    <input type="radio" name="afblb_vertical" value="fixed" disabled readonly />&nbsp;<?php esc_html_e('fixed','aspexifblikebox'); ?>
                                                    <input type="text" name="afblb_vertical_val" value="" size="3" disabled readonly />&nbsp;px <?php esc_html_e('from page top','aspexifblikebox'); ?><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Border Color', 'aspexifblikebox'); ?></th>
                                                <td><input type="text" name="afblb_bordercolor" class="bordercolor-field" value="#3B5998" size="6" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Border Width', 'aspexifblikebox'); ?></th>
                                                <td><input type="text" name="afblb_borderwidth" value="2" size="3" disabled readonly />&nbsp;px<?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Slide on Mouse', 'aspexifblikebox'); ?></th>
                                                <td><select name="afblb_slideon" disabled readonly>
                                                        <option value="hover" selected="selected"><?php esc_html_e('hover', 'aspexifblikebox'); ?></option>
                                                        <option value="click"><?php esc_html_e('click', 'aspexifblikebox'); ?></option>
                                                    </select><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Slide Time', 'aspexifblikebox'); ?></th>
                                                <td><input type="text" name="afblb_slidetime" value="400" size="3" disabled readonly />&nbsp;<?php esc_html_e('milliseconds', 'aspexifblikebox'); ?><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Auto open', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_autoopen" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?><br>
                                                    <?php esc_html_e('Auto open after', 'aspexifblikebox'); ?>&nbsp;<input type="text" name="afblb_autoopentime" value="400" size="3" disabled readonly />&nbsp;<?php esc_html_e('milliseconds', 'aspexifblikebox'); ?> (1000 milliseconds = 1 second)
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Auto close', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_autoopen" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?><br>
                                                    <?php esc_html_e('Auto close after', 'aspexifblikebox'); ?>&nbsp;<input type="text" name="afblb_autoopentime" value="400" size="3" disabled readonly />&nbsp;<?php esc_html_e('milliseconds', 'aspexifblikebox'); ?> (1000 milliseconds = 1 second)
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Auto open when user reaches bottom of the page', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_autoopenonbottom" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Auto open when user reaches position', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_autoopenonposition" disabled readonly /><br>
                                                    <?php echo esc_html__( 'Auto open when user is', 'aspexifblikebox' ); ?>:&nbsp;<input type="text" disabled readonly name="afblb_autoopenonposition_px" size="5">px&nbsp;from:
                                                    <select name="afblb_autoopenonposition_name" disabled readonly>
                                                        <option value="top"><?php echo esc_html__( 'Top', 'aspexifblikebox' ); ?></option>
                                                        <option value="bottom"><?php echo esc_html__( 'Bottom', 'aspexifblikebox' ); ?></option>
                                                    </select><br>
                                                    <?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Auto open when user reaches element', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_autoopenonelement" disabled readonly /><br>
                                                    <?php echo esc_html__( 'Auto open when user reaches', 'aspexifblikebox' ); ?>:&nbsp;<input type="text" disabled readonly name="afblb_autoopenonelement_name" size="10" value=""><small><?php echo esc_html__( '(jQuery selector for example #element_id, .some_class)', 'aspexifblikebox' ); ?></small><br>
                                                    <?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Delay FB content load', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Checking this box will prevent from loading the facebook content while loading the whole page. With this box checked the page will load faster, but facebook content may appear a bit later while opening the box for the first time.', 'aspexifbsidebox'); ?></span></th>
                                                <td><input type="checkbox" value="on" name="afbsb_async" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Disable on GET', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Example: set Parameter=iframe and Value=true. Sidebox will be disabled on all URLs like yourwebsite.com/?iframe=true.', 'aspexifblikebox'); ?></span></th>
                                                <td><?php esc_html_e('Parameter', 'aspexifblikebox'); ?>:&nbsp;<input type="text" name="afblb_disableparam" value="" size="6" disabled readonly /><br />
                                                    <?php esc_html_e('Value', 'aspexifblikebox'); ?>:&nbsp;<input type="text" name="afblb_disableval" value="" size="6" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Disable on Posts / Pages (comma separated):', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="text" name="afblb_disabled_on_ids" value="" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Disable on Posts:', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_disabled_on_posts" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Disable on all Pages:', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_disabled_on_pages" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <?php
                                            $types = get_post_types();
                                            unset($types['post']);
                                            unset($types['page']);
                                            unset($types['attachment']);
                                            unset($types['revision']);
                                            unset($types['nav_menu_item']);
                                            unset($types['custom_css']);
                                            unset($types['customize_changeset']);
                                            unset($types['oembed_cache']);
                                            if( count( $types ) > 0 ) :
                                                ?>
                                                <tr style="vertical-align:top">
                                                    <th scope="row"><?php esc_html_e('Disable on post types:', 'aspexifblikebox'); ?></th>
                                                    <td>
                                                        <?php
                                                        foreach ($types as $post_type) {
                                                            echo '<input type="checkbox" value="' . esc_html($post_type) . '" name="afblb_disabled_on_posttypes[]" disabled readonly /> ' .esc_html($post_type) . '<br>';
                                                        }
                                                        ?>
                                                        <?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Disable on Archives (listings):', 'aspexifblikebox'); ?></th>
                                                <td>
                                                    <input type="checkbox" value="on" name="afblb_disabled_on_archives" disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?>
                                                </td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Disable on Small Screens', 'aspexifblikebox'); ?><br /><span style="font-size: 10px"><?php esc_html_e('Dynamically hide the plugin if screen size is smaller than sidebox size (CSS media query)', 'aspexifblikebox'); ?></span></th>
                                                <td><input type="checkbox" value="on" name="afblb_smallscreens" checked disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <p><input class="button-primary" type="submit" name="send" value="<?php esc_html_e('Save all settings', 'aspexifblikebox'); ?>" id="submitbutton" />
                                    <input class="button-secondary" type="submit" name="preview" value="<?php esc_html_e('Save and preview', 'aspexifblikebox'); ?>" id="previewbutton" /></p>

                                <div class="postbox">
                                    <h3><span><?php esc_html_e('Enable on Mobile', 'aspexifblikebox'); ?></span></h3>
                                    <div class="inside">
                                        <table class="form-table">
                                            <tbody>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('iPad & iPod', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_edipad" checked disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('iPhone', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_ediphone" checked disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Android', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_edandroid" checked disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            <tr style="vertical-align:top">
                                                <th scope="row"><?php esc_html_e('Other Mobile Devices', 'aspexifblikebox'); ?></th>
                                                <td><input type="checkbox" value="on" name="afblb_edothers" checked disabled readonly /><?php echo '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'; ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <p><input class="button-primary" type="submit" name="send" value="<?php esc_html_e('Save all settings', 'aspexifblikebox'); ?>" id="submitbutton" />
                                    <input class="button-secondary" type="submit" name="preview" value="<?php esc_html_e('Save and preview', 'aspexifblikebox'); ?>" id="previewbutton" /></p>
                            </form>
                            <div class="postbox">
                                <h3><span><?php esc_html_e('Made by', 'aspexifblikebox'); ?></span></h3>
                                <div class="inside">
                                    <div style="width: 170px; margin: 0 auto;">
                                        <a href="<?php echo $this->get_pro_url(); ?>" target="_blank"><img src="<?php echo ASPEXIFBLIKEBOX_URL.'images/aspexi300.png'; ?>" alt="" border="0" width="150" /></a>
                                    </div>
                                </div>
                            </div>
                            <div class="postbox">
                                <h3><span><?php esc_html_e('Security Services with ASecure.me', 'aspexifblikebox'); ?></span></h3>
                                <div class="inside">
                                    <div style="width: 170px; margin: 0 auto;">
                                        <a href="https://asecure.me/?utm_source=likeboxfree" target="_blank"><img src="<?php echo ASPEXIFBLIKEBOX_URL.'images/be250.png'; ?>" alt="" border="0" width="170" /></a>
                                    </div>
                                    <p style="text-align: center;">
                                        <?php
                                        $asecure_me_link = '<a href="https://asecure.me/?utm_source=slideboxfree" target="_blank">Check out now</a>.';
                                        $asecure_me_text = esc_html__( 'We offer security services, backups and more. %s', 'aspexifblikebox' );
                                        echo sprintf($asecure_me_text, $asecure_me_link);
                                        ?></p>
                                </div>
                            </div>
                            <div id="aspexifblikebox-footer" style="text-align:left;text-shadow:0 1px 0 #fff;margin:0 0 10px;color:#888;"><?php echo sprintf(esc_html__('If you like %s please leave us a %s rating. A huge thank you in advance!'), '<strong>Aspexi Social Media Slider HD</strong>', '<a href="https://wordpress.org/support/plugin/aspexi-facebook-like-box/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733</a>') ?></div>
                            <script type="text/javascript">
                                jQuery(document).ready(function(){
                                    jQuery('#wpfooter').prepend( jQuery('#aspexifblikebox-footer') );
                                });
                            </script>
                        </div>
                    </div>
                </div>
            </div>
            <?php

            // Preview
            if( $preview ) {
                $this->init_scripts();
                echo $this->get_html($preview);
            }
        }

        public function get_pro_url() {
            return 'http://aspexi.com/downloads/aspexi-facebook-like-box-slider-hd/?src=free_plugin';
        }

        public function get_pro_link() {
            $ret = '';

            $ret .= '&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifblikebox' ).'</a>';

            return $ret;
        }

        /**
         * @return get_html
         */
        public function get_footer_html()     {
            echo $this->get_html();
        }

        public function get_html( $preview = false ) {

            $url            = apply_filters( 'aspexifblikebox_url', esc_attr($this->cf['url']));
            $status         = apply_filters( 'aspexifblikebox_status', esc_attr($this->cf['status']));

            // Disable maybe
            if( ( !strlen( $url ) || 'enabled' != $status ) && !$preview )
                return;

            // Options
            $locale         = apply_filters( 'aspexifblikebox_locale', esc_attr($this->cf['locale']));
            $height         = 234;
            $width          = 245;
            $placement      = 'right';
            $btspace        = 0;
            $btimage        = 'fb1-right.png';
            $bordercolor    = '#3B5998';
            $borderwidth    = 2;
            $bgcolor        = '#ffffff';

            $css_placement = array();
            if( 'left' == $placement ) {
                $css_placement[0] = 'right';
                $css_placement[1] = '0 '.(48+$btspace).'px 0 5px';
            } else {
                $css_placement[0] = 'left';
                $css_placement[1] = '0 0 0 '.(48+$btspace).'px';
            }

            $css_placement[2] = '50%;margin-top:-'.floor($height/2).'px';

            $smallscreenscss = '';
            if( $width > 0 ) {
                $widthmax = (int)($width + 48 + $borderwidth + 10);
                $smallscreenscss = '@media (max-width: '.$widthmax.'px) { .aspexifblikebox { display: none; } }';
            }

            $stream     = 'false';
            $header     = 'false';

            // Facebook button image (check in THEME CHILD -> THEME PARENT -> PLUGIN DIR)
            // TODO: move this to admin page
            $users_button_custom    = '/plugins/'.basename( dirname( __FiLE__ ) ).'/images/aspexi-fb-custom.png';
            $users_button_template  = get_template_directory() . $users_button_custom;
            $users_button_child     = get_stylesheet_directory() . $users_button_custom;
            $button_uri             = '';

            if( file_exists( $users_button_child ) )
                $button_uri = get_stylesheet_directory_uri() . $users_button_custom;
            elseif( file_exists( $users_button_template ) )
                $button_uri = get_template_directory_uri() . $users_button_custom;
            elseif( file_exists( plugin_dir_path( __FILE__ ).'images/'.$btimage ) )
                $button_uri = ASPEXIFBLIKEBOX_URL.'images/'.$btimage;

            if( '' == $button_uri ) {
                $button_uri = ASPEXIFBLIKEBOX_URL.'images/fb1-right.png';
            }

            $button_uri  = apply_filters( 'aspexifblikebox_button_uri', $button_uri );

            $output = '';

            $page_url = 'https://www.facebook.com/'.$url;

            $output .= '<div class="fb-root"></div>
            <script>(function(d, s, id) {
                var js, fjs = d.getElementsByTagName(s)[0];
                if (d.getElementById(id)) return;
                js = d.createElement(s); js.id = id;
                js.src = "//connect.facebook.net/'.$locale.'/sdk.js#xfbml=1&version=v14.0&appId=1548213832159053";
                fjs.parentNode.insertBefore(js, fjs);
            }(document, \'script\', \'facebook-jssdk\'));</script>
            <style type="text/css">' . $smallscreenscss.' .fb-xfbml-parse-ignore {
                    display: none;
                }
                
                .aspexifblikebox {
                    overflow: hidden;
                    z-index: 99999999;
                    position: fixed;
                    padding: '.$css_placement[1].';
                    top: ' . $css_placement[2] . ';
                    right: -' . ($width) . 'px;
                }
                
                .aspexifblikebox .aspexi_facebook_iframe {
                    padding: 0;
                    border: ' . $borderwidth . 'px solid ' . $bordercolor . ';
                    background: #fff;
                    width: ' . $width . 'px;
                    height: ' . $height . 'px;
                    box-sizing: border-box;
                }
                
                .aspexifblikebox .fb-page {
                    background: url("' . ASPEXIFBLIKEBOX_URL . 'images/load.gif") no-repeat center center;
                    width: ' . ($width - ($borderwidth * 2)). 'px;
                    height: ' . ($height - ($borderwidth * 2)). 'px;
                    margin: 0;
                }
                
                .aspexifblikebox .fb-page span {
                    background: #fff;
                    height: 100% !important;
                }
                
                .aspexifblikebox .aspexi_facebook_button {
                    background: url("' . $button_uri . '") no-repeat scroll transparent;
                    height: 155px;
                    width: 48px;
                    position: absolute;
                    top: 0;
                    left: 0;
                    cursor: pointer;
                }
            </style>
            <div class="aspexifblikebox">
                <div class="aspexi_facebook_button"></div>
                <div class="aspexi_facebook_iframe">
                    <div class="fb-page" data-href="'.$page_url.'" data-width="'.($width - 4).'" data-height="'.($height - 4).'" data-hide-cover="false" data-show-facepile="true" data-lazy="false"><div class="fb-xfbml-parse-ignore"><blockquote cite="'.$page_url.'"><a href="'.$page_url.'">Facebook</a></blockquote></div></div>
                </div>
            </div>';

            $output = apply_filters( 'aspexifblikebox_output', $output );

            return $output;
        }

        public function init_scripts() {
            $width      = 245;
            $placement  = 'right';
            $slideon    = 'hover';
            $ismobile   = wp_is_mobile();

            wp_enqueue_script( 'aspexi-facebook-like-box', ASPEXIFBLIKEBOX_URL . 'js/aflb.js', array( 'jquery' ), false, true );
            wp_localize_script( 'aspexi-facebook-like-box', 'aflb', array(
                'slideon'   => $slideon,
                'placement' => $placement,
                'width'     => (int)$width,
                'ismobile'  => $ismobile
            ) );
        }

        public static function uninstall() {

            delete_option( 'aspexifblikebox_options' );
        }

        public function admin_scripts() {

            wp_enqueue_script( 'aspexi-facebook-like-box-admin', ASPEXIFBLIKEBOX_URL . 'js/aflb-admin.js', array( 'jquery' ), false, true );

            wp_localize_script( 'aspexi-facebook-like-box-admin', 'aflb_admin', array(
                'nonce'   => wp_create_nonce( "afblhidenotice-nonce" )
            ) );

            return;
        }

        public function extras_init() {
            /* qTranslate */
            add_filter( 'aspexifblikebox_admin_settings', array( &$this, 'extras_qtranslate_admin' ) );
            add_filter( 'aspexifblikebox_admin_settings', array( &$this, 'extras_polylang_admin' ) );
        }

        public function extras_qtranslate_detect() {
            global $q_config;
            return (isset($q_config) && !empty($q_config));
        }

        public function extras_qtranslate_admin( $extra_admin_content ) {
            $qtranslate_locale = $this->extras_qtranslate_detect();

            if( $qtranslate_locale ) {
                $extra_admin_content .= '<tr style="vertical-align:top">
    <th scope="row">'.esc_html__('qTranslate/mqTranslate', 'aspexifblikebox').'<br /><span style="font-size: 10px">'.esc_html__('Try to detect qTranslate/mqTranslate language and force it instead of language set in Localization.', 'aspexifblikebox').'</span></th>
    <td><input type="checkbox" value="on" name="afblb_qtranslate" disabled readonly />'.'&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'.'</td>
</tr>';
            }

            return $extra_admin_content;
        }

        public function extras_polylang_admin( $extra_admin_content ) {

            if(function_exists('pll_current_language')) {

                $extra_admin_content .= '<tr style="vertical-align:top">
    <th scope="row">'.esc_html__('Polylang', 'aspexifblikebox').'<br /><span style="font-size: 10px">'.esc_html__('Try to detect Polylang language and force it instead of language set in Localization.', 'aspexifblikebox').'</span></th>
    <td><input type="checkbox" value="on" name="afblb_polylang" disabled readonly />'.'&nbsp;&nbsp;&nbsp;<a href="'.esc_url($this->get_pro_url()).'" target="_blank">'.esc_html__( 'Get PRO version', 'aspexifbsidebox' ).'</a>'.'</td>
</tr>';
            }

            return $extra_admin_content;
        }

        // Check if Page Plugin is enabled
        public function is_pp() {

            return $this->cf['page_plugin'];
        }

        public function admin_notices() {

            if( !isset($this->cf['hide_notice']) || @$this->cf['hide_notice'] != '1' ) {
                ?>
                <div class="notice notice-success" id="afblnotice" style="display: flex;flex-wrap: wrap;">
                    <p> <?php
                        $asecure_me_link = '<a href="https://asecure.me/?utm_source=likeboxfree" target="_blank">ASecure.me</a>';
                        $asecure_me_text = esc_html__( 'Is your site secure? Check out how can you protect your website with %s services.', 'aspexifblikebox' );
                        echo sprintf($asecure_me_text, $asecure_me_link);
                        ?>
                    <div style="flex: 1 300px;margin: .5em 0;text-align: right;">
                        <input type="button" id="afblhidenotice" value="<?php esc_html_e( 'Hide this notice', 'aspexifblikebox' ); ?>" class="button" />
                        <input type="button" value="<?php esc_html_e( 'Visit ASecure.me', 'aspexifblikebox' ); ?>" onclick="window.open('https://asecure.me/?utm_source=likeboxfree');" class="button-primary" />
                    </div>
                    </p>
                </div>
                <?php
            }
        }

        public function admin_notices_handle() {

            check_ajax_referer( 'afblhidenotice-nonce', 'nonce' );

            $this->cf['hide_notice'] = '1';

            update_option( 'aspexifblikebox_options',  $this->cf, '', 'yes' );

            die();
        }
    }

    /* Let's start the show */
    global $aspexifblikebox;

    $aspexifblikebox = new AspexiFBlikebox();
}
