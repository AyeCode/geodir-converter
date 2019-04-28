# geodir-converter
A plugin to convert other directories to GD


### PMD Migration

In order to migrate data from PMD to WordPress. 
We have to import few tables from PMD into WordPress database.  
and then use the related command to import and then delete those extra table respectively.


### Import Category Data 

Step 1: Import these two tables in WordPress database
- pmd_listings_categories	
- pmd_categories

WP-CLI Command:  wp convert category --removetable


### Import Listing Data 
Step 1: Import following table in WordPress database
- 	pmd_listings	

WP-CLI Command:  wp convert listing --removetable 



### Import User Data 
Step 1: Import following table in WordPress database
- 	pmd_users	

WP-CLI Command:  wp convert users --removetable 

### Import Invoice Data 

Step 1: Import following table in WordPress database
-   pmd_invoices	

WP-CLI Command:  wp convert invoice --removetable 

