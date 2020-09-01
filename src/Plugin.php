<?php
namespace stillhart;

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

      $locale = Craft::$app->getSites()->getCurrentSite()->language; // needed to decide what language the sender reads

      $submission = $e->sender; // what contactForm.vue submits
      $fromEmail = $submission->fromEmail; // sender/buyer (contacts seller)
      $fromName = $submission->fromName; // 
      $recipientEmail = $submission->message["recipientEmail"]; // recipient aka. seller (to be contacted)
      $subject = $submission->subject; // message subject
      $body = $submission->message["body"];// message
      $success_subject = null; // success message to sender/buyer
      $success_body = null;// success message to sender/buyer

      // set up message text for success message to sender (buyer)
      if ($locale == 'de') {
        $success_subject = 'Nachricht erfolgreich übermittelt';
        $success_body = "Ihre Nachricht mit dem Betreff: «".$subject."» wurde erfolgreich übermittelt.\n\nFreundliche Grüsse,\nMarché Patrimoine";
      } else {
        $success_subject = 'Message transmis.';
        $success_body = "Votre Message au sujet de : « ".$subject." » a bien été transmis.\n\nCordialement,\nMarché Patrimoine";
      }
      
      // Log to storage/logs/contactform-extension.log
      // see Ben Croker’s answer – https://craftcms.stackexchange.com/questions/25427/craft-3-plugins-logging-in-a-separate-log-file
      $file = Craft::getAlias('@storage/logs/contactform-extension.log');
      $log = date('Y-m-d H:i:s').' '.json_encode($submission)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);

      // Send email to sender/buyer
      // this has been tested locally and it worked
      Craft::$app->getMailer()->compose()
      ->setTo($fromEmail)
      ->setFrom([ 'info@marchepatrimoine.ch' => 'Marché Patrimoine']) // should be alias or env var
      ->setReplyTo([ 'info@marchepatrimoine.ch' => 'Marché Patrimoine']) // should be alias or env var
      ->setSubject($success_subject)
      ->setTextBody($success_body)
      ->send();

      // Send email to recipient/seller
      // it seems to work as well, but spam is a problem?
      Craft::$app->getMailer()->compose()
      ->setTo($recipientEmail)
      ->setFrom([ 'info@marchepatrimoine.ch' => $fromName])
      ->setReplyTo([ 'info@marchepatrimoine.ch' => $fromName])
      ->setSubject($subject)
      ->setTextBody($body)
      ->send();
    });


    Event::on(SaveController::class, SaveController::EVENT_AFTER_ERROR, function(SaveEvent $e) {
      // Grab the entry
      $entry = $e->entry;

      // Get any validation errors
      $errors = $entry->getErrors();
      
      $file = Craft::getAlias('@storage/logs/bloody-estate-form.log');
      $log = date('Y-m-d H:i:s').' '.json_encode($errors)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);
    });


    // Listen to save event by guest entries
    // IST DAS WIRKLICH NUR DANN; ODER BEI JEDEM SAVE?!?
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
      }
      if (!$sellerFirstname) {
        $entry->sellerFirstname = $ownerFirstname;
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
        $success_body = "Das Objekt «".$entry->title."» wurde erfolgreich erfasst.\n\nEs wird nach redaktioneller Prüfung live geschaltet.\n\nFreundliche Grüsse,\nMarché Patrimoine";
      } else {
        $success_subject = '…';
        $success_body = "Votre … « ".$entry->title." » a bien été transmis.\n\n…\n\nCordialement,\nMarché Patrimoine";
      }

      $file = Craft::getAlias('@storage/logs/new-estate-form.log');
      $log = date('Y-m-d H:i:s').' '.json_encode($sellerMail)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);

      Craft::$app->getMailer()->compose()
      ->setTo($sellerMail)
      ->setFrom([ 'info@marchepatrimoine.ch' => 'Marché Patrimoine']) // should be alias or env var
      ->setReplyTo([ 'info@marchepatrimoine.ch' => 'Marché Patrimoine']) // should be alias or env var
      ->setSubject($success_subject)
      ->setTextBody($success_body)
      ->send();
    });

  }
}
