<?php

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRQuestionnaireResponse;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseAnswer;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRQuestionnaireResponse\FHIRQuestionnaireResponseItem;

$data = $module->getData($module->getProjectId(), $_GET['id'])[0];

$edoc = $module->getQuestionnaireEDoc();

$questionnaire = $module->parse(file_get_contents(EDOC_PATH . $edoc['stored_name']));

$questionnaireResponse = new FHIRQuestionnaireResponse;
$responseObjects = [];
$getResponseObject = function($parentResponseItem, $item) use ($module, &$responseObjects, $questionnaireResponse){
    if(!$parentResponseItem){
        // This is the Questionnaire root
        return $questionnaireResponse;
    }

    $itemId = $module->getLinkId($item);
    $responseItem = $responseObjects[$itemId];
    if(!$responseItem){      
        $responseItem = new FHIRQuestionnaireResponseItem;
        $responseItem->setLinkId($itemId);
        $responseItem->setText($item->getText());

        $responseObjects[$itemId] = $responseItem;
        $parentResponseItem->addItem($responseItem);
    }

    return $responseItem;
};

$module->walkQuestionnaire($questionnaire, function($parents, $item) use ($module, $data, &$getResponseObject){
    $fieldName = $module->getFieldName($item);
    $value = $data[$fieldName];
    if(!$value){
        return;
    }

    $items = array_merge($parents, [$item]);
    $lastResponseItem = null; 
    foreach($items as $item){
        $responseItem = $getResponseObject($lastResponseItem, $item);
        $lastResponseItem = $responseItem;
    }    

    $responseItem->addAnswer(new FHIRQuestionnaireResponseAnswer([
        'valueString' => $value
    ]));
});

header("Content-Disposition: attachment; filename=questionnaire-export.json");
echo $module->jsonSerialize($questionnaireResponse);
