<?php
defined( 'ABSPATH' ) or	die( 'Cheatin&#8217; uh?' );

// Launch hooks that deletes all the cache domain
add_action( 'switch_theme'				,     'rocket_clean_domain' );	// When user change theme
add_action( 'user_register'				,     'rocket_clean_domain' );	// When a user is added
add_action( 'profile_update'			,     'rocket_clean_domain' );	// When a user is updated
add_action( 'deleted_user'				,     'rocket_clean_domain' );	// When a user is deleted
add_action( 'wp_update_nav_menu'		,     'rocket_clean_domain' );	// When a custom menu is update
add_action( 'update_option_sidebars_widgets', 'rocket_clean_domain' );	// When you change the order of widgets
add_action( 'update_option_category_base',    'rocket_clean_domain' );	// When category permalink prefix is update
add_action( 'update_option_tag_base'	,     'rocket_clean_domain' ); 	// When tag permalink prefix is update
add_action( 'permalink_structure_changed',    'rocket_clean_domain' ); 	// When permalink structure is update
add_action( 'create_term'				,     'rocket_clean_domain' ); 	// When a term is created
add_action( 'edited_terms'				,     'rocket_clean_domain' );	// When a term is updated
add_action( 'delete_term'				,     'rocket_clean_domain' );	// When a term is deleted
add_action( 'add_link'					,     'rocket_clean_domain' );	// When a link is added
add_action( 'edit_link'					,     'rocket_clean_domain' );	// When a link is updated
add_action( 'delete_link'				,     'rocket_clean_domain' );	// When a link is deleted
add_action( 'customize_save'			,     'rocket_clean_domain' );	// When customizer is saved
add_action( 'avada_clear_dynamic_css_cache',  'rocket_clean_domain' );	// When Avada theme purge its own cache
add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), 'rocket_clean_domain' ); // When location of a menu is updated

/* @since 2.3.5 */
// When SuperCacher (SiteGround) is purged
add_action( 'wp_ajax_sg-cachepress-purge',    'rocket_clean_domain', 0 );
add_action( 'admin_post_sg-cachepress-purge', 'rocket_clean_domain', 0 );

/* @since 1.1.1 */
add_filter( 'widget_update_callback'	, 'rocket_widget_update_callback' ); // When a widget is updated
function rocket_widget_update_callback( $instance ) { 
	rocket_clean_domain(); 
	return $instance; 
}

/* @since 1.3.3
 * For not conflit with WooCommerce when clean_post_cache is called
*/
add_filter( 'delete_transient_wc_products_onsale', 'wp_suspend_cache_invalidation' );

/* @since 2.3
 * For not conflit with SuperCacher (SiteGround) Pretty good hosting!
*/
add_action( 'after_rocket_clean_domain', 'rocket_clean_supercacher' );

/* @since 2.5.5
 * For not conflit with StudioPress Accelerator
*/
add_action( 'after_rocket_clean_domain', 'rocket_clean_studiopress_accelerator' );

/* @since 2.5.5
 * For not conflit with Varnish HTTP Purge
*/
add_action( 'after_rocket_clean_domain', 'rocket_clean_varnish_http_purge' );

/* @since 2.5.7
 * For not conflit with Pagely Hosting
*/
add_action( 'after_rocket_clean_domain', 'rocket_clean_pagely' );

/* @since 2.5.11
 * For not conflit with Pressidium Hosting
*/
add_action( 'after_rocket_clean_domain', 'rocket_clean_pressidium' );

/**
 * Update cache when a post is updated or commented
 *
 * @since 2.8   Only add post type archive if post type is not post
 * @since 2.6 	Purge the page defined in "Posts page" option
 * @since 2.5.5 Don't cache for auto-draft post status
 * @since 1.3.2 Add wp_update_comment_count to purge cache when a comment is added/updated/deleted
 * @since 1.3.0 Compatibility with WPML
 * @since 1.3.0 Add 2 hooks : before_rocket_clean_post, after_rocket_clean_post
 * @since 1.3.0 Purge all parents of the post and the author page
 * @since 1.2.2 Add wp_trash_post and delete_post to purge cache when a post is trashed or deleted
 * @since 1.1.3 Use clean_post_cache instead of transition_post_status, transition_comment_status and preprocess_comment
 * @since 1.0
 *
 */
add_action( 'wp_trash_post'				, 'rocket_clean_post' );
add_action( 'delete_post'				, 'rocket_clean_post' );
add_action( 'clean_post_cache'			, 'rocket_clean_post' );
add_action( 'wp_update_comment_count'	, 'rocket_clean_post' );
function rocket_clean_post( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) ) {
		return;
	}

	$purge_urls = array();

	// Get all post infos
	$post = get_post( $post_id );
	
	// No purge for specifics conditions
	if ( ! is_object( $post ) || $post->post_status == 'auto-draft' || empty( $post->post_type ) || $post->post_type == 'nav_menu_item' ) {
		return;
	}
	
	// Get the post language
	$lang = false;

	// WPML
	if ( rocket_is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) && ! rocket_is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
		$lang = $GLOBALS['sitepress']->get_language_for_element( $post_id, 'post_' . get_post_type( $post_id ) );

	// Polylang
	} else if ( rocket_is_plugin_active( 'polylang/polylang.php' ) ) {
		$lang = pll_get_post_language( $post_id );
	}
	
	// Get the permalink structure
    $permalink_structure = get_rocket_sample_permalink( $post_id );

    // Get permalink
    $permalink = str_replace( array( '%postname%', '%pagename%' ), $permalink_structure[1], $permalink_structure[0] );

	// Add permalink
	if( parse_url( $permalink, PHP_URL_PATH ) != '/' ) {
		array_push( $purge_urls, $permalink );	
	}
	
	// Add Posts page
	if( 'post' == $post->post_type && (int) get_option( 'page_for_posts' ) > 0 ) {
		array_push( $purge_urls, get_permalink( get_option( 'page_for_posts' ) ) );
	}
	
	// Add Post Type archive
	if ( 'post' !== $post->post_type ) {
	    if ( $post_type_archive = get_post_type_archive_link( get_post_type( $post_id ) ) ) {
	    	array_push( $purge_urls, $post_type_archive );
	    }
    }

	// Add next post
	$next_post = get_adjacent_post( false, '', false );
	if ( $next_post ) {
		array_push( $purge_urls, get_permalink( $next_post ) );
	}

	// Add next post in same category
	$next_in_same_cat_post = get_adjacent_post( true, '', false );
	if ( $next_in_same_cat_post && $next_in_same_cat_post != $next_post ) {
		array_push( $purge_urls, get_permalink( $next_in_same_cat_post ) );
	}

	// Add previous post
	$previous_post = get_adjacent_post( false, '', true );
	if ( $previous_post ) {
		array_push( $purge_urls, get_permalink( $previous_post ) );
	}

	// Add previous post in same category
	$previous_in_same_cat_post = get_adjacent_post( true, '', true );
	if ( $previous_in_same_cat_post && $previous_in_same_cat_post != $previous_post ) {
		array_push( $purge_urls, get_permalink( $previous_in_same_cat_post ) );
	}

	// Add urls page to purge every time a post is save
	$cache_purge_pages = get_rocket_option( 'cache_purge_pages' );
	if ( $cache_purge_pages ) {
		foreach( $cache_purge_pages as $page ) {
			array_push( $purge_urls, home_url( $page ) );
		}
	}

	// Add all terms archive page to purge
	$purge_terms = get_rocket_post_terms_urls( $post_id );
	if ( count($purge_terms) ) {
		$purge_urls = array_merge( $purge_urls, $purge_terms );
	}

	// Add all dates archive page to purge
	$purge_dates = get_rocket_post_dates_urls( $post_id );
	if ( count($purge_dates) ) {
		$purge_urls = array_merge( $purge_urls, $purge_dates );
	}

	// Add the author page
	$purge_author = array( get_author_posts_url( $post->post_author ) );
	$purge_urls = array_merge( $purge_urls, $purge_author );
	
	/**
	 * Fires before cache files related with the post are deleted
	 *
	 * @since 1.3.0
	 * @param obj 	 $post 		 The post object
	 * @param array  $purge_urls URLs cache files to remove
	 * @param string $lang 		 The post language
	*/
	do_action( 'before_rocket_clean_post', $post, $purge_urls, $lang );

	/**
	 * Filter URLs cache files to remove
	 *
	 * @since 1.0
	 * @param array $purge_urls List of URLs cache files to remove
	*/
	$purge_urls = apply_filters( 'rocket_post_purge_urls', $purge_urls, $post );
	
	// Purge all files
	rocket_clean_files( $purge_urls );
	
	// Never forget to purge homepage and their pagination
	rocket_clean_home( $lang );

    // Purge home feeds (blog & comments)
    rocket_clean_home_feeds();

	// Purge all parents
	$parents = get_post_ancestors( $post_id );
	if ( count( $parents ) ) {
		foreach( $parents as $parent_id ) {
			rocket_clean_post( $parent_id );
		}
	}

	/**
	 * Fires after cache files related with the post are deleted
	 *
	 * @since 1.3.0
	 * @param obj 	 $post 		 The post object
	 * @param array  $purge_urls URLs cache files to remove
	 * @param string $lang 		 The post language
	*/
	do_action( 'after_rocket_clean_post', $post, $purge_urls, $lang );
}

/**
 * Add pattern to clean files of connected users
 *
 * @since 2.0
 */
add_filter( 'rocket_clean_files', 'rocket_clean_files_users' );
function rocket_clean_files_users( $urls ) {
	$pattern_urls = array();
	foreach( $urls as $url ) {
		list( $host, $path, $scheme ) = get_rocket_parse_url( $url );
		$pattern_urls[] = $scheme . '://' . $host . '*' . $path;
	}
	return $pattern_urls;
}

/**
 * Return all translated version of a post when qTranslate is used.
 * Use the "rocket_post_purge_urls" filter to insert URLs of traduction post
 *
 * @since 1.3.5
 */
add_filter( 'rocket_post_purge_urls', 'rocket_post_purge_urls_for_qtranslate' );
function rocket_post_purge_urls_for_qtranslate( $urls ) {
	if ( rocket_is_plugin_active( 'qtranslate/qtranslate.php' ) || rocket_is_plugin_active( 'qtranslate-x/qtranslate.php' ) ) {

		global $q_config;

		// Get all languages
		$enabled_languages = $q_config['enabled_languages'];

		// Remove default language
		$enabled_languages = array_diff( $enabled_languages, array( $q_config['default_language'] ) );

		// Add translate URLs
		foreach( $urls as $url ) {
			foreach( $enabled_languages as $lang ) {
    			if ( rocket_is_plugin_active( 'qtranslate/qtranslate.php' ) ) {
				    $urls[] = qtrans_convertURL( $url, $lang, true );
				} elseif ( rocket_is_plugin_active( 'qtranslate-x/qtranslate.php' ) ) {
    				$urls[] = qtranxf_convertURL( $url, $lang, true );
				}
			}
		}
	}

	return $urls;
}

/**
 * Actions to be done after the purge cache files of a post
 * By Default, this hook call the WP Rocket Bot (cache json)
 *
 * @since 1.3.0
 */
add_action( 'after_rocket_clean_post', 'run_rocket_bot_after_clean_post', 10, 3 );
function run_rocket_bot_after_clean_post( $post, $purge_urls, $lang ) {
	// Run robot only if post is published
	if ( 'publish' != $post->post_status ) {
		return false;
	}

	// Add Homepage URL to $purge_urls for bot crawl
	array_push( $purge_urls, get_rocket_i18n_home_url( $lang ) );
	
	// Add default WordPress feeds (posts & comments)
	array_push( $purge_urls, get_feed_link() );
	array_push( $purge_urls, get_feed_link( 'comments_' ) );
	
	// Get the author page
	$purge_author = array( get_author_posts_url( $post->post_author ) );

	// Get all dates archive page
	$purge_dates = get_rocket_post_dates_urls( $post->ID );

	// Remove dates archives page and author page to preload cache
	$purge_urls = array_diff( $purge_urls, $purge_dates, $purge_author );

	// Create json file and run WP Rocket Bot
	$json_encode_urls = '["' . implode( '","', array_filter( $purge_urls ) ) . '"]';
	if ( rocket_put_content( WP_ROCKET_PATH . 'cache.json', $json_encode_urls ) ) {
		global $do_rocket_bot_cache_json;
		$do_rocket_bot_cache_json = true;
	}
}

/**
 * Actions to be done after the purge cache files of a term
 * By Default, this hook call the WP Rocket Bot (cache json)
 *
 * @since 2.6.8
 */
add_action( 'after_rocket_clean_term', 'run_rocket_bot_after_clean_term', 10, 3 );
function run_rocket_bot_after_clean_term( $post, $purge_urls, $lang ) {
	// Add Homepage URL to $purge_urls for bot crawl
	array_push( $purge_urls, get_rocket_i18n_home_url( $lang ) );

	// Create json file and run WP Rocket Bot
	$json_encode_urls = '["' . implode( '","', array_filter( $purge_urls ) ) . '"]';
	if ( rocket_put_content( WP_ROCKET_PATH . 'cache.json', $json_encode_urls ) ) {
		global $do_rocket_bot_cache_json;
		$do_rocket_bot_cache_json = true;
	}
}

/**
 * Run WP Rocket Bot when a post is added, updated or deleted
 *
 * @since 1.3.2
 */
add_action( 'shutdown', 'do_rocket_bot_cache_json' );
function do_rocket_bot_cache_json() {
	global $do_rocket_bot_cache_json;
	if ( $do_rocket_bot_cache_json ) {
    	run_rocket_preload_cache( 'cache-json', false );
	}
}

/**
 * Purge Cache file System in Admin Bar
 *
 * @since 1.3.0 Compatibility with WPML
 * @since 1.0
 */
add_action( 'admin_post_purge_cache', '__rocket_purge_cache' );
function __rocket_purge_cache() {
	if ( isset( $_GET['type'], $_GET['_wpnonce'] ) ) {

		$_type     = explode( '-', $_GET['type'] );
		$_type     = reset( $_type );
		$_id       = explode( '-', $_GET['type'] );
		$_id       = end( $_id );
		$_taxonomy = isset( $_GET['taxonomy'] ) ? $_GET['taxonomy'] : false;

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'purge_cache_' . $_GET['type'] ) ) {
			wp_nonce_ays( '' );
		}

		switch( $_type ) {

			// Clear all cache domain
			case 'all':

				// Remove all cache files
				$lang = isset( $_GET['lang'] ) && $_GET['lang'] != 'all' ? sanitize_key( $_GET['lang'] ) : '';
				// Remove all cache files
				rocket_clean_domain( $lang );

				// Remove all minify cache files
				rocket_clean_minify();

				// Generate a new random key for minify cache file
				$options = get_option( WP_ROCKET_SLUG );
				$options['minify_css_key'] = create_rocket_uniqid();
				$options['minify_js_key'] = create_rocket_uniqid();
				remove_all_filters( 'update_option_' . WP_ROCKET_SLUG );
				update_option( WP_ROCKET_SLUG, $options );

				rocket_dismiss_box( 'rocket_warning_plugin_modification' );
				break;

			// Clear terms, homepage and other files associated at current post in back-end
			case 'post':
				rocket_clean_post( $_id );
				break;
			
			// Clear a specific term
			case 'term':
				rocket_clean_term( $_id, $_taxonomy );
				break;
				
			// Clear a specific user
			case 'user':
				rocket_clean_user( $_id );
				break;
			
			// Clear cache file of the current page in front-end
			case 'url':
			    $referer = wp_get_referer();

			    if ( 0 !== strpos( $referer, 'http' ) ) {
    			    list( $host, $path, $scheme, $query ) = get_rocket_parse_url( untrailingslashit( home_url() ) );
                    $referer = $scheme . '://' . $host . $referer;
                }
			    
				rocket_clean_files( $referer );
				break;

			default:
				wp_nonce_ays( '' );
				break;
		}

		wp_redirect( wp_get_referer() );
		die();
	}
}

/**
 * Purge OPCache content in Admin Bar
 *
 * @since 2.7
 */
add_action( 'admin_post_rocket_purge_opcache', '__do_admin_post_rocket_purge_opcache' );
function __do_admin_post_rocket_purge_opcache() {
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'rocket_purge_opcache' ) ) {
        wp_nonce_ays( '' );
    }

    if ( function_exists( 'opcache_reset' ) ) {
        opcache_reset();
    }

    wp_redirect( wp_get_referer() );
    die();
}

/**
 * Preload cache system in Admin Bar
 * It launch the WP Rocket Bot
 *
 * @since 1.3.0 Compatibility with WPML
 * @since 1.0 (delete in 1.1.6 and re-add in 1.1.9)
 */
add_action( 'admin_post_preload',        '__rocket_preload_cache' );
add_action( 'admin_post_nopriv_preload', '__rocket_preload_cache' );
/* if you want to programmatically preload the cache, use run_rocket_bot() instead */
function __rocket_preload_cache() {
    if ( isset( $_GET['_wpnonce'] ) ) {

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'preload' ) ) {
			wp_nonce_ays( '' );
        }

		$lang = isset( $_GET['lang'] ) && $_GET['lang'] != 'all' ? sanitize_key( $_GET['lang'] ) : '';
		run_rocket_bot( 'cache-preload', $lang );

        if ( get_rocket_option( 'sitemap_preload' ) ) {
            run_rocket_sitemap_preload();
        }

        wp_redirect( wp_get_referer() );
        die();
    }
}

/**
 * Purge CloudFlare cache
 *
 * @since 2.5
 */
add_action( 'admin_post_rocket_purge_cloudflare', '__admin_post_rocket_purge_cloudflare' );
function __admin_post_rocket_purge_cloudflare() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'rocket_purge_cloudflare' ) ) {
		wp_nonce_ays( '' );
	}

	// Purge CloudFlare
	rocket_purge_cloudflare();

	wp_redirect( wp_get_referer() );
	die();
}

/**
 * Sitemap preload with async request
 *
 * @since 2.8
 * @author Remy Perona
 **/
add_action( 'wp_ajax_rocket_preload_sitemap', '_do_admin_post_rocket_preload_sitemap' );
function _do_admin_post_rocket_preload_sitemap() {
	if ( isset( $_POST['_ajax_nonce'], $_POST['sitemap_url'], $_POST['sitemap_id'] )
		&& check_ajax_referer( 'preload_sitemap-' . $_POST['sitemap_id'] )
	) {		
		rocket_process_sitemap( $_POST['sitemap_url'] );

		die( 1 );
	}
}