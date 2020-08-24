<?php
namespace stillhart;

use Craft;
use craft\contactform\models\Submission;
use yii\base\Event;

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
      $subject = 'Nachricht erfolgreich übermittelt';
      $body = $submission->message;

      $locale = Craft::$app->getSites()->getCurrentSite()->language;

      if ($locale == 'de') {
        $body = "Ihre Nachricht mit dem Betreff: «".$originalSubject."» wurde erfolgreich übermittelt.\n\nFreundliche Grüsse,\nMarché Patrimoine";
      } else {
        $subject = 'Message transmis.';
        $body = "Votre Message au sujet de : « ".$originalSubject." » a bien été transmis.\n\nCordialement,\nMarché Patrimoine";
      }

      // Log to storage/logs/contactform-extension.log
      // see Ben Croker’s answer – https://craftcms.stackexchange.com/questions/25427/craft-3-plugins-logging-in-a-separate-log-file
      // $file = Craft::getAlias('@storage/logs/contactform-extension.log');
      // $log = date('Y-m-d H:i:s').' '.json_encode($submission)."\n";
      // \craft\helpers\FileHelper::writeToFile($file, $log, ['append' => true]);

      // Send email to contact form sender
      Craft::$app->getMailer()->compose()
      ->setTo($fromEmail)
      ->setSubject($subject)
      ->setTextBody($body)
      ->send();

    });
  }
}
