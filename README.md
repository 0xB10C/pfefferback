# pfefferback


## motivation
SendPepper is an email marketing solution from ONTRAPORT. Customer data export was recently limited to CSV exports only.
**pfefferback** provides full data export via the SendPepper API and mail backup. Backups can be initiated manually or by cronjob.

## installation
requires the packages:
- php-curl
- php-xml
- php-cgi
- php7.0-zip

```
sudo apt install php-curl php-xml php-cgi php7.0-zip
```

copy pfefferback.ini to /etc/pfefferback.ini and edit:
- apiID
- apiKEY
- smtpHOST
- smtpUSER
- smtpPASSWORD
- mailFROM
- mailTO
- mailFROMNAME
- mailTONAME
- tempDir_

## usage
manual use: `php backup.php`

cronjob every Wednesday and Friday at 12am with appending logging:
```
# m  h  dom mon dow   command
  00 12 *   *   3,5   php ~/pfefferback/backup.php >> ~/pfeffer.log
```
