<?php
declare(strict_types=1);
$root=__DIR__.'/../htdocs/';
function contact_check(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
$handler=$root.'assets/contact-emailjs.js';
contact_check(is_file($handler),'EmailJS form handler exists');
$page=file_get_contents($root.'contact.php')?:'';
$script=file_get_contents($handler)?:'';
contact_check(str_contains($page,'https://cdn.jsdelivr.net/npm/@emailjs/browser@5/dist/email.min.js'),'official EmailJS Browser SDK v5 is loaded');
contact_check(str_contains($page,'assets/contact-emailjs.js'),'contact page loads its EmailJS handler');
contact_check(str_contains($script,'service_3wj9tdl')&&str_contains($script,'template_6j0an7q'),'service and template IDs are configured');
contact_check(str_contains($script,'o0Q2kyzM1xb3UKmrC')&&str_contains($script,'sendForm'),'public key initializes the SDK and form sends via EmailJS');
contact_check(!str_contains($script,'privateKey'),'no private key is shipped to the browser');
contact_check(str_contains($page,'name="email"')&&str_contains($page,'name="subject"')&&str_contains($page,'name="message"'),'form fields match the EmailJS template variables');
contact_check(!str_contains($page,'Ouvrir directement un e-mail')&&!str_contains($page,'contact-direct-link'),'visible direct-email link is removed');
contact_check(!str_contains($script,'Ouvrir directement un e-mail'),'EmailJS status does not reference the removed link');
contact_check(!str_contains($page,'Vous avez le plein droit de corriger ou de supprimer les données vous concernant')&&!str_contains($page,'cndp@mmsp.gov.ma'),'the requested CNDP correction/deletion sentence and email are absent');
contact_check(!str_contains($page,'via EmailJS'),'contact status does not mention EmailJS');
echo "OK: EmailJS contact integration checks passed\n";
