<?php
namespace FreePBX\Console\Command;
use FreePBX\modules\Backup\Handlers as Handler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Command\LockableTrait;
use function FreePBX\modules\Backup\Json\json_decode;
use function FreePBX\modules\Backup\Json\json_encode;
use FreePBX\modules\Backup\Models\BackupSplFileInfo;
use Symfony\Component\Console\Question\ChoiceQuestion;
#[\AllowDynamicProperties]
class Backup extends Command {
	use LockableTrait;

	protected function configure(){
		$this->setName('backup')
		->setAliases(array('bu'))
		->setDescription(_('Run backup and restore jobs'))
		->setDefinition(array(
				new InputOption('backup', '', InputOption::VALUE_REQUIRED, _('Backup ID')),
				new InputOption('externbackup', '', InputOption::VALUE_REQUIRED, _('Base64 encoded backup job')),
				new InputOption('dumpextern', '', InputOption::VALUE_REQUIRED, _('Dump Base64 backup data')),
				new InputOption('transaction', '', InputOption::VALUE_REQUIRED, _('Transaction ID for the backup')),
				new InputOption('list', '', InputOption::VALUE_NONE, _('List backups')),
				new InputOption('warmspare', '', InputOption::VALUE_NONE, _('Set the warmspare flag')),
				new InputOption('implemented', '', InputOption::VALUE_NONE, ''),
				new InputOption('filestore', '', InputOption::VALUE_REQUIRED, _('Use filestore ID to restore a file')),
				new InputOption('restore', '', InputOption::VALUE_REQUIRED, _('Restore File')),
				new InputOption('restorelegacycdr', '', InputOption::VALUE_NONE, _('pass the Option --restorelegacycdr to restore LegacyCDR')),
				new InputOption('ignoremodules', '', InputOption::VALUE_REQUIRED, _('pass the Option --ignoremodules get ignore modules names')),
				new InputOption('modules', '', InputOption::VALUE_REQUIRED, _('Specific Modules to restore from using --restore, separate each module by a comma')),
				new InputOption('restoresingle', '', InputOption::VALUE_REQUIRED, _('Module backup to restore')),
				new InputOption('backupsingle', '', InputOption::VALUE_REQUIRED, _('Module to backup')),
				new InputOption('singlesaveto', '', InputOption::VALUE_REQUIRED, _('Where to save the single module backup.')),
				new InputOption('b64import', '', InputOption::VALUE_REQUIRED, ''),
				new InputOption('fallback', '', InputOption::VALUE_NONE, ''),
				new InputOption('useinfiledb', '', InputOption::VALUE_NONE, _('Option --useinfiledb to restore Legacy backup using file based sqlite, By default system uses inmemory')),
				new InputOption('skiprestorehooks', '', InputOption::VALUE_NONE, _('Option --skiprestorehooks skip postrestore hooks on restore')),
				new InputOption('skipbindport', '', InputOption::VALUE_NONE, _('Option --skipbindport skip bindport on restore')),
				new InputOption('skipdns', '', InputOption::VALUE_NONE, _('Option --skipdns skip dns on restore')),
				new InputOption('skipremotenat', '', InputOption::VALUE_NONE, _('Option --skipremotenat skip remotenat on restore')),
				new InputOption('skiptrunksandroutes', '', InputOption::VALUE_NONE, _('Option --skiptrunksandroutes skip trunks on restore')),
				new InputOption('skipchansipexts', '', InputOption::VALUE_NONE, _('Skip legacy chan_sip extensions during restore')),
				new InputOption('convertchansipexts2pjsip', '', InputOption::VALUE_NONE, _('Convert legacy chan_sip extensions to chan_pjsip extensions during restore')),
				new InputOption('skipchansiptrunks', '', InputOption::VALUE_NONE, _('Skip legacy chan_sip trunks during restore')),
				new InputOption('convertchansiptrunks2pjsip', '', InputOption::VALUE_NONE, _('Convert legacy chan_sip trunks to chan_pjsip trunks during restore')),
		))
		->setHelp('Run a backup: fwconsole backup --backup [backup-id]'.PHP_EOL
		.'Run a restore: fwconsole backup --restore [/path/to/restore-xxxxxx.tar.gz]'.PHP_EOL
		.'List backups: fwconsole backup --list'.PHP_EOL
		.'Dump remote backup string: fwconsole backup --dumpextern [backup-id]'.PHP_EOL
		.'Run backup job with remote string: fwconsole backup --externbackup [Base64encodedString]'.PHP_EOL
		.'Run backup job with remote string and custom transaction id: fwconsole backup --externbackup [Base64encodedString] --transaction [yourstring]'.PHP_EOL
		.'Run backup on a single module: fwconsole backup --backupsingle [modulename] --singlesaveto [output/path]'.PHP_EOL
		.'Run a single module backup: fwconsole backup --restoresingle [filename]'.PHP_EOL
		);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$this->freepbx = \FreePBX::Create();
		$this->Backup = $this->freepbx->Backup;
		$this->tmpDir = $this->freepbx->Config->get("ASTSPOOLDIR").'/tmp';
		if(posix_getuid() === 0) {
			$AMPASTERISKWEBUSER = $this->freepbx->Config->get('AMPASTERISKWEBUSER');
			$info = posix_getpwnam($AMPASTERISKWEBUSER);
			if(empty($info)) {
				$output->writeln("$AMPASTERISKWEBUSER is not a valid user");
				return 0;
			}
			posix_setuid($info['uid']);
		}

		if (!$this->lock()) {
			$output->writeln('The command is already running in another process.');
			return 0;
		}

		$this->output = $output;
		$this->input = $input;
		$this->freepbx->Backup->output = $output;
		$list = $input->getOption('list');
		$warmspare = $input->getOption('warmspare');
		$backup = $input->getOption('backup');
		$filestore = $input->getOption('filestore');
		$restore = $input->getOption('restore');
		$restorelegacycdr = $input->getOption('restorelegacycdr');
		$useinfiledb = $input->getOption('useinfiledb');
		$remote = $input->getOption('externbackup');
		$dumpextern = $input->getOption('dumpextern');
		$transaction = $input->getOption('transaction');
		$backupsingle = $input->getOption('backupsingle');
		$restoresingle = $input->getOption('restoresingle');
		$b64import = $input->getOption('b64import');
		$skiprestorehooks = $input->getOption('skiprestorehooks');
		$cliarguments = array();
		$cliarguments['ignoremodules'] = (trim($input->getOption('ignoremodules') ?? "") != '') ? explode(',', $input->getOption('ignoremodules')) : array();
		$cliarguments['skipbindport'] = $input->getOption('skipbindport');
		$cliarguments['skipdns'] = $input->getOption('skipdns');
		$cliarguments['skipremotenat'] = $input->getOption('skipremotenat');
		$cliarguments['skiptrunksandroutes'] = $input->getOption('skiptrunksandroutes');
		$cliarguments['convertchansipexts2pjsip'] = $input->getOption('convertchansipexts2pjsip');
		$cliarguments['skipchansipexts'] = $input->getOption('skipchansipexts');
		$cliarguments['convertchansiptrunks2pjsip'] = $input->getOption('convertchansiptrunks2pjsip');
		$cliarguments['skipchansiptrunks'] = $input->getOption('skipchansiptrunks');

		if($b64import){
			return $this->addBackupByString($b64import);
		}

		if($input->getOption('implemented')){
			$backupHandler = new Handler\Backup($this->freepbx);
			$output->writeln(json_encode($backupHandler->getModules()));
			return;
		}

		if($transaction) {
			$transactionid = $transaction;
		} else {
			$transactionid = $this->freepbx->Backup->generateID();
			$output->writeln(sprintf(_("Transaction ID is: %s"),$transactionid));
		}

		switch (true) {
			case $backupsingle:
				$saveto = $input->getOption('singlesaveto')?$input->getOption('singlesaveto'):'';
				$saveto = !empty($saveto) ? $saveto : rtrim(getcwd());
				$backupHandler = new Handler\Backup\Single($this->freepbx, $saveto, $transactionid, posix_getpid());
				if($input->getOption('fallback')){
					$backupHandler->setDefaultFallback(true);
				}
				$backupHandler->setModule($backupsingle);
				$backupHandler->process();
				$errors = $backupHandler->getErrors();
				$warnings = $backupHandler->getWarnings();
				if(empty($errors) && empty($warnings)) {
					$backupHandler->sendEmail(false,$transactionid);
					$output->writeln(_("Backup completed successfully"));
				} else {
					if(!empty($errors)) {
						$backupHandler->sendEmail(true,$transactionid);
						$output->writeln(_("There were errors during the backup process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the backup process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}
				return 0;
			break;
			case $restoresingle:
				$restoreHandler = new Handler\Restore\Single($this->freepbx, $restoresingle, $transactionid, posix_getpid());
				if($input->getOption('fallback')){
					$restoreHandler->setDefaultFallback(true);
				}
				$restoreHandler->process();
				$errors = $restoreHandler->getErrors();
				$warnings = $restoreHandler->getWarnings();
				if(empty($errors) && empty($warnings)) {
					$output->writeln(_("Restore completed successfully"));
				} else {
					if(!empty($errors)) {
						$output->writeln(_("There were errors during the restore process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the restore process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}
			break;
			case $list:
				$this->listBackups();
				return 0;
			break;
			case $backup:
				$bkstatus = '';
				$buid = $input->getOption('backup');
				$item = $this->freepbx->Backup->getBackup($buid);
				if(empty($item)) {
					throw new \Exception("Invalid backup id!");
				}

				$running = $this->freepbx->Backup->getConfig($buid,"runningBackupJobs");
				if(!empty($running) && posix_getpgid($running['pid']) !== false) {
					throw new \Exception("This backup is already running!");
				}
				//run prebackup hook
				$prebu_hook = $this->freepbx->Backup->getConfig("prebu_hook",$buid);
				if(strlen(trim($prebu_hook))> 1) {
					$output->writeln(sprintf('Executing Pre Backup Hook: %s',$prebu_hook));
					exec($prebu_hook);
				}

				$this->freepbx->Backup->setConfig($buid,["pid" => posix_getpid(), "transaction" => $transactionid],"runningBackupJobs");
				$this->freepbx->Backup->setConfig($transactionid,["buid" => $buid, "status"=>"BACKUPSTARTED","backupstatus"=>$bkstatus],"runningBackupstatus");
				$backupHandler = new Handler\Backup\Multiple($this->freepbx, $buid, $transactionid, posix_getpid());
				if($input->getOption('fallback')){
					$backupHandler->setDefaultFallback(true);
				}
				$this->freepbx->Backup->setConfig($transactionid,["buid" => $buid, "status"=>"PROCESSINGMODULES","backupstatus"=>$bkstatus],"runningBackupstatus");
				$val = $backupHandler->process();
				if($val == "") {
					$backupHandler->sendEmail(true,$transactionid);
					return 0;
				}
				$maintenanceHandler = new Handler\Backup\Maintenance($this->freepbx, $buid, $transactionid, posix_getpid());
				$this->freepbx->Backup->setConfig($transactionid,["buid" => $buid, "status"=>"PERMOFMINGMAINTENANCE","backupstatus"=>$bkstatus],"runningBackupstatus");
				$output->writeln(_("Performing Local Maintenance"));
				$maintenanceHandler->processLocal();
				$output->writeln(_("Finished Local Maintenance"));
				$this->freepbx->Backup->setConfig($transactionid,["buid" => $buid, "status"=>"PERMOFMINGSTORAGE","backupstatus"=>$bkstatus],"runningBackupstatus");
				$storageHandler = new Handler\Storage($this->freepbx, $buid, $transactionid, posix_getpid(), $backupHandler->getFile());
				$storageHandler->process();

				$errors = array_merge($backupHandler->getErrors(),$maintenanceHandler->getErrors(),$storageHandler->getErrors());
				$warnings = array_merge($backupHandler->getWarnings(),$maintenanceHandler->getWarnings(),$storageHandler->getWarnings());

				$output->writeln(_("Performing Remote Maintenance"));
				$maintenanceHandler->processRemote();
				$output->writeln(_("Finished Remote Maintenance"));
				
				if(empty($errors) && empty($warnings)) {
					$backupHandler->sendEmail(false,$transactionid);
					$output->writeln(_("Backup completed successfully"));
				} else {
					$backuperror_warning = 0 ;// 0 mean no error
					if(!empty($errors)) {
						$backuperror_warning = 1;
						$output->writeln(_("There were errors during the backup process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the backup process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
					$bkstatus = $backuperror_warning == 1 ? true:false;
					$backupHandler->sendEmail($bkstatus,$transactionid);
				}
				$this->freepbx->Backup->delConfig($buid,"runningBackupJobs");
				$postbu_hook = $this->freepbx->Backup->getConfig("postbu_hook",$buid);
				if(strlen(trim($postbu_hook))> 1) {
					$output->writeln(sprintf('Executing Post Backup Hook: %s',$postbu_hook));
					exec($postbu_hook);
				}
				$this->freepbx->Backup->setConfig($transactionid,["buid" => $buid, "status"=>"FINISHED","backupstatus"=>$bkstatus,"backupfile"=>$backupHandler->getFile()],"runningBackupstatus");
				//trigger Warmspare API
				if($item['warmspareenabled'] == 'yes') {
					if($item['warmsparewayofrestore'] == 'API') {
						$output->writeln(_("Warmspare enabled for this backup"));
						$filestoreid = explode('_',$item['warmspare_remoteapi_filestoreid']);
						$sparefilepath = $this->freepbx->Filestore->getConfig('path',$filestoreid[1]);
						$output->writeln(_("We Have triggered an API to restore the backup on Spare Server"));
						$resp = $this->freepbx->Backup->triggerWarmSpareGqlAPI($item,basename($backupHandler->getFile()),$transactionid,$sparefilepath);
						$data = $resp->data->runWarmsparebackuprestore;
						$output->writeln(_("Response from Warmspare backup Server API"));
						$output->writeln("clientMutationId :".$data->clientMutationId);
						$output->writeln("Restorestatus :".$data->restorestatus);
					}
					if($item['warmsparewayofrestore'] == 'SSH') {
						$output->writeln(_("Warmspare enabled for this backup"));
						$output->writeln(_("We are running Restore command on Spare Server"));
						$resp = $this->freepbx->Backup->RunRestoreusingSSH($item,basename($backupHandler->getFile()),$transactionid);
						$output->writeln(_("Response from Warmspare Server "));
						$output->writeln("backup transactionid:".$transactionid);
						$output->writeln("Restorestatus :".$resp['msg']);
					}
				}
				return 0;
			break;
			case $filestore:
				$info = $this->freepbx->Filestore->getItemById($filestore);
				if(empty($info)) {
					throw new \Exception('Invalid filestore id');
				}
				$output->write(sprintf(_("Retrieving %s from %s:%s..."),basename($restore), $info['driver'],$info['name']));
				$path = $this->tmpDir.'/'.basename($restore);
				$this->freepbx->Filestore->download($filestore,$restore,$path);
				$output->writeln(_('Done'));
				$restore = $path;
			case $restore:
				$exceptionmsg = '';
				if(!file_exists($restore)) {
					$exceptionmsg = sprintf(_("Unable to access the file %s"),$restore);
				}
				if (!is_readable($restore)) {
					$exceptionmsg .= sprintf(_("\n Unable to read the file %s. FreePBX restore process run as asterisk user so please ensure that 'asterisk' user has permissions to read the file/directory path. Or move the backup file to the '/home/asterisk' path."), $restore);
				}
				if($exceptionmsg) {
					throw new \Exception($exceptionmsg);
				}
				$running = $this->freepbx->Backup->getConfig("runningRestoreJob");
				if(!empty($running) && posix_getpgid($running['pid']) !== false) {
					throw new \Exception(_("A restore is already in progress; please wait until it completes before starting a new one."));
				}

				$this->freepbx->Backup->setConfig("runningRestoreJob",["pid" => posix_getpid(), "transaction" => $transactionid, "fileid" => md5($restore)]);

				$output->write(_("Determining backup file type..."));
				$backupType = $this->freepbx->Backup->determineBackupFileType($restore);
				if($backupType === false){
					throw new \Exception(_('Unknown file type'));
				}
				$output->writeln(sprintf(_("type is %s"),$backupType));
				$pid = posix_getpid();

				if(((!isset($cliarguments['skipchansipexts']) || !$cliarguments['skipchansipexts']) && (!isset($cliarguments['convertchansipexts2pjsip']) || !$cliarguments['convertchansipexts2pjsip'])) && ((!isset($cliarguments['skipchansiptrunks']) || !$cliarguments['skipchansiptrunks']) && (!isset($cliarguments['convertchansiptrunks2pjsip']) || !$cliarguments['convertchansiptrunks2pjsip']))) {
					$version = \FreePBX::Config()->get('ASTVERSION');
					$fileClass = new BackupSplFileInfo($restore);
					$manifest = $fileClass->getMetadata();
					if((isset($manifest['chansipexists']) && $manifest['chansipexists']) || (isset($manifest['chansipTrunkExists']) && $manifest['chansipTrunkExists'])) {
						$helper = $this->getHelper('question');
						$question = new ChoiceQuestion(sprintf(_("Attention: The backup file contains legacy chan_sip extensions or trunks, which are not compatible with Asterisk 21. You have the option to proceed, in which case these legacy extensions or trunks will be converted to PJSIP. Alternatively, you can cancel the restore process and downgrade the Asterisk version first, then attempt to restore your backup.")),array(_("Continue"),_("Cancel")),0);
						$question->setErrorMessage('Choice %s is invalid');
						$action = $helper->ask($this->input,$this->output,$question);
						switch($action){
							case _("Continue"):
								$cliarguments['convertchansipexts2pjsip'] = 1;
								$cliarguments['convertchansiptrunks2pjsip'] = 1;
							break;
							case _("Cancel"):
								exit;
							break;
						}
					}
				}
				if($backupType === 'current'){
					$restoreHandler = new Handler\Restore\Multiple($this->freepbx,$restore,$transactionid, posix_getpid());
				}
				if($backupType === 'legacy'){
					if($useinfiledb){
						$useinmemory = false;
						$output->writeln("Legacy Restore is using filebased sqlite ");
					}else {
						$useinmemory = true;
					}
					if(isset($restorelegacycdr) && $restorelegacycdr ==1) {
						$restorelegacycdr = 1;
						$restorelegacycdrenabled = 'SELECTED';
					} else {
						$restorelegacycdr = 0;
						$restorelegacycdrenabled = 'NOT SELECTED';
					}
					$output->writeln("Legacy CDR Restore Option: $restorelegacycdr ");
					$restoreHandler = new Handler\Restore\Legacy($this->freepbx,$restore, $transactionid, posix_getpid(),$restorelegacycdr);
				}
				if($skiprestorehooks){
					$skiphooks = true;
				}else {
					$skiphooks = false;
				}
				if($input->getOption('fallback')){
					$restoreHandler->setDefaultFallback(true);
				}
				if($input->getOption('modules')) {
					$restoreHandler->setSpecificRestore(explode(",",$input->getOption('modules')));
				}
				$output->writeln(sprintf('Starting restore job with file: %s',$restore));
				if ($backupType === 'legacy') {
					$restoreHandler->process($useinmemory, $cliarguments);
				} else { 
					//current version 
					$restoreHandler->process($skiphooks, $cliarguments);
				}

				$errors = $restoreHandler->getErrors();
				$warnings = $restoreHandler->getWarnings();
				if(empty($errors) && empty($warnings)) {
					$output->writeln(_("Restore completed successfully"));
				} else {
					if(!empty($errors)) {
						$output->writeln(_("There were errors during the restore process"));
						foreach($errors as $error) {
							$output->writeln("\t<error>".$error."</error>");
						}
					}
					if(!empty($warnings)) {
						$output->writeln(_("There were warnings during the restore process"));
						foreach($warnings as $warning) {
							$output->writeln("\t<comment>".$warning."</comment>");
						}
					}
				}
				$this->freepbx->Backup->delConfig("runningRestoreJob");
			break;
			case $dumpextern:
				$backupdata = $this->freepbx->Backup->getBackup($input->getOption('dumpextern'));
				if(!$backupdata){
					$output->writeln("Could not find the backup specified please check the id.");
					return 1;
				}
				$backupdata['backup_items'] = $this->freepbx->Backup->getAll('modules_'.$input->getOption('dumpextern'));
				$output->writeln(base64_encode(json_encode($backupdata)));
				return 0;
			break;
			case $remote:
				$job = $transaction?$transaction:$this->freepbx->Backup->generateID();
				$output->writeln(sprintf('Starting backup job with ID: %s',$job));
				$pid = posix_getpid();
				$errors  = $backupHandler->process('',$job,$input->getOption('externbackup'),$pid);
			break;
			default:
				$output->writeln($this->getHelp());
			break;
		}
		return 0;
	}
	public function listBackups(){
		$this->output->writeln("fwconsole backup --backup [Backup ID]");
		$table = new Table($this->output);
		$table->setHeaders(['Backup Name','Description','Backup ID']);
		$list = [];
		foreach ($this->freepbx->Backup->listBackups() as $value) {
			$list[] = [$value['name'],$value['description'],$value['id']];
		}
		$table->setRows($list);
		$table->render();
	}

	public function addBackupByString($base64){
		$data = json_decode(base64_decode($base64), true);
		if(json_last_error() !== JSON_ERROR_NONE){
			$this->output->writeln(sprintf('Backup could not be imorted: %s',json_last_error_msg()));
			return false;
		}
		$items = [];
		if(isset($data['backup_items'])){
			$items = $data['backup_items'];
			unset($data['backup_items']);
		}
		$id = $this->freepbx->Backup->generateID();

		foreach($data as $key => $value){
			$this->freepbx->Backup->updateBackupSetting($id,$key,$value);
		}
		$this->freepbx->Backup->setModulesById($id, $items);
		$this->freepbx->Backup->setConfig($id, array('id' => $id, 'name' => $data['backup_name'], 'description' => $data['backup_description']), 'backupList');
		$this->output->writeln(sprintf('Backup created ID: %s', $id));
		return $id;
	}
}
