<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://www.blobinator.com
 * @since      1.0.0
 *
 * @package    Blobinator
 * @subpackage Blobinator/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Blobinator
 * @subpackage Blobinator/admin
 * @author     Michael Bordash <michael@internetdj.com>
 */
class Blobinator_Admin {

    private $option_name = 'blobinator';
    protected $api_host = 'https://www.blobinator.com';
    //protected $api_host         = 'http://localhost:8888';
    protected $api_path         = '/api/blobinator';
    protected $api_manager_path = '/';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name  = $plugin_name;
		$this->version      = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Blobinator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Blobinator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

    }

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Blobinator_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Blobinator_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

        wp_register_script(
            $this->plugin_name . '_sidebar',
            plugins_url( 'js/blobinator-admin-panel/build/index.js', __FILE__ ),
            array( 'wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-element', 'wp-components', 'wp-compose' ),
	        filemtime()
            
        );

        $blobinator_local_arr = array(
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'security'  => wp_create_nonce( 'blobinator-ajax-string' ),
            'postID'    => get_the_ID(),
            'apikey'    => get_option( $this->option_name . '_apikey' )
        );

        wp_localize_script( $this->plugin_name . '_sidebar', 'blobinatorAjaxObject', $blobinator_local_arr );
        wp_enqueue_script( $this->plugin_name . '_sidebar' );

	}

    /**
     * Handle ajax request for text processing and display
     *
     * @since  1.0.0
     */
    public function blobinator_process_text()
    {

        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to be on this page.');
        }

        //check_ajax_referer( 'blobinator-ajax-string', 'security' );
	    wp_verify_nonce($_POST['security'], 'blobinator-ajax-string');

        if ( isset( $_POST['blobinator_text_to_analyze'] ) && $_POST['blobinator_text_to_analyze'] !== '' ) {

            //header('Content-type: application/json');
            echo $this->blobinator_cognitive( $_POST['blobinator_text_to_analyze'], $_POST['service'], $_POST['post_ID'] );

        }

        exit;
    }

    public function blobinator_cognitive( $textToAnalyze, $service, $postId ) {

        //get and check API key exists, pass key along server side request
        $blobinatorApiKey       = get_option( $this->option_name . '_apikey' ) ? get_option( $this->option_name . '_apikey' ) : 'wc_order_58773985ef2e1_am_Vu6R0EbYeLPE';

        if ( !isset($blobinatorApiKey) || $blobinatorApiKey === '' ) {

            $response_array['status'] = "error";
            $response_array['message'] = "Your License Key for Blobinator is not set. Please go to Settings > Content Advisor - Free API Key Activation to set your key first.";

            return json_encode($response_array);

        }

        if ( isset( $textToAnalyze ) && $textToAnalyze !== '' ) {

            $textToAnalyze  = urlencode( sanitize_text_field( $textToAnalyze  ) );
            $service        = sanitize_text_field( $service );
            $postId         = sanitize_text_field( $postId );

            $requestBody = array(
                'blobinator_text_to_analyze'    => $textToAnalyze,
                'api_key'                       => $blobinatorApiKey,
                'service'                       => $service,
            );

            $opts = array(
                'body'      => $requestBody,
                'headers'   => 'Content-type: application/x-www-form-urlencoded',
                'timeout'   => 45,
            );

            $response = wp_remote_post($this->api_host . $this->api_path, $opts);

            if( !is_wp_error( $response ) ) {

                update_post_meta( $postId, $service, $response['body'] );

                return $response['body'];

                //error_log($response['body']);
                //error_log( print_r($_POST,true) );

            } else {

                $response_array['status'] = "error";
                $response_array['message'] = "Something went wrong with this request. Code received: " . $response['response']['code'];

                return $response_array;

            }
        }

    }

    /**
     * Add an options page under the Settings submenu
     *
     * @since  1.0.0
     */
    public function add_options_page() {

        $this->plugin_screen_hook_suffix = add_options_page(
            __( 'Content Advisor Settings', 'blobinator' ),
            __( 'Blobinator', 'blobinator' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_options_page' )
        );

    }

    /**
     * Render the options page for plugin
     *
     * @since  1.0.0
     */
    public function display_options_page() {
        include_once 'partials/blobinator-admin-display.php';
    }

    /**
     * Register all related settings of this plugin
     *
     * @since  1.0.0
     */
    public function register_setting() {

        add_settings_section(
            $this->option_name . '_general',
            __( 'General', 'blobinator' ),
            array( $this, $this->option_name . '_general_cb' ),
            $this->plugin_name
        );

        add_settings_field(
            $this->option_name . '_sentiment',
            __( 'Display Sentiment on Public Posts?', 'blobinator' ),
            array( $this, $this->option_name . '_sentiment_cb' ),
            $this->plugin_name,
            $this->option_name . '_general',
            array( 'label_for' => $this->option_name . '_sentiment' )
        );

        add_settings_field(
            $this->option_name . '_emotion',
            __( 'Display Emotions on Public Posts?', 'blobinator' ),
            array( $this, $this->option_name . '_emotion_cb' ),
            $this->plugin_name,
            $this->option_name . '_general',
            array( 'label_for' => $this->option_name . '_emotion' )
        );

        add_settings_field(
            $this->option_name . '_apikey',
            __( 'API Key (if you have <a target="_blank" href="https://www.Blobinator.com">purchased a subscription</a>)', 'blobinator' ),
            array( $this, $this->option_name . '_apikey_cb' ),
            $this->plugin_name,
            $this->option_name . '_general',
            array( 'label_for' => $this->option_name . '_apikey' )
        );


        register_setting( $this->plugin_name, $this->option_name . '_sentiment', array( $this, $this->option_name . '_sanitize_option' ) );
        register_setting( $this->plugin_name, $this->option_name . '_emotion', array( $this, $this->option_name . '_sanitize_option' ) );
        register_setting( $this->plugin_name, $this->option_name . '_apikey', array( $this, $this->option_name . '_sanitize_text' ) );

    }

    /**
     * Render the text for the general section
     *
     * @since  1.0.0
     */
    public function blobinator_general_cb() {

        echo '<p>' . __( 'Please change the settings accordingly.', 'blobinator' ) . '</p>';

    }


    /**
     * Render the text input field for apikey option
     *
     * @since  1.3.2
     */
    public function blobinator_apikey_cb() {

        $apikey = get_option( $this->option_name . '_apikey' );

        ?>

        <fieldset>
            <label>
                <input type="text" name="<?php echo $this->option_name . '_apikey' ?>" id="<?php echo $this->option_name . '_apikey' ?>" value="<?php echo $apikey; ?>">
            </label>
        </fieldset>

        <?php
    }

    /**
     * Render the radio input field for sentiment option
     *
     * @since  1.0.0
     */
    public function blobinator_sentiment_cb() {

        $sentiment = get_option( $this->option_name . '_sentiment' );

        ?>

        <fieldset>
            <label>
                <input type="radio" name="<?php echo $this->option_name . '_sentiment' ?>" id="<?php echo $this->option_name . '_sentiment' ?>" value="yes" <?php checked( $sentiment, 'yes' ); ?>>
                <?php _e( 'Yes', 'blobinator' ); ?>
            </label>
            <br>
            <label>
                <input type="radio" name="<?php echo $this->option_name . '_sentiment' ?>" value="no" <?php checked( $sentiment, 'no' ); ?>>
                <?php _e( 'No', 'blobinator' ); ?>
            </label>
        </fieldset>

        <?php
    }


    /**
     * Render the radio input field for emotion option
     *
     * @since  1.0.0
     */
    public function blobinator_emotion_cb() {

        $emotion = get_option( $this->option_name . '_emotion' );

        ?>

        <fieldset>
            <label>
                <input type="radio" name="<?php echo $this->option_name . '_emotion' ?>" id="<?php echo $this->option_name . '_emotion' ?>" value="yes" <?php checked( $emotion, 'yes' ); ?>>
                <?php _e( 'Yes', 'blobinator' ); ?>
            </label>
            <br>
            <label>
                <input type="radio" name="<?php echo $this->option_name . '_emotion' ?>" value="no" <?php checked( $emotion, 'no' ); ?>>
                <?php _e( 'No', 'blobinator' ); ?>
            </label>
        </fieldset>

        <?php
    }

    /**
     * Sanitize the text value before being saved to database
     * TODO: replace with wordpress sanitize option function
     *
     * @param  string $text $_POST value
     * @since  1.0.0
     * @return string           Sanitized value
     */
    public function blobinator_sanitize_option( $text ) {
        if ( in_array( $text, array( 'yes', 'no' ), true ) ) {
            return $text;
        }
    }

    /**
     * Sanitize the text value before being saved to database
     *
     * @param  string $text $_POST value
     * @since  1.3.2
     * @return string           Sanitized value
     */
    public function blobinator_sanitize_text( $text ) {

        return sanitize_text_field( $text );

    }


    /**
     * Add blobinator meta box to edit post page
     *
     * @param
     * @since  1.0.0
     * @return string           Sanitized value
     */
    public function add_blobinator_results_box( ) {

        add_meta_box('blobinator-results-box', 'Blobinator Content Advisor', array( $this, 'blobinator_create_results_div' ), 'page','normal','high',null);
        add_meta_box('blobinator-results-box', 'Blobinator Content Advisor', array( $this, 'blobinator_create_results_div' ), 'post','normal','high',null);

    }

}
