# geodir-converter
A plugin to convert other directories to GD

## Usage details

- Make sure you have installed and activated:-
    * GeoDirectory
    * GeoDirectory Location Manager - Otherwise all listings will be imported into the default location
    * GeoDirectory Pricing Manager - To import products/packages
    * GeoDirectory Events - To import events
    * Invoicing - To import invoices and discount codes
- The addon can only run on a fresh install of WordPress as most data will be overridden, including user accounts.

## Notes

- WordPress and PMD use different password hashing algorithms. The addon will try to automatically take care of this, but in rare cases where it can't, the user will have to reset their password.