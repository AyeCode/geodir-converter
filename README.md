# geodir-converter
A plugin to convert other directories to GD

## Usage details

- Make sure you have installed and activated:-
    * GeoDirectory
    * Locations Manager Addon - Otherwise all listings will be imported into the default location
    * Payment Manager Addon - To import products
    * Events Addon - To import events
    * Invoicing - To import invoices and discount codes
- The addon can only run on a fresh install of WordPress as most data will be overidden, including user accounts.

## Notes

- WordPress and PMD use different password hashing algorithyms. The addon will try to automagically take care of this, but in rare cases where it can't, the user will have to reset their password.