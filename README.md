# Cotizatii USR S2
A single page form and PHP script that checks the monthly contributions situations against a Google Sheet.


## Installation (shared hosting):

1. Upload `index.php` and `logo-usr16-flag_white.png` to a folder on your shared hosting account
2. Following the instructions @ https://www.youtube.com/watch?v=iTZyuszEkxI&t=14s generate a `credentials.json` file for the Google account owning the spreadsheet, upload `credentials.json` to the same folder
3. Grab the spreadsheet ID - as shown @ https://youtu.be/iTZyuszEkxI?t=165 - and use it as the value of the `$spreadsheetId` variable in `index.php` on line 77
4. From Google Drive/Sheets share the spreadsheet with the email found in `credentials.json` as shown @ https://youtu.be/iTZyuszEkxI?t=175
5. Download the vendor folder from https://cotizatii-usr-vendors-archive.s3.amazonaws.com/vendor.zip and upload it - the fodler - in the same folder as `index.php`

In the end, your folder structure should look like this:

```
folder/
  ├── vendor/
  ├── credentials.json
  ├── index.php
  ├── logo-usr16-flag_white.png
```


## Installation (VPS/cloud server)

1. Follow the 1st 4 steps above
2. Upload `composer.json` and `composer.lock` in the same folder
3. Use `composer install` from the terminal in the folder to create the `vendor` folder and download the needed packages (Google PHP API v4 and PHPMailer)
