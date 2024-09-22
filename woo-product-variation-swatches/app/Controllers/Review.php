<?php

namespace Rtwpvs\Controllers;

class Review {
	public static function init() {
		$current      = time();

		$start         = strtotime( '19 November 2023' );
		$end           = strtotime( '05 January 2024' );
		// Black Friday Notice

		// $black_friday = mktime(0, 0, 0, 11, 22, 2021) <= $current && $current <= mktime(0, 0, 0, 12, 6, 2021);
		if ( ! ( $start <= $current && $current <= $end ) ) {
			register_activation_hook(RTWPVS_PLUGIN_FILE, [__CLASS__, 'rtvs_activation_time']);
			add_action('admin_init', [__CLASS__, 'rtvs_check_installation_time']);
			add_action('admin_init', [__CLASS__, 'rtvs_spare_me'], 5);
		}
	}

	// add plugin activation time
	public static function rtvs_activation_time() {
		$get_activation_time = strtotime('now');
		add_option('rtvs_plugin_activation_time', $get_activation_time); // replace         your_plugin with Your plugin name
	}

	//check if review notice should be shown or not
	public static function rtvs_check_installation_time() {
        
		// Added Lines Start
		$nobug = get_option('rtvs_spare_me', '0');

		if ( $nobug == '1' || $nobug == '3') {
			return; 
		}

		$install_date = get_option('rtvs_plugin_activation_time');
		$past_date    = strtotime('-10 days');

		$remind_time = get_option('rtvs_remind_me');
		$remind_due  = strtotime('+15 days', $remind_time);
		$now         = strtotime('now');
        
		if ( $now >= $remind_due) {
			add_action('admin_notices', [__CLASS__, 'rtvs_display_admin_notice']);
		} elseif ( ($past_date >= $install_date) && $nobug !== '2') {
			add_action('admin_notices', [__CLASS__, 'rtvs_display_admin_notice']);
		}
	}

	/**
	 * Display Admin Notice, asking for a review.
	 **/
	public static function rtvs_display_admin_notice() {

		if ( isset($GLOBALS['woobundle_release_notice']) ) {
			return;
		}

		if ( isset($GLOBALS['rtwpvs_notice']) ) {
			return;
		}


		// wordpress global variable
		global $pagenow;

		$exclude = ['themes.php', 'users.php', 'tools.php', 'options-general.php', 'options-writing.php', 'options-reading.php', 'options-discussion.php', 'options-media.php', 'options-permalink.php', 'options-privacy.php', 'edit-comments.php', 'upload.php', 'media-new.php', 'admin.php', 'import.php', 'export.php', 'site-health.php', 'export-personal-data.php', 'erase-personal-data.php'];

		if (! in_array($pagenow, $exclude)) {

            $args         = [ '_wpnonce' => wp_create_nonce( 'rtvs_wc_notice_nonce' ) ];

			$dont_disturb = esc_url(add_query_arg( $args + [ 'rtvs_spare_me' => '1' ] , self::rtvs_current_admin_url()));
			$remind_me    = esc_url(add_query_arg( $args + [ 'rtvs_remind_me' => '1' ] , self::rtvs_current_admin_url()));
			$rated        = esc_url(add_query_arg( $args + [ 'rtvs_rated' => '1' ] , self::rtvs_current_admin_url()));
			$reviewurl    = esc_url('https://wordpress.org/support/plugin/woo-product-variation-swatches/reviews/?filter=5#new-post');

			printf(__('<div class="notice rtvs-review-notice rtvs-review-notice--extended"> 
                <div class="rtvs-review-notice_content">
                    <h3>Enjoying Variation Swatches for WooCommerce?</h3>
                    <p>Thank you for choosing Variation Swatches for WooCommerce. If you have found our plugin useful and makes you smile, please consider giving us a 5-star rating on WordPress.org. It will help us to grow.</p>
                    <div class="rtvs-review-notice_actions">
                        <a href="%s" class="rtvs-review-button rtvs-review-button--cta" target="_blank"><span>⭐ Yes, You Deserve It!</span></a>
                        <a href="%s" class="rtvs-review-button rtvs-review-button--cta rtvs-review-button--outline"><span>😀 Already Rated!</span></a>
                        <a href="%s" class="rtvs-review-button rtvs-review-button--cta rtvs-review-button--outline"><span>🔔 Remind Me Later</span></a>
                        <a href="%s" class="rtvs-review-button rtvs-review-button--cta rtvs-review-button--error rtvs-review-button--outline"><span>😐 No Thanks</span></a>
                    </div>
                </div> 
            </div>'), $reviewurl, $rated, $remind_me, $dont_disturb);

			echo '<style> 
            .rtvs-review-button--cta {
                --e-button-context-color: #5d3dfd;
                --e-button-context-color-dark: #5d3dfd;
                --e-button-context-tint: rgb(75 47 157/4%);
                --e-focus-color: rgb(75 47 157/40%);
            } 
            .rtvs-review-notice {
                position: relative;
                margin: 5px 20px 5px 2px;
                border: 1px solid #ccd0d4;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0,0,0,0.15);
                font-family: Roboto, Arial, Helvetica, Verdana, sans-serif;
                border-inline-start-width: 4px;
            }
            .rtvs-review-notice.notice {
                padding: 0;
            }
            .rtvs-review-notice:before {
                position: absolute;
                top: -1px;
                bottom: -1px;
                left: -4px;
                display: block;
                width: 4px;
                background: -webkit-linear-gradient(bottom, #5d3dfd 0%, #6939c6 100%);
                background: linear-gradient(0deg, #5d3dfd 0%, #6939c6 100%);
                content: "";
            } 
            .rtvs-review-notice_content {
                padding: 20px;
            } 
            .rtvs-review-notice_actions > * + * {
                margin-inline-start: 8px;
                -webkit-margin-start: 8px;
                -moz-margin-start: 8px;
            } 
            .rtvs-review-notice p {
                margin: 0;
                padding: 0;
                line-height: 1.5;
            }
            p + .rtvs-review-notice_actions {
                margin-top: 1rem;
            }
            .rtvs-review-notice h3 {
                margin: 0;
                font-size: 1.0625rem;
                line-height: 1.2;
            }
            .rtvs-review-notice h3 + p {
                margin-top: 8px;
            } 
            .rtvs-review-button {
                display: inline-block;
                padding: 0.4375rem 0.75rem;
                border: 0;
                border-radius: 3px;;
                background: var(--e-button-context-color);
                color: #fff;
                vertical-align: middle;
                text-align: center;
                text-decoration: none;
                white-space: nowrap; 
            }
            .rtvs-review-button:active {
                background: var(--e-button-context-color-dark);
                color: #fff;
                text-decoration: none;
            }
            .rtvs-review-button:focus {
                outline: 0;
                background: var(--e-button-context-color-dark);
                box-shadow: 0 0 0 2px var(--e-focus-color);
                color: #fff;
                text-decoration: none;
            }
            .rtvs-review-button:hover {
                background: var(--e-button-context-color-dark);
                color: #fff;
                text-decoration: none;
            } 
            .rtvs-review-button.focus {
                outline: 0;
                box-shadow: 0 0 0 2px var(--e-focus-color);
            } 
            .rtvs-review-button--error {
                --e-button-context-color: #d72b3f;
                --e-button-context-color-dark: #ae2131;
                --e-button-context-tint: rgba(215,43,63,0.04);
                --e-focus-color: rgba(215,43,63,0.4);
            }
            .rtvs-review-button.rtvs-review-button--outline {
                border: 1px solid;
                background: 0 0;
                color: var(--e-button-context-color);
            }
            .rtvs-review-button.rtvs-review-button--outline:focus {
                background: var(--e-button-context-tint);
                color: var(--e-button-context-color-dark);
            }
            .rtvs-review-button.rtvs-review-button--outline:hover {
                background: var(--e-button-context-tint);
                color: var(--e-button-context-color-dark);
            } 
            </style>';
		}
	}

	// remove the notice for the user if review already done or if the user does not want to
	public static function rtvs_spare_me() {

        if ( ! isset( $_REQUEST['_wpnonce'] ) || !wp_verify_nonce( $_REQUEST['_wpnonce'], 'rtvs_wc_notice_nonce' ) ) {
			return;
		}

		if (isset($_GET['rtvs_spare_me']) && ! empty($_GET['rtvs_spare_me'])) {
			$spare_me = $_GET['rtvs_spare_me'];
			if (1 == $spare_me) {
				update_option('rtvs_spare_me', '1');
			}
		}

		if (isset($_GET['rtvs_remind_me']) && ! empty($_GET['rtvs_remind_me'])) {
			$remind_me = $_GET['rtvs_remind_me'];
			if (1 == $remind_me) {
				$get_activation_time = strtotime('now');
				update_option('rtvs_remind_me', $get_activation_time);
				update_option('rtvs_spare_me', '2');
			}
		}

		if (isset($_GET['rtvs_rated']) && ! empty($_GET['rtvs_rated'])) {
			$rtvs_rated = $_GET['rtvs_rated'];
			if (1 == $rtvs_rated) {
				update_option('rtvs_rated', 'yes');
				update_option('rtvs_spare_me', '3');
			}
		}
	}

	protected static function rtvs_current_admin_url() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$uri = preg_replace( '|^.*/wp-admin/|i', '', $uri );
		if (! $uri) {
			return '';
		}
        return remove_query_arg(
            [   
                '_wpnonce', 
                '_wc_notice_nonce', 
                'wc_db_update', 
                'wc_db_update_nonce', 
                'wc-hide-notice',
                'rtvs_spare_me', 
                'rtvs_remind_me', 
                'rtvs_rated'
            ], 
            admin_url($uri)
        );
	}


}
