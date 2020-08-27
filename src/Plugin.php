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
      $submission = $e->sender;

      $fromEmail = $submission->fromEmail;
      $originalSubject = $submission->subject;
      $subject = null;
      $body = $submission->message;

      $locale = Craft::$app->getSites()->getCurrentSite()->language;

      if ($locale == 'de') {
        $subject = 'Nachricht erfolgreich übermittelt';
        $body = "Ihre Nachricht mit dem Betreff: «".$originalSubject."» wurde erfolgreich übermittelt.\n\nFreundliche Grüsse,\nMarché Patrimoine";
      } else {
        $subject = 'Message transmis.';
        $body = "Votre Message au sujet de : « ".$originalSubject." » a bien été transmis.\n\nCordialement,\nMarché Patrimoine";
      }
      
      // Was hier noch fehlt, du Hirnipicker, ist das Mail an den lieben Empfänger, duh!
      // dazu muss natürlich die Adresse des lieben Empfängers übermittelt werden oder.

      // Log to storage/logs/contactform-extension.log
      // see Ben Croker’s answer – https://craftcms.stackexchange.com/questions/25427/craft-3-plugins-logging-in-a-separate-log-file
      $file = Craft::getAlias('@storage/logs/contactform-extension.log');
      $log = date('Y-m-d H:i:s').' '.json_encode($submission)."\n";
      \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);

      // Send email to contact form sender
      Craft::$app->getMailer()->compose()
      ->setTo($fromEmail)
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
      }
    });

  }
}
