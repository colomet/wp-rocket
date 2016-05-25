<?php
if ( defined( 'AIOSEOP_VERSION' ) ) :
    $all_in_one_seo_xml_options = get_option( 'aioseop_options' );
	/**
	 * Improvement with All in One SEO Pack: auto-detect the XML sitemaps for the preload option
	 *
	 * @since 2.8
	 * @author Remy Perona
	 */
    if ( 'on' === $all_in_one_seo_xml_options['modules']['aiosp_feature_manager_options']['aiosp_feature_manager_enable_sitemap'] ) {
        add_filter( 'rocket_first_install_options', '__rocket_add_all_in_one_seo_sitemap_option' );
        function __rocket_add_all_in_one_seo_sitemap_option( $options ) {
            $options['all_in_one_seo_xml_sitemap'] = 0;

            return $options;
        }

        add_filter( 'rocket_inputs_sanitize', '__rocket_all_in_one_seo_sitemap_option_sanitize' );
        function __rocket_all_in_one_seo_sitemap_option_sanitize( $inputs ) {
            $inputs['all_in_one_seo_xml_sitemap'] = ! empty( $inputs['all_in_one_seo_xml_sitemap'] ) ? 1 : 0;

            return $inputs;
        }

        add_filter( 'rocket_sitemap_preload_list', '__rocket_add_all_in_one_seo_sitemap' );
        function __rocket_add_all_in_one_seo_sitemap( $sitemaps ) {
            if ( get_rocket_option( 'all_in_one_seo_xml_sitemap', false ) ) {
                $all_in_one_seo_xml = get_option( 'aioseop_options' );
                $sitemaps[] = trailingslashit( home_url() ) . $all_in_one_seo_xml['modules']['aiosp_sitemap_options']['aiosp_sitemap_filename'] . '.xml';
            }

            return $sitemaps;
        }

        add_filter( 'rocket_sitemap_preload_options', '__rocket_sitemap_preload_all_in_one_seo_option' );
        function __rocket_sitemap_preload_all_in_one_seo_option( $options ) {
            $options[] = array(
                'parent'        => 'sitemap_preload',
                 'type'         => 'checkbox',
                 'label'        => __('All in One SEO XML sitemap', 'rocket' ),
                 'label_for'    => 'all_in_one_seo_xml_sitemap',
                 'label_screen' => sprintf( __( 'Preload the sitemap from the %s plugin', 'rocket' ), 'All in One SEO Pack' ),
                 'default'      => 0,
             );
             $options[] = array(
                 'parent'       => 'sitemap_preload',
                 'type'			=> 'helper_description',
                 'name'			=> 'all_in_one_seo_xml_sitemap_desc',
                 'description'  => sprintf( __( 'We automatically detected the sitemap generated by the %s plugin. You can check the option to preload it.', 'rocket' ), 'All in One SEO Pack' )
             );
            return $options;
        }
    }
endif;