<?php
  require 'PHPMailer/PHPMailerAutoload.php';
  $date = getdate();

  echo "\n\r";
  echo "*****************************************************\n\r";
  echo "Starting sendpepperbackup @ ".date("H:i:s d.m.Y")."\n\r";

  $config = parse_ini_file('/etc/pfefferback.ini'); //loades pfefferback.ini as config file
  $appid = $config["apiID"];
  $key = $config["apiKEY"];
  $tempdir = $config["tempDir"];

  //creates temp dir if not existend
  if(!is_dir($tempdir)) {
    mkdir($tempdir,0777,true);
    echo "Created dir: ".$tempdir."\n\r";
  }
  if(!is_dir($tempdir.'notes/')) {
    mkdir($tempdir.'notes/',0777,true);
    echo "Created dir: ".$tempdir."notes/\n\r";
  }

  fetchContacts($appid,$key,$tempdir);
  fetchNotesForAllContacts($appid,$key,$tempdir);
  pullTagList($appid,$key,$tempdir);
  createZIP($config);
  cleanup($tempdir);

  echo "Finished sendpepperbackup @ ".date("H:i:s d.m.Y")."\n\r";


  function pullTagList($appid,$key,$tempdir)
  {
    $tagfile = fopen($tempdir."tagList.xml","w") or die("unable to open file tagList.xml in ".$tempdir." !");

    $reqType= "pull_tag";
    $postargs = "appid=".$appid."&key=".$key."&reqType=".$reqType;
    $request = "https://api.moon-ray.com/cdata.php";

    $session = curl_init($request);
    curl_setopt ($session, CURLOPT_POST, true);
    curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($session);
    curl_close($session);

    fwrite($tagfile,$response);

    fclose($tagfile);
    echo "Fetched Tag List \n\r";
  }


  function cleanup($tempdir)
  {
    $it = new RecursiveDirectoryIterator($tempdir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it,RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
      if ($file->isDir()){
          rmdir($file->getRealPath());
      } else {
          unlink($file->getRealPath());
      }
    }
    rmdir($tempdir);
    echo "Cleanup finished - removed ".$tempdir." \n\r";
  }


  function createZIP($config)
  {
    $tempdir = $config["tempDir"];
    $heute = getdate();
    $zip = new ZipArchive();
    $zipfile = $tempdir."pfefferback".$heute[0].".zip";

    $notesfolder = realpath($tempdir.'notes');

    if ($zip->open($zipfile, ZipArchive::CREATE)!==TRUE) {
      exit("cannot open <$zipfile>\n");
    }

    $zip->addFile($tempdir."contacts.xml","contacts.xml");
    $zip->addFile($tempdir."tagList.xml","tagList.xml");

    // Create recursive directory iterator
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($notesfolder),RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($files as $name => $file)
    {
      if (!$file->isDir()){ // Skip directories (they would be added automatically)
          $filePath = $file->getRealPath(); // Get real and relative path for current file
          $relativePath = substr($filePath, strlen($notesfolder) + 1);
          $zip->addFile($filePath, "notes/".$relativePath); // Add current file to archive
      }
    }

    $zip->close();

    $type = 'application/zip';
    if(file_exists($zipfile)){
      echo "Created file: ".$zipfile." \n\r";
      sendBackupMail($config,$zipfile);
    }else {
      echo "File ".$zipfile." could not be found!\n";
    }
  }

  function fetchContacts($appid,$key,$tempdir)
  {
    $contactfile = fopen($tempdir."contacts.xml","w") or die("unable to open file contacts.xml in ".$tempdir." !");
    $emptyPageString = "<result></result>";
    $page = 1;
    $pageIsEmpty = false;

    while(!$pageIsEmpty){
      $data =<<<EOT
<search page='$page'>
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

      $session = curl_init($request);
      curl_setopt ($session, CURLOPT_ENCODING ,"UTF-8");
      curl_setopt ($session, CURLOPT_POST, true);
      curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
      curl_setopt ($session, CURLOPT_HEADER, false);
      curl_setopt ($session, CURLOPT_RETURNTRANSFER, true);
      $response = utf8_decode(curl_exec($session));
      curl_close($session);

      if($response != $emptyPageString){
          fwrite($contactfile,$response);
      } else {
        $pageIsEmpty = true;
      }

    } //end while

    fclose($contactfile);
    echo "Fetched contacts \n\r";
  }

  function fetchNotesForAllContacts($appid,$key,$tempdir)
  {
    echo "Fetching notes for each contact... \n\r";
    $output_array = array("");
    $count_contacts = 0;
    $contactsfile_read = fopen($tempdir."contacts.xml", "r") or die("Unable to open file ".$tempdir."contacts.xml!");

    while(!feof($contactsfile_read)) {
      preg_match_all("/id='([^']*?)'/", fgets($contactsfile_read), $output_array);
      $count_contacts += count($output_array[1]);
      foreach ($output_array[1] as $contactId) {
        fetchNotesByContactID($appid,$key,$contactId,$tempdir);
      }
    }
    fclose($contactsfile_read);

    echo "Fetched notes for ".$count_contacts." contacts.\n\r";
  }


  function fetchNotesByContactID($appid,$key,$contactId,$tempdir)
  {
    $data =<<<EOT
<contact_id>$contactId</contact_id>
EOT;

    $reqType= "fetch_notes";
    $postargs = "appid=".$appid."&key=".$key."&reqType=".$reqType."&data=".$data;
    $request = "http://api.moon-ray.com/cdata.php";

    $session = curl_init($request);
    curl_setopt ($session, CURLOPT_POST, true);
    curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
    curl_setopt($session, CURLOPT_HEADER, false);
    curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($session);
    curl_close($session);


    $notefile = fopen($tempdir."notes/".$contactId.".xml","w") or die("unable to open file ".$contactId.".xml in /tmp/pfefferback/notes/ !");
    fwrite($notefile, $response);
    fclose($notefile);
  }


  function sendBackupMail($config,$zipfile) {
    $smtpHost = $config["smtpHOST"];
    $smtpUser = $config["smtpUSER"];
    $smtpPassword = $config["smtpPASSWORD"];
    $mailFrom = $config["mailFROM"];
    $mailFromName = $config["mailFROMNAME"];
    $mailTo = $config["mailTO"];
    $mailToName = $config["mailTONAME"];

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

    $mail->Body    = 'This backup was generated at <i>'.date("H:i:s d.m.Y").'.</i>';
    $mail->AltBody = 'The HTML part of this mail could not be displayed.';

    if(!$mail->send()) {
        echo "Message could not be sent.\n\r";
        echo "Mailer Error: " . $mail->ErrorInfo . " \n\r";
    } else {
        echo "Mail has been sent\n\r";
    }
  }
?>
