# pfefferback


## motivation
SendPepper is an email marketing solution from ONTRAPORT. Customer data export was recently limited to CSV exports only.
*pfefferback* provides full data export via a _SendPepper api_ and direct mail backups. Backups can be initiated manually or by cronjob.

## installation
requires the packages:
*php-curl*
*php-xml*
*php-cgi*
*php7.0-zip*


copy pfefferback.ini to /etc/pfefferback.ini and edit
_apiID, apiKEY, smtpHOST, smtpUSER, smtpPASSWORD, mailFROM, mailTO, mailFROMNAME, mailTONAME, tempDir_

## usage
manual use: _php backup.php_
