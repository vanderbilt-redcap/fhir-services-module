<?php

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaireResponse;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseItem;

$data = $module->getData($module->getProjectId(), $_GET['id'])[0];

$edoc = $module->getQuestionnaireEDoc();

$q = $module->parse(file_get_contents(EDOC_PATH . $edoc['stored_name']));

$objects = [];
$module->walkQuestionnaire($q, function($parents, $item) use ($module, $data){
    $fieldName = $module->getFieldName($item);
    $value = $data[$fieldName];
    if(!$value){
        return;
    }

    $linkId = $item->getLinkId();
    if(isset($objects[$linkId])){
        throw new Exception("LinkId set multiple times: $linkId");
    }

    $objects = new FHIRQuestionnaireResponseItem([
        'value' => $value
    ]);
});

// header("Content-Disposition: attachment; filename=questionnaire-export.json");
// TODO - test/handle repeatables