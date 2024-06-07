<ul class="nav nav-tabs mt-1" role="tablist">
	<li role="presentation" data-name="areminderov" class="active">
		<a href="#areminderov" aria-controls="areminderov" role="tab" data-toggle="tab">
			<?php echo _("Public key of this system")?>
		</a>
	</li>
	<li role="presentation" data-name="aremindgset" class="change-tab">
		<a href="#aremindgset" aria-controls="aremindgset" role="tab" data-toggle="tab">
			<?php echo _("Public key of other system")?>
		</a>
	</li>
</ul>
<div class="tab-content display">
	<div role="tabpanel" id="areminderov" class="tab-pane active"><br/>
			<div class="element-container">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="publickey"><?php echo _("Public Key") ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="publickey"></i>
						</div>
						<div class="col-md-9">
							<textarea disabled id="publickey" class="form-control" rows='8'><?php echo $publickey?></textarea>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12">
						<span id="publickey-help" class="help-block fpbx-help-block"><?php echo _("Public SSH key to allow other servers to connect. Only ECDSA SSH key is supported")?></span>
					</div>
				</div>
			</div>
	</div>
	<div role="tabpanel" id="aremindgset" class="tab-pane">
			<button type="button" id="addFieldsButton" class="btn btn-primary">
				<i class="fa fa-plus"></i> Add Public Key
			</button><br /><br />
			<table class="table" id="serverTable">
				<thead>
					<tr>
						<th>Server Name</th>
						<th>Public Key of Asterisk User</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<tr class="d-none">
						<td><input name="servername[]" class="form-control" /></td>
						<td><textarea name="publickeyAsteriskUser[]" class="form-control" rows="4"></textarea></td>
						<td><button type="button" class="btn btn-success saveRow">Save</button></td>
					</tr>
					<?php
					if(isset($publickeyAsteriskUser) && is_array($publickeyAsteriskUser) && count($publickeyAsteriskUser) >0) {
						foreach($publickeyAsteriskUser as $k=>$v) {
							echo '<tr>
							<td><input name="servername[]" class="form-control" value="'.($v['servername'] ?? '').'" readonly /></td>
							<td><textarea name="publickeyAsteriskUser[]" class="form-control" rows="4" readonly >'.($v['publickeyAsteriskUser'] ?? '').'</textarea></td>
							<td><button type="button" class="btn btn-danger deleteRow">Delete</button></td>
							</tr>';
						}
					}
					?>
				</tbody>
			</table>
	</div>
</div>

