<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;
use REDCap;

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
$group = $questionnaire;
foreach($fields as $field){
    if($field['form_name'] !== $formName){
        continue;
    }

    $fhirType = $module->getFHIRType($field);
    if($fhirType === null){
        $skippedFields[] = $field['field_name'];
        continue;
    }

    $sectionHeader = @$field['section_header'];
    if(!empty($sectionHeader)){
        $group = new FHIRQuestionnaireItem($module->createQuestionnaireItem([
           'field_name' => $field['field_name'] . "___section_header",
           'field_label' => $sectionHeader, 
           'field_type' => FHIR_GROUP,
        ]));

        $questionnaire->addItem($group);
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
        $group->addItem(new FHIRQuestionnaireItem($item));
    }
}

if(isset($_GET['return-skipped-fields'])){
    header('Content-type: application/fhir+json'); 
    echo json_encode($skippedFields);
    return;
}

header("Content-Disposition: attachment; filename=\"$formDisplayName.json\"");
$module->sendJSONResponse($questionnaire);