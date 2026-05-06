<?php
/** @var array $_ */
?>
<div id="sakuraalbum-personal-settings"
	data-settings="<?php p(json_encode($_['settings'], JSON_THROW_ON_ERROR)); ?>"
	data-effective-settings="<?php p(json_encode($_['effectiveSettings'], JSON_THROW_ON_ERROR)); ?>"
	data-admin-settings="<?php p(json_encode($_['adminSettings'], JSON_THROW_ON_ERROR)); ?>">
	<div class="sakuraalbum-settings"></div>
</div>
