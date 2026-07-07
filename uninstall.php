<?php
/**
 * Pulizia eseguita alla disinstallazione del plugin (non alla semplice disattivazione).
 *
 * @package Mavida_Core
 */

// WordPress invoca questo file solo tramite la procedura di disinstallazione: bloccare l'accesso diretto.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mavida_core_options' );
