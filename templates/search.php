<?php
/**
 * Docs search template
 *
 * This template can be overridden by copying it to yourtheme/docupress/search.php.
 *
 * @author  BytesCrafter
 * @package docupress/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

docupress()->get_template_part( 'global/wrap-start' );

// phpcs:ignore
$keys = implode( '|', explode( ' ', get_search_query() ) );

?>
<header class="page-header" style="margin: 0;">
    <h1 class="page-title">
        <?php
        // translators: %s search query.
        printf( esc_html__( 'Documentation search: "%s"', 'docupress' ), esc_html( get_search_query() ) );
        ?>
    </h1>
</header><!-- .page-header -->

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

    <div class="entry-content">
        <div class="docupress-search">
            <ul class="docupress-search-list">
                <?php
                if ( have_posts() ) :
                    while ( have_posts() ) :
                        the_post();
                        // highlight search terms in title.
                        // phpcs:disable
                        $title   = wp_trim_words( get_the_title(), 10 );
                        $title   = preg_replace( '/(' . $keys . ')/iu', '<mark>\0</mark>', $title );
                        $excerpt = wp_trim_words( get_the_excerpt(), 10 );
                        $excerpt = preg_replace( '/(' . $keys . ')/iu', '<mark>\0</mark>', $excerpt );
                        // phpcs:enable
                        ?>
                        <li class="docupress-search-list-item">
                            <a href="<?php the_permalink(); ?>">
                                <span class="docupress-search-list-item-title">
                                    <strong><?php echo wp_kses( $title, array( 'mark' => array() ) ); ?></strong>
                                </span>
                                <span class="docupress-search-list-item-excerpt"><?php echo wp_kses( $excerpt, array( 'mark' => array() ) ); ?></span>
                            </a>
                        </li>
                        <?php
                    endwhile;
                endif;
                ?>
            </ul>
        </div>

        <?php
            wp_link_pages(
                array(
                    'before' => '<div class="page-links">' . __( 'Pages:', 'docupress' ),
                    'after'  => '</div>',
                )
            );
            ?>
    </div>
</article>

<?php

docupress()->get_template_part( 'global/wrap-end' );
