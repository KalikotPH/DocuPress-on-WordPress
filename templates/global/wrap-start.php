<?php
/**
 * Docs wrap start template
 *
 * This template can be overridden by copying it to yourtheme/docupress/global/wrap-start.php.
 *
 * @author  BytesCrafter
 * @package docupress/Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function docupress_headstart_check() {
    return true;
}

get_header( 'docs' );

// phpcs:disable
$theme_class = '';

// additional class for default theme to add fix styles.
$current_theme = get_template();
if ( in_array( $current_theme, array( 'twentyseventeen', 'twentysixteen', 'twentyfifteen' ), true ) ) {
    $theme_class = ' docupress_theme_' . $current_theme;
}
// phpcs:enable

?>

    <div id="primary" class="content-area<?php echo esc_attr( $theme_class ); ?>">
        <main id="main" class="site-main" role="main">
