<?php

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;

$pid = $_GET['pid'];
$fields = REDCap::getDataDictionary($pid, 'array');
$formName = $_GET['form'];
$formDisplayNames = REDCap::getInstrumentNames();
$formDisplayName = $formDisplayNames[$formName];

$questionnaire = new FHIRQuestionnaire([
    'name' => $formName,
    'title' => $formDisplayName,
    'status' => 'draft',
    'url' => APP_PATH_WEBROOT_FULL . ltrim(APP_PATH_WEBROOT, '/') . "Design/online_designer.php?pid=$pid&page=$formName"
]);

$skippedFields = [];
foreach($fields as $field){
    if($field['form_name'] !== $formName){
        continue;
    }

    $fhirType = $module->getFHIRType($field);
    if($fhirType === null){
        $skippedFields[] = $field['field_name'];
        continue;
    }

    $items = [];
    if($field['field_type'] === 'checkbox'){
        $choices = $module->parseREDCapChoices($field);
        foreach($choices as $key=>$value){
            $item = $module->createQuestionnaireItem($field);
            $item['linkId'] .= "___$key";
            $item['text'] .= " - $value";
            $items[] = $item;
        }
    }
    else{
        $items[] = $module->createQuestionnaireItem($field);
    }

    foreach($items as $item){
        $questionnaire->addItem(new FHIRQuestionnaireItem($item));
    }
}

if(isset($_GET['return-skipped-fields'])){
    header('Content-type: application/fhir+json'); 
    echo json_encode($skippedFields);
    return;
}

header("Content-Disposition: attachment; filename=\"$formDisplayName.json\"");
$module->sendJSONResponse($questionnaire);