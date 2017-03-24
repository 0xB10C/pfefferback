<?php
  require 'PHPMailer/PHPMailerAutoload.php';

  $date = getdate();

    //creates temp dir if not existend
    if(!is_dir('/tmp/pfefferback/')) {
      mkdir('/tmp/pfefferback/',0777,true);
    }

    getDataFromAPI();


  function sendBackupMail($smtpHost,$smtpUser,$smtpPassword,$mailFrom,$mailTo,$mailFromName,$mailToName,$zipfile) {
          $mail = new PHPMailer;
          //$mail->SMTPDebug = 3;                               // Enable verbose debug output
          $mail->isSMTP();                                      // Set mailer to use SMTP
          $mail->Host = $smtpHost;                              // Specify main and backup SMTP servers
          $mail->SMTPAuth = true;                               // Enable SMTP authentication
          $mail->Username = $smtpUser;                          // SMTP username
          $mail->Password = $smtpPassword;                       // SMTP password
          $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
          $mail->Port = 587;                                    // TCP port to connect to
          $mail->setFrom($mailFrom, $mailFromName);
          $mail->addAddress($mailTo, $mailToName);              // Add a recipient
          $mail->addAttachment($zipfile, 'backup_'.date("d.m.Y_H:i:s").'.zip');    // Optional name
          $mail->isHTML(true);                                  // Set email format to HTML
          $mail->Subject = '[SendPepperBackup] ' . date("d.m.Y");

          $mail->Body    = 'Backup was generated at <i>'.date("d.m.Y_H:i:s").'</i>';
          $mail->AltBody = 'The HTML part of this mail could not be displayed.';

          if(!$mail->send()) {
              echo 'Message could not be sent.\n';
              echo 'Mailer Error: ' . $mail->ErrorInfo . ' \n';
          } else {
              echo 'Message has been sent\n';
          }
        }

  function getDataFromAPI(){
    //loades pfefferback.ini as config file
    $config = parse_ini_file('/etc/pfefferback.ini');

    $appid = $config["apiID"];
    $key = $config["apiKEY"];
    $smtpHost = $config["smtpHOST"];
    $smtpUser = $config["smtpUSER"];
    $smtpPassword = $config["smtpPASSWORD"];
    $mailFrom = $config["mailFROM"];
    $mailFromName = $config["mailFROMNAME"];
    $mailTo = $config["mailTO"];
    $mailToName = $config["mailTONAME"];

    $userfile = fopen("/tmp/pfefferback/user.xml","w") or die("unable to open file user.xml in /tmp/pfefferback/ !");
    $notefile = fopen("/tmp/pfefferback/note.xml","w") or die("unable to open file note.xml in /tmp/pfefferback/ !");
    $page = 1;
  while($page <= 3){ //TODO unconstant 3
$data =<<<EOT
<search site="$page">
<equation>
<field>E-Mail</field>
<op>n</op>
<value>123</value>
</equation>
</search>
EOT;
    $page++;

    $data = urlencode(urlencode($data));
    $reqType = "search";
    $postargs = "appid=".$appid."&key=".$key."&reqType=".$reqType."&data=".$data;
    $request = "https://api.moon-ray.com/cdata.php";

    header("Content-Type: text/html; charset=utf-8");

    $session = curl_init($request);

    curl_setopt ($session, CURLOPT_ENCODING ,"UTF-8");
    curl_setopt ($session, CURLOPT_POST, true);
    curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
    curl_setopt ($session, CURLOPT_HEADER, false);
    curl_setopt ($session, CURLOPT_RETURNTRANSFER, true);

    $response = utf8_decode(curl_exec($session));
    curl_close($session);
    fwrite($userfile,$response);
  }
  fclose($userfile);

  $heute = getdate();
  $zip = new ZipArchive();
  $zipfile = "/tmp/pfefferback/pfefferback".$heute[0].".zip";
  if ($zip->open($zipfile, ZipArchive::CREATE)!==TRUE) {
    exit("cannot open <$zipfile>\n");
  }

  $zip->addFile("/tmp/pfefferback/user.xml","user.xml");
  $zip->addFile("/tmp/pfefferback/note.xml","note.xml");
  $zip->close();

  $type = 'application/zip';
  if(file_exists($zipfile)){
    sendBackupMail($smtpHost,$smtpUser,$smtpPassword,$mailFrom,$mailTo,$mailFromName,$mailToName,$zipfile);
  }else {
    echo "zip dosnt exist\n";
  }
}
?>
