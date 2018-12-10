# Google Optimize Snippet

This WordPress Plugin helps integrate the necessary scripts etc to run a Google Optimize experiment on your WordPress website.  Requires a Google Analytics account and a Google Optimize account.

## Description

Creating a Google Optimize experiment can be somewhat complex.  Getting the required scripts embedded within the HEAD of your pages, including the so-called 'page-hiding snippet' that Google recommends, adds another layer of complexity.   Instead of manually editing your WP theme, this plugin will insert the scripts as one of the first elements in your HEAD, and lets you choose to either insert the scripts on specific pages or site-wide.

## Installation

1. Upload the 'google-optimize-snippet' folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the Google Optimize Settings page in the 'Settings' menu in WordPress and fill in the necessary fields of Google Analytics Property ID and Google Optimize Container ID (details of where to get those are on the Settings page). 
4. If running experiments on individual pages, go to the page in the WordPress admin, find the "Google Optimize Settings" box, and check that box on any page you are running an experiment on. 

## License

GPLv2 or later
