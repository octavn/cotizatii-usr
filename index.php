<?php
/*
	Name: Script de interogare Google spreadsheet privind cotizatiile lunare catre USR S2
	Author: naicuoctavian+usr@gmail.com
	GitHub: https://github.com/octavn/cotizatii-usr
*/


// Import PHPMailer classes into the global namespace
// Apparently these must be at the top of your script, not inside a function
// PHPMailer is used for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
	As per https://stackoverflow.com/questions/25523004/fatal-error-curl-reset-undefined-why the workaround below prevents a fatal error I've had when running this on PHP 5.6
	PHP Fatal error:  Call to undefined function GuzzleHttp\Handler\curl_reset() in /home/addpipe/public_html/usr-s2/vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php on line 77
*/

if (!function_exists('curl_reset'))
{
    function curl_reset(&$ch)
    {
        $ch = curl_init();
    }
}


/* 
	Helper function to log all requests to HDD in a folder
*/

function log_it($log_msg)
{
    $log_folder = "app-logs";
    if (!file_exists($log_folder)) 
    {
        // create directory/folder
        mkdir($log_folder, 0777, true);
    }
    $log_file_data = $log_folder.'/log_' . date('d-M-Y') . '.log';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, $log_msg . PHP_EOL, FILE_APPEND);
}


if (isset($_GET["email"])){
	//when the form get submitted we receive an e-mail through GET (GET also allows us to link directly to this script and execute it)
	$email=$_GET["email"];
	if (strlen(trim($email))<6){
		//email length in bytes is too short
		$error="Ați introdus un e-mail prea scurt, încercați din nou.";
		//log the attempt
		log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": email length in bytes was shorter than 6 bytes");
	}else{
		if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

			//log the attempt
			log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": email seems to be valid as per PHP's FILTER_VALIDATE_EMAIL filter");

			//Let's get the party started with some Google APIs 1st
			require __DIR__ . '/vendor/autoload.php';

			$client = new Google_Client();
			$client->setApplicationName('Interogare baza de date cu contributii');
			$client->setScopes(Google_Service_Sheets::SPREADSHEETS);
			$client->setAccessType('offline');

			//credentials.json holds the private key needed to authenticate against Google Cloud
			//See https://youtu.be/iTZyuszEkxI on how to generate such a .json file for your own project
			$client->setAuthConfig(__DIR__.'/credentials.json');

			// new service yay
			$service = new Google_Service_Sheets($client);

			// unique Id of your spreadsheet found in the spreadsheet URL between the /d/ and /edit 
			$spreadsheetId='1YB6Il-uHUDLA0YOD3hD1_lqVMtjIHfMIzUeP7HHsiF8'; 

			// range of the cells you want to grab, data in sheet starts at pos 4
			// A is prenume
			// B is nume
			// C is email
			$range="Cotizatii!A4:C1000";

			$response = $service->spreadsheets_values->get($spreadsheetId, $range);
			$values = $response->getValues();

			if (empty($values)) {
			    echo 'No data found' . PHP_EOL;
			    die();
			}

			//we assume the email is not in the spreadsheet
			$emailisindb = false;

			//we start searching with the row 4 of the sheet, so we init $position with 3 and increase it in the loop
			$position=3;

			foreach($values as $row) {
				//$row[2] is email
				//$row[1] is nume
				//$row[0] is prenume
				
				//increase the position as we enter the for loop
				$position++;

				//echo "{$row[2]} {$row[1]} {$row[0]}" . PHP_EOL;
				if ($row[2]==$email){
					$emailisindb=true;
					break;
				}
			    
			}

			// a privacy conscious message, we could also tell the user that the email was found but that would allow someone to see if an email is part of this organisation by brute forcing the form
			// $success="S-a declanșat procedura de interogare. Dacă e-mailul dvs e în baza de date, ar trebui să primiți un e-mail cu detaliile privind cotizația în câteva minute.";

			if ($emailisindb){

				//log the attempt
				log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": email has been found in the spreadsheet, attempting to send the data...");

				// a message that is NOT privacy conscious
				$success="S-a găsit e-mail la USeReu! În scurt timp o să primiți un e-mail pe adresa $email cu detaliile privind cotizația.";

				//let's go horizontally and extract all columns related to payments up to and including 2019
				$paymentRange="Cotizatii!N".$position.":BC".$position;
				if (date("Y")==2020){
					//if we're in 2020 we'll extend the row up to column BO
					$paymentRange="Cotizatii!N".$position.":BO".$position;
				} else if (date("Y")==2021){
					//if we're in 2021 we'll extend the row up to column BX
					$paymentRange="Cotizatii!N".$position.":BX".$position;

				}

				$paymentResponse = $service->spreadsheets_values->get($spreadsheetId, $paymentRange);
				$paymentColumns = $paymentResponse->getValues();
				$rowOfPayments = $paymentColumns[0];

				//an array of months, obviously!
				$months = array("Ianuarie", "Februarie", "Martie", "Aprilie", "Mai", "Iunie", "Iulie", "August", "Septembrie", "Octombrie","Noiembrie","Decembrie");

				//let's begin the email body
				$message = "Salut, iată situația cotizațiilor către USR Sector 2 așa cum apare ea în baza de date a USR S2:\n\n";

				//we start with 2016
				$year=2016;

				//start printing year separators only when we find a cell with contributions, 0 is considered a contribution
				$startprinting = false;

				//We're going through the list of payments, this relies highly on the sheet not being changed so hold on to something!
				for ($x = 0; $x < count($rowOfPayments); $x++) {

					//sheet starts with july 2016 so the month position is actually 6 ahead of the $x  because july is @ 6 in a 0 index array
					$monthindex = $x+6;

					if ($rowOfPayments[$x]!=" - " /*&& $rowOfPayments[$x]!=0*/){

						//print the situation for the current month
						$message .= $months[(($monthindex)%12)]." ".$year.": ".$rowOfPayments[$x]." LEI\n";

						$startprinting=true;
					}

					if (($monthindex+1)%12==0){
						//december month, 11 in a 0 index array, add a line and increase the year
						$year++;
						
						//we only add the year separator if there's data beforehand and if it's not the last item in the array
						if ($startprinting && $x<(count($rowOfPayments)-1)){
							$message .= "-----------\n";
						}
						
					}
				}

				//Let's end the email body
				$message .= "\nEchipa USR S2\nhttps://sector2.usr.ro";

				//let's power up the email sending machine
				$mail = new PHPMailer;

				/*
					//uncomment this block and configure the following to send email with a different SMTP service
					
					$mail->IsSMTP();							// Set mailer to use SMTP
					$mail->Host = 'mail.yoursite.com';          // Specify main and backup server
					$mail->Port = 465;							// Set the SMTP port
					$mail->SMTPAuth = true;						// Enable SMTP authentication
					$mail->Username = 'user';					// SMTP username
					$mail->Password = 'parola';					// SMTP password
					$mail->SMTPSecure = 'ssl';					// Enable encryption, 'ssl' also accepted
				*/

				//From email address and name
				$mail->From = "no-reply@sector2.usr.ro";
				$mail->FromName = "Echipa USR Sector 2";

				//Send e-mail as plain text
				$mail->isHTML(false);

				//Subject of email, tried diacritice, did NOT work!
				$mail->Subject = "Situatia cotizatiilor catre USR Sector 2 pentru membrul asociat $email";
				
				//Body of email
				$mail->Body = $message;

				//To whom to send the email, we could add the name of the  user from the sheet here
				$mail->addAddress($email);

				//we now unset the email variable so that the input in the HTML page is cleared to prevent users easily re-submitting the form
				unset($email);

				//carbon copy these 2 persons during development
				//$mail->addCC('');
				//$mail->addCC('');
				
				
				if(!$mail->send()){
				    echo "Mailer Error: " . $mail->ErrorInfo;
				    // a message that is NOT privacy conscious
					$error="Din păcate e-mailul nu a putut fi trimis. Detalii eroare: $mail->ErrorInfo;";

					//log the attempt
					log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": the data could not be emailed because: ".$mail->ErrorInfo);

				}else {
				    //echo "Message has been sent successfully";

					//log the attempt
					log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": the data has now been emailed.");
				}

				//we now unset the email variable so that the input in the HTML page is cleared to prevent users easily re-submitting the form
				unset($email);
			
			}else{
				// a message that is NOT privacy conscious
				$error="Acest e-mail nu a fost găsit în baza de date. Vă rugăm verificați e-mailul și încercați din nou.";

				//log the attempt
				log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": email was not found in the spreadsheet");
			}
		}else{
			$error="Nu pare să fi introdus un e-mail, încercați din nou.";
			
			//log the attempt
			log_it( "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a")." attempted to request info on ". $email. ": email string did not pass PHP's FILTER_VALIDATE_EMAIL filter");
		}
	}
}

?>
<!doctype html>
<html lang="en">
 <head>
	<!-- Required meta tags -->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

	<title>USR S2: Verifică situația cotizației</title>
	<style type="text/css">
		h1, h2, h3 {
			color:#ed1c24;
		}
		input[type="email"] {   
  			border-color: #00a1e4;
		}
		.btn-outline-primary {
			color: #ed1c24;
			background-color: transparent;
			background-image: none;
			border-color: #00a1e4;
		}
		.btn-outline-primary:hover {
			color: #fff;
			background-color: #ed1c24;
			border-color: #ed1c24;
		}
	</style>
</head>
<body>
	<nav class="navbar navbar-light bg-light">
		<a class="navbar-brand" href="https://sector2.usr.ro" title="Către pagina web USR Sector 2">
			<img src="logo-usr16-flag_white.png" width="30" height="30" alt="Logo USR">
		</a>
	</nav>
	<div class="row justify-content-center">
		<div class="col-11 col-md-11 col-lg-8 mt-4">
			<h2>Verifică situația cotizației către USR S2</h2>
			<p>Introdu adresa de e-mail mai jos și apasă butonul verifică. Dacă e-mailul există în baza noastră de date cu membrii, pe adresa respectivă va fi trimis un e-mail cu situația privind cotizația.</p>
			<?php if (isset($error)){ ?>
				<div class="alert alert-warning" role="alert"><?=$error?></div>
			<?php } ?>
			<?php if (isset($success)){ ?>
				<div class="alert alert-success" role="alert"><?=$success?></div>
			<?php } ?>
			<form method="GET">
				<div class="input-group mb-3">
					<input type="email" required="required" id="email" name="email" class="form-control" placeholder="Introdu adresa de e-mail" value="<?php if (isset($email)){ echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8');}?>">
					<div class="input-group-append">
						<button class="btn btn-outline-primary" type="submit">Verifică</button>
					</div>
				</div>
			</form>
		</div>
		<!--end of col-->
	</div>
	<!-- Optional JavaScript -->
	<!-- jQuery first, then Popper.js, then Bootstrap JS -->
	<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
</body>
</html>