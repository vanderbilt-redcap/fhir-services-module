<?php

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;

$pid = $_GET['pid'];
$fields = REDCap::getDataDictionary($pid, 'array');
$formName = $_GET['form'];
$formDisplayNames = REDCap::getInstrumentNames();

$questionnaire = new FHIRQuestionnaire([
    'name' => $formName,
    'title' => $formDisplayNames[$formName],
    'status' => 'draft',
    'url' => APP_PATH_WEBROOT_FULL . ltrim(APP_PATH_WEBROOT, '/') . "Design/online_designer.php?pid=$pid&page=$formName"
]);

foreach($fields as $field){
    if($field['form_name'] !== $formName){
        continue;
    }

    $fhirType = $module->getFHIRType($field);

    $item = [
        'linkId' => $field['field_name'],
        'text' => $field['field_label'],
        'type' => $fhirType
    ];

    if($field['required_field'] === 'y'){
        $item['required'] = true;
    }

    if($fhirType === 'choice'){
        $item['answerOption'] = $module->getFHIRAnswerOptions($field);
    }

    $questionnaire->addItem(new FHIRQuestionnaireItem($item));
}

$module->sendJSONResponse($questionnaire);