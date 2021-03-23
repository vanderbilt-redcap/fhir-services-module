<?php
$pid = $_GET['pid'];
$record = $_GET['record'];
$form = $_GET['form'];
$event = $_GET['event'];
$instance = $_GET['instance'];

$formSettings = $module->getEConsentFormSettings($form);
$eConsentData = $module->getEConsentData($form, $record, $instance);
$responses = $module->getSurveyResponses([
    'pid' => $pid,
    'event' => $event,
    'form' => $form,
    'record' => $record,
    'instance' => $instance
]);

$surveyResponseDetails = $responses->fetch_assoc();

global $pdf_custom_header_text;
$data = REDCap::getPDF($record, $form, $event, false, $instance, true, $pdf_custom_header_text);

$bundle = $module->getEConsentFHIRBundle([
    'consentId' => $module->getInstanceFHIRId($pid, $record, $event, $form, $instance),
    'scope' => $formSettings['econsent-scope'],
    'categories' => $formSettings['econsent-categories'],
    'dateTime' => $surveyResponseDetails['completion_time'],
    'data' => $data,
    'type' => $eConsentData['type'],
    'version' => $eConsentData['version'],
    'firstName' => $eConsentData['firstname'],
    'lastName' => $eConsentData['lastname'],
    'authority' => $module->getProjectHomeUrl($pid),
    'patientId' => $module->getRecordFHIRId($pid, $record),
    'birthDate' => $eConsentData['dob'],
]);

$module->validateInBrowserAndDisplay($bundle);