<div class="container-fluid">
	<h1><?php echo sprintf(_("Restoring %s"),$fileinfo->getFilename())?></h1>
	<div class="row">
		<div class="col-sm-12">
			<div class="fpbx-container">
				<div class="display full-border">
					<?php $date = \FreePBX::View()->getDateTime($meta['date']);?>
					<div class="row">
						<div class = "col-md-5">
						<div class="panel panel-default">
							<div class="panel-heading"><h3><?php echo _("Backup Info")?></h3></div>
							<div class="panel-body">
							<ul class = "list-group">
								<li class="list-group-item"><b><?php echo _("Name")?></b><span class = "pull-right"><?php echo $meta['backupInfo']['backup_name']?><span></li>
								<li class="list-group-item"><b><?php echo _("Description")?></b><span class = "pull-right"><?php echo $meta['backupInfo']['backup_description']?><span></li>
								<li class="list-group-item"><b><?php echo _("Run Date")?></b><span class = "pull-right"><?php echo $date?><span></li>
							</ul>
							</div>
						</div>
						</div>
						<div class="col-md-7">
						<div class="panel panel-default">
							<div class="panel-heading"><h3><?php echo _("Restore Information")?></h3></div>
							<div class="panel-body">
							<ul class="list-group">
							<?php if(version_compare(\FreePBX::Config()->get('ASTVERSION'), '21', 'ge')){ ?>
								<li class = "list-group-item list-group-item-danger">
									<?php echo _("The current version of Asterisk installed does not support chan_sip. Upgrade Asterisk to a supported version, convert chan_sip extensions to pjsip, or skip chan_sip extensions for restore.")?>
								</li>
							<?php } ?>
							<?php if(isset($meta['chansipexists']) && $meta['chansipexists']){ ?>
								<li class = "list-group-item list-group-item-danger">
									<?php echo _("The backup contains ChanSIP extensions! These ChanSIP extensions can either be converted to pjsip extensions or can be skipped during the restore process.")?>
								</li>
								<input type="hidden" id="chasipexists" value="1">
							<?php } ?>
							<?php if(isset($meta['chansipTrunkExists']) && $meta['chansipTrunkExists']){ ?>
								<li class = "list-group-item list-group-item-danger">
									<?php echo _("The backup contains ChanSIP Trunks! These ChanSIP Trunks can either be converted to pjsip  or can be skipped during the restore process.")?>
								</li>
								<input type="hidden" id="chasiptrunkexists" value="1">
							<?php } ?>
							<li class = "list-group-item list-group-item-danger"><?php echo _("Running a restore will overwrite current data. This cannot be undone!")?></li>
							<li class = "list-group-item list-group-item-info"><?php echo _("This restore will only affect the modules listed below")?></li>
							<li class = "list-group-item list-group-item-info"><?php echo _("After the restore you might reload with the apply config button")?></li>
							</ul>
						</div>
						</div> <!--End column-->
					</div><!--End Row-->
					</div>
					<div id="restoremodule-toolbar">
						<h3><?php echo _("Modules in this backup")?></h3>
						<p><?php echo _("This table will be empty on backups created prior to version 15") ?></p>
					</div>
					<table id="restoremodules"
						data-toggle="table"
						data-search="true"
						data-toolbar="#restoremodule-toolbar"
						data-id-field="modulename"
						data-maintain-selected="true"
						data-escape="true" 
						class="table table-striped">
						<thead>
						<tr>
							<th data-field="modulename" class="col-md-4"><?php echo _("Module")?></th>
							<th data-field="version" class="col-md-4"><?php echo _("Version in Backup")?></th>
							<th data-field="installed" class="col-md-4"><?php echo _("Status")?></th>
						</tr>
						</thead>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<input type="hidden" id="convertchansip"  value="">
<div id='sipmodal' class="modal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo _("Restore") ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p id="waittxt"><?php echo _("Scanning the backup file. Please wait..."); ?></p>
		<p id="warntxt" style="display:none;"><b><?php echo _("Attention:"); ?></b><?php echo _(" The backup file contains legacy chan_sip extensions or trunks, which are not compatible with Asterisk 21. You have the option to proceed, in which case these legacy extensions or trunks will be converted to PJSIP. Alternatively, you can cancel the restore process and downgrade the Asterisk version first, then attempt to restore your backup."); ?></p>
		<p id= "restoretxt" style="display:none;"><?php echo _('Are you sure, you want to restore this backup?'); ?></p>
      </div>
      <div class="modal-footer">
		<button type="button" class="btn btn-primary" id="convertbtn" style="display:none;"><?php echo _('Continue'); ?></button>
		<button type="button" class="btn btn-primary" id="okbtn" style="display:none;"><?php echo _('Ok'); ?></button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal" id ="cancelbtn"><?php echo _('Cancel'); ?></button>
      </div>
    </div>
  </div>
</div>

<script>
var runningRestore = <?php echo json_encode($runningRestore, JSON_THROW_ON_ERROR); ?>;
var fileid = "<?php echo $fileid?>";
var thing = {data: <?php echo $jsondata?>}
	$(document).ready(() => {
		$('#restoremodules').bootstrapTable({data: <?php echo $jsondata?>});

	});//end ready
	function installedFormatter(v){
		if(v){
			return `<i class="fa fa-check text-success"></i>`;
		}else{
			return `<i class="fa fa-times text-danger"></i>`;
		}
	}
</script>
