<?php
$pid = $module->getProjectId();
$record = $_GET['record'];
$form = $_GET['form'];
$event = $_GET['event'];
$instance = $_GET['instance'];

$bundle = $module->getEConsentFHIRBundleForInstance($pid, $record, $form, $event, $instance);
$module->validateInBrowserAndDisplay($bundle);