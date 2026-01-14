/**
 * jQuery Compatibility Layer
 * 
 * WordPress runs jQuery in noConflict mode, so $ is not defined globally.
 * This file must load FIRST (hence the 00- prefix for alphabetical sorting).
 * It creates a global $ alias for jQuery so legacy code works.
 */
var $ = jQuery;

