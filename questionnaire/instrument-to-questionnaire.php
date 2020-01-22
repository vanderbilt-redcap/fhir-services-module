<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaire;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaire\FHIRQuestionnaireItem;
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

$questionnaire = new FHIRQuestionnaire([
    'name' => $formName,
    'title' => $formDisplayName,
    'status' => 'draft',
    'url' => APP_PATH_WEBROOT_FULL . ltrim(APP_PATH_WEBROOT, '/') . "Design/online_designer.php?pid=$pid&page=$formName"
]);

$skippedFields = [];
$group = $questionnaire;
while($field = $result->fetch_assoc()){
    $fhirType = $module->getFHIRType($field);
    if($fhirType === null){
        $skippedFields[] = $field['field_name'];
        continue;
    }

    $sectionHeader = @$field['element_preceding_header'];
    if(!empty($sectionHeader)){
        $group = new FHIRQuestionnaireItem($module->createQuestionnaireItem([
           'field_name' => $field['field_name'] . "___section_header",
           'element_label' => $sectionHeader, 
           'element_type' => FHIR_GROUP,
        ]));

        $questionnaire->addItem($group);
    }

    $items = [];
    if($field['element_type'] === 'checkbox'){
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