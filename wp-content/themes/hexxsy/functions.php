<?php
/**
 * Hexxsy theme setup.
 *
 * Colors, fonts and sizes live in theme.json. This file loads the house
 * styles, names them in the editor, and adds pattern categories so the
 * admin's own patterns sort beside the theme's.
 *
 * @package Hexxsy
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load style.css on the site and in the editor.
 */
function hexxsy_styles(): void {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'hexxsy', get_stylesheet_uri(), array(), $version );
}
add_action( 'wp_enqueue_scripts', 'hexxsy_styles' );

/**
 * Theme supports.
 */
function hexxsy_setup(): void {
	add_editor_style( 'style.css' );
	add_theme_support( 'woocommerce' );
	// Patterns from the WordPress.org directory are off-brand; the admin's own and the theme's stay.
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', 'hexxsy_setup' );

/**
 * Block styles the editor offers by name. The CSS is in style.css.
 */
function hexxsy_block_styles(): void {
	$styles = array(
		'core/separator' => array(
			'double-rule' => __( 'Double rule', 'hexxsy' ),
			'thin-rule'   => __( 'Thin rule', 'hexxsy' ),
		),
		'core/group'     => array(
			'notice' => __( 'Notice', 'hexxsy' ),
			'card'   => __( 'Card', 'hexxsy' ),
			'band'   => __( 'Band', 'hexxsy' ),
		),
		'core/paragraph' => array(
			'hexx'    => __( 'Hexx speaks', 'hexxsy' ),
			'label'   => __( 'Small caps label', 'hexxsy' ),
			'fleuron' => __( 'Fleuron heading', 'hexxsy' ),
		),
	);

	foreach ( $styles as $block => $names ) {
		foreach ( $names as $name => $label ) {
			register_block_style( $block, array( 'name' => $name, 'label' => $label ) );
		}
	}
}
add_action( 'init', 'hexxsy_block_styles' );

/**
 * Pattern categories for the theme's patterns and the admin's own.
 */
function hexxsy_pattern_categories(): void {
	$categories = array(
		'hexxsy-sections' => __( 'Hexxsy: Sections', 'hexxsy' ),
		'hexxsy-hexx'     => __( "Hexxsy: Hexx's lines", 'hexxsy' ),
		'hexxsy-notices'  => __( 'Hexxsy: Notices', 'hexxsy' ),
		'hexxsy-rooms'    => __( 'Hexxsy: Rooms and offerings', 'hexxsy' ),
	);

	foreach ( $categories as $slug => $label ) {
		register_block_pattern_category( $slug, array( 'label' => $label ) );
	}
}
add_action( 'init', 'hexxsy_pattern_categories' );
