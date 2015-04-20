<?php
/**
 * Plugin Name:     Easy Digital Downloads - htaccess Editor
 * Plugin URI:      http://section214.com
 * Description:     Edit the htaccess file through the EDD Tools page
 * Version:         1.0.1
 * Author:          Daniel J Griffiths
 * Author URI:      http://section214.com
 *
 * @package         EDD\htaccessEditor
 * @author          Daniel J Griffiths <dgriffiths@section214.com>
 * @copyright       Copyright (c) 2014, Daniel J Griffiths
 */


// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


if( ! class_exists( 'EDD_htaccess_Editor' ) ) {


    /**
     * Main EDD_htaccess_Editor class
     *
     * @since       1.0.0
     */
    class EDD_htaccess_Editor {


        /**
         * @var         EDD_htaccess_Editor $instance The one true EDD_htaccess_Editor
         * @since       1.0.0
         */
        private static $instance;


        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      self::$instance The one true EDD_htaccess_Editor
         */
        public static function instance() {
            if( ! self::$instance ) {
                self::$instance = new EDD_htaccess_Editor();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin path
            define( 'EDD_HTACCESS_EDITOR_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_HTACCESS_EDITOR_URL', plugin_dir_url( __FILE__ ) );
        }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {
            // Add the editor
            add_action( 'edd_tools_tab_general', array( $this, 'htaccess_editor' ) );

            // Save the htaccess file
            add_action( 'edd_save_htaccess_file', array( $this, 'save_htaccess' ) );

            // Reset the htaccess file
            add_action( 'edd_reset_htaccess_file', array( $this, 'reset_htaccess' ) );

            // Override the default rules
            add_filter( 'edd_protected_directory_htaccess_rules', array( $this, 'override_rules' ), 10, 2 );
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
            $lang_dir = apply_filters( 'edd_htaccess_editor_lang_dir', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), '' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-htaccess-editor', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-htaccess-editor/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-htaccess-editor/ folder
                load_textdomain( 'edd-htaccess-editor', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-htaccess-editor/languages/ folder
                load_textdomain( 'edd-htaccess-editor', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-htaccess-editor', false, $lang_dir );
            }
        }


        /**
         * Display our editor
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function htaccess_editor() {
            $contents = edd_get_option( 'htaccess_rules', false );

            if( ! $contents ) {
                $contents = edd_get_htaccess_rules();
            } else {
                $contents = html_entity_decode( stripslashes( $contents ) );
            }
            ?>
                <div class="postbox">
                    <h3><span><?php _e( 'Edit htaccess', 'edd-htaccess-editor' ); ?></span></h3>
                    <div class="inside">
                        <?php if( ! stristr( $_SERVER['SERVER_SOFTWARE'], 'apache' ) ) { ?>
                            <p><?php _e( 'The htaccess editor is only useful with the Apache webserver!', 'edd-htaccess-editor' ); ?></p>
                        <?php } else { ?>
                            <form method="post" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-tools&tab=general' ); ?>">
                                <p>
                                    <textarea name="htaccess_contents" rows="10" class="large-text"><?php echo $contents; ?></textarea>
                                    <span class="description"><?php _e( '<strong>Warning!</strong> Incorrectly modifying your htaccess file could result in unexpected site behavior.', 'edd-htaccess-editor' ); ?></span>
                                </p>
                                <p>
                                    <input type="hidden" name="edd_action" value="save_htaccess_file" />
                                    <?php wp_nonce_field( 'edd_save_htaccess_nonce', 'edd_save_htaccess_nonce' ); ?>
                                    <?php submit_button( __( 'Save', 'edd-htaccess-editor' ), 'secondary', 'submit', false ); ?>
                                    <a href="<?php echo esc_url( add_query_arg( array( 'edd-action' => 'reset_htaccess_file' ) ) ); ?>" class="button secondary-button" style="color: #ff0000;"><?php _e( 'Reset htaccess file', 'edd-htaccess-editor' ); ?></a>
                                </p>
                            </form>
                        <?php } ?>
                    </div><!-- .inside -->
                </div><!-- .postbox -->
            <?php
        }


        /**
         * Save the htaccess rules
         *
         * @access      public
         * @since       1.0.0
         * @global      array $edd_options The EDD options
         * @return      void
         */
        public function save_htaccess() {
            global $edd_options;

            // Bail if nonce can't be verified
            if( ! wp_verify_nonce( $_POST['edd_save_htaccess_nonce'], 'edd_save_htaccess_nonce' ) ) {
                return;
            }

            // Bail if user shouldn't be editing this
            if( ! current_user_can( 'manage_shop_settings' ) ) {
                return;
            }

            // Sanitize the input
            $contents = esc_html( $_POST['htaccess_contents'] );

            $edd_options['htaccess_rules'] = $contents;
            update_option( 'edd_settings', $edd_options );

            edd_create_protection_files( true );
        }


        /**
         * Reset the htaccess file
         *
         * @access      public
         * @since       1.0.0
         * @global      array $edd_options The EDD options
         * @return      void
         */
        public function reset_htaccess() {
            global $edd_options;

            unset( $edd_options['htaccess_rules'] );
            update_option( 'edd_settings', $edd_options );

            wp_safe_redirect( add_query_arg( array( 'edd-action' => null ) ) );
            exit;
        }


        /**
         * Override the core rules
         *
         * @access      public
         * @since       1.0.0
         * @param       string $rules The current rules
         * @param       string $method The download method
         * @return      string $rules The updated rules
         */
        public function override_rules( $rules, $method ) {
            $contents = edd_get_option( 'htaccess_rules', false );

            if( $contents ) {
                $rules = html_entity_decode( stripslashes( $contents ) );
            }

            return $rules;
        }
    }
}


/**
 * The main function responsible for returning the one true EDD_htaccess_Editor
 * instance to functions everywhere.
 *
 * @since       1.0.0
 * @return      EDD_htaccess_Editor The one true EDD_htaccess_Editor
 */
function edd_htaccess_editor() {
    if( ! class_exists( 'Easy_Digital_Downloads' ) ) {
        if( ! class_exists( 'EDD_Extension_Activation' ) ) {
            require_once 'includes/class.extension-activation.php';
        }

        $activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
        $activation = $activation->run;

        return EDD_htaccess_Editor::instance();
    } else {
        return EDD_htaccess_Editor::instance();
    }
}
add_action( 'plugins_loaded', 'edd_htaccess_editor' );
