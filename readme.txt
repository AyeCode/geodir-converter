=== GeoDirectory Directory Converter ===
Contributors: stiofansisland, paoltaia, ayecode
Donate link: https://wpgeodirectory.com
Tags: convert, converter, connections, directorist, directories pro, directory, directory converter, geodirectory, hivepress, listify, listingpro, phpmydirectory, ulisting, vantage, edirectory
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.2.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 
Convert directories like phpMyDirectory, Listify, Business Directory Plugin, Vantage directory theme, eDirectory, Directorist, ListingPro, Directories Pro, uListing, HivePress, Connections, aDirectory, Houzez, Classified Listing, WP Store Locator, and CSV files to GeoDirectory.

== Description ==

= Convert your current directory to GeoDirectory! =

Our Directory Converter plugin takes the hard work out of converting from a different directory script to GeoDirectory.

Currently this product is in beta which means it should not be used on a live site, only a staging site.

Currently supported directories:
- phpMyDirectory - https://wordpress.org/plugins/phpmydirectory/
- Listify 3.0 or greater, WP Job Manager, and themes built on it - https://wordpress.org/plugins/wp-job-manager/
- Business Directory Plugin 6.3 or greater - https://wordpress.org/plugins/business-directory-plugin/
- Vantage 4.2 or greater - https://www.appthemes.com/themes/vantage/
- eDirectory - https://www.edirectory.com/
- Directorist 7.9.0 or greater - https://directorist.com/
- ListingPro 2.9.0 or greater - https://listingprowp.com/
- Directories Pro - https://directoriespro.com/
- uListing - https://wordpress.org/plugins/ulisting/
- HivePress - https://wordpress.org/plugins/hivepress/
- Connections 10.8 or greater - https://wordpress.org/plugins/connections/
- aDirectory - https://wordpress.org/plugins/adirectory/
- Houzez 4.x - https://themeforest.net/item/houzez-real-estate-wordpress-theme-/15752549
- Classified Listing - https://wordpress.org/plugins/classified-listing/
- WP Store Locator - https://wordpress.org/plugins/wp-store-locator/
- CSV files

== Changelog ==

= 2.2.2 - TBD =
* Added support for importing listings from Houzez theme - ADDED
* Added support for importing listings from Classified Listing plugin - ADDED
* Added support for importing stores from WP Store Locator plugin - ADDED
* Added support for importing listings from any WP Job Manager based site - ADDED
* Added support for importing Directorist reviews and favourites - ADDED
* WP Job Manager job types and expired, pending and private jobs were not imported - FIXED
* Retry Failed dropped items or re-imported every listing - FIXED
* phpMyDirectory custom field values were not imported - FIXED
* Directorist gallery images and social links were not imported - FIXED
* Directorist converter shows error when Directorist is not active - FIXED

= 2.2.1 - 2026-08-13 =
* Import no longer stalls silently when a record crashes the PHP worker - FIXED
* Imports now prefer GD over Imagick, which can crash on malformed images - CHANGED
* Interrupted batches resume from a checkpoint instead of replaying from the start - FIXED
* Progress, logs and failed items are saved during a batch, not only at the end - CHANGED
* Listings query was missing an ORDER BY, which could skip or duplicate records - FIXED
* Stats were counted repeatedly across batches in paginated imports - FIXED
* Progress reported 100% for an import that stopped early - FIXED
* Updated listings were logged and counted as skipped - FIXED
* Import log is capped at 1000 entries to keep large imports fast - CHANGED
* Business Directory converter shows error when Pricing Manager is not active - FIXED

= 2.2.0 - 2026-04-09 =
* Added support for importing listings from WP Residence theme - ADDED
* Added support for importing listings from MyListing theme - ADDED
* Added support for importing listings from aDirectory plugin - ADDED

= 2.1.5 - 2026-02-19 =
* Added CSV file importer with field mapping support - ADDED
* Added support for importing listings from Connections directory plugin - ADDED
* Added support for importing listings from Directories Pro plugin - ADDED
* Added support for importing listings from uListing plugin - ADDED
* Added support for importing listings from HivePress plugin - ADDED

= 2.1.4 - 2025-10-16 =
* Added support for importing listings from ListingPro - ADDED
* Improved logs UI to pause auto-scrolling when user is viewing older entries - CHANGED
* Auto-filled Listify addresses from coordinates via OpenStreetMap - FIXED

= 2.1.3 - 2025-07-10 =
* Added support for importing listings from Directorist plugin - ADDED

= 2.1.2 - 2025-06-26 =
* Changes for import eDirectory blogs - CHANGED

= 2.1.1 - 2025-05-29 =
* Added support for importing listings from eDirectory Software - ADDED
* Added support for importing listings from Vantage Directory Theme - ADDED

= 2.1.0 - 2025-04-03 =
* Revamped the user interface for better usability - ENHANCEMENT
* Added support for importing listings from Business Directory - ADDED

= 2.0.1 - 2022-08-09 =
* Sometimes import gallery images not working for Listify - FIXED

= 2.0.0 - 2020-10-07 =
* Listify + WPJM integration
* PMD version 1 throws errors when importing packages - FIXED
* Issue in converting PMD custom fields with special characters - FIXED

= 1.0.1 =
* Plugin asks for license key which is not required - FIXED

= 1.0.0-beta =
* Initial beta release

== Upgrade Notice ==