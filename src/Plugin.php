<?php
namespace stillhart;

// this handles the dispaching of email messages by
// - contact form (to buyer and seller)
// - new estate form (to MP and seller)
// a fifth mail message is sent by the contact-form plugin
// this fifth message is handled in config/contact-form.php,
// i.e. the plugin settings are overriden!

// for contact form
use Craft;
use craft\contactform\models\Submission;
use yii\base\Event;

// for guest entries
use craft\guestentries\controllers\SaveController;
use craft\guestentries\events\SaveEvent;



class Plugin extends \craft\base\Plugin
{
  public function init()
  {
    parent::init();


    // Listen for Submissions to/by Contact-Form Plugin
    Event::on(Submission::class, Submission::EVENT_AFTER_VALIDATE, function(Event $e) {

    // Address string needed in signature
    $mpContact = <<<EOD
c/o Schweizer Heimatschutz
Zollikerstrasse 128
8008 Zürich
info@marchepatrimoine.ch
T 044 252 28 72
marchepatrimoine.ch
EOD;

    // German Signature
    $signature_de = <<<EOD

--

Diese Mail wurde automatisch generiert von:

Marché Patrimoine
Die Plattform für Baudenkmäler

{$mpContact}
EOD;

    // French Signature
    $signature_fr = <<<EOD

--

Ce message a été généré automatiquement par:

Marché Patrimoine
La plateforme des bâtiments historiques

{$mpContact}
EOD;

      $submission = $e->sender; // what contactForm.vue submits

      $fromEmail = $submission->fromEmail; // sender/buyer (contacts seller)
      $fromName = $submission->fromName; // 
      $recipientEmail = $submission->message["recipientEmail"]; // recipient aka. seller (to be contacted)
      $subject = $submission->subject; // message subject
      $body = $submission->message["body"];// message
      $locale = $submission->message["locale"]; // test custom value
      $estateName = $submission->message["estateName"]; // test custom value
      $success_subject = null; // success message to sender/buyer
      $success_body = null;// success message to sender/buyer

      if ($locale == 'de') {

        // set up german message text for success message to sender (buyer)
        $success_subject = 'Nachricht erfolgreich übermittelt';
        $success_body = <<<EOD
Ihre Nachricht mit dem Betreff: «{$subject}» wurde erfolgreich übermittelt.

Der/die Verkäufer/in des Objektes «{$estateName}» wird sich mit Ihnen in Verbindung setzen.

Freundliche Grüsse,
Marché Patrimoine

{$signature_de}
EOD;

        // set up german subject for message to recipient (seller)
        $recipient_subject = 'Anfrage zu ihrem Objekt';
        // set up german body of message to recipient (seller)
        $recipient_body = <<<EOD
Guten Tag,
 
Sie haben eine Anfrage für Ihr Objekt auf marchepatrimoine.ch erhalten:

{$subject}

$body

{$signature_de}
EOD;

      } else { // $locale != 'de'

        // set up french message text for success message to sender (buyer)
        $success_subject = "Message transmis avec succès";
        $success_body = <<<EOD
Votre message concernant : « \$subject » a été transmis.

Le vendeur de l’objet « {$estateName} » vous contactera.

Avec nos meilleures salutations,
Marché Patrimoine

{$signature_fr}
EOD;

        // set up french subject for message to recipient (seller)
        $recipient_subject = 'Demande d’offre';
        // set up french body of message to recipient (seller)
        $recipient_body = <<<EOD
Bonjour,
vous avez reçu une demande d’offre pour votre objet sur marchepatrimoine.ch :
{$subject}

$body

{$signature_fr}
EOD;
      }
      
      // Log to storage/logs/contactform-extension.log
      // see Ben Croker’s answer – https://craftcms.stackexchange.com/questions/25427/craft-3-plugins-logging-in-a-separate-log-file
      $file = Craft::getAlias('@storage/logs/contactform-extension.log');
      $log = date('m-d H:i').' Locale: ' . $locale .', submission: '.json_encode($submission)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);

      $mpAddress = Craft::getAlias('@contactformRecipient'); // obviously this alias’ name was badly chosen
      // Send email to sender/buyer
      // this has been tested locally and it worked
      // tested on staging and worked on 1.9.2020
      Craft::$app->getMailer()->compose()
      ->setTo($fromEmail)
      ->setFrom([ $mpAddress => 'Marché Patrimoine']) // should be alias or env var
      ->setReplyTo([ $mpAddress => 'Marché Patrimoine']) // should be alias or env var
      ->setSubject($success_subject)
      ->setTextBody($success_body)
      ->send();

      // Send email to recipient/seller
      // it seems to work as well, but spam is a problem?
      // this has been tested locally and it worked
      // tested on staging and worked on 1.9.2020
      Craft::$app->getMailer()->compose()
      ->setTo($recipientEmail)
      ->setFrom([ $fromEmail => $fromName])
      ->setReplyTo([ $fromEmail => $fromName])
      ->setSubject($recipient_subject)
      ->setTextBody($recipient_body)
      ->send();
    });

    Event::on(SaveController::class, SaveController::EVENT_AFTER_ERROR, function(SaveEvent $e) {

    // Address string needed in signature
    $mpContact = <<<EOD
c/o Schweizer Heimatschutz
Zollikerstrasse 128
8008 Zürich
info@marchepatrimoine.ch
T 044 252 28 72
marchepatrimoine.ch
EOD;

    // German Signature
    $signature_de = <<<EOD

--

Diese Nachricht wurde automatisch generiert von:

Marché Patrimoine
Die Plattform für Baudenkmäler

{$mpContact}
EOD;

    // French Signature
    $signature_fr = <<<EOD

--

Ce message a été généré automatiquement par:

Marché Patrimoine
La plateforme des bâtiments historiques

{$mpContact}
EOD;

      // Grab the entry
      $entry = $e->entry;

      // Get any validation errors
      $errors = $entry->getErrors();
      
      $file = Craft::getAlias('@storage/logs/bloody-estate-form.log');
      $log = date('m-d H:i').' '.json_encode($errors)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);
    });


    // Listen to save event by guest entries
    // https://github.com/craftcms/contact-form
    // https://craftcms.stackexchange.com/questions/27267/guest-entries-how-i-can-send-message-to-user-after-saving-form-information
    Event::on(SaveController::class, SaveController::EVENT_BEFORE_SAVE_ENTRY, function(SaveEvent $e) {
      // Grab the entry
      $entry = $e->entry;

      // fix that awful form nonsense
      // because javascript really tries to set values which are then mishandled by WHAT?

      $sellerName = $entry->sellerName;
      $ownerName = $entry->ownerName;

      $sellerFirstname = $entry->sellerFirstname;
      $ownerFirstname = $entry->ownerFirstname;

      $sellerFirma = $entry->sellerFirma;
      $ownerFirma = $entry->ownerFirma;

      $sellerTel = $entry->sellerTel;
      $ownerTel = $entry->ownerTel;

      $sellerMail = $entry->sellerMail;
      $ownerMail = $entry->ownerMail;

      if (!$sellerName) {
        $entry->sellerName = $ownerName;
        $sellerName = $ownerName; // needed for message to fib
      }
      if (!$sellerFirstname) {
        $entry->sellerFirstname = $ownerFirstname;
        $sellerFirstname = $ownerFirstname; // needed for message to fib
      }
      if (!$sellerFirma) {
        $entry->sellerFirma = $ownerFirma;
      }
      if (!$sellerTel) {
        $entry->sellerTel = $ownerTel;
      }
      if (!$sellerMail) {
        $entry->sellerMail = $ownerMail;
        $sellerMail = $ownerMail; // needed for success message
      }

      // set up message text for success message to sender (buyer)
      $locale = Craft::$app->getSites()->getCurrentSite()->language; // needed to decide what language the sender reads
      if ($locale == 'de') {
        $success_subject = 'Objekt erfolgreich erfasst';
        $success_body = <<<EOD
Das Objekt «{$entry->title}» wurde erfolgreich erfasst.
  
Es wird nach redaktioneller Prüfung live geschaltet.

Freundliche Grüsse,
Marché Patrimoine

$signature_de
EOD;
      } else {
        $success_subject = 'Objet enregistré avec succès';
        $success_body = <<<EOD
Le bien « {$entry->title} » a été enregistré avec succès.

Il sera mis en ligne après notre contrôle rédactionnel.

Avec nos meilleures salutations,
Marché Patrimoine

$signature_fr
EOD;
      }

      /*
      $file = Craft::getAlias('@storage/logs/new-estate-form.log');
      $log = date('m-d H:i').' Locale: '. $locale .' Author of new entry who needs a confirmation mail:'.json_encode($sellerMail)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);
       */

      // get address from alias (in config/general, .env variable per environment)
      $mpAddress = Craft::getAlias('@contactformRecipient');

      // Success message to seller
      // locally tested and worked on 1.9.2020
      // tested on staging and worked on 1.9.2020
      Craft::$app->getMailer()->compose()
      ->setTo($sellerMail)
      ->setFrom([ $mpAddress => 'Marché Patrimoine'])
      ->setReplyTo([ $mpAddress => 'Marché Patrimoine'])
      ->setSubject($success_subject)
      ->setTextBody($success_body)
      ->send();

      // Tell FIB that a new entry has been made
      // locally tested and didnae work on 1.9.2020
      $name = $sellerFirstname . ' ' . $sellerName;
      $messageToFib = <<< EOT
$name ($sellerMail) hat ein neues Objekt erfasst.
  
Name: $entry->title
Ort: $entry->location

Der Eintrag ist noch nicht aktiviert.
EOT;
      // tested on staging and worked on 1.9.2020
      Craft::$app->getMailer()->compose()
      ->setTo([ $mpAddress => 'Marché Patrimoine'])
      ->setFrom([ $mpAddress => 'Marché Patrimoine'])
      ->setReplyTo([ $mpAddress => 'Marché Patrimoine'])
      ->setSubject('Neues Objekt auf Marché Patrimoine')
      ->setTextBody($messageToFib)
      ->send();

    });
  }
}
