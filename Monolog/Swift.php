<?php

namespace FreePBX\modules\Backup\Monolog;
use Monolog\Handler\MailHandler;
use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Swift as SwiftMailer;

/**
 * SwiftMailerHandler uses Swift_Mailer to send the emails
 *
 * This version is a manipulation for Backup and allows backup settings to be passed
 *
 * @author Gyula Sallai
 * @author Andrew Nagy
 */
class Swift extends MailHandler {
	private $mailer;
	private $messageTemplate;

	/**
	 * @param \Swift_Mailer		$mailer  The mailer to use
	 * @param \Swift_Message 	$message An example message for real messages, only the body will be replaced
	 * @param int				$level   The minimum logging level at which this handler will be triggered
	 * @param Boolean			$bubble  Whether the messages that are handled can bubble up the stack or not
	 */
	public function __construct(\Swift_Mailer $mailer, \Swift_Message $message, $level = Logger::DEBUG, $bubble = true, $backupInfo) {
		parent::__construct($level, $bubble);
		$this->backupInfo = $backupInfo;
		$this->mailer = $mailer;
		$this->messageTemplate = $message;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function send($content, array $records) {
		$errors = false;
		foreach ($records as $record) {
			if($record['level'] > 399){
				$errors = true;
			}
		}

		if($errors === false){
			/**
			 * double check
			 * within $content:
			 * 		Status: Failure
			 * 		Status: Success
			 */
			$errors = strpos(strtolower($content), _("success")) === false? true : false;
		}		
		
		switch($this->backupInfo['backup_emailtype']){
			case "both":
				break;
			case "success":
				if($errors === true){
					return;
				}
				break;
			case "failure":
				if($errors === false){
					return;
				}
				break;
		}

		$subject = sprintf(_('Backup %s success for %s'), $this->backupInfo['backup_name'], $this->backupInfo['ident']);
		if ($errors === true) {
			$subject = sprintf(_('Backup %s failed for %s'), $this->backupInfo['backup_name'], $this->backupInfo['ident']);
		}

		$this->messageTemplate->setSubject($subject);

		try {
			$this->mailer->send($this->buildMessage($content, $records));
		} catch(\Exception $e) {
			$nt = \FreePBX::Notifications();
			$nt->add_error('backup', 'EMAIL', _('Unable to send backup email!'), $e->getMessage(), "", true, true);
		}

	}

	/**
	 * Gets the formatter for the Swift_Message subject.
	 *
	 * @param  string			 $format The format of the subject
	 * @return FormatterInterface
	 */
	protected function getSubjectFormatter($format) {
		return new LineFormatter($format);
	}

	/**
	 * Creates instance of Swift_Message to be sent
	 *
	 * @param  string		  $content formatted email body to be sent
	 * @param  array		  $records Log records that formed the content
	 * @return \Swift_Message
	 */
	protected function buildMessage($content, array $records) {
		$location = \FreePBX::Config()->get('ASTLOGDIR');
		$message = null;

		$message = clone $this->messageTemplate;
		$message->generateId();

		if (!$message instanceof \Swift_Message) {
			throw new \InvalidArgumentException('Could not resolve message as instance of Swift_Message or a callable returning it');
		}

		if ($records) {
			$subjectFormatter = $this->getSubjectFormatter($message->getSubject());
			$message->setSubject($subjectFormatter->format($this->getHighestRecord($records)));
		}

		$inline = (!isset($this->backupInfo['backup_emailinline']) || $this->backupInfo['backup_emailinline'] === 'no') ? false : true;

		/**
		 * Creating new log file and cleaning content.
		 */
		$log_file = "backup-".strtotime("now").".log";
		copy($location."/backup.log", $location."/".$log_file);
		unlink($location."/backup.log");
		$log_content = str_replace("[] []","", file_get_contents($location."/".$log_file));
		preg_match_all('/]: (.+)/', $log_content, $matches, PREG_SET_ORDER, 0);
		$log_content = "";		
		foreach($matches as $line){
			if(empty($line)){
				continue;
			}
			$log_content .= $line[1]."\n";
		}

		if($inline) {	
			$message->setBody($content."\n".$log_content);
		} else {
			$message->attach(new \Swift_Attachment($log_content, $log_file, 'text/plain'));
			$message->setBody(_('See attachment'));
		}

		if (version_compare(SwiftMailer::VERSION, '6.0.0', '>=')) {
			$message->setDate(new \DateTimeImmutable());
		} else {
			$message->setDate(time());
		}

		return $message;
	}
}
