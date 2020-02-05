<?php namespace Vanderbilt\FHIRServicesExternalModule;

use REDCap;

$pid = $_GET['pid'];
$formName = $_GET['form'];
$formDisplayNames = REDCap::getInstrumentNames();
$formDisplayName = $formDisplayNames[$formName];

$result = $module->query('
    select *
    from redcap_metadata
    where 
        project_id = ?
        and form_name = ?
        and field_name != concat(form_name, \'_complete\')
    order by field_order
', [$pid, $formName]);

$fields = [];
while($field = $result->fetch_assoc()){
    $fields[] = $field;
}

// Used to generated the fields.json test file.
// echo json_encode($fields, JSON_PRETTY_PRINT);die();

list($questionnaire, $skippedFields) = $module->createQuestionnaire($pid, $formName, $formDisplayName, $fields);

if(isset($_GET['return-skipped-fields'])){
    header('Content-type: application/fhir+json'); 
    echo json_encode($skippedFields);
    return;
}

if(!isset($_GET['no-download'])){
    header("Content-Disposition: attachment; filename=\"$formDisplayName.json\"");
}

$module->sendRespondAndExit($questionnaire);